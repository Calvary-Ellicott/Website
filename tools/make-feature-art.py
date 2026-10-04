"""Portrait illustration: ornate cross on the Eastern Plains with the Rockies at sunset behind it.
Writes an SVG (1200 x 1600, 3:4) to wp-blocks/images/."""
import math, os, random

W, H = 1200, 1600
OUT = r"C:\Users\primo\Documents\Calvary Ellicott\Website\wp-blocks\images"
os.makedirs(OUT, exist_ok=True)
rnd = random.Random(7)

def ridge(base, amp, rough, peaks, step=24):
    """Return a closed path for a mountain range. peaks: list of (x, height, width)."""
    pts = []
    x = -step
    n = 0.0
    while x <= W + step:
        y = base
        for px, ph, pw in peaks:
            d = abs(x - px) / pw
            if d < 1:
                y -= ph * (1 - d) ** 1.35
        n = n * 0.55 + rnd.uniform(-1, 1) * rough
        y -= amp * (0.5 + 0.5 * math.sin(x / 97.0 + base)) + n
        pts.append((x, y))
        x += step
    d = "M %d %d " % (-step, H) + " ".join("L %.1f %.1f" % p for p in pts) + " L %d %d Z" % (W + step, H)
    return d, pts

def snow(pts, thresh, color, opacity):
    """Small snow caps on points higher than thresh."""
    out = []
    for i in range(1, len(pts) - 1):
        x, y = pts[i]
        if y < thresh and y <= pts[i - 1][1] and y <= pts[i + 1][1]:
            w = 26 + (thresh - y) * 0.22
            out.append('<path d="M %.1f %.1f L %.1f %.1f L %.1f %.1f L %.1f %.1f L %.1f %.1f Z" fill="%s" opacity="%.2f"/>' % (
                x, y - 1, x + w * 0.55, y + w * 0.9, x + w * 0.18, y + w * 0.62, x - w * 0.12, y + w * 1.05, x - w * 0.6, y + w * 0.8, color, opacity))
    return "\n".join(out)

back_d, back_pts = ridge(1010, 26, 9, [(180, 150, 260), (520, 120, 300), (900, 190, 330), (1120, 110, 220)])
mid_d, mid_pts = ridge(1035, 22, 11, [(330, 255, 330), (700, 130, 260), (1020, 150, 280)])
front_d, front_pts = ridge(1062, 16, 8, [(110, 120, 240), (560, 70, 300), (860, 105, 260), (1180, 90, 200)])

# ---------- ornate cross ----------
cx, top, bottom = 600, 395, 1255
arm_y = 640
vw, hw = 62, 62            # bar thickness
arm_half = 205
ring_r = 150

def trefoil(x, y, r, rot):
    """Three-lobed finial at the end of an arm; rot in degrees (0 = pointing up)."""
    out = []
    for a in (-90 + rot - 62, -90 + rot, -90 + rot + 62):
        out.append('<circle cx="%.1f" cy="%.1f" r="%.1f"/>' % (x + math.cos(math.radians(a)) * r * 0.95, y + math.sin(math.radians(a)) * r * 0.95, r))
    return "".join(out)

cross_shape = f'''
  <rect x="{cx - vw/2}" y="{top}" width="{vw}" height="{bottom - top}" rx="6"/>
  <rect x="{cx - arm_half}" y="{arm_y - hw/2}" width="{arm_half*2}" height="{hw}" rx="6"/>
  {trefoil(cx, top + 8, 30, 0)}
  {trefoil(cx - arm_half + 8, arm_y, 30, -90)}
  {trefoil(cx + arm_half - 8, arm_y, 30, 90)}
  <path d="M {cx - vw/2 - 26} {bottom} L {cx - vw/2} {bottom - 110} L {cx + vw/2} {bottom - 110} L {cx + vw/2 + 26} {bottom} Z"/>
'''

inlay = f'''
  <path d="M {cx} {top + 36} L {cx} {bottom - 24}" />
  <path d="M {cx - arm_half + 40} {arm_y} L {cx + arm_half - 40} {arm_y}" />
'''

knots = []
for (kx, ky) in [(cx, arm_y - 250), (cx, arm_y + 160), (cx, arm_y + 330), (cx, arm_y + 500), (cx - 125, arm_y), (cx + 125, arm_y)]:
    knots.append('<path d="M %d %d l 11 11 l -11 11 l -11 -11 Z"/>' % (kx, ky - 11))
knots = "\n  ".join(knots)

# ---------- plains details ----------
def field_lines():
    out = []
    y = 1105
    gap = 14
    while y < H:
        sway = 10 + (y - 1100) * 0.03
        out.append('<path d="M -20 %.1f C 300 %.1f, 700 %.1f, 1220 %.1f" stroke="#f3d48a" stroke-opacity="%.3f" stroke-width="%.1f" fill="none"/>' % (
            y, y - sway, y + sway, y - sway * 0.4, 0.05 + 0.10 * (1 - (y - 1100) / 520.0), 1 + (y - 1100) / 260.0))
        y += gap
        gap *= 1.16
    return "\n".join(out)

def grass():
    out = []
    for _ in range(170):
        x = rnd.uniform(-10, W + 10)
        y = rnd.uniform(1330, H + 10)
        h = 18 + (y - 1300) * 0.14 + rnd.uniform(0, 16)
        lean = rnd.uniform(-9, 9)
        out.append('<path d="M %.1f %.1f q %.1f %.1f %.1f %.1f" stroke="%s" stroke-width="%.1f" stroke-linecap="round" fill="none" opacity="%.2f"/>' % (
            x, y, lean * 0.2, -h * 0.6, lean, -h, rnd.choice(["#d9b45c", "#b89a4e", "#7d8a4b", "#e8c779"]), rnd.uniform(1.2, 2.6), rnd.uniform(0.35, 0.8)))
    return "\n".join(out)

def fence():
    out = []
    posts = [(60, 1500, 86), (190, 1420, 66), (300, 1362, 52), (392, 1318, 41), (466, 1286, 33), (524, 1262, 26)]
    for x, y, h in posts:
        out.append('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#14201a" opacity="0.9"/>' % (x, y - h, max(3, h * 0.11), h))
    for frac in (0.28, 0.62):
        d = "M " + " L ".join("%.1f %.1f" % (x + max(3, h * 0.11) / 2, y - h * (1 - frac)) for x, y, h in posts)
        out.append('<path d="%s" stroke="#14201a" stroke-width="2" fill="none" opacity="0.8"/>' % d)
    return "\n".join(out)

def stars():
    out = []
    for _ in range(70):
        x, y = rnd.uniform(0, W), rnd.uniform(0, 520)
        out.append('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="#fff" opacity="%.2f"/>' % (x, y, rnd.uniform(0.7, 1.9), rnd.uniform(0.15, 0.75) * (1 - y / 620.0)))
    return "\n".join(out)

svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">
<defs>
  <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#0f1d33"/>
    <stop offset="0.30" stop-color="#1f3a5c"/>
    <stop offset="0.50" stop-color="#55506e"/>
    <stop offset="0.62" stop-color="#b9694a"/>
    <stop offset="0.70" stop-color="#e9a45a"/>
    <stop offset="0.76" stop-color="#f6cf80"/>
  </linearGradient>
  <radialGradient id="sun" cx="0.5" cy="0.5" r="0.5">
    <stop offset="0" stop-color="#fff3c9" stop-opacity="0.95"/>
    <stop offset="0.25" stop-color="#ffd98a" stop-opacity="0.65"/>
    <stop offset="0.6" stop-color="#f1a257" stop-opacity="0.22"/>
    <stop offset="1" stop-color="#f1a257" stop-opacity="0"/>
  </radialGradient>
  <linearGradient id="plains" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#b08a45"/>
    <stop offset="0.22" stop-color="#7c7a3f"/>
    <stop offset="0.55" stop-color="#3f5234"/>
    <stop offset="1" stop-color="#15221b"/>
  </linearGradient>
  <linearGradient id="crossfill" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#1c2f3f"/>
    <stop offset="1" stop-color="#0c1620"/>
  </linearGradient>
  <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#fbe3a0"/>
    <stop offset="0.5" stop-color="#e2b257"/>
    <stop offset="1" stop-color="#b98432"/>
  </linearGradient>
  <filter id="soft" x="-60%" y="-30%" width="220%" height="160%"><feGaussianBlur stdDeviation="30"/></filter>
  <filter id="haze" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur stdDeviation="2.2"/></filter>
</defs>

<rect width="{W}" height="{H}" fill="url(#sky)"/>
{stars()}
<ellipse cx="{cx}" cy="1000" rx="760" ry="560" fill="url(#sun)"/>

<!-- thin cloud bands catching the light -->
<g opacity="0.55" filter="url(#haze)">
  <path d="M -40 760 C 180 735, 380 780, 640 752 S 1060 735, 1240 765 L 1240 778 C 1000 760, 760 792, 600 772 S 200 760, -40 780 Z" fill="#f7c98b"/>
  <path d="M 120 690 C 300 676, 470 700, 690 684 S 980 678, 1130 692 L 1130 700 C 960 692, 760 708, 620 696 S 300 690, 120 700 Z" fill="#e79c6b" opacity="0.8"/>
  <path d="M -40 850 C 220 838, 420 862, 700 846 S 1080 842, 1240 856 L 1240 864 C 1020 856, 800 872, 640 860 S 240 852, -40 866 Z" fill="#fbe0a6" opacity="0.9"/>
</g>

<!-- Rocky Mountains, three ranges -->
<path d="{back_d}" fill="#6f6f93" opacity="0.72"/>
{snow(back_pts, 880, "#fbe9cf", 0.55)}
<path d="{mid_d}" fill="#3d4f74"/>
{snow(mid_pts, 900, "#fdf1dc", 0.85)}
<path d="{front_d}" fill="#22344f"/>
<rect x="0" y="1020" width="{W}" height="70" fill="#f1b56a" opacity="0.07" filter="url(#haze)"/>

<!-- Eastern Plains -->
<path d="M -20 1085 C 240 1068, 520 1092, 780 1076 S 1100 1070, 1220 1084 L 1220 {H} L -20 {H} Z" fill="url(#plains)"/>
{field_lines()}

<!-- dirt road running out toward the horizon -->
<path d="M 700 {H} C 690 1470, 640 1360, 628 1250 C 622 1190, 640 1140, 676 1092 L 690 1092 C 668 1142, 664 1192, 690 1250 C 740 1360, 880 1470, 960 {H} Z" fill="#d9b773" opacity="0.42"/>

{fence()}

<!-- rise the cross stands on -->
<path d="M 250 {H} C 330 1330, 470 1250, 600 1246 C 730 1250, 880 1320, 980 {H} Z" fill="#15221b" opacity="0.55"/>
<path d="M 380 1330 C 460 1272, 540 1250, 600 1248 C 664 1250, 750 1272, 830 1330 C 740 1300, 660 1290, 600 1290 C 540 1290, 460 1300, 380 1330 Z" fill="#101a14" opacity="0.7"/>

<!-- glow behind the cross -->
<g filter="url(#soft)" opacity="0.5" fill="#ffe3a1">
  <rect x="{cx - 55}" y="{top - 30}" width="110" height="{bottom - top + 20}"/>
  <rect x="{cx - arm_half - 30}" y="{arm_y - 55}" width="{arm_half*2 + 60}" height="110"/>
</g>

<!-- ring -->
<circle cx="{cx}" cy="{arm_y}" r="{ring_r}" fill="none" stroke="#0c1620" stroke-width="34"/>
<circle cx="{cx}" cy="{arm_y}" r="{ring_r}" fill="none" stroke="url(#gold)" stroke-width="5"/>
<circle cx="{cx}" cy="{arm_y}" r="{ring_r - 13}" fill="none" stroke="#e2b257" stroke-width="1.5" opacity="0.8"/>
<circle cx="{cx}" cy="{arm_y}" r="{ring_r + 13}" fill="none" stroke="#e2b257" stroke-width="1.5" opacity="0.8"/>

<!-- cross body with a gold rim -->
<g fill="url(#gold)" stroke="url(#gold)" stroke-width="9" stroke-linejoin="round">{cross_shape}</g>
<g fill="url(#crossfill)">{cross_shape}</g>

<!-- gold inlay -->
<g stroke="#e2b257" stroke-width="3" stroke-linecap="round" fill="none" opacity="0.9">{inlay}</g>
<g fill="url(#gold)">
  {knots}
</g>
<circle cx="{cx}" cy="{arm_y}" r="30" fill="#0c1620" stroke="url(#gold)" stroke-width="5"/>
<path d="M {cx} {arm_y - 18} l 18 18 l -18 18 l -18 -18 Z" fill="url(#gold)"/>
<circle cx="{cx}" cy="{arm_y}" r="5" fill="#0c1620"/>

<!-- foreground grass -->
{grass()}
<rect width="{W}" height="{H}" fill="#0c1620" opacity="0.06"/>
</svg>
'''
path = os.path.join(OUT, "cross-rockies-plains.svg")
open(path, "w", encoding="utf-8", newline="\n").write(svg)
print("wrote", path, len(svg), "bytes")
