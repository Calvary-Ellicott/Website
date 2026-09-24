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

# ---------------- Home ----------------
hm = content["home"]
home = [
    cover("https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1900&q=85",
          "\n".join([eyebrow(hm["hero"]["eyebrow"], "#e8c779"), h(1, hm["hero"]["headline"]), p(hm["hero"]["intro"]),
                     buttons([(hm["hero"]["primaryButton"], "#visit"), (hm["hero"]["secondaryButton"], "/about/")]),
                     p(f'**{site["serviceDay"]} worship · {site["serviceTime"]} · {site["addressLine1"]}**'.replace("**", ""))])),
    f'<!-- wp:heading {{"level":2,"anchor":"visit"}} -->\n<h2 class="wp-block-heading" id="visit">{lines(hm["visit"]["headline"])}</h2>\n<!-- /wp:heading -->',
    columns([
        "\n".join([eyebrow(hm["visit"]["eyebrow"]), p(hm["visit"]["lead"]), buttons([(hm["visit"]["link"], CT)])]),
        detail_rows(hm["visit"]["details"]),
    ]),
    separator(),
    group("\n".join([eyebrow(hm["story"]["eyebrow"], "#e8c779"), h(2, hm["story"]["headline"]), p(hm["story"]["text"]), buttons([(hm["story"]["link"], "/about/")])]), bg="#182820"),
    separator(),
    eyebrow(hm["nextSteps"]["eyebrow"]), h(2, hm["nextSteps"]["headline"]),
    columns(["\n".join([h(3, c["title"]), p(c["text"]), buttons([("Go", "/" + c["href"].replace(".html", "") + "/")])]) for c in hm["nextSteps"]["cards"]]),
]
write("home.html", home, hm["hero"])

# ---------------- About ----------------
ab = content["about"]
about = [
    spacer(110),
    eyebrow(ab["hero"]["eyebrow"]), h(1, ab["hero"]["headline"]), p(ab["hero"]["lead"]),
    separator(),
    eyebrow(ab["story"]["eyebrow"]), h(2, ab["story"]["headline"]),
    "\n".join(p(t) for t in ab["story"]["paragraphs"]),
    separator(),
    eyebrow(ab["sunday"]["eyebrow"]), h(2, ab["sunday"]["headline"]),
    columns([p(ab["sunday"]["lead"]) + "\n" + buttons([(ab["sunday"]["link"], "/beliefs/")]), detail_rows(ab["sunday"]["details"])]),
    separator(),
    eyebrow(ab["nextSteps"]["eyebrow"]), h(2, ab["nextSteps"]["headline"]),
    columns(["\n".join([h(3, c["title"]), p(c["text"]), buttons([("Go", "/" + c["href"].replace(".html", "") + "/")])]) for c in ab["nextSteps"]["cards"]]),
]
write("about.html", about, ab["hero"])

# ---------------- Staff ----------------
st = content["staff"]
cards = []
for person in staff:
    cards.append("\n".join([
        '<!-- wp:image {"sizeSlug":"large"} -->\n<figure class="wp-block-image size-large"><img alt="' + html.escape(person["name"], quote=True) + '"/></figure>\n<!-- /wp:image -->',
        eyebrow(person["role"]), h(3, person["name"]), p(person["bio"]),
    ]))
cards.append("\n".join([eyebrow(st["invite"]["eyebrow"]), h(3, st["invite"]["headline"]), p(st["invite"]["text"]), buttons([(st["invite"]["link"], CT)])]))
staff_page = [
    spacer(110),
    eyebrow(st["hero"]["eyebrow"]), h(1, st["hero"]["headline"]), p(st["hero"]["lead"]),
    separator(),
    note("To add a person: copy one column below (from the Image block to the paragraph), paste it after the last one, and change the words. Click the empty image to upload their photo."),
    columns(cards),
]
write("staff.html", staff_page, st["hero"])

# ---------------- Beliefs ----------------
be = content["beliefs"]
beliefs = [
    spacer(110),
    eyebrow(be["hero"]["eyebrow"]), h(1, be["hero"]["headline"]), p(be["hero"]["lead"]),
    separator(),
    eyebrow(be["intro"]["eyebrow"]), h(2, be["intro"]["headline"]), p(be["intro"]["text"]),
    details(be["items"]),
]
write("beliefs.html", beliefs, be["hero"])

# ---------------- Events ----------------
ev = content["events"]
events = [
    spacer(110),
    eyebrow(ev["hero"]["eyebrow"]), h(1, ev["hero"]["headline"]), p(ev["hero"]["lead"]),
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
    spacer(110),
    eyebrow(bu["hero"]["eyebrow"]), h(1, bu["hero"]["headline"]), p(bu["hero"]["lead"]),
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
    spacer(110),
    eyebrow(wa["hero"]["eyebrow"]), h(1, wa["hero"]["headline"]), p(wa["hero"]["lead"]),
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
    spacer(110),
    eyebrow(co["hero"]["eyebrow"]), h(1, co["hero"]["headline"]), p(co["hero"]["lead"]),
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

## Things to replace after pasting

- Every link that says `https://REPLACE-WITH-CHURCHTRAC-LINK` needs the real public ChurchTrac form or calendar URL. In the visual editor, click the button → the link icon → paste the address.
- The Watch page has `https://REPLACE-WITH-BOXCAST-EMBED-URL` inside a Custom HTML block. Replace it with the `src` address from BoxCast's embed code.
- Staff photos: click each empty image block and upload the photo.
- Bulletin and Watch pages use **File** blocks for PDFs. Click the placeholder file block, choose Replace or upload, and change the link text to the date.

## Two things to test once

- Save the Events or Watch page and preview it. If the iframe box is empty, the site strips iframes for your account. Delete the Custom HTML block and rely on the button links instead.
- The small red uppercase lines and the dark green box on Home use the theme's colour presets. If they look off in the pastor's theme, they are plain paragraphs and groups and can be restyled or removed in the visual editor without touching anything else.

The look of the pages comes from whatever theme is active. These files carry the words and structure, not the design.
"""
open(os.path.join(OUT, "README.md"), "w", encoding="utf-8", newline="\n").write(readme)
print("wrote README.md")
