# Calvary Ellicott website — working notes

Static multi-page church website for Calvary Ellicott (Ellicott/Calhan, Colorado), hosted on GitHub Pages under the church's own GitHub organization. No build step, no server, no dependencies.

## Structure
- Pages: `index.html` (home), `about.html`, `staff.html`, `beliefs.html`, `events.html`, `bulletin.html`, `watch.html`, `connect.html`, `404.html`. Each page is focused on one thing; the owner chose pages over one long scrolling page.
- Header, nav, and footer markup is duplicated verbatim in every page (no templating). Change it everywhere at once. `scripts/app.js` sets `aria-current="page"` on the matching nav link.
- Inner pages open with a `.page-hero` block (eyebrow, h1, lead). The home page keeps the full-bleed `.hero`.
- An unconfigured ChurchTrac link on any page redirects to `connect.html` and shows the "coming soon" note there via `sessionStorage`.

## Project decisions
- Hosting: GitHub Pages, deploy from `main` branch, root folder. Domain DNS will live at GoDaddy (records are in README.md). Domain not yet chosen.
- Backend: ChurchTrac supplies forms and the events calendar via public URLs only. Those URLs live in `data/churchtrac.json`; `scripts/app.js` reads that file on load. Any key works: `data-churchtrac-link="key"` on an `<a>` opens it in a new tab, `data-churchtrac-embed="key"` on a `<div>` swaps in an iframe. Blank or non-https values show a friendly "coming soon" message.
- All page copy lives in `data/content.json`. `scripts/app.js` fills `[data-content="path"]` elements (string or array of lines; `*text*` becomes `<em>`; `{site.key}` interpolates) and `[data-content-list="path"][data-list-type]` blocks (types: details, cards, paragraphs, beliefs, forms). The HTML keeps the same copy as a fallback; keep both in sync when adding sections. Content is applied before ChurchTrac wiring because the Connect form list is rendered from JSON.
- Dated PDFs: `findDatedFiles(folder, variants, settings)` in app.js sends HEAD requests for today plus the last `weeksBack` Sundays. Bulletin page uses `bulletins/YYYY-MM-DD.pdf` (settings in `bulletin`); Watch page uses `sermons/YYYY-MM-DD.pdf` (handout) and `sermons/YYYY-MM-DD-notes.pdf` (pastor notes) with settings in `watch`. CSP has `frame-src 'self'` and `object-src 'self'` so same-origin PDFs can embed. Expect 404s in the console from the probing.
- Watch page livestream: `data/watch.json` `boxcastEmbed` https URL goes into an iframe in `[data-watch-embed]` with autoplay/fullscreen allowed. BoxCast keeps its own archive of past services inside the player.
- Alert banner: `data/alert.json` (enabled, style warning|info, message, link, linkText, expires YYYY-MM-DD). `applyAlert` prepends `.site-alert` to body and sets `--alert-height` so the fixed header sits below it. Dismissal is per session via sessionStorage keyed by message text.
- Staff cards render from `data/staff.json` (name, role, bio, email, optional photo). Photos are found by convention: `images/staff/<slug-of-name>.jpg` (then jpeg/png/webp), falling back to initials. The browser probes for the file, so 404s in the console for staff images are expected. The placeholder cards in `index.html` only show if the JSON is missing or empty.
- Keep it static. Do not reintroduce a server, admin panel, framework, or bundler. The site owner dislikes the GoDaddy and ChurchTrac site builders and wants hand-written HTML/CSS/JS.
- Security policy is a `<meta http-equiv="Content-Security-Policy">` tag in `index.html`. If you add a new external host (images, embeds, fonts), add it there too or it will be silently blocked.
- Never commit ChurchTrac credentials, API keys, or member data. Only public URLs belong in the repo.
- Preview locally with `python -m http.server 8080` from this folder.

## History
- Started as an RMCE Ellicott mockup with a Planning Center backend, then rebranded to Calvary Ellicott with ChurchTrac and a small Node admin panel.
- On 2026-09-06 the Node server was removed, the site was made fully static, and it was moved into this fresh repo so the church org owns it outright.
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
