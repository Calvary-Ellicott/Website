# Calvary Ellicott website — working notes

Static multi-page church website for Calvary Ellicott (Ellicott/Calhan, Colorado), hosted on GitHub Pages under the church's own GitHub organization. No build step, no server, no dependencies.

## Structure
- Pages: `index.html` (home), `about.html`, `staff.html`, `beliefs.html`, `events.html`, `bulletin.html`, `watch.html`, `connect.html`, `404.html`, plus `update-guide.html` (helper guide, no nav link, `noindex`; its source is `docs/helper-guide.md` and the HTML must be regenerated or hand-updated when the markdown changes). Each page is focused on one thing; the owner chose pages over one long scrolling page.
- Header, nav, and footer markup is duplicated verbatim in every page (no templating). Change it everywhere at once. `scripts/app.js` sets `aria-current="page"` on the matching nav link.
- Inner pages open with a `.page-hero` block (eyebrow, h1, lead). The home page keeps the full-bleed `.hero`.
- An unconfigured ChurchTrac link on any page redirects to `connect.html` and shows the "coming soon" note there via `sessionStorage`.

## Project decisions
- Hosting: GitHub Pages, deploy from `main` branch, root folder (static site, now superseded by WordPress.com, see below). The domain is `calvaryellicott.org` (the `.com` is not registered), registered at Automattic/WordPress.com. On 2026-10-11 its nameservers moved to Cloudflare (root and `www` stay DNS-only records to WordPress.com). `connect.calvaryellicott.org` is the ChurchTrac Church Connect side (ChurchTrac rejects subdomains containing "churchtrac"). The README's GoDaddy/GitHub Pages DNS steps are historical. Full plan and current state: `docs/cloudflare-and-churchtrac.md`.
- Backend: ChurchTrac supplies forms and the events calendar via public URLs only. Those URLs live in `data/churchtrac.json`; `scripts/app.js` reads that file on load. Any key works: `data-churchtrac-link="key"` on an `<a>` opens it in a new tab, `data-churchtrac-embed="key"` on a `<div>` swaps in an iframe. Blank or non-https values show a friendly "coming soon" message.
- All page copy lives in `data/content.json`. `scripts/app.js` fills `[data-content="path"]` elements (string or array of lines; `*text*` becomes `<em>`; `{site.key}` interpolates) and `[data-content-list="path"][data-list-type]` blocks (types: details, cards, paragraphs, beliefs, forms). The HTML keeps the same copy as a fallback; keep both in sync when adding sections. Content is applied before ChurchTrac wiring because the Connect form list is rendered from JSON.
- Dated PDFs: `findDatedFiles(folder, variants, settings)` in app.js sends HEAD requests for today plus the last `weeksBack` Sundays. Bulletin page uses `bulletins/YYYY-MM-DD.pdf` (settings in `bulletin`); Watch page uses `sermons/YYYY-MM-DD.pdf` (handout) and `sermons/YYYY-MM-DD-notes.pdf` (pastor notes) with settings in `watch`. CSP has `frame-src 'self'` and `object-src 'self'` so same-origin PDFs can embed. Expect 404s in the console from the probing.
- Watch page livestream: `data/watch.json` `boxcastEmbed` https URL goes into an iframe in `[data-watch-embed]` with autoplay/fullscreen allowed. BoxCast keeps its own archive of past services inside the player.
- Alert banner: `data/alert.json` (enabled, style warning|info, message, link, linkText, expires YYYY-MM-DD). `applyAlert` prepends `.site-alert` to body and sets `--alert-height` so the fixed header sits below it. Dismissal is per session via sessionStorage keyed by message text.
- Staff cards render from `data/staff.json` (name, role, bio, email, optional photo). Photos are found by convention: `images/staff/<slug-of-name>.jpg` (then jpeg/png/webp), falling back to initials. The browser probes for the file, so 404s in the console for staff images are expected. The placeholder cards in `index.html` only show if the JSON is missing or empty.
- Keep it static. Do not reintroduce a server, admin panel, framework, or bundler. The site owner dislikes the GoDaddy and ChurchTrac site builders and wants hand-written HTML/CSS/JS.
- Security policy is a `<meta http-equiv="Content-Security-Policy">` tag in `index.html`. If you add a new external host (images, embeds, fonts), add it there too or it will be silently blocked.
- Never commit ChurchTrac credentials, API keys, or member data. Only public URLs belong in the repo.
- Preview locally by double-clicking `preview.bat` (runs `tools/preview-server.ps1`, a PowerShell TcpListener static server on port 8080 with HEAD support) or `preview.command` on Mac. `python -m http.server 8080` also works.
- Review policy: ruleset on `main` requires a PR with 2 approvals, code-owner review, dismisses stale approvals, and the `Required approvals` status check from `.github/workflows/required-approvals.yml`, which passes only when both `primo4e` and Pastor Dan (placeholder `PASTOR-DAN-GITHUB-USERNAME` in CODEOWNERS and the workflow) have approved or authored. Repo admins may bypass.

## WordPress theme (current direction)
- On 2026-09-22 the pastor chose WordPress. On 2026-09-23 it turned out the site is on **WordPress.com, Premium plan** (calvaryellicott.org, still "Coming soon"). Premium allows custom theme upload and plugins but not GitHub Deployments or SSH (Business plan only), so the theme is installed by zip upload. The design is being kept, so `wp-theme/calvary-ellicott/` is a custom theme reproducing the static site. Work on the theme going forward; the static pages remain as a reference and are no longer the deliverable.
- Site structure (pastor's spec, 2026-10-11): the public site is only **Home, Discover, Visit, Learn**. Everything people do (forms, events/registration, groups, serving, giving, follow-up) lives in ChurchTrac's Church Connect at `https://connect.calvaryellicott.org`; the site links out to it. Theme v1.1.0.
- Theme layout: `functions.php` bootstraps `inc/` (template-tags, post-types, meta-boxes, customizer, setup). Page templates in `page-templates/` are `discover.php` (story = page content, beliefs accordion, staff grid), `visit.php` (details, directions, plan-a-visit link), `learn.php` (BoxCast, sermons, bulletins); `ce_page_by_template()` finds pages by template so links never depend on slugs. `front-page.php` is the homepage with a Church Connect band; copy comes from the Customizer (`ce_opt()` with defaults in `inc/customizer.php`). Church Connect links: `ce_connect_url($key)` / `ce_connect_link()` use `connect_url` plus optional `connect_<events|groups|serve|give|forms>_url`; the nav fallback and Site setup add a Church Connect CTA item.
- Custom post types (all non-public, shown by templates): `ce_staff` (title=name, content=bio, featured image=photo, meta `_ce_role`, `_ce_email`, ordered by menu_order), `ce_belief` (menu_order), `ce_bulletin` (post date = Sunday, meta `_ce_pdf`), `ce_sermon` (post date = Sunday, meta `_ce_handout`, `_ce_notes`), `ce_alert` (published = shown, meta `_ce_style`, `_ce_expires`, `_ce_link`, `_ce_link_page`, `_ce_link_text`). Pages have `_ce_eyebrow`; the excerpt is the banner lead.
- `Appearance → Site setup` (inc/setup.php) creates Home/Discover/Visit/Learn, sample beliefs/staff (Dr. Dan Hooker, Don Drury), the menu with a Church Connect custom link, and sets the front page; idempotent.
- `style.css` is the static `styles.css` plus a theme header and a few WordPress-specific rules (menu `ul/li` reset, `.prose` for block content). Keep the two stylesheets in sync if the static site is still touched.
- Scott has WordPress admin as of 2026-10-11. The Editor-only workaround (block markup pasted into the pastor's theme) is archived in `archive/wp-blocks/` with its generator.
- Deploy: `.github/workflows/deploy-theme.yml` ("Theme") lints PHP on PRs and pushes; on push to main it zips the theme and attaches `calvary-ellicott.zip` to a GitHub Release tagged `theme-v<Version from style.css>`. Locally, `tools/package-theme.bat` writes `wp-theme/dist/calvary-ellicott.zip` (gitignored). Install/update = Appearance → Themes → Upload Theme → Replace current with uploaded. No GoDaddy/SSH workflow remains. Local testing: `wp-theme/docker-compose.yml` (WordPress on :8088, theme bind-mounted). No PHP is installed on this machine; lint with Docker `php:8.2-cli`.
- `docs/helper-guide.md` is written for the WordPress dashboard (Contributor role, Submit for Review). `update-guide.html` is regenerated from it by the scratch generator.
- Content review on WordPress uses roles (Contributor/Editor) and optionally PublishPress; the GitHub two-approval rule now only covers theme code.

## History
- Started as an RMCE Ellicott mockup with a Planning Center backend, then rebranded to Calvary Ellicott with ChurchTrac and a small Node admin panel.
- On 2026-09-06 the Node server was removed, the site was made fully static, and it was moved into this fresh repo so the church org owns it outright.
- The static GitHub Pages site (index.html etc.) was completed 2026-09-13 with pages, JSON content, alert banner, bulletin and watch pages, then superseded by the WordPress decision on 2026-09-22.
- The retired mockup lives at `Documents/Storm Veil Technologies/RMCE Website` and should not be edited.

## Remaining work
Content (needs church leadership):
- Real staff names, titles, bios, photos for `data/staff.json` (photos being taken the week of 2026-09-07)
- Final statement of faith (`beliefs.items` in content.json) and transition wording (`about.story` and `home.story`)
- The seven public ChurchTrac URLs for `data/churchtrac.json`
- BoxCast embed URL for `data/watch.json`; online-giving link if wanted
- Confirm the address: 2150 N. Ellicott Hwy, Calhan, CO 80808

Build tasks (can be done any time):
- Add a Give / livestream section (the original mockup had one; it was dropped in the rebrand)
- Favicon and Open Graph image/tags for link previews
- Replace the four Unsplash hotlinked photos in `styles.css` with self-hosted church photos
- Map or directions link for the address
- Test what the ChurchTrac calendar embed actually looks like in the iframe once the URL exists
- Add the `CNAME` file once the custom domain is set in GitHub Pages settings
