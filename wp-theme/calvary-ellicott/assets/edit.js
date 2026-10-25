/* Edit mode for logged-in editors: click "Edit this page", click outlined text, type, Save.
   Loaded only for users who can edit pages (see inc/inline-edit.php). */
(function () {
  const cfg = window.CE_EDIT || {};
  const t = cfg.text || {};
  const fields = Array.from(document.querySelectorAll("[data-ce-edit]"));
  const plaintextOnly = (() => {
    const probe = document.createElement("div");
    probe.contentEditable = "plaintext-only";
    return probe.contentEditable === "plaintext-only";
  })();

  let editing = false;
  const original = new Map(); // element -> { html, text }

  /* ---------- the bar ---------- */
  const bar = document.createElement("div");
  bar.className = "ce-edit-bar";
  bar.setAttribute("role", "region");
  bar.setAttribute("aria-label", t.edit || "Edit this page");
  bar.innerHTML =
    '<button type="button" class="ce-edit-start"></button>' +
    '<span class="ce-edit-hint"></span>' +
    '<span class="ce-edit-status" role="status"></span>' +
    '<span class="ce-edit-actions">' +
    '<button type="button" class="ce-edit-save" disabled></button>' +
    '<button type="button" class="ce-edit-discard"></button>' +
    '<button type="button" class="ce-edit-done"></button>' +
    '<a class="ce-edit-more"></a></span>';
  document.body.appendChild(bar);

  const btnStart = bar.querySelector(".ce-edit-start");
  const hint = bar.querySelector(".ce-edit-hint");
  const status = bar.querySelector(".ce-edit-status");
  const btnSave = bar.querySelector(".ce-edit-save");
  const btnDiscard = bar.querySelector(".ce-edit-discard");
  const btnDone = bar.querySelector(".ce-edit-done");
  const more = bar.querySelector(".ce-edit-more");

  btnStart.textContent = t.edit || "Edit this page";
  hint.textContent = t.hint || "";
  btnDiscard.textContent = t.discard || "Discard changes";
  btnDone.textContent = t.done || "Done";
  more.textContent = t.more || "More in the dashboard";
  more.href = cfg.settings || "/wp-admin/";

  let statusTimer = 0;
  const say = (text, sticky) => {
    status.textContent = text || "";
    clearTimeout(statusTimer);
    if (text && !sticky) statusTimer = setTimeout(() => { status.textContent = ""; }, 5000);
  };

  /* ---------- reading and writing values ---------- */
  const read = (el) => {
    const raw = el.innerText.replace(/ /g, " ");
    if (el.dataset.ceFormat === "lines") {
      return raw.replace(/\r\n?/g, "\n").split("\n").map((l) => l.trim()).join("\n").replace(/\n{3,}/g, "\n\n").trim();
    }
    return raw.replace(/\s+/g, " ").trim();
  };

  const write = (el, value) => {
    el.textContent = value; // white-space: pre-line keeps the line breaks while editing
  };

  const changed = () => {
    const map = new Map();
    fields.forEach((el) => {
      const value = read(el);
      if (value !== original.get(el).text) map.set(el.dataset.ceEdit, value);
    });
    return map;
  };

  const refresh = () => {
    const map = changed();
    fields.forEach((el) => el.classList.toggle("is-changed", map.has(el.dataset.ceEdit)));
    btnSave.disabled = map.size === 0;
    btnSave.textContent = map.size ? (t.changes || "%d to save").replace("%d", map.size) : (t.save || "Save");
    return map;
  };

  /* ---------- entering and leaving ---------- */
  const enter = () => {
    if (editing) return;
    if (!fields.length) { say(t.nothing || "Nothing to edit here.", true); return; }
    editing = true;
    document.body.classList.add("ce-editing");
    bar.classList.add("is-editing");
    fields.forEach((el) => {
      original.set(el, { html: el.innerHTML, text: read(el) });
      el.contentEditable = plaintextOnly ? "plaintext-only" : "true";
      el.spellcheck = true;
      el.setAttribute("data-ce-empty", t.empty || "Type here");
    });
    refresh();
    say("");
  };

  const restore = () => {
    fields.forEach((el) => {
      const o = original.get(el);
      if (o) el.innerHTML = o.html;
      el.classList.remove("is-changed");
    });
  };

  const leave = () => {
    editing = false;
    document.body.classList.remove("ce-editing");
    bar.classList.remove("is-editing");
    fields.forEach((el) => {
      el.removeAttribute("contenteditable");
      el.removeAttribute("spellcheck");
      el.classList.remove("is-changed");
    });
  };

  /* ---------- saving ---------- */
  const save = async () => {
    const map = refresh();
    if (!map.size) return;
    btnSave.disabled = true;
    btnDiscard.disabled = true;
    say(t.saving || "Saving…", true);
    const changes = Array.from(map, ([target, value]) => ({ target, value }));
    try {
      const res = await fetch(cfg.rest, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": cfg.nonce || "" },
        body: JSON.stringify({ changes, page: cfg.page || 0 }),
      });
      const data = await res.json();
      if (!res.ok || !data || !data.ok) {
        const problem = data && data.results ? data.results.filter((r) => r.error).map((r) => r.error).join(" ") : "";
        throw new Error(problem || (data && data.message) || "");
      }
      try {
        sessionStorage.setItem("ce-edit-on", "1");
        sessionStorage.setItem("ce-edit-saved", "1");
      } catch (e) {}
      window.removeEventListener("beforeunload", warn);
      window.location.reload();
    } catch (err) {
      say(((t.failed || "Could not save.") + " " + (err && err.message ? err.message : "")).trim(), true);
      btnDiscard.disabled = false;
      refresh();
    }
  };

  /* ---------- wiring ---------- */
  btnStart.addEventListener("click", enter);
  btnSave.addEventListener("click", save);
  btnDiscard.addEventListener("click", () => {
    if (changed().size && !window.confirm(t.confirm || "Discard your unsaved changes?")) return;
    restore();
    refresh();
  });
  btnDone.addEventListener("click", () => {
    if (changed().size && !window.confirm(t.confirm || "Discard your unsaved changes?")) return;
    restore();
    leave();
  });

  const adminBarItem = document.getElementById("wp-admin-bar-ce-inline-edit");
  if (adminBarItem) adminBarItem.addEventListener("click", (e) => { e.preventDefault(); enter(); window.scrollTo({ top: 0, behavior: "smooth" }); });
  if (window.location.hash === "#ce-edit") enter();

  const warn = (e) => {
    if (editing && changed().size) { e.preventDefault(); e.returnValue = t.leave || ""; }
  };
  window.addEventListener("beforeunload", warn);

  /* Typing goes to the field and nowhere else. WordPress.com and plugins listen for single-key
     shortcuts on the document, so every keyboard and paste event that starts inside an editable
     field is handled here, at the window in the capture phase, and then stopped before any other
     script sees it. The browser's own default (inserting the character) still happens. */
  const inField = (e) => editing && e.target && e.target.closest && e.target.closest("[data-ce-edit]");
  window.addEventListener("keydown", (e) => {
    const el = inField(e);
    if (!el) return;
    if (e.key === "Escape") { e.preventDefault(); el.blur(); }
    else if (e.key === "Enter" && el.dataset.ceFormat !== "lines") { e.preventDefault(); el.blur(); }
    e.stopImmediatePropagation();
  }, true);
  ["keypress", "keyup"].forEach((type) => {
    window.addEventListener(type, (e) => { if (inField(e)) e.stopImmediatePropagation(); }, true);
  });
  window.addEventListener("paste", (e) => {
    const el = inField(e);
    if (!el) return;
    e.stopImmediatePropagation();
    if (plaintextOnly) return;
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData("text");
    document.execCommand("insertText", false, text);
  }, true);

  /* Mirror the same setting shown twice on a page, and count the changes. */
  fields.forEach((el) => {
    el.addEventListener("input", () => {
      if (!editing) return;
      const value = read(el);
      fields.forEach((other) => {
        if (other !== el && other.dataset.ceEdit === el.dataset.ceEdit && read(other) !== value) write(other, value);
      });
      refresh();
    });
  });

  /* In edit mode a click on editable text must not follow its link or fold a belief. */
  document.addEventListener("click", (e) => {
    if (!editing || bar.contains(e.target)) return;
    const field = e.target.closest && e.target.closest("[data-ce-edit]");
    if (field && (field.closest("a") || field.closest("summary"))) e.preventDefault();
  }, true);

  /* Back in edit mode after a save reloads the page. */
  try {
    if (sessionStorage.getItem("ce-edit-on")) {
      sessionStorage.removeItem("ce-edit-on");
      enter();
      if (sessionStorage.getItem("ce-edit-saved")) {
        sessionStorage.removeItem("ce-edit-saved");
        say(t.saved || "Saved.");
      }
    }
  } catch (e) {}
})();
