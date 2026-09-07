# Bulletins

Save each week's bulletin here as a PDF named by its Sunday date: `YYYY-MM-DD.pdf`.

    bulletins/2026-09-13.pdf
    bulletins/2026-09-20.pdf

The Bulletin page shows the newest one it finds and lists the rest as an archive. It looks back 12 Sundays (change `bulletin.weeksBack` and `bulletin.weekday` in `data/content.json` if the schedule changes). Older files can stay in the folder; they just stop being listed.

- Keep the name exact: four-digit year, two-digit month, two-digit day, lowercase `.pdf`.
- Export at a modest size (under about 2 MB) so it loads quickly on phones.
