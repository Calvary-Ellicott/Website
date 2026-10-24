/* Calvary Ellicott email admin (/emailadmin). Plain JavaScript, no build step. */
(function () {
  "use strict";
  const CFG = window.CE_MAIL || {};
  const $ = (sel, el) => (el || document).querySelector(sel);
  const $$ = (sel, el) => Array.from((el || document).querySelectorAll(sel));
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const state = { status: null, view: "groups", groups: [], demoGroups: false, mailboxes: [], demoMail: false, rules: [], report: null, suggestions: [] };
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  /* ---------- api ---------- */
  async function api(path, opts) {
    opts = opts || {};
    const headers = { "X-WP-Nonce": CFG.nonce };
    if (opts.body && typeof opts.body !== "string") { headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(opts.body); }
    const res = await fetch(CFG.rest + "admin/" + path, Object.assign({ credentials: "same-origin" }, opts, { headers }));
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    if (!res.ok) throw new Error(data && data.message ? data.message : "Something went wrong (" + res.status + ").");
    return data;
  }

  /* ---------- ui helpers ---------- */
  let toastTimer = null;
  function toast(msg, kind) {
    const t = $("#toast");
    t.textContent = msg; t.className = "toast" + (kind ? " " + kind : ""); t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.hidden = true; }, kind === "bad" ? 7000 : 3200);
  }
  function banner(html) { const b = $("#banner"); if (html) { b.innerHTML = html; b.hidden = false; } else { b.hidden = true; } }
  function modal(html) { const m = $("#modal"); $(".modal-card", m).innerHTML = html; m.hidden = false; return $(".modal-card", m); }
  function closeModal() { $("#modal").hidden = true; }
  function confirmBox(text, okLabel) {
    return new Promise((resolve) => {
      const card = modal('<h2>' + esc(text) + '</h2><div class="modal-foot"><button class="button ghost" data-x="no">Cancel</button><button class="button primary" data-x="yes">' + esc(okLabel || "Yes") + '</button></div>');
      card.onclick = (e) => { const x = e.target.closest("[data-x]"); if (!x) return; closeModal(); resolve(x.dataset.x === "yes"); };
    });
  }
  function busy(el, on) { if (!el) return; el.disabled = !!on; el.dataset.label = el.dataset.label || el.textContent; el.textContent = on ? "Working…" : el.dataset.label; }
  function full(local) { return local ? local + "@" + (state.status ? state.status.domain : CFG.domain) : ""; }
  function showBanner() {
    if (!state.status) return;
    if (!state.status.connected) return banner('Preview with sample data. <a href="' + esc(state.status.settings) + '">Add the Purelymail API token</a> under Mail → Settings to manage the real account.');
    if (state.status.error) return banner("Purelymail did not answer: " + esc(state.status.error));
    banner("");
  }

  /* ---------- navigation ---------- */
  function setView(view) {
    state.view = view;
    $("#app").dataset.view = view; $("#app").dataset.menu = "";
    $$(".nav a[data-view]").forEach((a) => a.classList.toggle("active", a.dataset.view === view));
    showBanner();
    if (view === "groups") renderGroups();
    if (view === "mailboxes") renderMailboxes();
    if (view === "rules") renderRules();
  }

  /* ---------- groups ---------- */
  async function loadGroups() { const r = await api("groups"); state.groups = r.items; state.demoGroups = !!r.demo; }

  function groupCard(g) {
    const addr = g.address ? '<span class="pill draft">' + esc(full(g.address)) + "</span>" : '<span class="pill">no address</span>';
    const sync = g.address ? (g.synced_at ? '<span class="pill sent">in Purelymail</span>' : '<span class="pill sending">not synced</span>') : "";
    const members = g.members.length ? g.members.map((m) => "<li>" + esc(m) + "</li>").join("") : '<li class="help">Nobody yet. ' + (g.address ? "Mail to this address would bounce." : "") + "</li>";
    return '<div class="card group" data-id="' + g.id + '"><div class="row between"><div><h2>' + esc(g.name) + "</h2><p class=\"row\" style=\"gap:8px\">" + addr + sync + "</p>" + (g.description ? '<p class="help">' + esc(g.description) + "</p>" : "") + "</div>" +
      '<div class="row"><button class="button small" data-action="edit-group" data-id="' + g.id + '">Edit</button><button class="button small danger" data-action="delete-group" data-id="' + g.id + '">Delete</button></div></div>' +
      '<p class="field-label" style="margin-top:14px">Members (' + g.members.length + ")</p><ul class=\"members\">" + members + "</ul></div>";
  }

  function renderGroups() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Mail groups</h1><span class="grow"></span><button class="button ghost" data-action="sync">Sync to Purelymail</button><button class="button primary" data-action="new-group">+ New group</button></div><div class="pane" id="groups-pane"><div class="empty">Loading…</div></div>';
    refreshGroups();
  }

  async function refreshGroups() {
    const pane = $("#groups-pane"); if (!pane) return;
    try {
      await loadGroups();
      let intro = '<div class="card"><h2>How groups work</h2><p>A group is a list of mailboxes kept here in WordPress. Give it an address, such as <strong>info</strong>, and <em>Sync to Purelymail</em> writes one routing rule so mail to <strong>' + esc(full("info")) + "</strong> is delivered to every member. Members can be any address, including ones outside the church domain. A group without an address is just a list.</p></div>";
      if (state.report) intro += reportCard(state.report);
      pane.innerHTML = intro + (state.groups.length ? state.groups.map(groupCard).join("") : '<div class="empty"><h2>No groups yet</h2>Create one, for example “Staff” with the address “info”.</div>');
    } catch (e) { pane.innerHTML = '<div class="empty">' + esc(e.message) + "</div>"; }
  }

  function reportCard(rep) {
    const rows = rep.groups.map((g) => '<tr><td>' + esc(g.name) + "</td><td>" + esc(g.address ? full(g.address) : "—") + '</td><td><span class="pill ' + ({ ok: "sent", created: "sent", updated: "sent", empty: "sending", error: "failed", list: "" }[g.status] || "") + '">' + esc(g.status) + "</span></td><td>" + esc(g.message) + "</td></tr>").join("");
    return '<div class="card"><div class="row between"><h2>Last sync</h2><button class="button small ghost" data-action="hide-report">Hide</button></div><table class="table"><thead><tr><th>Group</th><th>Address</th><th>Result</th><th>Detail</th></tr></thead><tbody>' + rows + "</tbody></table></div>";
  }

  /* ---------- email picker: search + dropdown + chips ---------- */
  async function loadSuggestions() {
    try { const r = await api("suggestions"); state.suggestions = r.items; } catch (e) { state.suggestions = []; }
  }

  function emailPicker(host, selected) {
    const sel = (selected || []).map((s) => String(s).toLowerCase());
    const sugg = state.suggestions;
    let q = "", hi = 0;
    host.className = "picker";
    host.innerHTML = '<div class="chips"></div><div class="picker-box"><input type="text" class="picker-input" placeholder="Search names or addresses, or type an address and press Enter" autocomplete="off" spellcheck="false" /><div class="picker-list" hidden></div></div>' +
      '<div class="row" style="margin-top:8px"><button type="button" class="button small ghost" data-pick="all-mailboxes">Add every mailbox</button><button type="button" class="button small ghost" data-pick="clear">Remove all</button></div>';
    const chips = $(".chips", host), input = $(".picker-input", host), list = $(".picker-list", host);
    const labelOf = (e) => { const s = sugg.find((x) => x.email === e); return s && s.label ? s.label : ""; };

    function renderChips() {
      chips.innerHTML = sel.length ? sel.map((e) => '<span class="chip" title="' + esc(e) + '">' + (labelOf(e) ? "<b>" + esc(labelOf(e)) + "</b> " : "") + esc(e) + '<button type="button" data-remove="' + esc(e) + '" aria-label="Remove ' + esc(e) + '">×</button></span>').join("") : '<span class="help">Nobody yet. Search below or type an address.</span>';
    }
    function matches() {
      const t = q.trim().toLowerCase();
      return sugg.filter((s) => !sel.includes(s.email) && (!t || s.email.includes(t) || s.label.toLowerCase().includes(t))).slice(0, 12);
    }
    function renderList() {
      const m = matches(), typed = q.trim().toLowerCase();
      const canAdd = EMAIL_RE.test(typed) && !sel.includes(typed) && !m.some((s) => s.email === typed);
      const n = m.length + (canAdd ? 1 : 0);
      if (!n) { list.hidden = true; return; }
      hi = Math.max(0, Math.min(hi, n - 1));
      list.innerHTML = m.map((s, i) => '<div class="picker-item' + (i === hi ? " hi" : "") + '" data-email="' + esc(s.email) + '"><span>' + (s.label ? "<b>" + esc(s.label) + "</b> " : "") + esc(s.email) + '</span><span class="pill">' + esc(s.source) + "</span></div>").join("") +
        (canAdd ? '<div class="picker-item' + (m.length === hi ? " hi" : "") + '" data-email="' + esc(typed) + '"><span>Add <b>' + esc(typed) + '</b></span><span class="pill">new address</span></div>' : "");
      list.hidden = false;
      const cur = $(".picker-item.hi", list); if (cur && cur.scrollIntoView) cur.scrollIntoView({ block: "nearest" });
    }
    function add(emails) {
      let bad = 0;
      [].concat(emails).forEach((e) => { e = String(e).trim().toLowerCase(); if (!e) return; if (!EMAIL_RE.test(e)) { bad++; return; } if (!sel.includes(e)) sel.push(e); });
      if (bad) toast(bad + " item(s) did not look like an email address and were skipped", "bad");
      q = ""; input.value = ""; hi = 0; renderChips(); renderList(); input.focus();
    }

    input.oninput = () => { q = input.value; hi = 0; renderList(); };
    input.onfocus = () => renderList();
    input.onblur = () => setTimeout(() => { list.hidden = true; }, 150);
    input.onpaste = (e) => { const t = (e.clipboardData || window.clipboardData).getData("text"); if (/[\s,;]/.test(t.trim())) { e.preventDefault(); add(t.split(/[\s,;]+/)); } };
    input.onkeydown = (e) => {
      const items = $$(".picker-item", list);
      if (e.key === "ArrowDown") { e.preventDefault(); if (list.hidden) renderList(); else { hi = Math.min(hi + 1, items.length - 1); renderList(); } }
      else if (e.key === "ArrowUp") { e.preventDefault(); hi = Math.max(hi - 1, 0); renderList(); }
      else if (e.key === "Enter" || e.key === "Tab" && !list.hidden && input.value) {
        e.preventDefault();
        const it = !list.hidden ? items[hi] : null;
        if (it) add(it.dataset.email);
        else if (input.value.trim()) add(input.value);
      }
      else if (e.key === "Escape") { if (!list.hidden) { e.stopPropagation(); list.hidden = true; } }
      else if (e.key === "Backspace" && !input.value && sel.length) { sel.pop(); renderChips(); }
    };
    list.onmousedown = (e) => { const it = e.target.closest(".picker-item"); if (it) { e.preventDefault(); add(it.dataset.email); } };
    host.onclick = (e) => {
      const rm = e.target.closest("[data-remove]"); if (rm) { sel.splice(sel.indexOf(rm.dataset.remove), 1); renderChips(); input.focus(); return; }
      const pick = e.target.closest("[data-pick]"); if (!pick) return;
      if (pick.dataset.pick === "all-mailboxes") { const m = sugg.filter((s) => s.source === "mailbox").map((s) => s.email); if (!m.length) toast("No mailboxes known yet. Connect the API token first.", "bad"); else add(m); }
      if (pick.dataset.pick === "clear") { sel.length = 0; renderChips(); }
    };
    renderChips();
    return { values: () => sel.slice() };
  }

  function groupForm(g) {
    g = g || { id: 0, name: "", address: "", description: "", members: [] };
    const card = modal('<h2>' + (g.id ? "Edit group" : "New group") + '</h2><form id="group-form">' +
      '<label class="field"><span>Name</span><input type="text" name="name" value="' + esc(g.name) + '" required placeholder="Staff" /></label>' +
      '<label class="field"><span>Address (optional)</span><div class="row" style="gap:6px"><input type="text" name="address" value="' + esc(g.address) + '" placeholder="info" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" style="max-width:220px" /><span>@' + esc(state.status ? state.status.domain : CFG.domain) + "</span></div><p class=\"help\">Leave blank for a list with no address of its own. Do not create a Purelymail mailbox with this name; the routing rule takes its place.</p></label>" +
      '<label class="field"><span>Description (optional)</span><input type="text" name="description" value="' + esc(g.description) + '" /></label>' +
      '<div class="field"><span class="field-label">Members</span><div id="member-picker"></div><p class="help">Suggestions come from the mailboxes on the domain, people with a website login, and other groups. Any other address can be typed in; pasting a list separated by commas or new lines works too.</p></div>' +
      '<div class="modal-foot"><button type="button" class="button ghost" data-action="close">Cancel</button><button type="submit" class="button primary">Save group</button></div></form>');
    const picker = emailPicker($("#member-picker", card), g.members);
    $("#group-form", card).onsubmit = async (e) => {
      e.preventDefault(); const btn = $("button[type=submit]", card); busy(btn, true);
      const f = Object.fromEntries(new FormData(e.target).entries());
      const pending = $(".picker-input", card).value.trim();
      const members = picker.values();
      if (pending && EMAIL_RE.test(pending.toLowerCase()) && !members.includes(pending.toLowerCase())) members.push(pending.toLowerCase());
      try {
        await api(g.id ? "groups/" + g.id : "groups", { method: g.id ? "PUT" : "POST", body: { name: f.name, address: f.address, description: f.description, members } });
        closeModal(); toast("Group saved. Sync to Purelymail when the list is right.", "ok"); refreshGroups();
      } catch (err) { toast(err.message, "bad"); busy(btn, false); }
    };
  }

  async function syncGroups(btn) {
    if (state.status && !state.status.connected) return toast("Add the Purelymail API token first (Mail → Settings).", "bad");
    busy(btn, true);
    try {
      const r = await api("sync", { method: "POST" });
      state.report = r;
      const bad = r.groups.filter((g) => g.status === "error").length;
      toast(bad ? bad + " group(s) could not be synced" : "Purelymail routing rules are up to date", bad ? "bad" : "ok");
      refreshGroups();
    } catch (e) { toast(e.message, "bad"); }
    busy(btn, false);
  }

  /* ---------- mailboxes ---------- */
  async function loadMailboxes() { try { const r = await api("mailboxes"); state.mailboxes = r.items; state.demoMail = !!r.demo; } catch (e) { state.mailboxes = []; } }

  function renderMailboxes() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Mailboxes</h1><span class="grow"></span><button class="button ghost" data-action="refresh-mailboxes">↻</button><button class="button primary" data-action="new-mailbox">+ New mailbox</button></div><div class="pane" id="mail-pane"><div class="empty">Loading…</div></div>';
    refreshMailboxes();
  }

  async function refreshMailboxes() {
    const pane = $("#mail-pane"); if (!pane) return;
    try {
      const r = await api("mailboxes"); state.mailboxes = r.items; state.demoMail = !!r.demo;
      const inGroups = {}; state.groups.forEach((g) => g.members.forEach((m) => { (inGroups[m] = inGroups[m] || []).push(g.name); }));
      const rows = state.mailboxes.map((m) => "<tr><td><strong>" + esc(m) + "</strong></td><td>" + (inGroups[m] ? inGroups[m].map((n) => '<span class="pill">' + esc(n) + "</span>").join(" ") : '<span class="help">—</span>') + '</td><td style="text-align:right"><button class="button small ghost" data-action="password" data-email="' + esc(m) + '">Reset password</button></td></tr>').join("");
      pane.innerHTML = '<div class="card"><p>Real mailboxes on <strong>' + esc(state.status.domain) + "</strong>, read from Purelymail. People sign in to the <a href=\"" + esc(CFG.site.mail) + "\">mail portal</a> or <a href=\"" + esc(CFG.webmail) + "\" target=\"_blank\" rel=\"noopener\">Purelymail webmail</a> with these. Deleting a mailbox is deliberately not offered here; do that in Purelymail itself.</p></div>" +
        '<table class="table"><thead><tr><th>Mailbox</th><th>In groups</th><th></th></tr></thead><tbody>' + (rows || '<tr><td colspan="3" class="help">No mailboxes on this domain yet.</td></tr>') + "</tbody></table>";
    } catch (e) { pane.innerHTML = '<div class="empty">' + esc(e.message) + "</div>"; }
  }

  function mailboxForm() {
    const card = modal('<h2>New mailbox</h2><form id="mailbox-form">' +
      '<label class="field"><span>Address</span><div class="row" style="gap:6px"><input type="text" name="local" required pattern="[A-Za-z0-9][A-Za-z0-9._-]*" placeholder="firstname" style="max-width:220px" /><span>@' + esc(state.status.domain) + "</span></div></label>" +
      '<label class="field"><span>Password (optional)</span><input type="text" name="password" autocomplete="off" placeholder="Leave blank to have one suggested" /><p class="help">At least 12 characters. The password is shown once after the mailbox is created; pass it on and have the person change it in Purelymail.</p></label>' +
      '<label class="field"><span>Recovery email (optional)</span><input type="email" name="recovery" placeholder="their personal address" /><p class="help">Lets them reset their own password through Purelymail.</p></label>' +
      '<label class="field"><input type="checkbox" name="welcome" value="1" /> Have Purelymail send its welcome email to the new mailbox</label>' +
      '<div class="modal-foot"><button type="button" class="button ghost" data-action="close">Cancel</button><button type="submit" class="button primary">Create mailbox</button></div></form>');
    $("#mailbox-form", card).onsubmit = async (e) => {
      e.preventDefault(); const btn = $("button[type=submit]", card); busy(btn, true);
      const f = Object.fromEntries(new FormData(e.target).entries());
      try {
        const r = await api("mailboxes", { method: "POST", body: f });
        showPassword(r.email, r.password, "Mailbox created");
        refreshMailboxes();
      } catch (err) { toast(err.message, "bad"); busy(btn, false); }
    };
  }

  function passwordForm(email) {
    const card = modal('<h2>Reset password</h2><form id="pw-form"><p>New password for <strong>' + esc(email) + "</strong>. Their phone and mail apps will need the new one too.</p>" +
      '<label class="field"><span>Password (optional)</span><input type="text" name="password" autocomplete="off" placeholder="Leave blank to have one suggested" /></label>' +
      '<div class="modal-foot"><button type="button" class="button ghost" data-action="close">Cancel</button><button type="submit" class="button primary">Set password</button></div></form>');
    $("#pw-form", card).onsubmit = async (e) => {
      e.preventDefault(); const btn = $("button[type=submit]", card); busy(btn, true);
      const f = Object.fromEntries(new FormData(e.target).entries());
      try { const r = await api("mailboxes/password", { method: "POST", body: { email, password: f.password } }); showPassword(r.email, r.password, "Password changed"); }
      catch (err) { toast(err.message, "bad"); busy(btn, false); }
    };
  }

  function showPassword(email, password, title) {
    modal("<h2>" + esc(title) + "</h2><p>Write this down or pass it on now. It is not stored anywhere in WordPress and will not be shown again.</p>" +
      '<div class="card"><p class="field-label">Mailbox</p><p><strong>' + esc(email) + '</strong></p><p class="field-label">Password</p><p><code style="font-size:1.1rem;user-select:all">' + esc(password) + "</code></p></div>" +
      '<div class="modal-foot"><button type="button" class="button ghost" data-action="copy-password" data-text="' + esc(password) + '">Copy password</button><button type="button" class="button primary" data-action="close">Done</button></div>');
  }

  /* ---------- rules ---------- */
  function renderRules() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Routing rules in Purelymail</h1><span class="grow"></span><button class="button ghost" data-action="refresh-rules">↻</button></div><div class="pane" id="rules-pane"><div class="empty">Loading…</div></div>';
    refreshRules();
  }

  async function refreshRules() {
    const pane = $("#rules-pane"); if (!pane) return;
    try {
      const r = await api("rules"); state.rules = r.items;
      const owned = {}; state.groups.forEach((g) => { if (g.rule_id) owned[g.rule_id] = g.name; });
      const rows = state.rules.map((x) => "<tr><td>" + (x.catchall ? "<em>catch-all</em>" : esc(x.matchUser) + "@" + (x.prefix ? "… (prefix)" : "")) + "</td><td>" + x.targets.map(esc).join("<br>") + "</td><td>" + (owned[x.id] ? '<span class="pill sent">group: ' + esc(owned[x.id]) + "</span>" : '<span class="pill">made in Purelymail</span>') + "</td></tr>").join("");
      pane.innerHTML = '<div class="card"><p>Everything Purelymail has for <strong>' + esc(r.domain) + "</strong>, read-only. Rules a group owns are rewritten by <em>Sync</em>; the others were made on Purelymail's own Routing page and are left alone.</p></div>" +
        '<table class="table"><thead><tr><th>Mail to</th><th>Goes to</th><th>Managed by</th></tr></thead><tbody>' + (rows || '<tr><td colspan="3" class="help">' + (r.demo ? "Connect the API token to see real rules." : "No routing rules yet.") + "</td></tr>") + "</tbody></table>";
    } catch (e) { pane.innerHTML = '<div class="empty">' + esc(e.message) + "</div>"; }
  }

  /* ---------- events ---------- */
  document.addEventListener("click", async (e) => {
    const nav = e.target.closest("a[data-view]");
    if (nav) { e.preventDefault(); return setView(nav.dataset.view); }
    const btn = e.target.closest("[data-action]");
    if (!btn) return;
    const a = btn.dataset.action;
    if (a === "menu") { const app = $("#app"); app.dataset.menu = app.dataset.menu === "open" ? "" : "open"; }
    if (a === "close") closeModal();
    if (a === "new-group") { busy(btn, true); await loadSuggestions(); busy(btn, false); groupForm(); }
    if (a === "edit-group") { busy(btn, true); await loadSuggestions(); busy(btn, false); groupForm(state.groups.find((g) => g.id === +btn.dataset.id)); }
    if (a === "delete-group") {
      const g = state.groups.find((x) => x.id === +btn.dataset.id);
      if (g && await confirmBox("Delete “" + g.name + "”?" + (g.address && g.rule_id ? " Its routing rule in Purelymail is removed too, so mail to " + full(g.address) + " will bounce." : ""), "Delete")) {
        try { const r = await api("groups/" + g.id, { method: "DELETE" }); if (r.warning) toast(r.warning, "bad"); else toast("Group deleted"); refreshGroups(); } catch (err) { toast(err.message, "bad"); }
      }
    }
    if (a === "sync") syncGroups(btn);
    if (a === "hide-report") { state.report = null; refreshGroups(); }
    if (a === "new-mailbox") { if (state.status && !state.status.connected) return toast("Add the Purelymail API token first (Mail → Settings).", "bad"); mailboxForm(); }
    if (a === "password") { if (state.status && !state.status.connected) return toast("Add the Purelymail API token first (Mail → Settings).", "bad"); passwordForm(btn.dataset.email); }
    if (a === "copy-password") { try { await navigator.clipboard.writeText(btn.dataset.text); toast("Copied", "ok"); } catch (err) { toast("Select the password and copy it by hand.", "bad"); } }
    if (a === "refresh-mailboxes") refreshMailboxes();
    if (a === "refresh-rules") refreshRules();
  });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !$("#modal").hidden) closeModal(); });
  $("#modal").addEventListener("click", (e) => { if (e.target.id === "modal") closeModal(); });

  /* ---------- boot ---------- */
  (async function boot() {
    try {
      state.status = await api("status");
      await loadGroups();
      const hash = (location.hash || "#groups").slice(1);
      setView(["groups", "mailboxes", "rules"].includes(hash) ? hash : "groups");
    } catch (e) {
      $("#view").innerHTML = '<div class="empty"><h2>Email admin could not start</h2>' + esc(e.message) + "</div>";
    }
  })();
})();
