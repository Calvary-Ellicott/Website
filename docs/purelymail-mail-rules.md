# Purelymail: spam pre-filter and the info@ fan-out

How mail for `@calvaryellicott.org` is routed inside Purelymail. Nothing here touches the website; it lives in
the Purelymail account portal (Account → Routing). Written 2026-10-23 while the trial was being set up.

## What Purelymail gives us, and where each thing has to go

Purelymail processes a message in this order:

1. **SMTP time:** senders on known blocklists are rejected before the message is even received, and
   unknown senders may be greylisted. This is free and already on.
2. **Account routing rules** (Routing page): exact / prefix / catch-all address matches that redirect to
   one or more addresses (comma-separated). Runs before any Sieve.
3. **Account-wide Sieve script** (the text box in the screenshot): runs for every address on the domain,
   even ones with no mailbox. `redirect`, `keep`, `reject` work; `fileinto` does not. **The SpamAssassin
   headers (`X-Spam-Status`) are not reliably present yet at this stage**, so this script cannot filter
   on the spam score.
4. **Spam scoring** per user (SpamAssassin, threshold 5, not adjustable). Anything over 5 lands in Junk.
5. **User Sieve filters** (webmail → Filters, one set per mailbox): full Sieve, including `fileinto`,
   and the spam score is available here.

Consequences:

- A real "kill spam before any mailbox sees it" rule is only possible for things we can recognise
  without a score (sender domains, subject phrases). Expect that to catch a little, not most.
- The thing that actually keeps storage down is a **per-mailbox** filter that discards mail scoring
  well above the threshold so it never sits in Junk. Each person needs the same filter.
- `info@` does not need a mailbox. A routing rule (or the Sieve `redirect` block) accepts mail for it
  and hands copies to everyone else. Because the fan-out happens before spam scoring, each copy is
  scored in its recipient's mailbox, so the per-mailbox filter covers that too.

## Step 1: info@ fan-out (routing rule, preferred)

On the Routing page add one rule:

| Domain | Match | Address | Deliver to |
| --- | --- | --- | --- |
| `calvaryellicott.org` | Exact | `info` | `dan@calvaryellicott.org, scott@calvaryellicott.org, don@calvaryellicott.org` |

Use the real mailbox names. Update the list whenever a mailbox is added or removed. Routing rules match
the envelope recipient, so this also catches mail where `info@` was only in Bcc, which the Sieve version
below cannot see. Do **not** create an `info` user; a rule takes precedence over a mailbox anyway.

## Step 2: account-wide Sieve script (the box in the screenshot)

```sieve
# Calvary Ellicott - account-wide filter (Purelymail -> Account -> Routing)
# Runs for every address on calvaryellicott.org before spam scoring and before
# any user's own filters. No spam score is available here, so this is only a
# cheap first cut. Keep the real spam handling in each mailbox's filter.
require ["reject"];

# --- 1. Obvious junk by sender domain -----------------------------------
# One entry per domain; subdomains are NOT matched, add them separately.
if address :domain :is "from" [
    "example-spammer.com",
    "another-junk.net"
] {
    discard;
    stop;
}

# --- 2. Obvious junk by subject phrase (case-insensitive) ---------------
if header :contains "subject" [
    "[SPAM]",
    "casino bonus",
    "crypto airdrop",
    "SEO services for your website"
] {
    discard;
    stop;
}

# --- 3. Mail with no From header at all ---------------------------------
if not exists "from" {
    discard;
    stop;
}

# --- 4. info@ fan-out ---------------------------------------------------
# Only needed if the routing rule in Step 1 is NOT set up. If both exist the
# routing rule wins and this block never runs. Matches To/Cc only; Bcc'd mail
# to info@ is invisible here, which is why the routing rule is preferred.
if address :is ["to", "cc"] "info@calvaryellicott.org" {
    redirect "dan@calvaryellicott.org";
    redirect "scott@calvaryellicott.org";
    redirect "don@calvaryellicott.org";
    stop;   # redirect cancels the implicit keep; info@ has no mailbox anyway
}
```

Notes:

- `discard` silently drops the message. `reject` would send a bounce back, which for spam is just
  backscatter to forged senders; use `reject` only for a real address you want to refuse politely.
  Purelymail's note lists `reject` as working at this stage and does not mention `discard`; if a test
  message is still delivered after a `discard` rule, swap it for `reject` and tell them (the note says to
  open a feature request for missing pieces).
- Lists are easy to grow: add a line, keep the commas, keep the closing `]`.
- Do not add a rule like "drop anything not addressed to our domain". Bcc'd mail, calendar invites and
  mailing lists all look like that and would be lost.

## Step 3: per-mailbox filter (each person, webmail → Filters → edit as Sieve)

```sieve
require ["fileinto"];

# Drop mail that scores 10 or more (the Junk threshold is 5) so it never uses
# storage. Everything between 5 and 10 still goes to Junk for review.
# X-Spam-Status looks like: "Yes, score=12.3 required=5.0 tests=..."
# "??" = exactly two characters before the first dot, i.e. a two-digit score.
if header :matches "X-Spam-Status" ["Yes, score=??.*", "Yes, score=???.*"] {
    discard;
    stop;
}

# Copies that arrived via info@ go to their own folder.
if address :is ["to", "cc"] "info@calvaryellicott.org" {
    fileinto "Info";
    stop;
}
```

Before trusting the discard rule, open one message in Junk, view its headers and confirm the
`X-Spam-Status` line really starts with `Yes, score=`. If Purelymail formats it differently, adjust the
pattern. A gentler first week: replace `discard;` with `fileinto "Junk";` and look at what lands there
with a two-digit score, then switch to `discard` once nothing legitimate shows up.

Marking real mail as "Not spam" and junk as "Spam" in webmail trains each mailbox's own classifier,
which only kicks in after about 200 examples of each.

## Keeping storage down otherwise

- Empty Junk and Trash now and then; Purelymail bills on storage, and Junk counts.
- Large attachments are the other big cost. Save them elsewhere and delete the message if it matters.
