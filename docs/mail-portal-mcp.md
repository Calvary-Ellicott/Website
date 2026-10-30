# Church mail connector for AI assistants (MCP) — build spec

Status: **0.3.1 discovery verified live; 0.4.0 connector implemented locally, awaiting live acceptance** (2026-10-27).
It extends the existing mail portal plugin in `wp-plugin/ce-mail/`; read `docs/mail-portal.md` first.

### Post-0.4.4 discovery investigation

The user discontinued the WordPress-hosted ChatGPT approach and requested a personal,
standalone Purelymail connection. Cloudflare Bot Fight Mode was restored to ON at their
request; no additional hosting security setting had been changed. Sending stays off.
The alternative should use a separate authenticated MCP service connected directly to
`imap.purelymail.com:993` over TLS, using a mailbox-specific, IMAP-only app password.
Initial tools: list folders, list/search messages, and read messages/threads. Use read-only
IMAP selection and body peek, with no SMTP, flag/move/delete/draft tools or website dependency.
Hosting choice (always-on service versus personal computer) is pending. This alternative
has not yet been implemented or connected to the mailbox.

Cloudflare Security > Analytics > Events, filtered to the exact registration path, confirmed
Bot Fight Mode issuing a Managed Challenge to POST requests at 2026-10-27 23:13:54 MDT
and 20:57:11 MDT. Latest Ray ID: `a51774320d0a3925`; user agent:
`Python/3.14 aiohttp/3.14.4`; source ASN: Microsoft Corporation (8075).
WordPress.com web server logs filtered to the same path for Oct 21-27 contained successful
201 test registrations and one earlier 503, with no 403 entries. The Cloudflare challenge
explains the observed registration failure before WordPress. Evidence screenshot:
`wp-plugin/dist/cloudflare-bot-fight-evidence.png` (ignored).
Cloudflare's free Bot Fight Mode does not support per-path WAF Skip exceptions.
Disabling it is a domain-wide protection change requiring action-time confirmation;
After explicit user approval, Bot Fight Mode was switched off and its off state verified
after a page reload. ChatGPT creation was retried: the upstream failure changed from
403 to 429 (`Dynamic client registration failed: registration endpoint returned 429`).
WordPress.com logs now record the POST at 23:29 MDT as 429, renderer `static`,
1,168 response bytes, with the same Python/aiohttp user agent; this suggests a hosting
rate limit rather than the plugin's small JSON response. A direct registration probe with
that same user agent returned 201 at 23:30 MDT. Fresh ChatGPT discovery still failed.
The ChatGPT connection is not accepted yet. Sending remains off; live metadata still
reports 0.4.4 without mail:send. Additional ignored screenshots:
`wp-plugin/dist/cloudflare-bot-fight-off.png`, `wp-plugin/dist/chatgpt-registration-429.png`.
Both WordPress.com WAF and Defensive Mode settings show a Business/Commerce upgrade gate
on this Premium site. No hosting security setting was altered. Hosting support needs to
identify the static 429 at 2026-10-28 05:29 UTC (Oct 27 23:29 MDT) on the registration path;
no plan purchase or support message has been submitted.

The next captured creation response identified the concrete upstream failure: ChatGPT's
`POST /backend-api/aip/connectors/mcp` returned HTTP 400 with
`detail.message = "Dynamic client registration failed: registration endpoint returned 403"`
and `detail.status_code = 403`. The submitted payload was inspected: authorization/token/registration
URLs, authorization-server base, resource, S256 PKCE, DCR, and mail:read scopes were correct.
The live registration handler has no 403 response branch, and public-client registration from the
local test machine returns 201. This points to a request-specific block before that handler
(Cloudflare or WordPress.com security), not a demonstrated client-metadata validation error.
Check Cloudflare/hosting security events for POST `/wp-json/ce-mail/v1/oauth/register` at the
ChatGPT attempt before changing plugin behavior or weakening any site-wide security settings.
Sending remained off in the live metadata. Diagnostic screenshot: ignored build output
`wp-plugin/dist/chatgpt-registration-403.png`.

Browser DevTools subsequently captured ChatGPT's own `POST /backend-api/aip/connectors/mcp/oauth_config`
returning HTTP 404 with detail `MCP server https://calvaryellicott.org/wp-json/ce-mail/v1/mcp does not implement OAuth`.
This response does not reveal the failing upstream request. The earlier connector creation produced HTTP 400
and the generic settings-rejected UI; its response body was not captured at the time. Discovery retry resets
the builder's manual OAuth fields, registration method and PKCE checkbox, so restore the verified values before
another creation attempt. The manual read-only configuration has been restored and capture is ready to inspect
the creation response. Public-client registration with the ChatGPT callback was independently accepted (201).

Live 0.4.4 passed both metadata routes, the resource-specific metadata alias, and unauthenticated
GET/POST with and without the ChatGPT Origin, all returning the expected discovery challenge.
ChatGPT's builder still displayed its generic discovery failure.

An additional independent test used the official `@modelcontextprotocol/sdk` client instead of
the plugin's checker. `discoverOAuthServerInfo()` fetched the resource-specific metadata (200)
and root authorization metadata (200), validated the documents with SDK schemas, and reported the
expected issuer/resource, registration endpoint, public-client `none` auth, S256 PKCE, and no send scope.
The reusable script is `node tools/check-mail-sdk-discovery.mjs`; install the SDK with
`npm install --prefix wp-plugin/dist/mcp-diagnostics --ignore-scripts @modelcontextprotocol/sdk` first.
That directory is ignored and is not in the uploaded plugin.

The precise remaining ChatGPT builder failure has not been observed at the HTTP level. The generic
UI message alone does not establish a new server defect. The next connection attempt can use the
builder's explicit OAuth endpoints and DCR/public-client setup, preserving the same authentication
and disabled sending setting. Do not describe the ChatGPT integration as accepted until that succeeds.

### 0.4.4 absent Origin header correction

ChatGPT discovery continued to fail on 0.4.3 despite the browser-origin test passing. A live
server-to-server GET without Origin reproduced HTTP 403 with `Origin not allowed`, so the client
could not see the OAuth challenge. WordPress `WP_REST_Request::get_header()` returns null for an
absent header, while the 0.4.3 allowlist only accepted an empty string. The test request stub
incorrectly returned an empty string, masking the regression.

0.4.4 accepts missing Origin (`null` or empty string) while preserving rejection of unrelated
browser origins, including the literal string `null`. Missing Authorization is handled without
PHP null-argument warnings. The stub now matches WordPress, and tests require 401 plus discovery
challenge for both GET and POST without Origin. The live checker covers those cases alongside
ChatGPT-origin requests. Local validation passed 88 regression checks and seven handler scenarios.
The user confirmed the exact MCP URL. Live 0.4.4/ChatGPT acceptance still requires upload.

### 0.4.3 ChatGPT browser discovery compatibility

The 0.4.2 test client passed live sign-in, consent, code exchange, MCP initialization/tool discovery,
refresh and revocation. ChatGPT's builder subsequently reported that it could not discover OAuth settings.
Live checks identified compatibility gaps: requests with `Origin: https://chatgpt.com` received 403 before
the authentication challenge; public metadata had no CORS headers; and the RFC 9728 resource-specific
discovery URL `/.well-known/oauth-protected-resource/wp-json/ce-mail/v1/mcp` returned 404.

0.4.3 permits the exact ChatGPT origin alongside the existing site/loopback origins, while rejecting
unrelated and spoofed origins. Anonymous ChatGPT-origin MCP requests now get the normal 401 challenge.
The challenge includes available scopes; CORS exposes WWW-Authenticate and the protocol header and permits
the MCP protocol request header. Public metadata supports cross-origin reads and OPTIONS without exposing
credentials; the resource-specific metadata path returns the same document as the root path.

83 local security/protocol checks and seven authorization handler scenarios passed, including origin
allow/reject behavior and the metadata alias. The expanded discovery checker tests these live endpoints
after upload. 0.4.3 and ChatGPT builder acceptance remain pending deployment; these gaps alone do not prove
the exact request ChatGPT's builder used. Sending remains controlled by its separate disabled switch.
Reference: [OpenAI authentication and discovery contract](https://developers.openai.com/plugins/build/auth).

### 0.4.2 callback navigation fix

The user observed that the first Allow click left the form visible and a second click reported
`flow_missing`. The old response consumed the flow and issued an external HTTP redirect while
the source form had `Content-Security-Policy: form-action 'self'`. This browser behavior was
reproduced with synthetic local endpoints: the POST/302 stayed on the consent form; the new
production handoff template reached the cross-origin callback automatically in the same browser.
This is distinct from an expired login or incorrect password.

0.4.2 completes Allow/Cancel POSTs with an HTTP 200 completion document, then navigates through
a meta refresh to the exact registered callback with code/error and state. A visible Continue to
assistant link provides a fallback. The strict CSP, no-store/no-referrer headers, cookie binding,
and one-use code checks stay in place. Passwords and bearer tokens are never included in the page.

Local validation: 72 regression checks, seven authorization handler scenarios, PHP syntax checks,
and a browser regression using the production return template. The browser fixture is
`node tests/oauth-return-browser.cjs <php-path>`; it creates no real OAuth grants. Live 0.4.2
acceptance is pending installation. The discovery checker now expects 0.4.2.

### 0.4.1 sign-in transition update

0.4.0 is now verified live: discovery, disabled/enabled MCP responses, public client registration,
and a cookie-bound cancellation POST passed. The user repeatedly received the combined
"expired or cookie missing" error after sign-in/Allow. The root cause is not yet established;
the generic error did not identify the failed check. The in-app browser also showed a separate
ERR_CACHE_MISS error, which does not establish the cause of the server-side rejection.

0.4.1 verifies the browser cookie roundtrip before presenting a password form and uses
POST/Redirect/GET after a successful sign-in. Consent refresh no longer resubmits the password.
Successful sign-in gives the consent stage a fresh ten-minute deadline; it does not extend grants.
Invalid passwords offer a retry link retaining the browser-bound flow. Distinct non-secret error
codes identify a missing form, missing cookie, changed cookie, missing/consumed flow, expired flow,
or storage failure. Cookie binding, PKCE, and one-use code protections remain enforced.

Local PHP 8.3 validation passed 72 security/protocol checks plus six isolated real-handler/template
scenarios (initial redirect, successful fixture sign-in, consent display, wrong-password retry,
missing-cookie rejection, Allow/code issuance). These use a fake mailbox and SQLite, not real
credentials or a browser. Upload 0.4.1 and repeat the live test before claiming this incident resolved.
No database migration is needed beyond 0.4.0. The discovery checker now expects 0.4.1.

## Current implementation and verification

Both live discovery URLs returned HTTP 200 JSON with version **0.3.1** on 2026-10-27 after
the user uploaded the probe. The issuer, resource, and endpoint checks passed. Acceptance
test 0 is complete; no alternate host or issuer-path fallback is needed for this installation.

Version **0.4.0** implements client registration, browser-bound sign-in/consent, S256 PKCE,
single-use authorization codes, rotating refresh tokens with replay revocation, mailbox-bound
grants, scope-aware MCP tools, audit rows, rate limits, both administrator switches, and the
Connected assistants list/revocation in portal Settings. Both switches default to off.
Discovery remains public and now reports `ce_mail_stage: connector`.

Local verification: PHP 8.3 syntax checks and JavaScript syntax checks; `tests/connector.php`
passes 65 security/protocol/mailbox regression checks with a SQLite persistence adapter and
fixture mailbox. Tests cover PKCE and redirect rejection, one-use codes, audience binding,
refresh rotation/replay, cross-mailbox isolation, revocation, switches, malformed RPC/arguments,
rate limits, read-without-marking-seen, and safe archive handling. SQLite does **not** verify
MySQL locking or WordPress REST integration. CI runs this suite and lint on PHP 7.4 and 8.3;
CI has not been run for these local changes. No real mail was read, drafted, or sent during development.

### Install and accept 0.4.0

1. Upload `wp-plugin/dist/ce-mail.zip` and replace the existing plugin.
2. Run `powershell -ExecutionPolicy Bypass -File tools/check-mail-discovery.ps1` from the repo.
   To recheck the old probe, add `-ExpectedVersion 0.3.1`.
3. Confirm MCP returns 503 while **Mail → Allow AI assistants** is off. Enable it only after
   the pastor approves the vendor/data sharing. Leave **Allow assistants to send** off initially.
4. Confirm unauthenticated POST to `/wp-json/ce-mail/v1/mcp` returns 401 with the discovery challenge.
5. Use MCP Inspector with a dedicated church test mailbox. Request `mail:read mail:draft mail:organize`,
   authorize with `resource` equal to the MCP URL, then test reading, drafts, flagging, and archiving.
6. Revoke from portal Settings and verify the next call returns 401. Reauthorize, disable the
   administrator switch, and verify 503. Then perform the ChatGPT and Claude acceptance checks below.

Live acceptance tests 1–8, real SMTP/IMAP behavior, and the WordPress database upgrade remain unverified
until this version is installed. The local package is not automatically deployed or committed.

### Implementation refinements to the original design below

- Database version 3 adds seven InnoDB tables named `ce_mail_oauth_clients`, `flows`, `codes`,
  `grants`, `tokens`, `rates`, and `audit` under the site's WordPress prefix. Actual names after
  the first are also prefixed with `ce_mail_oauth_`. The separate grant owns encrypted credentials,
  scope, audience, and revocation; tokens hold only hashes and grant IDs. Short-lived flows and
  codes contain encrypted credentials, never plaintext passwords.
- Refresh tokens expire with the grant, 90 days from initial consent. Rotation does not extend
  that deadline. Consumed refresh hashes remain until expiry to detect reuse. Code consumption
  and refresh rotation use InnoDB transactions and row locks. Consent is tied to an HttpOnly,
  Secure, SameSite=Lax browser cookie and a one-use, ten-minute server-side flow.
- Both authorization and token requests require the exact resource URL. Redirects must match
  registration exactly, including loopback ports. No redirect URI or scope is trusted from consent POSTs.
- Atomic database counters replace transient counters for connector limits. Sends are limited
  to 20/hour/**grant**, preventing token rotation from bypassing the limit. Registration and
  token exchange also have limits. Login has both the existing IP throttle and a mailbox counter.
- Archive requires the IMAP MOVE capability. It refuses the portal's COPY/EXPUNGE fallback,
  which could expunge other messages already marked deleted.
- Replies, snippets and threads are bounded: up to 50 records, 2 MB raw messages, 64 KiB text per
  message, 10 reference IDs searched, approximately 256 KiB of thread text and a 20-second IMAP
  budget. A large thread may time out with a tool error; use individual messages in that case.
  Snippets use a bounded MIME preview and may be empty for complex multipart mail. Quote removal
  is heuristic and is not guaranteed for every email client's formatting.
- Draft IDs use APPENDUID where provided; otherwise the response reports `saved: true`, a null
  draft ID, and a warning. A successful send with a failed Sent copy is reported as sent with a
  warning, never as a failure inviting duplicate sends. Message bodies and recipient lists are
  omitted from audit summaries. Read tools do not set the Seen flag or fetch remote images.
- Revocation prevents subsequent requests; an operation already in progress may finish.

The metadata and transport follow the pinned [MCP 2025-06-18 authorization specification](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization).

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
**Verified on this site (2026-10-27):** WordPress.com passes both `/.well-known/*` paths to this plugin.
For a future host where it does not, fall back to
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

- WordPress.com discovery routing: answered yes on this site by the live 0.3.1 check.
- Should Pastor Dan's mailbox be connectable at all, or only office/scheduling addresses? Default the
  "Allow AI assistants" switch to off and let the pastor decide per address; a per-mailbox allow-list in
  Settings is a small addition if wanted.
- Attachment text (PDF agendas) is the most likely follow-up request; leave a hook for `get_attachment_text`.
