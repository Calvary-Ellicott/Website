# Calvary Ellicott WordPress theme

The static site's design as a WordPress theme, with editing screens for the things that change weekly. The theme lives in `calvary-ellicott/`. The site is hosted on **WordPress.com (Premium plan)**, which allows custom themes and plugins but not automatic deploys from GitHub, so the theme is installed by uploading a zip.

## What the theme adds to WordPress

| Screen (left menu)          | What it is                                                                           |
| --------------------------- | ------------------------------------------------------------------------------------ |
| **Pages**                   | Home, About, Staff, Beliefs, Events, Bulletin, Watch, Connect. Each has a "Page banner" box for the small line above the title; the excerpt is the sentence under the title. |
| **Staff**                   | One entry per person. Name is the title, bio is the text, photo is the featured image, role and email are in the box below. Order with the Order field. |
| **Beliefs**                 | Statement of faith items, one per entry, in menu order.                              |
| **Bulletins**               | One per week. Set the publish date to the Sunday, attach the PDF.                    |
| **Sermons**                 | One per week. Set the publish date to the Sunday, attach the handout before service and the notes after. |
| **Alerts**                  | Publish one to show a banner on every page. Draft it to hide it, or set an expiry date. |
| **Appearance → Customize**  | Under "Calvary Ellicott": service time and address, homepage wording and photos, ChurchTrac links, BoxCast embed, wording for the Staff invite card and empty states. |
| **Appearance → Site setup** | One button that creates the standard pages, sample beliefs and staff, the menu, and sets the homepage. Safe to run more than once. |

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
5. **Appearance → Customize → Calvary Ellicott**: enter the service time, address, ChurchTrac links, and BoxCast embed. Click **Publish**.
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
