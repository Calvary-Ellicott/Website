/* Calvary Ellicott mail portal. Plain JavaScript, no build step. */
(function () {
  "use strict";
  const CFG = window.CE_MAIL || {};
  const $ = (sel, el) => (el || document).querySelector(sel);
  const $$ = (sel, el) => Array.from((el || document).querySelectorAll(sel));
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

  const state = {
    me: null, view: "inbox", folders: [], folder: "INBOX", folderRole: "inbox",
    messages: [], total: 0, page: 1, q: "", message: null, selected: new Set(),
    campaigns: [], campaign: null, contacts: [], contactsTotal: 0, contactsPage: 1, contactsQ: "", counts: null, demoMail: false, demoMarketing: false
  };

  /* ---------- api ---------- */
  async function api(path, opts) {
    opts = opts || {};
    const headers = { "X-WP-Nonce": CFG.nonce };
    if (opts.body && typeof opts.body !== "string") { headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(opts.body); }
    const res = await fetch(CFG.rest + path, Object.assign({ credentials: "same-origin" }, opts, { headers }));
    let data = null;
    try { data = await res.json(); } catch (e) { data = null; }
    if (!res.ok) {
      if (data && (data.code === "rest_cookie_invalid_nonce" || (res.status === 401 && CFG.user && CFG.user.guest))) { location.reload(); return new Promise(() => {}); }
      const msg = data && data.message ? data.message : "Something went wrong (" + res.status + ").";
      throw new Error(msg);
    }
    return data;
  }

  /* ---------- small ui helpers ---------- */
  let toastTimer = null;
  function toast(msg, kind) {
    const t = $("#toast");
    t.textContent = msg; t.className = "toast" + (kind ? " " + kind : ""); t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.hidden = true; }, kind === "bad" ? 6000 : 3200);
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
  function fmtDate(ts) {
    const d = new Date(ts * 1000), now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
    if (d.getFullYear() === now.getFullYear()) return d.toLocaleDateString([], { month: "short", day: "numeric" });
    return d.toLocaleDateString([], { month: "short", day: "numeric", year: "numeric" });
  }
  function fmtLong(ts) { return new Date(ts * 1000).toLocaleString([], { weekday: "short", month: "long", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }); }
  function fmtSize(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + " MB" : n > 1024 ? Math.round(n / 1024) + " KB" : n + " B"; }
  function who(a) { return a ? (a.name || a.email || "") : ""; }
  function addr(a) { return a ? (a.name ? a.name + " <" + a.email + ">" : a.email) : ""; }
  function busy(el, on) { if (!el) return; el.disabled = !!on; el.dataset.label = el.dataset.label || el.textContent; el.textContent = on ? "Working…" : el.dataset.label; }

  /* ---------- navigation ---------- */
  function setView(view, extra) {
    if (!state.me.marketing.enabled && ["campaigns", "campaign", "contacts", "import"].includes(view)) view = "inbox";
    state.view = view;
    $("#app").dataset.view = view;
    $("#app").dataset.menu = "";
    $$(".nav a[data-view]").forEach((a) => a.classList.toggle("active", a.dataset.view === view));
    $$("#folder-list a").forEach((a) => a.classList.toggle("active", view === "inbox" && a.dataset.folder === state.folder));
    if (view === "inbox") renderMail();
    if (view === "campaigns") renderCampaigns();
    if (view === "campaign") renderCampaignEditor(extra);
    if (view === "contacts") renderContacts();
    if (view === "import") renderImport();
    if (view === "settings") renderSettings();
  }

  function renderFolders() {
    const ul = $("#folder-list");
    const names = { inbox: "Inbox", sent: "Sent", drafts: "Drafts", archive: "Archive", junk: "Junk", trash: "Trash" };
    ul.innerHTML = state.folders.map((f) => {
      const label = names[f.role] || f.name;
      const count = f.unseen ? '<span class="count">' + f.unseen + "</span>" : "";
      return '<li><a href="#inbox" data-view="inbox" data-folder="' + esc(f.name) + '" data-role="' + esc(f.role) + '" class="' + (f.name === state.folder ? "active" : "") + '">' + esc(label) + count + "</a></li>";
    }).join("");
  }

  /* ---------- mail ---------- */
  async function loadFolders() {
    try {
      const r = await api("folders");
      state.demoMail = !!r.demo;
      state.folders = r.folders;
      if (!state.folders.find((f) => f.name === state.folder)) { state.folder = state.folders[0] ? state.folders[0].name : "INBOX"; }
      renderFolders();
    } catch (e) { toast(e.message, "bad"); }
  }

  async function loadMessages() {
    const r = await api("messages?folder=" + encodeURIComponent(state.folder) + "&page=" + state.page + "&q=" + encodeURIComponent(state.q));
    state.messages = r.items; state.total = r.total; state.demoMail = !!r.demo;
  }

  function renderMail() {
    const v = $("#view");
    v.innerHTML = '<div class="mail" id="mail"><div class="list"><div class="topbar"><h1>' + esc(folderLabel()) + '</h1><input class="search" type="search" id="q" placeholder="Search" value="' + esc(state.q) + '" /><button class="button ghost small" data-action="refresh">↻</button></div>' +
      '<div class="bulk" id="bulk" hidden></div><div class="list-scroll" id="list"><div class="empty">Loading…</div></div><div class="list-foot" id="foot"></div></div>' +
      '<div class="reader" id="reader"><div class="empty"><h2>Nothing open</h2>Pick a message on the left.</div></div></div>';
    showDemoBanner();
    refreshList();
  }

  function folderLabel() {
    const f = state.folders.find((x) => x.name === state.folder);
    const names = { inbox: "Inbox", sent: "Sent", drafts: "Drafts", archive: "Archive", junk: "Junk", trash: "Trash" };
    return f ? (names[f.role] || f.name) : state.folder;
  }

  async function refreshList() {
    const list = $("#list"); if (!list) return;
    try {
      await loadMessages();
      if (!state.messages.length) {
        list.innerHTML = '<div class="empty"><h2>' + (state.q ? "No matches" : "All caught up") + '</h2>' + (state.q ? "Try a different word." : "Nothing in " + esc(folderLabel()) + ".") + "</div>";
      } else {
        list.innerHTML = state.messages.map((m) => '<div class="msg' + (m.seen ? "" : " unseen") + (state.message && state.message.uid === m.uid ? " active" : "") + '" data-uid="' + m.uid + '">' +
          '<input type="checkbox" class="msg-check" data-uid="' + m.uid + '" ' + (state.selected.has(m.uid) ? "checked" : "") + ' aria-label="Select" />' +
          '<div class="msg-from">' + (m.flagged ? '<span class="msg-flag">★</span>' : "") + esc(state.folderRole === "sent" ? "To: " + who(m.to && m.to[0]) : who(m.from)) + "</div>" +
          '<div class="msg-date">' + (m.attachments ? '<span class="msg-clip">📎 </span>' : "") + fmtDate(m.date) + "</div>" +
          '<div class="msg-subject">' + esc(m.subject || "(no subject)") + "</div></div>").join("");
      }
      const pages = Math.max(1, Math.ceil(state.total / (state.me.page_size || 25)));
      $("#foot").innerHTML = '<span>' + state.total + (state.total === 1 ? " message" : " messages") + '</span><span><button class="button ghost small" data-action="prev" ' + (state.page <= 1 ? "disabled" : "") + '>‹</button> ' + state.page + " / " + pages + ' <button class="button ghost small" data-action="next" ' + (state.page >= pages ? "disabled" : "") + '>›</button></span>';
      renderBulk();
    } catch (e) {
      list.innerHTML = '<div class="empty"><h2>Could not load mail</h2>' + esc(e.message) + '<br><br><button class="button" data-action="refresh">Try again</button> <a class="button ghost" href="#settings" data-view="settings">Check settings</a></div>';
    }
  }

  function renderBulk() {
    const b = $("#bulk"); if (!b) return;
    const n = state.selected.size;
    b.hidden = n === 0;
    if (n) b.innerHTML = '<span class="grow">' + n + " selected</span>" + bulkButtons();
  }
  function bulkButtons() {
    const role = state.folderRole;
    return '<button class="button small" data-action="bulk-read">Mark read</button>' +
      (role !== "archive" ? '<button class="button small" data-action="bulk-move" data-to="archive">Archive</button>' : "") +
      (role !== "trash" ? '<button class="button small danger" data-action="bulk-move" data-to="trash">Delete</button>' : '<button class="button small" data-action="bulk-move" data-to="inbox">Move to Inbox</button>');
  }

  async function openMessage(uid) {
    const reader = $("#reader"); const mail = $("#mail");
    reader.innerHTML = '<div class="empty">Opening…</div>';
    if (mail) mail.dataset.open = "true";
    try {
      const m = await api("message?folder=" + encodeURIComponent(state.folder) + "&uid=" + uid);
      state.message = m;
      const row = $('.msg[data-uid="' + uid + '"]'); if (row) { row.classList.add("unseen"); row.classList.remove("unseen"); $$(".msg").forEach((r) => r.classList.remove("active")); row.classList.add("active"); }
      const atts = (m.attachments || []).map((a) => '<a class="att" href="' + (m.demo ? "#" : CFG.rest + "attachment?folder=" + encodeURIComponent(m.folder) + "&uid=" + m.uid + "&part=" + encodeURIComponent(a.part) + "&_wpnonce=" + CFG.nonce) + '" ' + (m.demo ? 'data-action="demo-att"' : "download") + '>📎 ' + esc(a.name) + " <small>" + fmtSize(a.size) + "</small></a>").join("");
      reader.innerHTML = '<div class="reader-head"><button class="button ghost small" data-action="back" style="margin-bottom:10px">‹ Back</button><h1>' + esc(m.subject || "(no subject)") + "</h1>" +
        '<div class="reader-meta"><span><b>From</b> ' + esc(addr(m.from)) + "</span><span><b>To</b> " + esc((m.to || []).map(addr).join(", ")) + "</span>" + (m.cc && m.cc.length ? "<span><b>Cc</b> " + esc(m.cc.map(addr).join(", ")) + "</span>" : "") + "<span>" + esc(fmtLong(m.date)) + "</span></div>" +
        '<div class="reader-actions"><button class="button primary small" data-action="reply">Reply</button><button class="button small" data-action="reply-all">Reply all</button><button class="button small" data-action="forward">Forward</button>' +
        (state.folderRole !== "archive" ? '<button class="button small" data-action="move" data-to="archive">Archive</button>' : "") +
        '<button class="button small" data-action="flag">' + (flaggedNow(uid) ? "★ Unflag" : "☆ Flag") + '</button><button class="button small" data-action="unread">Mark unread</button><button class="button small danger" data-action="move" data-to="trash">Delete</button></div></div>' +
        (atts ? '<div class="atts" style="padding-top:16px">' + atts + "</div>" : "") +
        (m.html ? '<div class="reader-body">' + m.html + "</div>" : '<div class="reader-body plain">' + esc(m.text) + "</div>");
      $$("#list .msg").forEach((r) => { if (+r.dataset.uid === uid) r.classList.remove("unseen"); });
    } catch (e) { reader.innerHTML = '<div class="empty"><h2>Could not open that message</h2>' + esc(e.message) + "</div>"; }
  }
  function flaggedNow(uid) { const m = state.messages.find((x) => x.uid === uid); return m && m.flagged; }

  async function flagMessages(uids, flag, value) {
    try { await api("messages/flag", { method: "POST", body: { folder: state.folder, uids, flag, value } }); } catch (e) { toast(e.message, "bad"); }
  }
  async function moveMessages(uids, to) {
    try {
      await api("messages/move", { method: "POST", body: { folder: state.folder, uids, to } });
      toast(to === "trash" ? "Deleted" : "Moved to " + to, "ok");
      state.selected.clear();
      if (state.message && uids.includes(state.message.uid)) { state.message = null; $("#reader").innerHTML = '<div class="empty"><h2>Nothing open</h2>Pick a message on the left.</div>'; const mail = $("#mail"); if (mail) mail.dataset.open = ""; }
      refreshList(); loadFolders();
    } catch (e) { toast(e.message, "bad"); }
  }

  /* ---------- compose ---------- */
  function openCompose(preset) {
    preset = preset || {};
    const card = modal('<h2>' + esc(preset.title || "New message") + '</h2><form class="compose" id="compose-form">' +
      '<label class="field"><span>To</span><input type="text" name="to" value="' + esc(preset.to || "") + '" placeholder="name@example.com, another@example.com" required /></label>' +
      '<div class="row"><label class="field grow"><span>Cc</span><input type="text" name="cc" value="' + esc(preset.cc || "") + '" /></label><label class="field grow"><span>Bcc</span><input type="text" name="bcc" /></label></div>' +
      '<label class="field"><span>Subject</span><input type="text" name="subject" value="' + esc(preset.subject || "") + '" /></label>' +
      '<label class="field"><span>Message</span><textarea name="text">' + esc(preset.text || "") + '</textarea></label>' +
      '<input type="hidden" name="in_reply_to" value="' + esc(preset.in_reply_to || "") + '" /><input type="hidden" name="references" value="' + esc(preset.references || "") + '" />' +
      '<div class="modal-foot"><span class="help grow">Sent from ' + esc(state.me.mailbox.email || "your mailbox") + '</span><button type="button" class="button ghost" data-x="cancel">Cancel</button><button type="submit" class="button primary">Send</button></div></form>');
    const form = $("#compose-form", card);
    $('[name="' + (preset.to ? "text" : "to") + '"]', form).focus();
    card.onclick = (e) => { if (e.target.closest('[data-x="cancel"]')) closeModal(); };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const btn = $('button[type=submit]', form); busy(btn, true);
      const body = Object.fromEntries(new FormData(form).entries());
      try {
        const r = await api("send", { method: "POST", body });
        closeModal();
        toast(r.demo ? "In the preview, nothing is really sent. Connect your mailbox in Settings to send mail." : "Sent", r.demo ? "" : "ok");
        if (state.folderRole === "sent") refreshList();
      } catch (err) { toast(err.message, "bad"); busy(btn, false); }
    };
  }
  function quoteOf(m) {
    const text = m.text || "";
    return "\n\n\nOn " + fmtLong(m.date) + ", " + addr(m.from) + " wrote:\n" + text.split("\n").map((l) => "> " + l).join("\n");
  }
  function replyPreset(m, all) {
    const to = m.reply_to && m.reply_to.email ? m.reply_to.email : m.from.email;
    let cc = "";
    if (all) {
      const mine = (state.me.mailbox.email || "").toLowerCase();
      cc = [].concat(m.to || [], m.cc || []).map((a) => a.email).filter((e) => e && e.toLowerCase() !== mine && e.toLowerCase() !== to.toLowerCase()).join(", ");
    }
    return { title: all ? "Reply all" : "Reply", to, cc, subject: /^re:/i.test(m.subject) ? m.subject : "Re: " + m.subject, text: quoteOf(m), in_reply_to: m.message_id, references: m.references };
  }
  function forwardPreset(m) {
    return { title: "Forward", subject: /^fwd?:/i.test(m.subject) ? m.subject : "Fwd: " + m.subject, text: "\n\n---------- Forwarded message ----------\nFrom: " + addr(m.from) + "\nDate: " + fmtLong(m.date) + "\nSubject: " + m.subject + "\nTo: " + (m.to || []).map(addr).join(", ") + "\n\n" + (m.text || "") };
  }

  /* ---------- campaigns ---------- */
  async function renderCampaigns() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Campaigns</h1><span class="grow"></span><button class="button primary" data-action="new-campaign">+ New campaign</button></div><div class="pane" id="campaign-list">Loading…</div>';
    showDemoBanner();
    try {
      const r = await api("campaigns"); state.campaigns = r.items; state.demoMarketing = !!r.demo;
      const box = $("#campaign-list");
      if (!state.campaigns.length) { box.innerHTML = '<div class="empty"><h2>No campaigns yet</h2>A campaign is one email sent to everyone in your contact list, like a newsletter or an event invitation.<br><br><button class="button primary" data-action="new-campaign">Write the first one</button></div>'; return; }
      box.innerHTML = '<table class="table"><thead><tr><th>Campaign</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>' + state.campaigns.map((c) => '<tr><td><strong>' + esc(c.name) + '</strong><br><small>' + esc(c.subject) + '</small></td><td><span class="pill ' + esc(c.status) + '">' + esc(c.status) + '</span>' + (c.sent_at ? '<br><small>' + esc(c.sent_at) + '</small>' : "") + '</td><td>' + esc((c.updated_at || "").slice(0, 10)) + '</td><td style="text-align:right"><a class="button small" href="#campaign" data-view="campaign" data-id="' + c.id + '">' + (c.status === "draft" ? "Edit" : "View") + "</a></td></tr>").join("") + "</tbody></table>";
      if (!state.me.marketing.ready) box.insertAdjacentHTML("afterbegin", '<div class="card" style="margin-bottom:16px"><h2>Marketing email is not connected yet</h2><p class="help">Campaigns can be drafted now and sent once Resend is set up' + (state.me.admin ? ' under <a href="' + esc(CFG.site.settings) + '">Mail → Settings</a> in the dashboard' : " by the website admin") + ".</p></div>");
    } catch (e) { $("#campaign-list").innerHTML = '<div class="empty">' + esc(e.message) + "</div>"; }
  }

  async function renderCampaignEditor(id) {
    const v = $("#view");
    let c = { id: 0, name: "", subject: "", from_email: state.me.marketing.from, from_name: state.me.marketing.name, reply_to: state.me.marketing.reply_to, html: "", status: "draft" };
    if (id) { try { c = await api("campaigns/" + id); } catch (e) { toast(e.message, "bad"); return setView("campaigns"); } }
    state.campaign = c;
    const locked = c.status !== "draft";
    v.innerHTML = '<div class="topbar"><a class="button ghost small" href="#campaigns" data-view="campaigns">‹ Campaigns</a><h1>' + (c.id ? esc(c.name) : "New campaign") + '</h1><span class="grow"></span><span class="pill ' + esc(c.status) + '">' + esc(c.status) + "</span></div>" +
      '<div class="pane"><form id="campaign-form">' +
      '<div class="row"><label class="field grow"><span>Campaign name (for you)</span><input type="text" name="name" value="' + esc(c.name) + '" placeholder="November newsletter" ' + (locked ? "disabled" : "") + ' /></label></div>' +
      '<label class="field"><span>Subject line (what people see)</span><input type="text" name="subject" value="' + esc(c.subject) + '" placeholder="What’s happening this month" ' + (locked ? "disabled" : "") + ' /></label>' +
      '<div class="row"><label class="field grow"><span>From</span><input type="text" value="' + esc(c.from_name + " <" + c.from_email + ">") + '" disabled /></label><label class="field grow"><span>Replies go to</span><input type="email" name="reply_to" value="' + esc(c.reply_to || "") + '" placeholder="office@…" ' + (locked ? "disabled" : "") + ' /></label></div>' +
      '<div class="field-label">Message</div>' + (locked ? '<div class="editor" style="border-radius:9px">' + (c.html || esc(c.text)) + "</div>" :
        '<div class="toolbar"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button><button type="button" data-cmd="formatBlock" data-val="h2">Heading</button><button type="button" data-cmd="formatBlock" data-val="p">Text</button><button type="button" data-cmd="insertUnorderedList">• List</button><button type="button" data-cmd="link">Link</button><button type="button" data-cmd="removeFormat">Clear</button></div>' +
        '<div class="editor" id="editor" contenteditable="true" data-placeholder="Dear church family, …">' + (c.html || "") + "</div>") +
      '<p class="help">The church name, mailing address, and an unsubscribe link are added automatically at the bottom of every campaign.</p>' +
      '<div class="modal-foot" style="justify-content:flex-start">' + (locked ? '<button type="button" class="button" data-action="preview">Preview</button><button type="button" class="button ghost" data-action="duplicate">Duplicate as new draft</button>' :
        '<button type="submit" class="button">Save draft</button><button type="button" class="button" data-action="preview">Preview</button><button type="button" class="button" data-action="test">Send me a test</button><span class="grow"></span>' + (c.id ? '<button type="button" class="button ghost danger" data-action="delete-campaign">Delete</button>' : "") + '<button type="button" class="button primary" data-action="send-campaign">Send to everyone…</button>') +
      "</div></form></div>";
    showDemoBanner();
    const form = $("#campaign-form");
    form.onsubmit = async (e) => { e.preventDefault(); await saveCampaign(true); };
  }

  function campaignBody() {
    const form = $("#campaign-form"); const ed = $("#editor");
    return { name: form.name ? form.name.value : state.campaign.name, subject: form.subject ? form.subject.value : state.campaign.subject, reply_to: form.reply_to ? form.reply_to.value : state.campaign.reply_to, html: ed ? ed.innerHTML : state.campaign.html, text: ed ? ed.innerText : state.campaign.text };
  }
  async function saveCampaign(say) {
    const body = campaignBody();
    if (!body.subject.trim() && !body.html.trim()) { toast("Add a subject or a message first.", "bad"); return null; }
    try {
      const c = await api(state.campaign.id ? "campaigns/" + state.campaign.id : "campaigns", { method: state.campaign.id ? "PUT" : "POST", body });
      state.campaign = c; if (say) toast("Draft saved", "ok");
      $(".topbar h1").textContent = c.name;
      return c;
    } catch (e) { toast(e.message, "bad"); return null; }
  }
  async function previewCampaign() {
    const c = state.campaign.status === "draft" ? await saveCampaign(false) : state.campaign;
    if (!c) return;
    try {
      const r = await api("campaigns/" + c.id + "/preview");
      const card = modal('<h2>Preview</h2><iframe class="preview-frame" sandbox="allow-same-origin" title="Preview"></iframe><div class="modal-foot"><button class="button" data-x="close">Close</button></div>');
      $("iframe", card).srcdoc = r.html;
      card.onclick = (e) => { if (e.target.closest("[data-x]")) closeModal(); };
    } catch (e) { toast(e.message, "bad"); }
  }
  async function testCampaign() {
    const c = await saveCampaign(false); if (!c) return;
    const card = modal('<h2>Send a test</h2><p class="help">A copy goes to one address so you can see it in a real inbox before sending to everyone.</p><label class="field"><span>Send the test to</span><input type="email" id="test-to" value="' + esc(state.me.email) + '" /></label><div class="modal-foot"><button class="button ghost" data-x="cancel">Cancel</button><button class="button primary" data-x="go">Send test</button></div>');
    card.onclick = async (e) => {
      const x = e.target.closest("[data-x]"); if (!x) return;
      if (x.dataset.x === "cancel") return closeModal();
      busy(x, true);
      try { await api("campaigns/" + c.id + "/test", { method: "POST", body: { to: $("#test-to").value } }); closeModal(); toast("Test sent. Check that inbox.", "ok"); }
      catch (err) { toast(err.message, "bad"); busy(x, false); }
    };
  }
  async function sendCampaign() {
    const c = await saveCampaign(false); if (!c) return;
    const n = state.me.counts ? state.me.counts.synced : 0;
    const ok = await confirmBox("Send “" + c.subject + "” to " + n + " contacts now? This cannot be undone.", "Send now");
    if (!ok) return;
    try { const r = await api("campaigns/" + c.id + "/send", { method: "POST" }); state.campaign = r.campaign; toast("Sending to everyone. Resend delivers it over the next few minutes.", "ok"); setView("campaigns"); }
    catch (e) { toast(e.message, "bad"); }
  }

  /* ---------- contacts ---------- */
  async function renderContacts() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Contacts</h1><input class="search" type="search" id="cq" placeholder="Search name or email" value="' + esc(state.contactsQ) + '" /><span class="grow"></span><button class="button" data-action="add-contact">+ Add one</button><a class="button ghost" href="#import" data-view="import">Import CSV</a></div><div class="pane" id="contacts-box">Loading…</div>';
    showDemoBanner();
    await refreshContacts();
  }
  async function refreshContacts() {
    const box = $("#contacts-box"); if (!box) return;
    try {
      const r = await api("contacts?page=" + state.contactsPage + "&q=" + encodeURIComponent(state.contactsQ));
      state.contacts = r.items; state.contactsTotal = r.total; state.counts = r.counts; state.demoMarketing = !!r.demo;
      const c = r.counts;
      box.innerHTML = '<div class="stats"><div class="stat"><b>' + c.total + '</b><span>contacts</span></div><div class="stat"><b>' + c.synced + '</b><span>ready to receive campaigns</span></div><div class="stat"><b>' + c.unsynced + '</b><span>waiting to sync</span></div></div>' +
        (c.unsynced ? '<div class="card" style="margin-bottom:16px"><div class="row between"><div><h2>' + c.unsynced + ' contacts are not synced yet</h2><p class="help">Syncing sends them to Resend so campaigns can reach them. It takes a few seconds per batch.</p></div><button class="button primary" data-action="sync">Sync now</button></div></div>' : "") +
        (r.items.length ? '<table class="table"><thead><tr><th>Name</th><th>Email</th><th>Tags</th><th>Status</th><th></th></tr></thead><tbody>' + r.items.map((x) => '<tr><td>' + esc((x.first_name + " " + x.last_name).trim() || "—") + "</td><td>" + esc(x.email) + "</td><td>" + esc(x.tags) + '</td><td><span class="pill ' + (x.resend_id ? "sent" : "draft") + '">' + (x.resend_id ? "synced" : "waiting") + '</span></td><td style="text-align:right"><button class="button small ghost danger" data-action="delete-contact" data-id="' + x.id + '">Remove</button></td></tr>').join("") + "</tbody></table>" : '<div class="empty"><h2>No contacts yet</h2>Import the ChurchTrac export, or add people one at a time.</div>') +
        '<div class="list-foot" style="border:0"><span>' + r.total + ' total</span><span><button class="button ghost small" data-action="cprev" ' + (state.contactsPage <= 1 ? "disabled" : "") + '>‹</button> ' + state.contactsPage + ' <button class="button ghost small" data-action="cnext" ' + (state.contactsPage * r.per >= r.total ? "disabled" : "") + '>›</button></span></div>';
    } catch (e) { box.innerHTML = '<div class="empty">' + esc(e.message) + "</div>"; }
  }
  async function syncContacts(btn) {
    busy(btn, true);
    let guard = 0;
    try {
      while (guard++ < 200) {
        const r = await api("contacts/sync", { method: "POST" });
        if (r.errors && r.errors.length) { toast(r.errors[0], "bad"); }
        if (btn) btn.textContent = r.remaining + " left…";
        if (!r.remaining || (r.errors && r.errors.length && !r.synced)) break;
      }
      toast("Sync finished", "ok");
    } catch (e) { toast(e.message, "bad"); }
    refreshContacts(); loadMe();
  }
  function addContact() {
    const card = modal('<h2>Add a contact</h2><form id="add-form"><div class="row"><label class="field grow"><span>First name</span><input type="text" name="first_name" /></label><label class="field grow"><span>Last name</span><input type="text" name="last_name" /></label></div><label class="field"><span>Email</span><input type="email" name="email" required /></label><label class="field"><span>Tags (optional)</span><input type="text" name="tags" placeholder="Members, Visitors…" /></label><div class="modal-foot"><button type="button" class="button ghost" data-x="cancel">Cancel</button><button type="submit" class="button primary">Add</button></div></form>');
    card.onclick = (e) => { if (e.target.closest('[data-x="cancel"]')) closeModal(); };
    $("#add-form", card).onsubmit = async (e) => {
      e.preventDefault();
      try { await api("contacts", { method: "POST", body: Object.fromEntries(new FormData(e.target).entries()) }); closeModal(); toast("Added", "ok"); refreshContacts(); }
      catch (err) { toast(err.message, "bad"); }
    };
  }

  /* ---------- import ---------- */
  function renderImport() {
    const v = $("#view");
    v.innerHTML = '<div class="topbar"><h1>Import from ChurchTrac</h1></div><div class="pane">' +
      '<div class="card"><div class="steps"><div class="step"><b>Step 1</b>In ChurchTrac, open People and export the list as a CSV file.</div><div class="step"><b>Step 2</b>Drop that file below, or open it and paste the text in.</div><div class="step"><b>Step 3</b>Review the counts, then go to Contacts and click Sync.</div></div></div>' +
      '<div class="card"><div class="drop" id="drop">Drop the CSV file here, or <label style="color:var(--rust);cursor:pointer;text-decoration:underline">choose a file<input type="file" id="file" accept=".csv,text/csv,text/tab-separated-values,.txt" hidden /></label></div>' +
      '<p class="help">Columns are matched by their names, so the export can have any columns in any order as long as one is called Email. First name, Last name (or Name), and Tags or Group are picked up when present. People already in the list are updated, not duplicated.</p>' +
      '<label class="field"><span>Or paste the CSV text</span><textarea id="csv" placeholder="First Name,Last Name,Email&#10;Maria,Alvarez,maria@example.org"></textarea></label>' +
      '<div class="row"><button class="button primary" data-action="import">Import contacts</button><span class="help" id="import-result"></span></div></div></div>';
    showDemoBanner();
    const drop = $("#drop"); const file = $("#file");
    ["dragenter", "dragover"].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add("over"); }));
    ["dragleave", "drop"].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove("over"); }));
    drop.addEventListener("drop", (e) => { const f = e.dataTransfer.files[0]; if (f) readFile(f); });
    file.addEventListener("change", () => { if (file.files[0]) readFile(file.files[0]); });
  }
  function readFile(f) {
    const r = new FileReader();
    r.onload = () => { $("#csv").value = r.result; $("#import-result").textContent = f.name + " loaded. Click Import contacts."; };
    r.readAsText(f);
  }
  async function runImport(btn) {
    const csv = $("#csv").value;
    if (!csv.trim()) return toast("Choose a file or paste the CSV text first.", "bad");
    busy(btn, true);
    try {
      const r = await api("contacts/import", { method: "POST", body: { csv } });
      $("#import-result").textContent = r.added + " added, " + r.updated + " updated, " + r.skipped + " skipped (no valid email).";
      toast("Imported. Now sync them under Contacts.", "ok"); loadMe();
    } catch (e) { toast(e.message, "bad"); }
    busy(btn, false);
  }

  /* ---------- settings ---------- */
  function renderSettings() {
    const v = $("#view"); const mb = state.me.mailbox;
    v.innerHTML = '<div class="topbar"><h1>Settings</h1></div><div class="pane">' +
      '<div class="card"><h2>Your mailbox</h2>' + (state.me.guest ? '<p>Signed in as <strong>' + esc(mb.email) + '</strong> with your mailbox password. This browser sign-in keeps your credentials in an encrypted cookie. Assistant connections below store separate encrypted credentials on the website.</p><div class="row"><a class="button ghost danger" href="' + esc(CFG.site.logout) + '">Sign out</a><a class="button ghost" href="' + esc(CFG.webmail) + '" target="_blank" rel="noopener">Open in Purelymail</a></div>' : mb.linked ? '<p>Connected as <strong>' + esc(mb.email) + '</strong>. Your inbox, sent mail, and replies all use this account.</p><div class="row"><button class="button ghost danger" data-action="unlink">Disconnect</button><a class="button ghost" href="' + esc(CFG.webmail) + '" target="_blank" rel="noopener">Open in Purelymail</a></div>' :
        '<p class="help">Enter the email address and password of your church mailbox. The password is stored encrypted and only used to talk to the mail server (' + esc(CFG.imap_host) + ').</p><form id="link-form"><div class="row"><label class="field grow"><span>Email address</span><input type="email" name="email" value="' + esc(state.me.email) + '" required /></label><label class="field grow"><span>Password</span><input type="password" name="password" required autocomplete="current-password" /></label></div><div class="row"><button type="submit" class="button primary">Connect mailbox</button><a class="button ghost" href="' + esc(CFG.webmail) + '" target="_blank" rel="noopener">Open Purelymail instead</a></div></form>') + "</div>" +
      (state.me.marketing.enabled ? '<div class="card"><h2>Marketing email</h2>' + (state.me.marketing.ready ? "<p>Campaigns send through Resend from <strong>" + esc(state.me.marketing.from) + "</strong>" + (state.me.marketing.reply_to ? ", replies go to <strong>" + esc(state.me.marketing.reply_to) + "</strong>" : "") + ".</p>" : '<p class="help">Not connected yet. ' + (state.me.admin ? 'Set it up under <a href="' + esc(CFG.site.settings) + '">Mail → Settings</a> in the dashboard: a Resend API key, an audience, and the sending address.' : "The website admin sets this up in the dashboard.") + "</p>") + "</div>" : '<div class="card"><h2>Newsletters and mass email</h2><p class="help">Those go out from ChurchTrac. This portal is for your own mailbox.</p></div>') +
      '<div class="card"><h2>Signed in as</h2><p>' + esc(state.me.name) + " · " + esc(state.me.email) + (state.me.guest ? " · mailbox sign-in" : "") + (state.me.admin ? ' · <a href="' + esc(CFG.site.settings) + '">Portal settings</a>' : "") + "</p></div></div>";
    const assistants = document.createElement("div");
    assistants.className = "card";
    assistants.innerHTML = '<h2>Connected assistants</h2><p class="help">An assistant connection stores encrypted mailbox credentials separately from your browser sign-in. Revoke it here to stop access.</p><div class="assistant-list">Loading connections…</div>';
    $(".pane", v).appendChild(assistants);
    const assistantList = $(".assistant-list", assistants);
    api("assistants").then((r) => {
      assistantList.innerHTML = r.items.length ? r.items.map((c) => '<div class="card"><strong>' + esc(c.name || "AI assistant") + '</strong><p>' + esc(c.mailbox_email) + '<br>Permissions: ' + esc(c.scope) + '<br>Connected: ' + esc(new Date(Number(c.created_at) * 1000).toLocaleString()) + '<br>Last used: ' + esc(new Date(Number(c.last_used_at) * 1000).toLocaleString()) + '</p><button class="button ghost danger" data-revoke="' + esc(c.grant_id) + '">Revoke</button></div>').join("") : '<p>No connected assistants.</p>';
      assistantList.querySelectorAll("[data-revoke]").forEach((button) => {
        button.onclick = async () => {
          if (!(await confirmBox("Revoke this assistant's access to your mailbox?", "Revoke"))) return;
          button.disabled = true;
          try { await api("assistants/" + button.dataset.revoke, { method: "DELETE" }); button.closest(".card").remove(); toast("Assistant access revoked", "ok"); }
          catch (error) { toast(error.message, "bad"); button.disabled = false; }
        };
      });
    }).catch((error) => { assistantList.textContent = error.message; });
    showDemoBanner();
    const form = $("#link-form");
    if (form) form.onsubmit = async (e) => {
      e.preventDefault(); const btn = $("button[type=submit]", form); busy(btn, true);
      try { await api("mailbox", { method: "POST", body: Object.fromEntries(new FormData(form).entries()) }); toast("Mailbox connected", "ok"); await loadMe(); await loadFolders(); state.folder = "INBOX"; setView("inbox"); }
      catch (err) { toast(err.message, "bad"); busy(btn, false); }
    };
  }

  function showDemoBanner() {
    if (state.view === "inbox" && !state.me.mailbox.linked) return banner('Preview with sample messages. <a href="#settings" data-view="settings">Connect your mailbox</a> to see real mail.');
    if (["campaigns", "campaign", "contacts", "import"].includes(state.view) && !state.me.marketing.ready) return banner("Preview: marketing email is not connected yet, so nothing here is really sent." + (state.me.admin ? ' <a href="' + esc(CFG.site.settings) + '">Set up Resend</a>.' : ""));
    banner("");
  }

  /* ---------- events ---------- */
  document.addEventListener("click", async (e) => {
    const nav = e.target.closest("a[data-view]");
    if (nav) {
      e.preventDefault();
      if (nav.dataset.folder) { state.folder = nav.dataset.folder; state.folderRole = nav.dataset.role; state.page = 1; state.q = ""; state.selected.clear(); state.message = null; }
      return setView(nav.dataset.view, nav.dataset.id);
    }
    const row = e.target.closest(".msg");
    if (row && !e.target.classList.contains("msg-check")) return openMessage(+row.dataset.uid);
    const btn = e.target.closest("[data-action],[data-cmd]");
    if (!btn) return;
    if (btn.dataset.cmd) {
      e.preventDefault();
      const ed = $("#editor"); if (!ed) return; ed.focus();
      if (btn.dataset.cmd === "link") { const url = prompt("Link address (https://…)"); if (url) document.execCommand("createLink", false, url); return; }
      document.execCommand(btn.dataset.cmd, false, btn.dataset.val || null); return;
    }
    const a = btn.dataset.action;
    const uid = state.message ? state.message.uid : 0;
    if (a === "menu") { const app = $("#app"); app.dataset.menu = app.dataset.menu === "open" ? "" : "open"; }
    if (a === "compose") openCompose();
    if (a === "refresh") refreshList();
    if (a === "prev") { state.page--; refreshList(); }
    if (a === "next") { state.page++; refreshList(); }
    if (a === "back") { const mail = $("#mail"); if (mail) mail.dataset.open = ""; }
    if (a === "reply" && state.message) openCompose(replyPreset(state.message, false));
    if (a === "reply-all" && state.message) openCompose(replyPreset(state.message, true));
    if (a === "forward" && state.message) openCompose(forwardPreset(state.message));
    if (a === "move" && uid) moveMessages([uid], btn.dataset.to);
    if (a === "flag" && uid) { const on = !flaggedNow(uid); await flagMessages([uid], "flagged", on); const m = state.messages.find((x) => x.uid === uid); if (m) m.flagged = on; btn.textContent = on ? "★ Unflag" : "☆ Flag"; refreshList(); }
    if (a === "unread" && uid) { await flagMessages([uid], "seen", false); const r = $('.msg[data-uid="' + uid + '"]'); if (r) r.classList.add("unseen"); toast("Marked unread"); loadFolders(); }
    if (a === "demo-att") { e.preventDefault(); toast("Attachments open once a real mailbox is connected."); }
    if (a === "bulk-read") { const ids = [...state.selected]; await flagMessages(ids, "seen", true); state.selected.clear(); refreshList(); loadFolders(); }
    if (a === "bulk-move") moveMessages([...state.selected], btn.dataset.to);
    if (a === "new-campaign") setView("campaign");
    if (a === "preview") previewCampaign();
    if (a === "test") testCampaign();
    if (a === "send-campaign") sendCampaign();
    if (a === "duplicate") { const c = state.campaign; state.campaign = { id: 0, name: c.name + " (copy)", subject: c.subject, from_email: c.from_email, from_name: c.from_name, reply_to: c.reply_to, html: c.html, text: c.text, status: "draft" }; const saved = await saveCampaign(false); if (saved) setView("campaign", saved.id); }
    if (a === "delete-campaign") { if (await confirmBox("Delete this draft?", "Delete")) { try { await api("campaigns/" + state.campaign.id, { method: "DELETE" }); toast("Deleted"); setView("campaigns"); } catch (err) { toast(err.message, "bad"); } } }
    if (a === "add-contact") addContact();
    if (a === "delete-contact") { if (await confirmBox("Remove this contact? They will stop receiving campaigns.", "Remove")) { try { await api("contacts/" + btn.dataset.id, { method: "DELETE" }); refreshContacts(); loadMe(); } catch (err) { toast(err.message, "bad"); } } }
    if (a === "sync") syncContacts(btn);
    if (a === "cprev") { state.contactsPage--; refreshContacts(); }
    if (a === "cnext") { state.contactsPage++; refreshContacts(); }
    if (a === "import") runImport(btn);
    if (a === "unlink") { if (await confirmBox("Disconnect your mailbox from the portal?", "Disconnect")) { await api("mailbox", { method: "DELETE" }); await loadMe(); setView("settings"); } }
  });
  document.addEventListener("change", (e) => {
    if (e.target.classList.contains("msg-check")) { const id = +e.target.dataset.uid; e.target.checked ? state.selected.add(id) : state.selected.delete(id); renderBulk(); }
  });
  let qTimer = null;
  document.addEventListener("input", (e) => {
    if (e.target.id === "q") { clearTimeout(qTimer); qTimer = setTimeout(() => { state.q = e.target.value; state.page = 1; refreshList(); }, 350); }
    if (e.target.id === "cq") { clearTimeout(qTimer); qTimer = setTimeout(() => { state.contactsQ = e.target.value; state.contactsPage = 1; refreshContacts(); }, 350); }
  });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape" && !$("#modal").hidden) closeModal(); });
  $("#modal").addEventListener("click", (e) => { if (e.target.id === "modal") closeModal(); });

  /* ---------- boot ---------- */
  async function loadMe() { state.me = await api("me"); $("#side-user").textContent = state.me.name; $("#marketing-nav").hidden = !state.me.marketing.enabled; }
  (async function boot() {
    try {
      await loadMe();
      await loadFolders();
      const hash = (location.hash || "#inbox").slice(1);
      setView(["inbox", "campaigns", "contacts", "import", "settings"].includes(hash) ? hash : "inbox");
    } catch (e) {
      $("#view").innerHTML = '<div class="empty"><h2>The mail portal could not start</h2>' + esc(e.message) + "</div>";
    }
  })();
})();
