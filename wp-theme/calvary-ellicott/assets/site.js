(function () {
  const header = document.querySelector(".site-header");
  const navToggle = document.querySelector(".nav-toggle");
  const navLinks = document.querySelector(".nav-links");
  const status = document.querySelector("[data-integration-status]");

  if (header) {
    const setHeaderState = () => { header.dataset.scrolled = String(window.scrollY > 24); };
    window.addEventListener("scroll", setHeaderState, { passive: true });
    setHeaderState();
  }

  if (navToggle && navLinks) {
    navToggle.addEventListener("click", () => {
      const open = navLinks.dataset.open === "true";
      navLinks.dataset.open = String(!open);
      navToggle.setAttribute("aria-expanded", String(!open));
    });
    navLinks.addEventListener("click", () => { navLinks.dataset.open = "false"; navToggle.setAttribute("aria-expanded", "false"); });
  }

  /* Unconfigured ChurchTrac links on the Connect page: show the note without reloading. */
  if (status) {
    document.querySelectorAll("[data-soon]").forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        status.textContent = `${link.dataset.soon} will open in ChurchTrac once its public form link is added.`;
        status.scrollIntoView({ behavior: "smooth", block: "center" });
      });
    });
  }

  /* Hero album: crossfade through the banner photos. */
  const slides = Array.from(document.querySelectorAll(".hero-slide"));
  if (slides.length > 1 && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    const interval = ((window.CE_HERO && +window.CE_HERO.interval) || 7) * 1000;
    let current = 0;
    slides.slice(1).forEach((s) => { const img = new Image(); img.src = s.style.backgroundImage.slice(5, -2).replace(/^["']|["']$/g, ""); });
    setInterval(() => {
      slides[current].classList.remove("is-active");
      current = (current + 1) % slides.length;
      slides[current].classList.add("is-active");
    }, interval);
  }

  /* Alert banner: keep the fixed header below it; dismiss for the session. */
  const banner = document.querySelector(".site-alert");
  if (banner) {
    const key = `alert-dismissed:${banner.dataset.alertId}`;
    let dismissed = false;
    try { dismissed = Boolean(sessionStorage.getItem(key)); } catch (e) {}
    if (dismissed) {
      banner.remove();
    } else {
      const size = () => document.documentElement.style.setProperty("--alert-height", `${banner.offsetHeight}px`);
      size();
      window.addEventListener("resize", size, { passive: true });
      const close = banner.querySelector(".site-alert-close");
      if (close) {
        close.addEventListener("click", () => {
          banner.remove();
          document.documentElement.style.setProperty("--alert-height", "0px");
          try { sessionStorage.setItem(key, "1"); } catch (e) {}
        });
      }
    }
  }
})();
