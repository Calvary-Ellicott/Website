# Calvary Ellicott Mail 0.4.4

Install by uploading `ce-mail.zip` in WordPress Plugins and replacing the existing version.
The portal remains at `/mail`; email administration remains at `/emailadmin`.

0.4.4 fixes server-to-server OAuth discovery: WordPress returns null for an absent Origin header.
That case now receives the OAuth challenge instead of an origin rejection. Browser origin checks
remain enforced. The test request stub now follows WordPress header semantics and the live checker
covers requests both with and without Origin. Sending remains separately disabled.

0.4.3 adds browser-readable public discovery metadata, the resource-specific metadata URL, and
support for the exact ChatGPT browser origin. Unauthenticated requests from ChatGPT now receive
the OAuth challenge instead of an origin rejection. Other unrelated origins remain blocked.
Sending still requires its separate administrator switch and explicit consent. Local regression
checks passed; ChatGPT builder acceptance must be checked after installing this update.

0.4.2 fixes the return after Allow/Cancel: a completion page automatically navigates back to the
registered assistant and provides a Continue link. This avoids browsers blocking the previous
POST redirect under the strict form-action security policy. The old failure and corrected handoff
were reproduced in a synthetic browser test. Live acceptance is still pending this upload.

0.4.1 changes sign-in/consent to redirect to a fresh GET page after login, checks the secure cookie
before asking for a password, offers wrong-password retries, and identifies session failures with
non-secret diagnostic codes. No additional database migration. Local tests passed; the reported
live consent failure still needs retesting after this upload.

## AI connector

The connector URL is `/wp-json/ce-mail/v1/mcp` on the site's HTTPS origin. **Mail → Allow AI
assistants** defaults to off. Each staff member authorizes their own church mailbox using
OAuth with S256 PKCE. Access is bound to that mailbox, requested permissions, and this connector.
Read/search, thread retrieval, drafting, flags, read state, and archiving are available according
to scope. Sending also requires **Allow assistants to send** and explicit consent; prefer drafts.
The connector never offers deletion or bulk export. Pages and mutation batches are capped at 50.

Connecting a church mailbox to a third-party assistant shares member names, message content,
and potentially pastoral or financial matters with that vendor. The pastor should sign off
before staff connect. Messages are untrusted data, not instructions for an assistant.

Revoke connections in `/mail` → Settings → Connected assistants. Browser sign-out does not
revoke assistant access. The site switch suspends all connections; grants remain until revoked
or expired. Grants expire after 90 days. Changing mailbox passwords or WordPress salts requires
reconnection. Credentials are encrypted with the existing WordPress-salt-derived key; only
token hashes are stored. Daily WP-Cron removes expired records and audit rows older than 90 days.

Version 0.4.0 adds seven InnoDB connector tables.
It passed local PHP 8.3 lint and offline security/protocol tests. The earlier discovery probe was
verified live. Real OAuth consent, MySQL transaction behavior, IMAP/SMTP, and assistant-vendor
acceptance must still be checked after installation; do not assume the package is live-tested.

For development and acceptance instructions, see the repository's `docs/mail-portal-mcp.md`.
