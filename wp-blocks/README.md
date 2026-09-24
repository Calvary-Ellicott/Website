# Block markup for the WordPress pages

One file per page, generated from `data/content.json` and `data/staff.json`. Each is the page body in WordPress block markup, ready for **code editor mode**.

## How to use a file

1. In WordPress, open the page (Pages → Add new, or an existing one) and give it the title shown below.
2. Click the **⋮** menu at the top right of the editor → **Code editor**.
3. Delete anything already in the box, open the matching file here, copy all of it, paste it in.
4. Switch back with **⋮ → Visual editor** to check it, then **Save draft**, **Preview**, and publish or submit for review.

| Page title | File           | Page link (slug) |
| ---------- | -------------- | ---------------- |
| Home       | `home.html`    | set as homepage under Settings → Reading |
| About      | `about.html`   | `about`          |
| Staff      | `staff.html`   | `staff`          |
| Beliefs    | `beliefs.html` | `beliefs`        |
| Events     | `events.html`  | `events`         |
| Bulletin   | `bulletin.html`| `bulletin`       |
| Watch      | `watch.html`   | `watch`          |
| Connect    | `connect.html` | `connect`        |

Buttons between pages use those slugs (`/about/`, `/events/`, and so on), so keep them.

## Things to replace after pasting

- Every link that says `https://REPLACE-WITH-CHURCHTRAC-LINK` needs the real public ChurchTrac form or calendar URL. In the visual editor, click the button → the link icon → paste the address.
- The Watch page has `https://REPLACE-WITH-BOXCAST-EMBED-URL` inside a Custom HTML block. Replace it with the `src` address from BoxCast's embed code.
- Staff photos: click each empty image block and upload the photo.
- Bulletin and Watch pages use **File** blocks for PDFs. Click the placeholder file block, choose Replace or upload, and change the link text to the date.

## Two things to test once

- Save the Events or Watch page and preview it. If the iframe box is empty, the site strips iframes for your account. Delete the Custom HTML block and rely on the button links instead.
- The small red uppercase lines and the dark green box on Home use the theme's colour presets. If they look off in the pastor's theme, they are plain paragraphs and groups and can be restyled or removed in the visual editor without touching anything else.

The look of the pages comes from whatever theme is active. These files carry the words and structure, not the design.
