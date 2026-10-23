# Calvary Ellicott WordPress theme

The static site's design as a WordPress theme, with editing screens for the things that change weekly. The theme lives in `calvary-ellicott/`. The site is hosted on **WordPress.com (Premium plan)**, which allows custom themes and plugins but not automatic deploys from GitHub, so the theme is installed by uploading a zip.

## What the theme adds to WordPress

| Screen (left menu)          | What it is                                                                           |
| --------------------------- | ------------------------------------------------------------------------------------ |
| **Pages**                   | Home, Discover, Visit, Learn, Events. That is the whole public site; everything people *do* (forms, events, groups, serving, giving) happens in ChurchTrac's Church Connect, which the site links to. Each page has a "Page banner" box for the small line above the title; the excerpt is the sentence under the title. |
| **Staff**                   | One entry per person, shown on Discover. A single form: name, role, bio, email; photo in the box on the right. Order with the Order field. |
| **Mission & Vision**        | Mission, vision, and values statements, one per entry, shown on Discover in menu order. |
| **Beliefs**                 | Statement of faith items, one per entry, in menu order. Shown on Discover.           |
| **Sermons**                 | One per week, shown on Learn. A single form: Sunday date, handout (before service), notes (after), optional summary. |
| **Alerts**                  | The title is the message. Publish one to show a banner on every page; draft or trash it to hide it, or set an expiry date. |
| **Site settings**           | Top of the left menu. Every setting as a plain form, in tabs: church details (address, email, phone), Church Connect links, homepage wording and photos, Discover page, Events page (calendar), Learn page (BoxCast, video archive, announcements picture). Appearance → Customize shows the same settings with a live preview. |
| **Appearance → Site setup** | One button that creates the four pages, sample beliefs and staff, the menu with a Church Connect link, and sets the homepage. Safe to run more than once. |

Helpers never see any of the code. They work in these screens; the theme is the only thing that comes from this repository. See `docs/helper-guide.md` for the helper-facing instructions.

## Getting the zip

Two ways, same result:

- **From GitHub.** Every push to `main` that touches `wp-theme/` runs the *Theme* workflow, which checks the PHP and attaches `calvary-ellicott.zip` to a release named after the version in `style.css` (Releases on the repo page). Download it from there.
- **On your own computer.** Double-click `tools/package-theme.bat` (or run `tools/package-theme.ps1`). It writes `wp-theme/dist/calvary-ellicott.zip`.

Bump the `Version:` line in `calvary-ellicott/style.css` when you make a change worth naming; the release tag follows it.

## First-time install on WordPress.com

1. In the WordPress.com dashboard for the site, open **Appearance → Themes**.
2. Click **Add New Theme** (or **Install theme**), then **Upload Theme**, choose `calvary-ellicott.zip`, and click **Install Now**. WordPress.com may take a minute the first time; it moves the site to its plugin-capable hosting.
3. Click **Activate**.
4. **Appearance → Site setup → Create starter content**.
5. **Appearance → Customize → Calvary Ellicott**: check the service time and address, confirm the Church Connect address (`https://connect.calvaryellicott.org`), and paste the BoxCast embed. Click **Publish**.
   Pages built earlier by hand (from `archive/wp-blocks/`) can be moved to Trash once the new ones look right. The theme finds pages by their template, not their name, so leftovers do no harm.
6. **Settings → Permalinks**: choose **Post name**, save.
7. When ready, switch the site from **Coming soon** to public under **Settings → General → Privacy** (or the Launch button on the site card).

## Updating the theme later

1. Get the new zip (see above).
2. **Appearance → Themes → Add New → Upload Theme**, choose the zip.
3. WordPress notices the theme is already installed and offers **Replace current with uploaded**. Click it.

Content is untouched by a theme update; pages, staff, bulletins, and settings all live in the WordPress database.

## Roles and review

- Helpers: **Contributor**. Their only option is *Submit for Review*.
- Pastor Dan and Scott: **Editor** or **Administrator**. They approve and publish.
- For a strict two-person sign-off, install the PublishPress plugin (Plugins → Add New) and require both approvals in its workflow. Theme code keeps the GitHub pull-request review.

## Local testing with Docker

```bash
docker compose -f wp-theme/docker-compose.yml up -d
```

Then open http://localhost:8088, finish the five-minute install, and the theme is already in place under Appearance → Themes. Stop with `docker compose -f wp-theme/docker-compose.yml down -v`.

## If the church ever moves to the Business plan

WordPress.com's **GitHub Deployments** (Settings → Hosting) can watch this repository and copy `wp-theme/calvary-ellicott` to `wp-content/themes/calvary-ellicott` on every push to `main`, which removes the zip step. Nothing in the theme needs to change for that.
