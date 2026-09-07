# Calvary Ellicott website

A responsive church website for **Calvary Ellicott**, prepared for the congregation's transition from Rocky Mountain Calvary Ellicott.

The site is fully static: plain HTML, CSS, and JavaScript with no build step and no server. It is hosted on **GitHub Pages** and uses **ChurchTrac** for forms and the events calendar.

## Pages

| File            | Purpose                                                       |
| --------------- | ------------------------------------------------------------- |
| `index.html`    | Home: hero, Sunday details, story teaser, next-step links     |
| `about.html`    | Our story and what a Sunday looks like                        |
| `staff.html`    | Staff cards, generated from `data/staff.json`                 |
| `beliefs.html`  | Statement of faith                                            |
| `events.html`   | Embedded ChurchTrac calendar                                  |
| `bulletin.html` | This week's bulletin PDF from the `bulletins/` folder         |
| `watch.html`    | BoxCast livestream embed and sermon notes from `sermons/`     |
| `connect.html`  | Links to every ChurchTrac form, plus address and service time |
| `404.html`      | Shown by GitHub Pages for missing URLs                        |

Every page shares the same header, navigation, and footer markup. When you change the navigation, update it in every page (a find-and-replace across `*.html` is the quickest way). Shared styles live in `styles.css`; shared behaviour lives in `scripts/app.js`.

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

### Adding a new ChurchTrac form or embed

Any key you add to `data/churchtrac.json` is available to the page. To use it:

- **As a button or link** that opens in a new tab, add `data-churchtrac-link="yourKey"` to an `<a>` in `index.html`.
- **Embedded on the page** as an iframe, add `data-churchtrac-embed="yourKey"` to a `<div>` (optionally with `data-embed-title="..."` for screen readers). The Events calendar works this way.

Only public URLs belong in this file. Never store ChurchTrac usernames, passwords, private API keys, or member data in this repository.

### Updating a link

1. Open `data/churchtrac.json` on GitHub and click the pencil icon.
2. Paste the public URL from ChurchTrac between the quotes.
3. Commit the change. GitHub Pages republishes within a minute or two.

## Livestream and sermon notes

**Livestream.** Paste the BoxCast embed URL into [`data/watch.json`](data/watch.json):

```json
{ "boxcastEmbed": "https://boxcast.tv/view-embed/..." }
```

In BoxCast, open the channel or broadcast, choose Embed, and copy the address from the `src="..."` part of the code it gives you. Only that address is needed. Leave the value blank to show a "being connected" placeholder.

**Sermon notes.** Two PDFs per week in the [`sermons/`](sermons/) folder, named by the Sunday date:

```
sermons/2026-09-13.pdf         the handout, posted before the service
sermons/2026-09-13-notes.pdf   the pastor's full notes, posted afterwards
```

Either can exist on its own. The Watch page shows this week's files as download buttons, notes that the full notes are coming if only the handout is up, and lists previous weeks underneath. It looks back 12 Sundays (`watch.weeksBack` and `watch.weekday` in `data/content.json`).

## Weekly bulletin

Save each week's bulletin as a PDF in the [`bulletins/`](bulletins/) folder, named by its Sunday date:

```
bulletins/2026-09-13.pdf
```

That is the whole process. The Bulletin page finds the newest file, shows it in a viewer with an Open PDF button, and lists the older ones underneath. It looks at today and the last 12 Sundays; change `bulletin.weeksBack` or `bulletin.weekday` (0 = Sunday) in `data/content.json` if the schedule differs. When no file is found the page shows a "posted soon" message.

## Alert banner

For weather closures, schedule changes, or any short notice, edit [`data/alert.json`](data/alert.json):

```json
{
  "enabled": true,
  "style": "warning",
  "message": "Sunday services are canceled this week due to snow. Stay safe and warm!",
  "linkText": "Contact us",
  "link": "connect.html",
  "expires": "2026-01-20"
}
```

| Field      | Notes                                                                                   |
| ---------- | --------------------------------------------------------------------------------------- |
| `enabled`  | `true` shows the banner on every page, `false` hides it                                 |
| `style`    | `warning` (red) or `info` (gold)                                                        |
| `message`  | The text. `*asterisks*` for emphasis work here too                                      |
| `link`     | Optional. A page such as `connect.html` or a full `https://` address                    |
| `linkText` | Optional label for the link, defaults to "Learn more"                                   |
| `expires`  | Optional date, `YYYY-MM-DD`. The banner hides itself after that day, so you can forget it |

Visitors can dismiss the banner with the × for the rest of their visit. A new message shows again.

## Editing the words on the site

All headings, paragraphs, labels, and the church's basic details live in [`data/content.json`](data/content.json). Edit that file to change what the site says; you do not need to touch the HTML.

- The `site` block holds the details used in several places: name, service day and time, and address. Change the time once and it updates the hero, the visit details, the Connect page, and the footer.
- Each page has its own block (`home`, `about`, `staff`, `beliefs`, `events`, `connect`) with a `hero` for the banner and a block per section below it.
- A headline can be a single string or a list of strings. Each list item is one line.
- Wrap words in asterisks to show them in the accent style: `"*Growing in grace.*"`.
- Write `{site.serviceTime}` (or any other `site` value) inside a string to reuse it.
- Lists you can add to or reorder: `beliefs.items` (statement of faith), `connect.forms` (each needs a `key` matching `data/churchtrac.json` and a `label`), the `details` rows under a section, and the `nextSteps.cards` on Home and About.

Keep the file valid JSON: every string in double quotes, commas between items, no comma after the last one. If the file has a mistake the page falls back to the built-in copy, so nothing breaks, but your edits will not show until it is fixed. Pasting the file into a JSON checker (for example jsonlint.com) will point to the problem line.

## Staff

Staff cards are generated from [`data/staff.json`](data/staff.json). The page loops over the list and builds one card per entry, in the order listed, so adding a person is adding an entry:

```json
{
  "name": "Pastor John Smith",
  "role": "Lead Pastor",
  "bio": "One or two sentences.",
  "email": "john@example.org"
}
```

| Field   | Notes                                                                          |
| ------- | ------------------------------------------------------------------------------ |
| `name`  | Displayed as the card heading; also decides the photo file name (see below)    |
| `role`  | Short title, for example "Lead Pastor"                                         |
| `bio`   | One or two sentences                                                           |
| `email` | Optional; adds a "Send an email" link                                          |
| `photo` | Optional override, for example `images/staff/other-name.jpg`                   |

**Photos link by name.** Save the photo in `images/staff/` as the person's name in lowercase with hyphens: `Pastor John Smith` becomes `pastor-john-smith.jpg`. No `photo` field needed. If no file matches, the card shows initials. Details are in [`images/staff/README.md`](images/staff/README.md).

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
