# Staff photos

Photos in this folder link to people in `data/staff.json` by file name. Take the person's `name`, make it lowercase, and replace spaces and punctuation with hyphens:

| `name` in staff.json | File to add here        |
| -------------------- | ----------------------- |
| `Pastor John Smith`  | `pastor-john-smith.jpg` |
| `Mary O'Brien`       | `mary-o-brien.jpg`      |
| `José Peña`          | `jose-pena.jpg`         |

The page looks for `.jpg` first, then `.jpeg`, `.png`, and `.webp`. If no file matches, the card shows the person's initials instead, so a missing photo never breaks the page. To use a file with a different name, add a `"photo": "images/staff/whatever.jpg"` field to that person's entry.

- Landscape crop, 4:3 (for example 1200 × 900 pixels). The card crops to that shape.
- JPEG, kept under about 300 KB each. Squoosh (squoosh.app) or any photo app can resize and compress.
- Lowercase file names only. GitHub Pages treats `John.jpg` and `john.jpg` as different files.
