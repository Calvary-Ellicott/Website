# Church Connect (connect.calvaryellicott.org)

How the ChurchTrac side is styled and built, one page at a time.

## How Connect is put together

- The **home screen** is a grid of cards (events, giving, forms, links, page cards). Cards are managed under Cards; the home screen has no sections.
- Every other **page** is a Page card. A page is a stack of **sections** (header, body sections, footer). Each section has a code view that takes raw HTML, and there is no size limit per section.
- There is one **custom CSS** box for the whole site, limited to about **2,000 characters**. Anything over that is silently cut off.
- ChurchTrac ships a small layout toolkit on every page (`connect-template` classes): `template-split` (side by side), `template-grid-2/3/4` (tiles that wrap), `template-tile` + `template-tile-body`, `template-eyebrow`, `template-actions` (button row), `template-btn-outline`, `template-media` (centered image), `template-map` (fixed-height iframe), `template-measure` (narrow text column). Text helpers: `text-color-light`, `text-color-dark`, `text-color-muted`, `text-color-accent`, `paragraph-1/2/3`, `h1`…`h4`.

## The split

| Goes in | What |
| --- | --- |
| `custom-compact.css` (the 2,000-char box) | Site-wide chrome only: fonts, page and nav colours, card corners, button and link colours, tile recolouring. Keep it under the limit; `wc -c` it before pasting. |
| Section code view | Everything else. Build sections from the files in `sections/`, using ChurchTrac's template classes for layout and inline styles for anything specific to that section. |
| Card Section Options (Cards screen) | Page background colour, button colour (set to rust `#b75c3d`), max container width. These reach places CSS cannot. |

## Working a page

1. Open the page in Page Setup. Note every section and what content it holds.
2. For each section, open the code view and copy the current HTML somewhere safe (a text file in `docs/churchconnect/pages/<page>/` is the habit). Nothing is lost that way.
3. Pick the matching file from `sections/`, paste it over the section, put the real words back in, set the section background colour in the toolbar, mark it Published.
4. Reload the public page and check it on a phone width too.
5. Only when the page looks right, move to the next page.

## Files

| File | Use |
| --- | --- |
| `custom-compact.css` | The global stylesheet. Paste into the custom CSS box. |
| `custom.css` | The same ideas, uncompressed and commented, for reference. Too long for the box. |
| `sections/hero-split.html` | Page opener: eyebrow, headline, intro, buttons, photo on the right. Dark section background. |
| `sections/tiles-3.html` | Three white tiles with label, heading, sentence, button. Any background. |
| `sections/text-column.html` | A readable narrow column of headings and paragraphs. Light background. |
| `sections/cta-band.html` | One headline, one sentence, one or two buttons. Dark background. |
| `sections/map-split.html` | Address and service time beside a Google map. Light background. |
| `sections/staff-grid.html` | Staff cards (name, role, bio, photo or initials) plus the gold invite tile. Light background. |
| `section-welcome.html` | The first experiment (hero split + three tiles together). |
| `staff-page-card.html` | Earlier inline-styled staff grid; superseded by `sections/staff-grid.html`. |

## Current inventory (2026-10-20)

Home screen cards: Trunk or Treat (registration), Online Giving, Sample Connection Card (form), New Announcement (page card #1), Sermon podcast (link), Psalm verse, Staff (page card #32 at `/staff`), calvaryellicott.org (link), Add my family. Pages built from sections: `/staff` (two default ChurchTrac template sections). Page card #1 is a plain announcement.
