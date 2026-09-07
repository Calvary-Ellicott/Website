const header = document.querySelector(".site-header");
const navToggle = document.querySelector(".nav-toggle");
const navLinks = document.querySelector(".nav-links");
const status = document.querySelector("[data-integration-status]");

function setHeaderState() { header.dataset.scrolled = String(window.scrollY > 24); }
navToggle.addEventListener("click", () => {
  const open = navLinks.dataset.open === "true";
  navLinks.dataset.open = String(!open);
  navToggle.setAttribute("aria-expanded", String(!open));
});
navLinks.addEventListener("click", () => { navLinks.dataset.open = "false"; navToggle.setAttribute("aria-expanded", "false"); });

async function loadJson(file, fallback) {
  try {
    const response = await fetch(file, { headers:{ Accept:"application/json" }, cache:"no-cache" });
    if (!response.ok) throw new Error(`${file} unavailable`);
    return await response.json();
  } catch { return fallback; }
}

/* ------------------------------------------------------------------
   Content: words on the page come from data/content.json.

   <p data-content="home.hero.intro">          text (string) or lines (array, joined with <br>)
   <div data-content-list="x" data-list-type>  a repeated block rendered from an array

   Inside any string: {site.serviceTime} is replaced with that value,
   and *words between asterisks* are emphasised.
   ------------------------------------------------------------------ */
let content = {};

function get(path) {
  return path.split(".").reduce((value, key) => (value == null ? undefined : value[key]), content);
}

function fill(text) {
  return String(text).replace(/\{([\w.]+)\}/g, (match, path) => {
    const value = get(path);
    return value == null || typeof value === "object" ? match : String(value);
  });
}

function appendText(el, text) {
  fill(text).split(/(\*[^*]+\*)/).forEach((part) => {
    if (!part) return;
    if (/^\*[^*]+\*$/.test(part)) {
      const em = document.createElement("em");
      em.textContent = part.slice(1, -1);
      el.append(em);
    } else {
      el.append(part);
    }
  });
}

function setLines(el, value) {
  const lines = Array.isArray(value) ? value : [value];
  el.replaceChildren();
  lines.forEach((line, index) => {
    if (index) el.append(document.createElement("br"));
    appendText(el, line);
  });
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text != null) appendText(node, text);
  return node;
}

function number(index) { return String(index + 1).padStart(2, "0"); }

const listRenderers = {
  details(items) {
    return items.map((item, index) => {
      const row = el("div");
      const p = el("p");
      p.append(el("strong", "", item.label));
      const text = el("span");
      setLines(text, item.text || "");
      p.append(...text.childNodes);
      row.append(el("span", "detail-number", number(index)), p);
      return row;
    });
  },
  cards(items) {
    return items.map((item, index) => {
      const card = el("a", "next-step");
      const href = String(item.href || "");
      card.href = /^(https:\/\/|[\w-]+\.html(#[\w-]*)?$)/.test(href) ? href : "#";
      card.append(el("span", "detail-number", number(index)), el("h3", "", item.title), el("p", "", item.text));
      return card;
    });
  },
  paragraphs(items) {
    return items.map((text) => el("p", "", text));
  },
  beliefs(items) {
    return items.map((item, index) => {
      const block = el("details");
      if (index === 0) block.open = true;
      const summary = el("summary");
      summary.append(el("span", "", number(index)));
      appendText(summary, item.title || "");
      block.append(summary, el("p", "", item.text));
      return block;
    });
  },
  forms(items) {
    return items.map((item) => {
      const link = el("a");
      link.dataset.churchtracLink = String(item.key || "");
      link.href = "#connect";
      link.append(el("span", "", item.label), el("b", "", "→"));
      return link;
    });
  }
};

function applyContent(data) {
  content = data && typeof data === "object" ? data : {};
  document.querySelectorAll("[data-content]").forEach((node) => {
    const value = get(node.dataset.content);
    if (value == null || value === "" || typeof value === "object" && !Array.isArray(value)) return;
    setLines(node, value);
  });
  document.querySelectorAll("[data-content-list]").forEach((node) => {
    const items = get(node.dataset.contentList);
    const render = listRenderers[node.dataset.listType];
    if (!render || !Array.isArray(items) || items.length === 0) return;
    node.replaceChildren(...render(items));
  });
}

/* ------------------------------------------------------------------
   ChurchTrac: every key in data/churchtrac.json becomes available to
   <a data-churchtrac-link="key"> (opens in a new tab) and
   <div data-churchtrac-embed="key"> (replaced with an iframe). Only https URLs are honoured.
   ------------------------------------------------------------------ */
function applyChurchTrac(raw) {
  const churchTrac = {};
  for (const [key, value] of Object.entries(raw || {})) {
    const url = String(value || "").trim();
    if (/^https:\/\//i.test(url)) churchTrac[key] = url;
  }
  document.querySelectorAll("[data-churchtrac-link]").forEach((link) => {
    const url = churchTrac[link.dataset.churchtracLink];
    if (url) {
      link.href = url; link.target = "_blank"; link.rel = "noopener noreferrer";
    } else {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        const label = link.textContent.trim().replace(/[^\p{L}\p{N}\s]+$/u, "").trim();
        const message = `${label} will open in ChurchTrac once its public form link is added.`;
        if (status) {
          status.textContent = message;
          status.scrollIntoView({ behavior:"smooth", block:"center" });
        } else {
          try { sessionStorage.setItem("churchtrac-note", message); } catch {}
          location.href = "connect.html#connect";
        }
      });
    }
  });
  document.querySelectorAll("[data-churchtrac-embed]").forEach((shell) => {
    const url = churchTrac[shell.dataset.churchtracEmbed];
    if (!url) return;
    const frame = document.createElement("iframe");
    frame.src = url;
    frame.title = shell.dataset.embedTitle || "ChurchTrac";
    frame.loading = "lazy";
    frame.referrerPolicy = "strict-origin-when-cross-origin";
    shell.replaceChildren(frame);
  });
}

/* ------------------------------------------------------------------
   Staff: cards are rendered from data/staff.json. If the file is missing or empty,
   the placeholder cards already in the HTML stay in place.
   ------------------------------------------------------------------ */
/* Photo convention: images/staff/<name-as-slug>.jpg (or .png / .webp), for example
   "Pastor John Smith" -> images/staff/pastor-john-smith.jpg. A "photo" field in the JSON
   overrides this. If no file is found the card shows the person's initials instead. */
const PHOTO_TYPES = ["jpg", "jpeg", "png", "webp"];

function slug(text) {
  return String(text || "").normalize("NFD").replace(/\p{M}/gu, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}

function showInitials(portrait, name) {
  portrait.classList.add("portrait-placeholder");
  portrait.textContent = String(name || "").split(/\s+/).map((part) => part[0] || "").join("").slice(0, 2).toUpperCase();
  portrait.setAttribute("aria-hidden", "true");
}

function staffCard(person) {
  const card = el("article", "staff-card");
  const portrait = el("div", "portrait");
  const base = slug(person.name);
  const candidates = person.photo ? [String(person.photo)] : base ? PHOTO_TYPES.map((type) => `images/staff/${base}.${type}`) : [];
  if (candidates.length) {
    const img = document.createElement("img");
    img.alt = person.name || ""; img.loading = "lazy";
    let attempt = 0;
    img.addEventListener("error", () => {
      attempt += 1;
      if (attempt < candidates.length) img.src = candidates[attempt];
      else { img.remove(); showInitials(portrait, person.name); }
    });
    img.src = candidates[0];
    portrait.append(img);
  } else {
    showInitials(portrait, person.name);
  }
  card.append(portrait, el("p", "role", person.role || ""), el("h3", "", person.name || ""), el("p", "", person.bio || ""));
  if (person.email && /^[^\s@]+@[^\s@]+$/.test(person.email)) {
    const link = el("a", "text-link");
    link.href = `mailto:${person.email}`;
    link.append("Send an email ", el("span", "", "→"));
    card.append(link);
  }
  return card;
}

function applyStaff(people) {
  const grid = document.querySelector("[data-staff-grid]");
  if (!grid || !Array.isArray(people) || people.length === 0) return;
  const invite = grid.querySelector(".staff-invite");
  grid.replaceChildren(...people.map(staffCard));
  if (invite) grid.append(invite);
}

/* ------------------------------------------------------------------
   Alert banner: data/alert.json. Shown on every page while "enabled" is true
   and the optional "expires" date (YYYY-MM-DD) has not passed. Visitors can
   dismiss it for their browsing session; a changed message reappears.
   ------------------------------------------------------------------ */
function applyAlert(alert) {
  if (!alert || alert.enabled !== true) return;
  const message = String(alert.message || "").trim();
  if (!message) return;
  if (alert.expires) {
    const expires = new Date(`${alert.expires}T23:59:59`);
    if (!Number.isNaN(expires.getTime()) && expires < new Date()) return;
  }
  const key = `alert-dismissed:${message}`;
  try { if (sessionStorage.getItem(key)) return; } catch {}

  const banner = el("div", `site-alert site-alert-${alert.style === "info" ? "info" : "warning"}`);
  banner.setAttribute("role", "status");
  const text = el("p");
  appendText(text, message);
  const url = String(alert.link || "").trim();
  if (url && /^(https:\/\/|[\w-]+\.html(#[\w-]*)?$)/.test(url)) {
    text.append(" ");
    const link = el("a", "", alert.linkText || "Learn more");
    link.href = url;
    if (url.startsWith("https://")) { link.target = "_blank"; link.rel = "noopener noreferrer"; }
    text.append(link);
  }
  const close = el("button", "site-alert-close");
  close.type = "button";
  close.setAttribute("aria-label", "Dismiss message");
  close.textContent = "×";
  close.addEventListener("click", () => {
    banner.remove();
    document.documentElement.style.setProperty("--alert-height", "0px");
    try { sessionStorage.setItem(key, "1"); } catch {}
  });
  banner.append(text, close);
  document.body.prepend(banner);

  const sizeBanner = () => document.documentElement.style.setProperty("--alert-height", `${banner.offsetHeight}px`);
  sizeBanner();
  window.addEventListener("resize", sizeBanner, { passive:true });
}

/* ------------------------------------------------------------------
   Dated files: bulletins and sermon notes are PDFs named by Sunday date.
   findDatedFiles("bulletins", [""]) checks today and the last N Sundays for
   bulletins/YYYY-MM-DD.pdf; findDatedFiles("sermons", ["", "-notes"]) checks
   sermons/YYYY-MM-DD.pdf and sermons/YYYY-MM-DD-notes.pdf. Returns newest first.
   ------------------------------------------------------------------ */
function isoDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function longDate(iso) {
  const [year, month, day] = iso.split("-").map(Number);
  return new Date(year, month - 1, day).toLocaleDateString("en-US", { weekday:"long", month:"long", day:"numeric", year:"numeric" });
}

async function exists(path) {
  try { return (await fetch(path, { method:"HEAD", cache:"no-cache" })).ok; }
  catch { return false; }
}

async function findDatedFiles(folder, variants, settings) {
  const weekday = Number.isInteger(settings.weekday) ? settings.weekday : 0;
  const weeksBack = Number.isInteger(settings.weeksBack) ? settings.weeksBack : 12;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const dates = new Set([isoDate(today)]);
  const sunday = new Date(today);
  sunday.setDate(today.getDate() - ((today.getDay() - weekday + 7) % 7));
  for (let week = 0; week < weeksBack; week += 1) {
    dates.add(isoDate(sunday));
    sunday.setDate(sunday.getDate() - 7);
  }
  const results = await Promise.all([...dates].map(async (date) => {
    const files = {};
    await Promise.all(variants.map(async (variant) => {
      const path = `${folder}/${date}${variant}.pdf`;
      if (await exists(path)) files[variant] = path;
    }));
    return Object.keys(files).length ? { date, files } : null;
  }));
  return results.filter(Boolean).sort((a, b) => (a.date < b.date ? 1 : -1));
}

function pdfLink(path, label, className = "") {
  const link = el("a", className, label);
  link.href = path; link.target = "_blank"; link.rel = "noopener";
  return link;
}

/* Bulletin page: newest bulletins/YYYY-MM-DD.pdf in a viewer, the rest listed. */
async function applyBulletin() {
  const section = document.querySelector("[data-bulletin]");
  if (!section) return;
  const settings = get("bulletin") || {};
  const found = await findDatedFiles("bulletins", [""], settings);
  if (found.length === 0) return;

  const [latest, ...older] = found;
  const heading = el("div", "bulletin-heading");
  heading.append(el("p", "eyebrow", longDate(latest.date)), pdfLink(latest.files[""], settings.openButton || "Open PDF", "button primary"));
  const frame = document.createElement("iframe");
  frame.src = `${latest.files[""]}#view=FitH`;
  frame.title = `Bulletin for ${longDate(latest.date)}`;
  frame.loading = "lazy";
  section.querySelector("[data-bulletin-current]").replaceChildren(heading, frame);

  if (older.length) {
    section.querySelector("[data-bulletin-list]").replaceChildren(...older.map((entry) => {
      const item = el("li");
      item.append(pdfLink(entry.files[""], longDate(entry.date)));
      return item;
    }));
    section.querySelector("[data-bulletin-archive]").hidden = false;
  }
}

/* ------------------------------------------------------------------
   Watch page: BoxCast embed from data/watch.json, plus sermon notes from
   sermons/YYYY-MM-DD.pdf (handout, posted before the service) and
   sermons/YYYY-MM-DD-notes.pdf (pastor's notes, posted afterwards).
   ------------------------------------------------------------------ */
function applyWatchEmbed(watch) {
  const shell = document.querySelector("[data-watch-embed]");
  if (!shell) return;
  const url = String(watch && watch.boxcastEmbed || "").trim();
  if (!/^https:\/\//i.test(url)) return;
  const frame = document.createElement("iframe");
  frame.src = url;
  frame.title = shell.dataset.embedTitle || "Livestream";
  frame.setAttribute("allow", "autoplay; fullscreen; picture-in-picture");
  frame.setAttribute("allowfullscreen", "");
  frame.referrerPolicy = "strict-origin-when-cross-origin";
  shell.replaceChildren(frame);
}

async function applySermons() {
  const section = document.querySelector("[data-sermons]");
  if (!section) return;
  const settings = get("watch") || {};
  const labels = { "": settings.handoutLabel || "Sermon handout", "-notes": settings.notesLabel || "Pastor’s notes" };
  const found = await findDatedFiles("sermons", ["", "-notes"], settings);
  if (found.length === 0) return;

  const [latest, ...older] = found;
  const current = section.querySelector("[data-sermons-current]");
  current.replaceChildren(el("p", "eyebrow", longDate(latest.date)));
  const buttons = el("div", "sermon-buttons");
  for (const [variant, label] of Object.entries(labels)) {
    if (latest.files[variant]) buttons.append(pdfLink(latest.files[variant], label, variant ? "button outline" : "button primary"));
  }
  if (!latest.files["-notes"] && settings.notesPending) buttons.append(el("p", "sermon-pending", settings.notesPending));
  current.append(buttons);

  if (older.length) {
    section.querySelector("[data-sermons-list]").replaceChildren(...older.map((entry) => {
      const item = el("li");
      item.append(el("span", "sermon-date", longDate(entry.date)));
      const links = el("span", "sermon-links");
      for (const [variant, label] of Object.entries(labels)) {
        if (entry.files[variant]) links.append(pdfLink(entry.files[variant], label));
      }
      item.append(links);
      return item;
    }));
    section.querySelector("[data-sermons-archive]").hidden = false;
  }
}

/* Highlight the current page in the navigation. */
const current = location.pathname.replace(/index\.html$/, "").replace(/\/$/, "");
document.querySelectorAll(".nav-links a, .site-footer nav a").forEach((link) => {
  const target = link.pathname.replace(/index\.html$/, "").replace(/\/$/, "");
  if (target === current) link.setAttribute("aria-current", "page");
});

document.querySelector("[data-year]").textContent = new Date().getFullYear();
window.addEventListener("scroll", setHeaderState, { passive:true });
setHeaderState();

/* Content first so ChurchTrac can wire up any links it creates; then the carried-over note. */
loadJson("data/content.json", {}).then(applyContent).then(async () => {
  applyChurchTrac(await loadJson("data/churchtrac.json", {}));
  applyBulletin();
  applySermons();
  applyWatchEmbed(await loadJson("data/watch.json", {}));
  if (status) {
    try {
      const note = sessionStorage.getItem("churchtrac-note");
      if (note) { status.textContent = note; sessionStorage.removeItem("churchtrac-note"); }
    } catch {}
  }
});
loadJson("data/staff.json", []).then(applyStaff);
loadJson("data/alert.json", null).then(applyAlert);
