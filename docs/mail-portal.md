# Staff mail portal (`/mail`)

A WordPress plugin, `wp-plugin/ce-mail/`, that adds a staff-only mail app at `calvaryellicott.org/mail`. It is separate from the theme and can be installed, previewed, or removed without affecting the public site.

## What it does

| Area | How it works |
| --- | --- |
| **Inbox** | Each staff member connects their own Purelymail mailbox (email + password, stored encrypted). The portal reads it over IMAP and sends over SMTP, so mail looks the same in Purelymail's own webmail or a phone app. Read, reply, reply all, forward, flag, archive, delete, search, attachments. |
| **Marketing** (off by default) | Hidden unless *Show in the portal* is ticked under Mail → Settings, because the church sends mass email from ChurchTrac. When on, campaigns go through Resend's Broadcasts from `info@calvaryellicott.org`. Resend adds a per-recipient unsubscribe link and handles bounces and complaints. Draft, preview, send a test to yourself, send to everyone. |
| **Contacts** | A local list mirrored to one Resend audience. Add people one at a time or import the ChurchTrac People export (CSV). Columns are matched by name. Sync pushes new people to Resend in batches. |
| **Preview** | Anyone with access who has not connected a mailbox sees the full interface with sample messages, contacts, and campaigns, with a banner saying so. Nothing in preview is sent. |

## Install

1. Zip the `wp-plugin/ce-mail` folder (or run `tools/package-plugin.bat`, which writes `wp-plugin/dist/ce-mail.zip`).
2. WordPress → Plugins → Add New → Upload Plugin → the zip → Activate.
3. Visit `/mail`. Administrators have access immediately. To let Editors in, go to **Mail → Settings** in the dashboard and tick the role.

Activation creates four tables (`wp_ce_mail_contacts`, `wp_ce_mail_campaigns`, `wp_ce_mail_groups`, `wp_ce_mail_group_members`) and the `/mail` and `/emailadmin` addresses. Deactivating removes the addresses and leaves the tables.

## Setting up Purelymail (inboxes)

The portal only needs the server names, which are already set to Purelymail's (`imap.purelymail.com` 993 SSL, `smtp.purelymail.com` 465 SSL). Each person connects from the portal's Settings tab with their own mailbox email and password.

DNS for Purelymail goes in Cloudflare (the zone is already there, see `cloudflare-and-churchtrac.md`). Purelymail's setup screen lists them; typically:

| Type | Name | Value |
| --- | --- | --- |
| MX | `@` | `mailserver.purelymail.com` (priority 50) |
| TXT | `@` | `v=spf1 include:_spf.purelymail.com ~all` |
| CNAME | `purelymail1._domainkey` | `key1.dkimroot.purelymail.com` (and `purelymail2`, `purelymail3`) |
| CNAME | `_dmarc` | `dmarcroot.purelymail.com` |

Keep these **DNS only** (grey cloud). Copy the exact values from Purelymail; the names above are from their docs at the time of writing.

## Email admin (`/emailadmin`): mailboxes and groups

Administrators (WordPress `manage_options`) get a second page at `calvaryellicott.org/emailadmin`, also linked
from the Mail toolbar menu. It talks to Purelymail's account API (`https://purelymail.com/api/v0/`, token in a
`Purelymail-Api-Token` header), so it needs the account's API token pasted into **Mail → Settings → Email
administration**. The token is stored encrypted like mailbox passwords. Until it is saved the page shows sample
data and does nothing.

| Tab | What it does |
| --- | --- |
| **Groups** | Lists kept in WordPress (`wp_ce_mail_groups`, `wp_ce_mail_group_members`). A group may have an address (the part before `@`, for example `info`). **Sync to Purelymail** writes one exact-match routing rule per addressed group, targets = members, and replaces the rule whenever the members change. Groups without an address are plain lists. Members can be any email address. Deleting a group removes its rule. A group with an address and no members has no rule, so mail to it bounces; the page says so. |
| **Mailboxes** | Real mailboxes on the domain, read live (`listUser`). Create a mailbox (`createUser`, password shown once, optional recovery email) or reset a password (`modifyUser`). Deleting a mailbox is deliberately left to Purelymail's own portal. |
| **Routing rules** | Everything Purelymail has for the domain, read-only, marked as "group: X" or "made in Purelymail". Sync never touches rules it does not own. |

Purelymail notes that matter here: routing rules act on the envelope recipient and run before spam scoring and
before any Sieve, a rule takes precedence over a mailbox of the same name, and there is no API for Sieve
scripts, so the account-wide filter in `purelymail-mail-rules.md` stays a paste job. The `info@` rule from that doc
is exactly what the "Staff" group produces, so keep one or the other, not both.

API calls used: `checkAccountCredit` (connection check), `listUser`, `createUser`, `modifyUser`,
`listRoutingRules`, `createRoutingRule`, `deleteRoutingRule`. There is no "update rule" call; sync deletes and
recreates. Purelymail does not publish a reference page for the API at the time of writing; the request shapes
were taken from the open-source Raycast Purelymail extension and should be confirmed against the live account
on first use. The REST routes live under `ce-mail/v1/admin/*` and refuse anyone without `manage_options`.

### Short addresses: `mail.` and `mailadmin.` subdomains

WordPress.com cannot serve one path of the site on a different hostname, so a subdomain pointed at the site
would just bounce to `calvaryellicott.org`. The practical option is a Cloudflare redirect, the same pattern as
route B in `cloudflare-and-churchtrac.md`:

1. DNS: `AAAA` records `mail` and `mailadmin` → `100::`, **proxied** (orange cloud). Purelymail's own records
   (`MX`, `_dmarc`, `purelymail*._domainkey`) do not use the `mail` name, so there is no clash.
2. Rules → Redirect Rules: `https://mail.calvaryellicott.org/*` → `https://calvaryellicott.org/mail/`
   (301, do not preserve the path) and `https://mailadmin.calvaryellicott.org/*` →
   `https://calvaryellicott.org/emailadmin/`.
3. The address bar ends up on `calvaryellicott.org/mail`; the subdomain is a memorable door, not a mask.

Hosting the mail tools somewhere else (GitHub Pages, Cloudflare Pages) was considered on 2026-10-23. A static
host cannot keep the Purelymail token or talk IMAP, so it would need Cloudflare Pages Functions + D1 + Access
and a rewrite of the plugin in JavaScript. Not worth it while the WordPress plugin does the job.

## Setting up Resend (marketing)

1. Create a Resend account for the church and add the domain `calvaryellicott.org`. Resend shows DNS records to add in Cloudflare:
   - a DKIM `TXT` at `resend._domainkey`,
   - an `MX` and an SPF `TXT` on a **subdomain** such as `send.calvaryellicott.org` (Resend uses it for bounces).
   Because Resend's SPF and MX live on `send.`, they do not clash with Purelymail's records on the root. Both can be verified at once.
2. Resend → Audiences → create one, for example "Church family". Copy its ID.
3. Resend → API Keys → create a key with sending access.
4. WordPress → **Mail → Settings**: paste the key and audience ID, set *Send from* to `info@calvaryellicott.org`, set *Replies go to* to a mailbox someone reads, and check the footer address.
5. In the portal: import contacts, open Contacts, click **Sync now**. Then write a campaign, send yourself a test, and send.

Bulk email law (CAN-SPAM) needs a physical mailing address and a working unsubscribe link in every campaign. The portal adds both automatically from the settings.

## Importing from ChurchTrac

ChurchTrac → People → export to CSV. In the portal, Import from ChurchTrac → drop the file (or paste its text) → Import. The importer needs a column called Email and picks up First name, Last name (or a single Name column), and Tags or Group when present. Existing people are updated rather than duplicated. Then sync.

## Security notes

- Mailbox passwords are encrypted with a key derived from the site's own salts (wp-config.php) using libsodium when available, otherwise AES-256-GCM. They are only ever sent to the IMAP/SMTP servers over TLS.
- The Resend API key is stored in WordPress options and never shown again after saving.
- All portal routes require a signed-in user with the `ce_mail_access` capability plus the standard REST nonce.
- Email HTML is sanitised before display (no scripts, frames, or forms). Remote images in emails are shown.
- The Resend embed-style field in the theme and this plugin both accept raw HTML only from administrators.

## Known limits (first version)

- No drafts saved on the server; a half-written reply lives only in the open window.
- Folder list shows Inbox, Sent, Drafts, Archive, Junk, Trash, and any custom folders, but moving is limited to Archive, Trash, and Inbox.
- The campaign editor is deliberately simple: headings, bold, italic, lists, links. No images yet.
- Hosts sometimes block outbound SMTP ports. If sending fails with a connection error but reading works, switch SMTP to port 587 / STARTTLS in Mail → Settings; if both ports fail, the host blocks SMTP and sending has to stay in Purelymail's webmail.
