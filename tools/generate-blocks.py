"""Generate WordPress block markup for each page from data/content.json and data/staff.json.
Run: python tools/generate-blocks.py   ->  writes wp-blocks/<page>.html (paste into the page code editor)."""
import json, os, re, html

ROOT = r"C:\Users\primo\Documents\Calvary Ellicott\Website"
OUT = os.path.join(ROOT, "wp-blocks")
os.makedirs(OUT, exist_ok=True)

content = json.load(open(os.path.join(ROOT, "data", "content.json"), encoding="utf-8"))
staff = json.load(open(os.path.join(ROOT, "data", "staff.json"), encoding="utf-8"))
site = content["site"]

CT = "https://REPLACE-WITH-CHURCHTRAC-LINK"   # placeholder until the public ChurchTrac URLs exist
BOXCAST = "https://REPLACE-WITH-BOXCAST-EMBED-URL"

def fill(text):
    text = re.sub(r"\{([\w.]+)\}", lambda m: str(content.get(m.group(1).split(".")[0], {}).get(m.group(1).split(".")[1], m.group(0))) if m.group(1).startswith("site.") else m.group(0), str(text))
    return text

def inline(text):
    text = html.escape(fill(text), quote=False)
    return re.sub(r"\*([^*]+)\*", r"<em>\1</em>", text)

def lines(value):
    return "<br>".join(inline(v) for v in (value if isinstance(value, list) else [value]))

def p(text, cls=""):
    attrs = ' {"className":"%s"}' % cls if cls else ""
    return f'<!-- wp:paragraph{attrs} -->\n<p{" class=%s" % chr(34)+cls+chr(34) if cls else ""}>{inline(text)}</p>\n<!-- /wp:paragraph -->'

def eyebrow(text, color="#b75c3d"):
    return f'<!-- wp:paragraph {{"style":{{"color":{{"text":"{color}"}},"typography":{{"fontSize":"0.8rem","textTransform":"uppercase","letterSpacing":"0.16em","fontWeight":"700"}}}}}} -->\n<p class="has-text-color" style="color:{color};font-size:0.8rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase">{inline(text)}</p>\n<!-- /wp:paragraph -->'

def h(level, value):
    return f'<!-- wp:heading {{"level":{level}}} -->\n<h{level} class="wp-block-heading">{lines(value)}</h{level}>\n<!-- /wp:heading -->'

def buttons(items):
    inner = "".join(
        f'<!-- wp:button -->\n<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{html.escape(href)}"{" target=%s_blank%s rel=%snoopener%s" % (chr(34),chr(34),chr(34),chr(34)) if href.startswith("http") else ""}>{inline(label)}</a></div>\n<!-- /wp:button -->\n'
        for label, href in items)
    return f'<!-- wp:buttons -->\n<div class="wp-block-buttons">{inner}</div>\n<!-- /wp:buttons -->'

def separator():
    return '<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->'

def spacer(px=40):
    return f'<!-- wp:spacer {{"height":"{px}px"}} -->\n<div style="height:{px}px" aria-hidden="true" class="wp-block-spacer"></div>\n<!-- /wp:spacer -->'

def columns(cols):
    inner = "".join(f'<!-- wp:column -->\n<div class="wp-block-column">\n{c}\n</div>\n<!-- /wp:column -->\n' for c in cols)
    return f'<!-- wp:columns -->\n<div class="wp-block-columns">{inner}</div>\n<!-- /wp:columns -->'

def group(inner, bg=None, text="#f4f0e6"):
    if bg:
        return f'<!-- wp:group {{"align":"full","style":{{"spacing":{{"padding":{{"top":"48px","bottom":"48px","left":"32px","right":"32px"}}}},"color":{{"background":"{bg}","text":"{text}"}},"elements":{{"link":{{"color":{{"text":"{text}"}}}},"heading":{{"color":{{"text":"{text}"}}}}}}}},"layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group alignfull has-text-color has-background has-link-color" style="color:{text};background-color:{bg};padding:48px 32px">\n{inner}\n</div>\n<!-- /wp:group -->'
    return f'<!-- wp:group {{"layout":{{"type":"constrained"}}}} -->\n<div class="wp-block-group">\n{inner}\n</div>\n<!-- /wp:group -->'

def cover(url, inner):
    return (f'<!-- wp:cover {{"url":"{url}","dimRatio":60,"overlayColor":"black","minHeight":70,"minHeightUnit":"vh","isDark":true,"align":"full","style":{{"spacing":{{"padding":{{"top":"150px","bottom":"72px","left":"32px","right":"32px"}}}}}},"layout":{{"type":"constrained"}}}} -->\n'
            f'<div class="wp-block-cover alignfull" style="padding-top:150px;padding-bottom:72px;padding-left:32px;padding-right:32px;min-height:70vh"><span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-60 has-background-dim"></span>'
            f'<img class="wp-block-cover__image-background" alt="" src="{url}" data-object-fit="cover"/><div class="wp-block-cover__inner-container">\n{inner}\n</div></div>\n<!-- /wp:cover -->')

def details(items):
    out = []
    for i, it in enumerate(items):
        out.append(f'<!-- wp:details {{"summary":"{html.escape(fill(it["title"]), quote=True)}"{",\"showContent\":true" if i == 0 else ""}}} -->\n<details class="wp-block-details"{" open" if i == 0 else ""}><summary>{inline(it["title"])}</summary>{p(it["text"])}</details>\n<!-- /wp:details -->')
    return "\n".join(out)

def detail_rows(rows):
    return "\n".join(f'<!-- wp:paragraph -->\n<p><strong>{inline(r["label"])}</strong><br>{lines(r["text"])}</p>\n<!-- /wp:paragraph -->' for r in rows)

def html_block(markup):
    return f'<!-- wp:html -->\n{markup}\n<!-- /wp:html -->'

def note(text):
    return f'<!-- wp:paragraph {{"style":{{"typography":{{"fontSize":"0.9rem"}}}},"textColor":"cyan-bluish-gray"}} -->\n<p class="has-cyan-bluish-gray-color has-text-color" style="font-size:0.9rem">{inline(text)}</p>\n<!-- /wp:paragraph -->'

def write(name, blocks, banner):
    body = "\n\n".join(b for b in blocks if b)
    with open(os.path.join(OUT, name), "w", encoding="utf-8", newline="\n") as f:
        f.write(body + "\n")
    print("wrote", name)

def big_heading(value):
    return ('<!-- wp:heading {"level":2,"style":{"typography":{"fontSize":"clamp(2.4rem, 5.5vw, 4.2rem)","lineHeight":"1.02"}}} -->\n'
            f'<h2 class="wp-block-heading" style="font-size:clamp(2.4rem, 5.5vw, 4.2rem);line-height:1.02">{lines(value)}</h2>\n<!-- /wp:heading -->')

def lead(text):
    return ('<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2rem","lineHeight":"1.6"}}} -->\n'
            f'<p style="font-size:1.2rem;line-height:1.6">{inline(text)}</p>\n<!-- /wp:paragraph -->')

def wide(inner, bg=None):
    """A full-width row for everything below the first row (which sits beside the theme's featured image)."""
    color = f',"color":{{"background":"{bg}"}}' if bg else ""
    cls = " has-background" if bg else ""
    style = f"background-color:{bg};" if bg else ""
    return (f'<!-- wp:group {{"align":"full","style":{{"spacing":{{"padding":{{"top":"48px","bottom":"48px","left":"32px","right":"32px"}}}}{color}}},"layout":{{"type":"constrained"}}}} -->\n'
            f'<div class="wp-block-group alignfull{cls}" style="{style}padding:48px 32px">\n{inner}\n</div>\n<!-- /wp:group -->')

# ---- Colour bands that reach under the theme's side image -------------------------------
# The theme puts page content in a left column and the featured image in a right column.
# REACH is a negative right margin: on desktop it pulls a block across the gap and the image
# column (46% of the content column's width); below 782px, where the theme stacks, it is 0.
# If a band stops short of the image's right edge or pokes past it, change the 46.
REACH = "calc(-1 * clamp(0px, (100vw - 782px) * 9999, 46%))"
NAVY, SAND, SKY, GOLD, WHITE, INK = "#14304d", "#f6ecd9", "#e3eef8", "#e8b85c", "#ffffff", "#14304d"

def ch(level, value, color):
    return (f'<!-- wp:heading {{"level":{level},"style":{{"color":{{"text":"{color}"}}}}}} -->\n'
            f'<h{level} class="wp-block-heading has-text-color" style="color:{color}">{lines(value)}</h{level}>\n<!-- /wp:heading -->')

def cp(text, color, size=None):
    typo = f',"typography":{{"fontSize":"{size}"}}' if size else ""
    fs = f";font-size:{size}" if size else ""
    return (f'<!-- wp:paragraph {{"style":{{"color":{{"text":"{color}"}}{typo}}}}} -->\n'
            f'<p class="has-text-color" style="color:{color}{fs}">{lines(text)}</p>\n<!-- /wp:paragraph -->')

def cbuttons(items, bg, text):
    inner = "".join(
        f'<!-- wp:button {{"style":{{"color":{{"background":"{bg}","text":"{text}"}}}}}} -->\n'
        f'<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background wp-element-button" href="{html.escape(href)}" style="color:{text};background-color:{bg}"'
        f'{" target=%s_blank%s rel=%snoopener%s" % (chr(34), chr(34), chr(34), chr(34)) if href.startswith("http") else ""}>{inline(label)}</a></div>\n<!-- /wp:button -->\n'
        for label, href in items)
    return f'<!-- wp:buttons -->\n<div class="wp-block-buttons">{inner}</div>\n<!-- /wp:buttons -->'

def band(inner, bg, text, reach=True, pad="48px", side="40px"):
    margin = f'"margin":{{"right":"{REACH}"}},' if reach else ""
    mstyle = f"margin-right:{REACH};" if reach else ""
    return (f'<!-- wp:group {{"style":{{"border":{{"radius":"14px"}},"color":{{"text":"{text}","background":"{bg}"}},'
            f'"spacing":{{{margin}"padding":{{"top":"{pad}","right":"{side}","bottom":"{pad}","left":"{side}"}}}}}},"layout":{{"type":"default"}}}} -->\n'
            f'<div class="wp-block-group has-text-color has-background" style="border-radius:14px;color:{text};background-color:{bg};{mstyle}'
            f'padding-top:{pad};padding-right:{side};padding-bottom:{pad};padding-left:{side}">\n{inner}\n</div>\n<!-- /wp:group -->')

def card(inner, bg=WHITE, text=INK):
    return band(inner, bg, text, reach=False, pad="28px", side="28px")

def first_row(inner):
    """Row 1 sits beside the theme's featured image. The image is about 29vw tall on desktop and the
    theme's page title uses roughly 90px of that, so this keeps row 1 at least as tall as the picture.
    On phones the value is tiny and has no effect. Raise the 29 if a band tucks under the image."""
    mh = "calc(29vw - 90px)"
    return (f'<!-- wp:group {{"style":{{"dimensions":{{"minHeight":"{mh}"}}}},"layout":{{"type":"default"}}}} -->\n'
            f'<div class="wp-block-group" style="min-height:{mh}">\n{inner}\n</div>\n<!-- /wp:group -->')

# ---------------- Home ----------------
# Row 1 (label, headline, intro, buttons) sits beside the theme's featured image.
# Every band after it reaches across under the image.
hm = content["home"]
d = hm["visit"]["details"]
home = [
    first_row("\n".join([
        eyebrow(hm["hero"]["eyebrow"]),
        big_heading(hm["hero"]["headline"]),
        lead(hm["hero"]["intro"]),
        cbuttons([(hm["hero"]["primaryButton"], "#visit")], GOLD, INK).replace("</div>\n<!-- /wp:buttons -->",
            '<!-- wp:button -->\n<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">' + inline(hm["hero"]["secondaryButton"]) + '</a></div>\n<!-- /wp:button -->\n</div>\n<!-- /wp:buttons -->'),
    ])),
    spacer(24),

    # Band 1: when / where / what to expect, navy
    band(columns([
        "\n".join([eyebrow(row["label"], GOLD), cp(row["text"], WHITE, "1.15rem")]) for row in d
    ]), NAVY, WHITE, pad="40px"),

    # Band 2: a simple Sunday, warm sand
    band("\n".join([
        eyebrow(hm["visit"]["eyebrow"]),
        f'<!-- wp:heading {{"level":2,"anchor":"visit","style":{{"color":{{"text":"{INK}"}}}}}} -->\n<h2 class="wp-block-heading has-text-color" id="visit" style="color:{INK}">{lines(hm["visit"]["headline"])}</h2>\n<!-- /wp:heading -->',
        cp(hm["visit"]["lead"], INK, "1.15rem"),
        cbuttons([(hm["visit"]["link"], CT)], NAVY, WHITE),
    ]), SAND, INK),

    # Band 3: our next chapter, navy with gold
    band("\n".join([
        eyebrow(hm["story"]["eyebrow"], GOLD),
        ch(2, hm["story"]["headline"], WHITE),
        cp(hm["story"]["text"], "#dbe6f1", "1.1rem"),
        cbuttons([(hm["story"]["link"], "/about/")], GOLD, INK),
    ]), NAVY, WHITE),

    # Band 4: next steps, pale blue with white cards
    band("\n".join([
        eyebrow(hm["nextSteps"]["eyebrow"]),
        ch(2, hm["nextSteps"]["headline"], INK),
        columns([card("\n".join([
            ch(3, c["title"], INK), cp(c["text"], "#3d5670"),
            cbuttons([("Go", "/" + c["href"].replace(".html", "") + "/")], NAVY, WHITE),
        ])) for c in hm["nextSteps"]["cards"]]),
    ]), SKY, INK),
]
write("home.html", home, hm["hero"])

# ---------------- About ----------------
ab = content["about"]
about = [
    first_row("\n".join([
        big_heading(ab["hero"]["headline"]),
        lead(ab["hero"]["lead"]),
    ])),
    spacer(24),

    # Band 1: who we are, navy
    band("\n".join([
        eyebrow(ab["story"]["eyebrow"], GOLD),
        ch(2, ab["story"]["headline"], WHITE),
        "\n".join(cp(t, "#dbe6f1", "1.1rem") for t in ab["story"]["paragraphs"]),
    ]), NAVY, WHITE),

    # Band 2: what a Sunday looks like, warm sand with three white cards
    band("\n".join([
        eyebrow(ab["sunday"]["eyebrow"]),
        ch(2, ab["sunday"]["headline"], INK),
        cp(ab["sunday"]["lead"], INK, "1.15rem"),
        columns([card("\n".join([ch(3, row["label"], INK), cp(row["text"], "#3d5670")])) for row in ab["sunday"]["details"]]),
        cbuttons([(ab["sunday"]["link"], "/beliefs/")], NAVY, WHITE),
    ]), SAND, INK),

    # Band 3: keep exploring, pale blue with white cards
    band("\n".join([
        eyebrow(ab["nextSteps"]["eyebrow"]),
        ch(2, ab["nextSteps"]["headline"], INK),
        columns([card("\n".join([
            ch(3, c["title"], INK), cp(c["text"], "#3d5670"),
            cbuttons([("Go", "/" + c["href"].replace(".html", "") + "/")], NAVY, WHITE),
        ])) for c in ab["nextSteps"]["cards"]]),
    ]), SKY, INK),
]
write("about.html", about, ab["hero"])

def grid(inner, min_width="14rem", columns_max=3):
    """A group whose children flow into as many columns as fit. Duplicate a child and it finds its own place."""
    return (f'<!-- wp:group {{"className":"ce-grid","layout":{{"type":"grid","columnCount":{columns_max},"minimumColumnWidth":"{min_width}"}}}} -->\n'
            f'<div class="wp-block-group ce-grid">\n{inner}\n</div>\n<!-- /wp:group -->')

EMPTY_IMAGE = '<!-- wp:image -->\n<figure class="wp-block-image"><img alt=""/></figure>\n<!-- /wp:image -->'

# ---------------- Staff ----------------
# Each person is one self-contained white card (a Group). To add someone in WordPress:
# select a card, choose Duplicate from its menu, and change the words. The grid makes room.
st = content["staff"]
staff_cards = "\n".join(
    card("\n".join([
        EMPTY_IMAGE,
        eyebrow(person["role"]),
        ch(3, person["name"], INK),
        cp(person["bio"], "#3d5670"),
    ])) for person in staff)
staff_page = [
    first_row("\n".join([
        big_heading(st["hero"]["headline"]),
        lead(st["hero"]["lead"]),
    ])),
    spacer(24),

    # Band 1: the people, pale blue with white cards
    band(grid(staff_cards), SKY, INK),

    # Band 2: invite, navy
    band("\n".join([
        eyebrow(st["invite"]["eyebrow"], GOLD),
        ch(2, st["invite"]["headline"], WHITE),
        cp(st["invite"]["text"], "#dbe6f1", "1.1rem"),
        cbuttons([(st["invite"]["link"], CT)], GOLD, INK),
    ]), NAVY, WHITE),
]
write("staff.html", staff_page, st["hero"])

def cdetails(items, color, body_color):
    """Accordion (core Details blocks) with explicit colours so it reads on any band."""
    out = []
    for i, it in enumerate(items):
        opened = ',"showContent":true' if i == 0 else ""
        out.append(
            f'<!-- wp:details {{"summary":"{html.escape(fill(it["title"]), quote=True)}"{opened},"style":{{"color":{{"text":"{color}"}},"typography":{{"fontSize":"1.5rem"}}}}}} -->\n'
            f'<details class="wp-block-details has-text-color" style="color:{color};font-size:1.5rem"{" open" if i == 0 else ""}><summary>{inline(it["title"])}</summary>'
            f'{cp(it["text"], body_color, "1.1rem")}</details>\n<!-- /wp:details -->')
    return "\n".join(out)

# ---------------- Beliefs ----------------
be = content["beliefs"]
beliefs = [
    first_row("\n".join([
        big_heading(be["hero"]["headline"]),
        lead(be["hero"]["lead"]),
    ])),
    spacer(24),

    # Band 1: intro, navy
    band("\n".join([
        eyebrow(be["intro"]["eyebrow"], GOLD),
        ch(2, be["intro"]["headline"], WHITE),
        cp(be["intro"]["text"], "#dbe6f1", "1.1rem"),
    ]), NAVY, WHITE),

    # Band 2: the statement of faith as an accordion, warm sand
    band(cdetails(be["items"], INK, "#3d5670"), SAND, INK),
]
write("beliefs.html", beliefs, be["hero"])

# ---------------- Events ----------------
ev = content["events"]
events = [
    eyebrow(ev["hero"]["eyebrow"]), lead(ev["hero"]["lead"]),
    separator(),
    eyebrow(ev["calendar"]["eyebrow"]), h(2, ev["calendar"]["headline"]),
    buttons([(ev["calendar"]["button"], CT)]),
    note("The box below is the embedded ChurchTrac calendar. Replace the address in it with the calendar embed link from ChurchTrac. If the calendar does not appear after saving, the site is stripping iframes and the button above will have to do."),
    html_block(f'<iframe src="{CT}" title="Event calendar" style="width:100%;height:650px;border:0" loading="lazy"></iframe>'),
]
write("events.html", events, ev["hero"])

# ---------------- Bulletin ----------------
bu = content["bulletin"]
bulletin = [
    eyebrow(bu["hero"]["eyebrow"]), lead(bu["hero"]["lead"]),
    separator(),
    note("Each week: add a File block at the top of the list below (type /file, upload the PDF), and put the Sunday date as the block's text. Older weeks stay underneath as the archive."),
    h(2, "This week"),
    '<!-- wp:file -->\n<div class="wp-block-file"><a href="#">Upload this week’s bulletin PDF here</a></div>\n<!-- /wp:file -->',
    h(2, bu["archiveHeading"]),
    p("Previous bulletins will be listed here."),
]
write("bulletin.html", bulletin, bu["hero"])

# ---------------- Watch ----------------
wa = content["watch"]
watch = [
    eyebrow(wa["hero"]["eyebrow"]), lead(wa["hero"]["lead"]),
    separator(),
    eyebrow(wa["live"]["eyebrow"]), h(2, wa["live"]["headline"]), p(wa["live"]["text"]),
    note("The box below is the BoxCast player. Replace the address in it with the src address from BoxCast's embed code. If nothing appears after saving, the site is stripping iframes; use a Button linking to the BoxCast channel page instead."),
    html_block(f'<iframe src="{BOXCAST}" title="Livestream" style="width:100%;aspect-ratio:16/9;border:0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>'),
    separator(),
    eyebrow(wa["notes"]["eyebrow"]), h(2, wa["notes"]["headline"]), p(wa["notes"]["text"]),
    note("Each week: add a File block for the handout before the service and another for the pastor’s notes afterwards. Put the Sunday date in the heading."),
    h(3, "This week"),
    '<!-- wp:file -->\n<div class="wp-block-file"><a href="#">Sermon handout (upload PDF)</a></div>\n<!-- /wp:file -->',
    p(wa["notesPending"]),
    h(3, wa["notes"]["archiveHeading"]),
    p("Previous weeks will be listed here."),
]
write("watch.html", watch, wa["hero"])

# ---------------- Connect ----------------
co = content["connect"]
connect = [
    eyebrow(co["hero"]["eyebrow"]), lead(co["hero"]["lead"]),
    separator(),
    columns([
        "\n".join([eyebrow(co["intro"]["eyebrow"]), h(2, co["intro"]["headline"]), p(co["intro"]["text"]), detail_rows(co["intro"]["details"])]),
        "\n".join([buttons([(f["label"], CT)]) for f in co["forms"]]) + "\n" + note(co["note"]),
    ]),
]
write("connect.html", connect, co["hero"])

readme = """# Block markup for the WordPress pages

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

## How these pages fit the theme

These are built to work with the theme's normal page layout rather than against it. Leave the page template on its default.

- The theme prints the **page title** as the big heading, so the files do not repeat it. Title each page as in the table above. For the homepage a title such as `Welcome to Calvary Ellicott` reads better than `Home`.
- The theme shows a **featured image** beside the top of the page. Set one on every page (right sidebar → Page → Featured image). A tall, portrait-shaped photo fits that slot best. The first few blocks in each file are sized to sit next to it.
- Everything after that first row is a coloured band with a negative right margin, which pulls it across under the image on desktop and does nothing on phones. If the bands stop short of the image's right edge, or poke past it, open the band in the editor (Styles → Dimensions → Margin → right) and change the `46%` near the end of the value up or down a little. The same number is `REACH` in `tools/generate-blocks.py`.

## Things to replace after pasting

- Every link that says `https://REPLACE-WITH-CHURCHTRAC-LINK` needs the real public ChurchTrac form or calendar URL. In the visual editor, click the button → the link icon → paste the address.
- The Watch page has `https://REPLACE-WITH-BOXCAST-EMBED-URL` inside a Custom HTML block. Replace it with the `src` address from BoxCast's embed code.
- Staff: each person is one white card. Click the empty image at the top of a card to upload their photo. To add a person, click a card, open the three-dot menu in its toolbar, choose **Duplicate**, and change the words; the grid makes room on its own. If a click lands on the text instead of the card, open List View (top left of the editor) and pick the Group that holds that person. To remove someone, select their card and press Delete.
- Beliefs: each item is a Details block. Click one to change its title or text. To add an item, select one, choose **Duplicate** from its three-dot menu, and edit it. Drag to reorder.
- Bulletin and Watch pages use **File** blocks for PDFs. Click the placeholder file block, choose Replace or upload, and change the link text to the date.

## Two things to test once

- Save the Events or Watch page and preview it. If the iframe box is empty, the site strips iframes for your account. Delete the Custom HTML block and rely on the button links instead.
- The small red uppercase lines and the dark green box on Home use the theme's colour presets. If they look off in the pastor's theme, they are plain paragraphs and groups and can be restyled or removed in the visual editor without touching anything else.

The look of the pages comes from whatever theme is active. These files carry the words and structure, not the design.
"""
open(os.path.join(OUT, "README.md"), "w", encoding="utf-8", newline="\n").write(readme)
print("wrote README.md")
