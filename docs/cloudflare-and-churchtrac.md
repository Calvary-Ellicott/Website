# Cloudflare and the ChurchTrac subdomain (connect.calvaryellicott.org)

Goal: `connect.calvaryellicott.org/<anything>` opens the matching page on the church's ChurchTrac
Church Connect site, and the domain's DNS is on Cloudflare so other Cloudflare features can be added later.

## Where things stand (checked 2026-10-11)

| Item | Value |
| --- | --- |
| Domain | `calvaryellicott.org` (the `.com` is not registered; every link uses `.org`) |
| Registrar | Automattic (WordPress.com). Transferred in 2026-09-09, expires 2027-11-19 |
| Nameservers | `ns1/ns2/ns3.wordpress.com` (WordPress.com manages DNS) |
| Root record | `A` → `192.0.78.24`, `192.0.78.25` (WordPress.com); `www` is a `CNAME` to the root |
| WordPress.com site address | `hookerdk-qsyuk.wordpress.com` (visible in the site's response headers; use it as the CNAME target) |
| `connect.calvaryellicott.org` | does not exist yet |

**Update 2026-10-11 (later):** option 2 chosen with the pastor. Nameservers were switched at WordPress.com to
`kobe.ns.cloudflare.com` and `vivienne.ns.cloudflare.com`; the .org registry shows them, and public resolvers were
still serving the cached WordPress.com nameservers (24 h TTL). The Cloudflare zone holds: root `A` to the two
WordPress.com IPs (DNS only), `www` CNAME to root (DNS only), and a leftover `churchtrac` CNAME to the root,
which gives a certificate error and should be deleted. ChurchTrac's Domain Setup will not accept a name
containing "churchtrac", so the subdomain is `connect` instead (chosen 2026-10-11, matching the Church Connect
product name). The old
WordPress.com zone had no MX or TXT records, so no email was affected. DNSSEC is not enabled yet.

**Done 2026-10-11 (evening):** working. ChurchTrac's Cloudflare-API path failed with error D653 ("error retrieving
your domain information from Cloudflare") even with a valid token (Zone:Read + DNS:Edit on the zone). What worked:
in ChurchTrac's Domain Setup, choose the option saying the domain is **not** on Cloudflare and add the records it
shows by hand. Result: `connect` is a **DNS-only** CNAME to a ChurchTrac CloudFront host (`dxxo0f0orhwmb.cloudfront.net`)
and serves Church Connect over HTTPS. Keep it DNS only (CloudFront holds the certificate), and do not delete any
underscore-prefixed validation CNAMEs ChurchTrac asked for. Root and `www` serve WordPress.

Two facts shape the design:

- WordPress.com **allows** moving nameservers to Cloudflare but asks that the records for the WordPress site stay
  **DNS only** (grey cloud, no proxy) and that SSL/TLS be set to **Full (strict)**. Proxying the main site through
  Cloudflare is discouraged, so Cloudflare's WAF/cache features are not on the table for the WordPress pages; DNS,
  redirect rules, and email routing are.
- ChurchTrac Church Connect has its own **Domain Setup** (ChurchTrac → Connect Settings → Domain Setup). It walks
  you through the records and recommends Cloudflare nameservers. The public docs only show the root-domain case
  (`yourchurch.com` becoming the Connect site), so whether it accepts a subdomain is something to try in that
  screen first. The outcome decides which of the two routes below applies.

## Step 1: move DNS to Cloudflare (needed for either route)

1. Create a free Cloudflare account for the church (church email, not a personal one) and **Add a site** →
   `calvaryellicott.org` → Free plan.
2. Cloudflare scans the existing records. Before changing nameservers, compare the scan with what WordPress.com
   shows under **Hosting Dashboard → Domains → calvaryellicott.org → DNS records**, and copy anything missing
   (MX, TXT, SPF/DKIM, verification records). Missing mail records are the usual way a nameserver move breaks email.
3. Make the WordPress records look like this, all **DNS only** (grey cloud):

   | Type | Name | Target | Proxy |
   | --- | --- | --- | --- |
   | CNAME | `@` | `hookerdk-qsyuk.wordpress.com` | DNS only |
   | CNAME | `www` | `calvaryellicott.org` | DNS only |

   Cloudflare flattens the root CNAME automatically. Keeping the two `A` records instead also works, but
   WordPress.com says its IPs may change, so the CNAME is safer.
4. In WordPress.com, **Hosting Dashboard → Domains → calvaryellicott.org → Name servers**, turn off
   "Use WordPress.com name servers" and enter the two Cloudflare nameservers shown in the Cloudflare dashboard.
5. In Cloudflare, **SSL/TLS → Overview → Full (strict)**. Wait for Cloudflare to report the zone active (minutes to
   a few hours). Check `https://calvaryellicott.org` and `https://www.calvaryellicott.org` still load.

## Step 2, route A: ChurchTrac hosts the subdomain directly (the plan)

Decided 2026-10-11: once DNS is on Cloudflare, ChurchTrac's Domain Setup can take a subdomain, so this is the
route. Root and `www` stay WordPress; `connect.calvaryellicott.org/*` is the Connect site. Route B below is
kept only as a fallback.

In ChurchTrac → Connect Settings → Domain Setup, enter `connect.calvaryellicott.org`. If it accepts a subdomain
it will show one or more records (normally a `CNAME` on `connect`, sometimes a `TXT` for verification). Add
them in Cloudflare exactly as shown, **DNS only** at first. Once ChurchTrac reports the domain connected and
HTTPS works, you are done: every Connect path works under the subdomain because it *is* the Connect site, nothing
to maintain, and the address bar stays on `calvaryellicott.org`.

Only after it works, optionally flip the `connect` record to proxied and re-test. If ChurchTrac's certificate
check fails with the proxy on, set it back to DNS only and leave it.

## Step 2, route B: Cloudflare redirect (if ChurchTrac insists on a root domain)

This forwards every path to the ChurchTrac-hosted address. Visitors end up on `<church>.churchtrac.com` in the
address bar; the subdomain is a short, memorable door, not a mask.

1. DNS record (must be **proxied**, otherwise the redirect rule never runs):

   | Type | Name | Target | Proxy |
   | --- | --- | --- | --- |
   | AAAA | `connect` | `100::` | Proxied |

2. **Rules → Redirect Rules → Create rule**, wildcard pattern:

   | Field | Value |
   | --- | --- |
   | Request URL | `https://connect.calvaryellicott.org/*` |
   | Target URL | `https://CHURCH-SLUG.churchtrac.com/${1}` |
   | Status code | 302 while testing, 301 once it is right |
   | Preserve query string | on |

   Replace `CHURCH-SLUG` with the church's ChurchTrac subdomain (the address of the Connect site today). Use
   `${1}` because the only wildcard in the pattern is the path one; Cloudflare's own example uses `${2}` only
   because its pattern starts with `http*://`.

3. Test `https://connect.calvaryellicott.org/`, `/give`, `/events`, and a URL with `?x=1`. Then add a second
   rule for `http://connect.calvaryellicott.org/*` or turn on **SSL/TLS → Edge Certificates → Always Use
   HTTPS** so plain-http links also work.

## What not to do

- Do not reverse-proxy ChurchTrac through a Cloudflare Worker to hide the `churchtrac.com` address. Forms and
  giving rely on absolute URLs, cookies, and their own security headers; it breaks in ways that are hard to see
  and the church cannot support it.
- Do not proxy (orange-cloud) the `@` or `www` records; WordPress.com warns against it.

## Related settings once the subdomain exists

- Form Cards and giving embeds only load on the domain entered under **Form/Giving Embed Domain** in ChurchTrac.
  That should be `calvaryellicott.org` (the WordPress site, where the iframes are), not the subdomain.
- Buttons in the WordPress pages (`wp-blocks/*.html` placeholders starting with `https://REPLACE-WITH-CHURCHTRAC-`)
  can point at `https://connect.calvaryellicott.org/...` once it resolves, so the links survive a later move.
- The WordPress site also has a Connect page at `/connect/`. Keep it as the page that links out to the ChurchTrac
  forms so the two "Connect" names point the same way.
