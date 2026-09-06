# Calvary Ellicott website

A responsive, single-page church website for **Calvary Ellicott**, prepared for the congregation's transition from Rocky Mountain Calvary Ellicott.

The site is fully static: plain HTML, CSS, and JavaScript with no build step and no server. It is hosted on **GitHub Pages** and uses **ChurchTrac** for forms and the events calendar.

## How the ChurchTrac connections work

All public ChurchTrac links live in one file: [`data/churchtrac.json`](data/churchtrac.json). The page reads it on load and wires each link to the matching button.

| Key             | What it does                                                         |
| --------------- | -------------------------------------------------------------------- |
| `calendarEmbed` | Embeddable public calendar URL, shown inside the Events section      |
| `calendar`      | Full public calendar page, opened by the "Full calendar" button      |
| `visit`         | Plan-a-visit form                                                    |
| `prayer`        | Prayer-request form                                                  |
| `connect`       | Connection card or new-here form                                     |
| `serve`         | Volunteer-interest form                                              |
| `contact`       | General contact form                                                 |

Every value must begin with `https://`. Blank values intentionally display a friendly "coming soon" message. Forms open in a new tab; the calendar is embedded on the page.

Only public URLs belong in this file. Never store ChurchTrac usernames, passwords, private API keys, or member data in this repository.

### Updating a link

1. Open `data/churchtrac.json` on GitHub and click the pencil icon.
2. Paste the public URL from ChurchTrac between the quotes.
3. Commit the change. GitHub Pages republishes within a minute or two.

## Hosting on GitHub Pages

1. In the repository, open **Settings → Pages**.
2. Under **Build and deployment**, choose **Deploy from a branch**, select `main` and the `/ (root)` folder, then save.
3. The site publishes at `https://<org>.github.io/<repo>/` until a custom domain is added.

### Custom domain

1. In **Settings → Pages → Custom domain**, enter the domain (for example `calvaryellicott.org`) and save. GitHub creates a `CNAME` file in the repo; commit it.
2. At the DNS provider (GoDaddy), add these records:
   - `A` records for `@` pointing to `185.199.108.153`, `185.199.109.153`, `185.199.110.153`, and `185.199.111.153`
   - `CNAME` record for `www` pointing to `<org>.github.io`
3. Once DNS propagates, check **Enforce HTTPS** in the Pages settings.

## Previewing locally

No install is required. From the project folder, run any static file server, for example:

```bash
python -m http.server 8080
```

Then open `http://localhost:8080`.

## Content still needed before launch

- Confirmed staff names, titles, biographies, and photos
- Final statement of faith approved by church leadership
- Confirmation of the new legal/organizational status and transition wording
- Public ChurchTrac calendar and form URLs
- Final livestream and online-giving links, if desired
- Real photos to replace the stock Unsplash images referenced in `styles.css`
- Favicon and a social-sharing (Open Graph) image
