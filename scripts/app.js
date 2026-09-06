const defaultChurchTrac = { calendarEmbed:"", calendar:"", visit:"", prayer:"", connect:"", serve:"", contact:"" };
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

async function loadChurchTrac() {
  try {
    const response = await fetch("data/churchtrac.json", { headers:{ Accept:"application/json" }, cache:"no-cache" });
    if (!response.ok) throw new Error("Settings unavailable");
    const settings = await response.json();
    const safe = {};
    for (const key of Object.keys(defaultChurchTrac)) {
      const value = String(settings[key] || "").trim();
      safe[key] = /^https:\/\//i.test(value) ? value : "";
    }
    return safe;
  } catch { return defaultChurchTrac; }
}

function applyChurchTrac(churchTrac) {
  document.querySelectorAll("[data-churchtrac-link]").forEach((link) => {
    const url = churchTrac[link.dataset.churchtracLink];
    if (url) {
      link.href = url; link.target = "_blank"; link.rel = "noopener noreferrer";
    } else {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        document.querySelector("#connect").scrollIntoView({ behavior:"smooth" });
        const label = link.textContent.trim().replace(/[^\p{L}\p{N}\s]+$/u, "");
        status.textContent = `${label} will open in ChurchTrac once its public form link is added.`;
      });
    }
  });
  if (churchTrac.calendarEmbed) {
    const shell = document.querySelector("[data-churchtrac-calendar]");
    const frame = document.createElement("iframe");
    frame.src = churchTrac.calendarEmbed;
    frame.title = "Calvary Ellicott event calendar";
    frame.loading = "lazy";
    frame.referrerPolicy = "strict-origin-when-cross-origin";
    shell.replaceChildren(frame);
  }
}

document.querySelector("[data-year]").textContent = new Date().getFullYear();
window.addEventListener("scroll", setHeaderState, { passive:true });
setHeaderState();
loadChurchTrac().then(applyChurchTrac);
