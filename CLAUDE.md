# Calvary Ellicott website — working notes

Static single-page church website for Calvary Ellicott (Ellicott/Calhan, Colorado), hosted on GitHub Pages under the church's own GitHub organization. No build step, no server, no dependencies.

## Project decisions
- Hosting: GitHub Pages, deploy from `main` branch, root folder. Domain DNS will live at GoDaddy (records are in README.md). Domain not yet chosen.
- Backend: ChurchTrac supplies forms and the events calendar via public URLs only. Those URLs live in `data/churchtrac.json`; `scripts/app.js` reads that file on load and wires buttons marked `data-churchtrac-link`. Blank values show a friendly "coming soon" message.
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
- Real staff names, titles, bios, photos (both staff cards are placeholders)
- Final statement of faith and transition wording for the "Our next chapter" section
- The seven public ChurchTrac URLs for `data/churchtrac.json`
- Livestream and online-giving links
- Confirm the address: 2150 N. Ellicott Hwy, Calhan, CO 80808

Build tasks (can be done any time):
- Add a Give / livestream section (the original mockup had one; it was dropped in the rebrand)
- Favicon and Open Graph image/tags for link previews
- Replace the four Unsplash hotlinked photos in `styles.css` with self-hosted church photos
- Map or directions link for the address
- Test what the ChurchTrac calendar embed actually looks like in the iframe once the URL exists
- Add the `CNAME` file once the custom domain is set in GitHub Pages settings
