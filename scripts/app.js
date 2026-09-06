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
function staffCard(person) {
  const card = el("article", "staff-card");
  const portrait = el("div", "portrait");
  if (person.photo) {
    const img = document.createElement("img");
    img.src = person.photo; img.alt = person.name || ""; img.loading = "lazy";
    portrait.append(img);
  } else {
    portrait.classList.add("portrait-placeholder");
    portrait.textContent = (person.name || "").split(/\s+/).map((part) => part[0] || "").join("").slice(0, 2).toUpperCase();
    portrait.setAttribute("aria-hidden", "true");
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
  if (status) {
    try {
      const note = sessionStorage.getItem("churchtrac-note");
      if (note) { status.textContent = note; sessionStorage.removeItem("churchtrac-note"); }
    } catch {}
  }
});
loadJson("data/staff.json", []).then(applyStaff);
