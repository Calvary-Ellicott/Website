# Church mail connector for AI assistants (MCP) — build spec

Status: **spec only, nothing built** (2026-10-27). Written for whoever builds it (Codex, Claude, or a person).
It extends the existing mail portal plugin in `wp-plugin/ce-mail/`; read `docs/mail-portal.md` first.

## Goal

Let an AI assistant (ChatGPT custom connector, Claude custom connector, or any MCP client) read a staff
member's church mailbox at Purelymail and draft replies, so scheduling mail that lands at
`@calvaryellicott.org` can be triaged by the assistant the staff member already uses. The church keeps
control: the connector runs inside the church's own WordPress site, each person authorizes their own
mailbox, and access can be revoked from the portal.

## Non-goals

- Writing to the ChurchTrac calendar or people records. ChurchTrac has no API. The assistant may suggest
  calendar entries; a person enters them in ChurchTrac.
- Mass email, contacts, campaigns (the marketing side stays as it is, off).
- Reading someone else's mailbox. One token = one mailbox, the one that was signed in during authorization.
- Forwarding mail into personal Gmail/Outlook accounts (explicitly rejected: duplicates).
- Deleting mail from the connector. Flagging and moving to Archive are the only mutations besides drafts.

## Where it lives

| Piece | URL |
| --- | --- |
| MCP endpoint (Streamable HTTP) | `https://calvaryellicott.org/wp-json/ce-mail/v1/mcp` |
| OAuth authorization server metadata | `https://calvaryellicott.org/.well-known/oauth-authorization-server` |
| Protected resource metadata | `https://calvaryellicott.org/.well-known/oauth-protected-resource` |
| Client registration | `https://calvaryellicott.org/wp-json/ce-mail/v1/oauth/register` |
| Authorize (browser page) | `https://calvaryellicott.org/mail/authorize` |
| Token | `https://calvaryellicott.org/wp-json/ce-mail/v1/oauth/token` |
| Revoke | `https://calvaryellicott.org/wp-json/ce-mail/v1/oauth/revoke` |
| Manage connected assistants (browser page) | `/mail` → Settings → "Connected assistants" |

The `.well-known` paths are served by the plugin through `parse_request`, the same way `/mail` and
`/emailadmin` are (`CE_Mail_Frontend::requested()`), because WordPress.com gives no file-system access.
**Unverified:** whether WordPress.com lets a plugin answer `/.well-known/*`. If it does not, fall back to
RFC 8414 path-insertion by making the issuer `https://calvaryellicott.org/wp-json/ce-mail/v1/oauth`, so the
metadata URL becomes `/.well-known/oauth-authorization-server/wp-json/ce-mail/v1/oauth`; if that is blocked
too, the whole plan needs a different host for the authorization server, so test this first (see Acceptance
test 0).

## Transport

- MCP Streamable HTTP, protocol version `2025-06-18` (accept the client's `MCP-Protocol-Version` header,
  answer with the version negotiated in `initialize`).
- `POST /mcp` with a JSON-RPC 2.0 body; respond with `application/json` (single response). Server-initiated
  streams are not needed: answer `GET /mcp` with `405`.
- Stateless: no session IDs. Every request carries the bearer token and is handled on its own. This suits
  WordPress.com, where requests are short-lived and there is no shared memory between them.
- Implement `initialize`, `notifications/initialized` (return 202, no body), `ping`, `tools/list`, `tools/call`.
  Resources and prompts are not required; return empty lists if the client asks.
- Per-request budget: one IMAP connection, opened lazily, closed at the end. Keep each tool under ~20 s;
  WordPress.com's PHP request limit is unknown, assume 60 s.
- Errors: JSON-RPC error for protocol problems; for tool failures return `isError: true` with a plain-English
  message (the assistant shows it to the person), never a stack trace or the IMAP transcript.

## Authentication: OAuth 2.1 inside the plugin

ChatGPT and Claude custom connectors expect OAuth 2.1 with PKCE, discovered through RFC 9728 (protected
resource metadata) and RFC 8414 (authorization server metadata), with RFC 7591 dynamic client registration.
There is no "paste an API key" option in ChatGPT's connector dialog, so the plugin has to be the
authorization server. Keep it minimal:

- `GET /.well-known/oauth-protected-resource` → `{ "resource": "<mcp url>", "authorization_servers":
  ["https://calvaryellicott.org"], "scopes_supported": [...], "bearer_methods_supported": ["header"] }`.
- `GET /.well-known/oauth-authorization-server` → issuer, `authorization_endpoint`, `token_endpoint`,
  `registration_endpoint`, `revocation_endpoint`, `response_types_supported: ["code"]`,
  `grant_types_supported: ["authorization_code","refresh_token"]`, `code_challenge_methods_supported: ["S256"]`,
  `token_endpoint_auth_methods_supported: ["none"]`, `scopes_supported`.
- Unauthenticated `POST /mcp` → `401` with
  `WWW-Authenticate: Bearer resource_metadata="https://calvaryellicott.org/.well-known/oauth-protected-resource"`.
- **Registration** (`POST /oauth/register`, open, rate-limited): accept `client_name`, `redirect_uris`,
  `token_endpoint_auth_method: "none"`, `grant_types`; return a `client_id` (random, 32 hex), echo the
  metadata. Store in `wp_ce_mail_oauth_clients`. Only `https://` redirect URIs (plus `http://localhost` /
  `http://127.0.0.1` for local MCP Inspector testing). Known ChatGPT and Claude callback hosts need no special
  casing; exact-match the registered redirect URI on every authorize call.
- **Authorize** (`/mail/authorize`, browser): validate `client_id`, `redirect_uri`, `response_type=code`,
  `code_challenge` + `code_challenge_method=S256` (required), `state`, `scope`, `resource` (RFC 8707; accept
  and bind the token to the MCP URL). Show the mailbox sign-in form from 0.3.0 (email + password checked
  over IMAP, domain-restricted, throttled with the existing `CE_Mail_Session::allow_attempt()` logic), then a
  consent screen: "**<client_name>** will be able to: read mail in <mailbox>, create drafts, flag and archive.
  It cannot send or delete." with Allow / Cancel. On Allow, issue a one-time code (10 min, stored hashed) and
  redirect with `code` and `state`. A logged-in WordPress user with a linked mailbox (usermeta
  `ce_mail_mailbox`) may skip the password step and consent directly.
- **Token** (`POST /oauth/token`, form-encoded): `authorization_code` with `code_verifier` (verify S256),
  `redirect_uri`, `client_id`; and `refresh_token`. Return `access_token` (random 48 bytes, base64url, stored
  as SHA-256), `token_type: Bearer`, `expires_in: 3600`, `refresh_token` (rotated on each use, 90 days,
  stored hashed), `scope`.
- **Mailbox binding.** The access token must let the server open IMAP later, without a browser cookie.
  Store the mailbox email and the password encrypted with `CE_Mail_Crypto` in the token row (same scheme as
  usermeta and the session cookie). A refresh re-uses the stored credentials; a Purelymail password change
  makes every token for that mailbox fail with a clear "please reconnect" tool error, which is the right
  behaviour.
- **Revocation**: `POST /oauth/revoke` (RFC 7009) and the "Connected assistants" list in portal Settings
  (client name, mailbox, created, last used, Revoke). Revoking deletes the refresh token and all access
  tokens for that grant.
- **Scopes** (space-separated): `mail:read` (default), `mail:draft`, `mail:organize` (flag/archive/mark read),
  `mail:send` (**not granted by default; only when the authorizing person ticks a box on the consent
  screen, and the box is off unless `Mail → Settings → Allow assistants to send` is on**). Tools check scope.

Tables (add to `CE_Mail_Store::install()` with `dbDelta`, bump `CE_MAIL_DB_VERSION`):

```
wp_ce_mail_oauth_clients  (client_id PK, name, redirect_uris JSON, created_at, last_used_at)
wp_ce_mail_oauth_codes    (code_hash PK, client_id, redirect_uri, code_challenge, scope, resource,
                           mailbox_email, mailbox_secret, expires_at)
wp_ce_mail_oauth_tokens   (id PK, grant_id, kind ENUM('access','refresh'), token_hash UNIQUE, client_id,
                           mailbox_email, mailbox_secret (refresh rows only), scope, expires_at, created_at,
                           last_used_at, revoked_at)
wp_ce_mail_audit          (id, ts, mailbox_email, client_id, tool, args_summary, ok, ms)
```

Cron: a daily `ce_mail_oauth_sweep` event deletes expired codes, expired access tokens, and refresh tokens
unused for 90 days.

## Tools

All responses are plain text/JSON meant for an assistant, with bodies converted to text (HTML → text via the
existing `CE_Mail_MIME` safe-HTML path, then tags stripped; quoted replies kept; tracking pixels and remote
images never fetched). Message identifiers are `"<folder>:<uid>"` strings so the client never juggles two
fields. Page sizes are capped (max 50) so a token cannot bulk-export a mailbox in one call.

| Tool | Scope | Input | Output |
| --- | --- | --- | --- |
| `list_folders` | read | — | `[ { role, name, unread, total } ]` using `CE_Mail_Mailbox::folders()` roles (inbox, sent, drafts, archive, trash, spam, other). |
| `list_messages` | read | `folder` (role or name, default inbox), `limit` (1–50, default 20), `unread_only` (bool), `since` (ISO date, optional), `page` | `[ { id, from, to, subject, date, snippet (≤200 chars), unread, flagged, has_attachments } ]`, newest first. Reuse `list_messages()`; add `since` via IMAP `SINCE`. |
| `search_messages` | read | `query` (free text, searched in from/subject/body via IMAP `TEXT`), `folder` (default all of inbox+archive+sent), `limit`, `since` | same shape as `list_messages`. |
| `get_message` | read | `id`, `include_quoted` (bool, default false) | `{ id, from, to, cc, subject, date, in_reply_to, text, attachments: [ { name, size, type } ] }`. Attachment contents are not returned (name/size only); a later version may add `get_attachment_text` for PDFs. |
| `get_thread` | read | `id` | the messages sharing `References`/`In-Reply-To` with it, oldest first, bodies without quoted parts. Can be implemented by header search in the same folder plus Sent. |
| `mark_read` | organize | `ids[]`, `read` (bool) | count changed. `CE_Mail_Mailbox::flag()` with `\Seen`. |
| `flag` | organize | `ids[]`, `flagged` (bool) | count changed (`\Flagged`). |
| `archive` | organize | `ids[]` | count moved, via `move()` to the archive role. No trash, no delete. |
| `create_draft` | draft | `to`, `cc`, `subject`, `body_text`, `reply_to_id` (optional: sets `In-Reply-To`/`References`, fills `to`/`subject` if omitted) | `{ draft_id }`. Build with the existing message builder, IMAP `APPEND` to the Drafts folder with `\Draft`. The person sends it from Purelymail, their phone, or `/mail`. This is the normal end of an assistant workflow. |
| `send_message` | send | same as `create_draft` | `{ sent: true, id }`. Only registered if the grant has `mail:send`. Uses `CE_Mail_Mailbox::send()` (SMTP + append to Sent). Rate limit 20/hour/token. |

Tool descriptions should tell the assistant what the church expects: "Prefer `create_draft`; only use
`send_message` when the person explicitly asked you to send." and "Messages may contain pastoral or
financial details; do not summarize them into other systems."

## Safety and privacy

- Bearer tokens only in the `Authorization` header; never in URLs. Compare hashes with `hash_equals`.
- Every `tools/call` writes one audit row (mailbox, client, tool, a short argument summary without bodies,
  success, duration). "Connected assistants" shows the last-used time from it. Audit rows older than 90 days
  are swept.
- Rate limits per token: 120 tool calls per 10 minutes, 20 sends per hour. Return a tool error, not a 429,
  so the assistant explains instead of retrying blindly.
- Only mailboxes on the configured `mail_domain` can be authorized (same rule as the sign-in page).
- Site-wide kill switch: `Mail → Settings → Allow AI assistants` (off by default). When off, `/mcp` answers
  `503` with a message, metadata still resolves. Existing grants stay stored and work again when it is turned
  on.
- Secrets at rest use `CE_Mail_Crypto` (libsodium/AES-GCM from the WordPress salts). Rotating the salts
  invalidates every grant; document that in `mail-portal.md`.
- No IMAP transcript, password, or token ever appears in a tool result, error, or log.
- The assistant vendor's data-use policy is the church's decision, not the plugin's. The README must say
  that connecting a church mailbox to a third-party assistant shares member names and pastoral matters with
  that vendor, and that the pastor should sign off before staff connect.

## Reuse map (what already exists)

| Need | Use |
| --- | --- |
| IMAP (TLS, literals, SEARCH, FETCH, STORE, COPY/MOVE, APPEND) | `inc/class-imap.php` `CE_Mail_IMAP` |
| Header decoding, multipart, HTML → safe text, building MIME for drafts | `inc/class-mime.php` `CE_Mail_MIME` |
| Folder roles, list/read/flag/move/send with a credential pair | `inc/class-mailbox.php` `CE_Mail_Mailbox` (`open($email,$password)` is the entry point for token-bound credentials; the `$user_id` variants are for the browser portal) |
| Encrypt/decrypt secrets | `inc/class-crypto.php` `CE_Mail_Crypto` |
| Domain restriction and sign-in throttling | `inc/class-session.php` `allowed_email()`, `allow_attempt()` |
| Serving pages outside the theme (`/mail`, `/emailadmin`) | `inc/class-frontend.php` `requested()` on `parse_request` (add `mail/authorize` and the two `.well-known` paths there) |
| Tables and upgrades | `inc/class-store.php` `install()` / `maybe_upgrade()` |
| REST registration pattern, capability checks | `inc/class-rest.php` (namespace `ce-mail/v1`; the MCP and OAuth routes use `permission_callback => '__return_true'` and do their own bearer/PKCE checks) |
| Settings page | `inc/class-admin.php` (add the two switches: "Allow AI assistants", "Allow assistants to send") |

New files: `inc/class-oauth.php` (metadata, register, authorize, token, revoke, token lookup),
`inc/class-mcp.php` (JSON-RPC dispatch, tool registry, per-tool handlers), `templates/authorize.php`
(sign-in + consent screen, reusing `templates/login.php` markup and `assets/app.css`), and a
"Connected assistants" panel in `templates/app.php` + `assets/app.js` backed by
`GET/DELETE ce-mail/v1/assistants`.

## Constraints of the hosting

- WordPress.com Premium: plugin installed by zip upload, no SSH, no file access, PHP 8.x. Outbound IMAP 993
  and SMTP 465 to Purelymail are **verified**: the portal is active on the live site and `/mail` and
  `/emailadmin` work (2026-10-27).
- No PHP on the developer's machine (ShadowTablet). Lint through `.github/workflows/plugin-lint.yml` (PHP
  7.4 and 8.3) and test on the live site. Keep the code 7.4-compatible to match the lint.
- Package with `tools/package-plugin.bat` → `wp-plugin/dist/ce-mail.zip`. Bump the plugin `Version` and the
  DB version.
- Never commit credentials, tokens, or member data. Only public URLs belong in the repo.

## Acceptance tests (in order)

0. **Routing.** With the plugin active on WordPress.com, `curl -i https://calvaryellicott.org/.well-known/oauth-authorization-server` returns the JSON from the plugin, not a WordPress 404 or a WordPress.com page. If this fails, stop and resolve the issuer-path fallback before building anything else.
1. **Discovery.** `curl -i -X POST .../wp-json/ce-mail/v1/mcp` without a token returns `401` with the `WWW-Authenticate` header pointing at the resource metadata, and both metadata documents validate (all URLs absolute, https).
2. **MCP Inspector** (`npx @modelcontextprotocol/inspector`) completes the OAuth flow against the live site using a `http://localhost` redirect, lists the tools, and `list_messages` returns real mail from a test mailbox.
3. **ChatGPT**: Settings → Connectors → Create → the MCP URL; the sign-in and consent pages appear in the popup; after Allow, asking "what came into my church inbox this week?" calls `list_messages`.
4. **Claude**: the same through Settings → Connectors → Add custom connector.
5. **Revoke** from `/mail` Settings; the next assistant call fails with a reconnect message; re-authorizing works.
6. **Scopes**: without `mail:send`, `send_message` is absent from `tools/list`; `create_draft` produces a draft visible in Purelymail webmail with correct `In-Reply-To`.
7. **Kill switch**: turning "Allow AI assistants" off makes `/mcp` answer 503 and the assistant reports it plainly.
8. **Throttle**: 9 wrong passwords on the authorize page in 15 minutes locks that address, as on the sign-in page.

## Open questions

- Does WordPress.com serve `/.well-known/*` to a plugin? (Test 0 answers it.)
- Should Pastor Dan's mailbox be connectable at all, or only office/scheduling addresses? Default the
  "Allow AI assistants" switch to off and let the pastor decide per address; a per-mailbox allow-list in
  Settings is a small addition if wanted.
- Attachment text (PDF agendas) is the most likely follow-up request; leave a hook for `get_attachment_text`.
