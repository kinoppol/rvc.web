// ผู้ช่วย AI สั่งงาน — ปุ่มลอยมุมขวาล่างในระบบจัดการ
// บทสนทนาเก็บใน sessionStorage ของแท็บนี้ (เปลี่ยนหน้าแล้วยังคุยต่อได้) · การแก้ไขข้อมูลต้องกดยืนยันทุกครั้ง
(function () {
  'use strict';
  var box = document.getElementById('agent'), fab = document.getElementById('agent-fab');
  if (!box || !fab) return;
  var cfg = JSON.parse(box.getAttribute('data-cfg'));
  var KEY = 'rvc-agent-' + cfg.user, OPEN = 'rvc-agent-open';

  function store(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); if (v === null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, v); } catch (e) { return null; } }
  function load() { try { return JSON.parse(store(KEY) || '[]'); } catch (e) { return []; } }
  var log = load();          // [{role:'user'|'assistant'|'note', content, proposals?, links?, done?}]
  function save() { store(KEY, JSON.stringify(log.slice(-40))); }

  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function svg(path) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' + path + '"/></svg>'; }

  /* ---------- โครงหน้าต่าง ---------- */
  box.innerHTML = '';
  var head = el('div', 'agent-h');
  head.appendChild(el('b', 'hd', 'ผู้ช่วย AI'));
  var sel = el('select', 'agent-model'); sel.setAttribute('aria-label', 'โมเดล');
  (cfg.models || []).forEach(function (m) { var o = el('option', null, m.name); o.value = m.id; if (m.default) o.selected = true; sel.appendChild(o); });
  var savedModel = store('rvc-agent-model'); if (savedModel && sel.querySelector('option[value="' + savedModel + '"]')) sel.value = savedModel;
  sel.addEventListener('change', function () { store('rvc-agent-model', sel.value); });
  var clr = el('button', 'agent-ic'); clr.type = 'button'; clr.title = 'เริ่มบทสนทนาใหม่'; clr.innerHTML = svg('M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14');
  var cls = el('button', 'agent-ic'); cls.type = 'button'; cls.title = 'ปิด'; cls.innerHTML = svg('M6 6l12 12M18 6L6 18');
  if (cfg.models && cfg.models.length > 1) head.appendChild(sel);
  head.appendChild(clr); head.appendChild(cls);
  var list = el('div', 'agent-list'); list.setAttribute('aria-live', 'polite');
  var form = el('form', 'agent-f');
  var ta = el('textarea'); ta.rows = 2; ta.placeholder = 'พิมพ์คำสั่ง เช่น "ร่างข่าวกิจกรรมวันไหว้ครู แล้วบันทึกเป็นฉบับร่าง"'; ta.setAttribute('aria-label', 'ข้อความถึงผู้ช่วย');
  var send = el('button', 'btn sm'); send.type = 'submit'; send.innerHTML = svg('M4 12l16-8-6 16-2-7z') + '<span class="sr">ส่ง</span>';
  form.appendChild(ta); form.appendChild(send);
  box.appendChild(head); box.appendChild(list);
  if (cfg.setup) {
    var w = el('div', 'agent-empty');
    w.appendChild(el('p', null, 'ยังไม่ได้เปิดใช้งานโมเดล AI — เชื่อมต่อผู้ให้บริการและเลือกโมเดลก่อน แล้วจึงสนทนาสั่งงานได้'));
    var a = el('a', 'btn sm', 'ตั้งค่าผู้ช่วย AI'); a.href = cfg.setup; w.appendChild(a);
    list.appendChild(w);
  } else {
    box.appendChild(form);
  }
  var foot = el('div', 'agent-note', 'ผู้ช่วยอาจผิดพลาดได้ · การแก้ไขข้อมูลทุกครั้งต้องกดยืนยันก่อน');
  if (!cfg.setup) box.appendChild(foot);

  /* ---------- แสดงข้อความ ---------- */
  function scroll() { list.scrollTop = list.scrollHeight; }

  function renderProposal(p, msg, idx) {
    var c = el('div', 'agent-card' + (p.danger ? ' danger' : ''));
    c.appendChild(el('div', 'hd t', p.label));
    var dl = el('dl');
    [].concat(Object.values(p.lines || {})).forEach(function (l) { dl.appendChild(el('dt', null, l[0])); dl.appendChild(el('dd', null, l[1])); });
    c.appendChild(dl);
    var act = el('div', 'a');
    if (p.state === 'done' || p.state === 'cancel' || p.state === 'error') {
      act.appendChild(el('span', 'st ' + p.state, p.state === 'done' ? '✓ ' + (p.result || 'ดำเนินการแล้ว') : p.state === 'cancel' ? 'ยกเลิกแล้ว' : '✕ ' + (p.result || 'ไม่สำเร็จ')));
      if (p.link) { var lk = el('a', 'btn sm ghost', p.link.label); lk.href = p.link.url; act.appendChild(lk); }
    } else {
      var ok = el('button', 'btn sm' + (p.danger ? ' danger' : ''), p.danger ? 'ยืนยันลบ' : 'ยืนยัน'); ok.type = 'button';
      var no = el('button', 'btn sm ghost', 'ยกเลิก'); no.type = 'button';
      ok.addEventListener('click', function () { confirmProposal(p, ok, no); });
      no.addEventListener('click', function () { p.state = 'cancel'; save(); render(); });
      act.appendChild(ok); act.appendChild(no);
    }
    c.appendChild(act);
    return c;
  }

  function render() {
    if (cfg.setup) return;
    list.innerHTML = '';
    if (!log.length) {
      var w = el('div', 'agent-empty');
      w.appendChild(el('p', null, 'สวัสดีครับ พิมพ์สิ่งที่ต้องการให้ช่วยได้เลย เช่น ค้นหาข่าว ร่างข่าว อนุมัติข่าว สร้างหรือปิดป๊อปอัพ แก้ข้อมูลเว็บไซต์'));
      var chips = el('div', 'agent-chips');
      (cfg.hints || []).forEach(function (h) {
        var b = el('button', 'chip', h); b.type = 'button';
        b.addEventListener('click', function () { ta.value = h; ta.focus(); });
        chips.appendChild(b);
      });
      w.appendChild(chips); list.appendChild(w);
      return;
    }
    log.forEach(function (m, i) {
      if (m.role === 'note') { list.appendChild(el('div', 'agent-msg note', m.content)); return; }
      var b = el('div', 'agent-msg ' + (m.role === 'user' ? 'me' : 'ai'));
      if (m.content) b.appendChild(el('div', 'tx', m.content));
      (m.proposals || []).forEach(function (p) { b.appendChild(renderProposal(p, m, i)); });
      if (m.links && m.links.length) {
        var ls = el('div', 'agent-links');
        m.links.forEach(function (l) { var a = el('a', 'btn sm ghost', l.label + ' →'); a.href = l.url; ls.appendChild(a); });
        b.appendChild(ls);
      }
      list.appendChild(b);
    });
    scroll();
  }

  /* ---------- ส่งคำขอ ---------- */
  function post(data) {
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + r.status + ')'); }); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || 'เกิดข้อผิดพลาด'); return j; });
  }

  // ส่งให้โมเดลเฉพาะข้อความผู้ใช้/ผู้ช่วย พร้อมบอกผลการยืนยันที่ผ่านมา
  function history() {
    var out = [];
    log.forEach(function (m) {
      if (m.role === 'note') { out.push({ role: 'user', content: '[บันทึกระบบ] ' + m.content }); return; }
      var t = m.content || '';
      if (m.proposals && m.proposals.length) {
        t += '\n[ข้อเสนอที่ส่งให้ผู้ใช้: ' + m.proposals.map(function (p) {
          return p.label + ' → ' + (p.state === 'done' ? 'ยืนยันแล้ว: ' + (p.result || '') : p.state === 'cancel' ? 'ผู้ใช้ยกเลิก' : p.state === 'error' ? 'ล้มเหลว: ' + (p.result || '') : 'ยังไม่ยืนยัน');
        }).join(' | ') + ']';
      }
      if (t) out.push({ role: m.role === 'user' ? 'user' : 'assistant', content: t });
    });
    return out;
  }

  var busy = false;
  function setBusy(b) {
    busy = b; send.disabled = b; ta.disabled = b;
    var t = list.querySelector('.agent-typing');
    if (b && !t) { t = el('div', 'agent-msg ai agent-typing'); t.innerHTML = '<span></span><span></span><span></span>'; t.setAttribute('aria-label', 'ผู้ช่วยกำลังพิมพ์'); list.appendChild(t); scroll(); }
    if (!b && t) t.remove();
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var text = ta.value.trim();
    if (!text || busy) return;
    log.push({ role: 'user', content: text }); save(); render();
    ta.value = '';
    setBusy(true);
    post({ action: 'chat', model: sel.value, messages: JSON.stringify(history()) })
      .then(function (j) {
        log.push({ role: 'assistant', content: j.reply || '', proposals: j.proposals || [], links: j.links || [] });
      })
      .catch(function (e) { log.push({ role: 'note', content: 'เกิดข้อผิดพลาด: ' + e.message }); })
      .then(function () { save(); setBusy(false); render(); ta.focus(); });
  });
  ta.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); form.requestSubmit(); }
  });

  function confirmProposal(p, ok, no) {
    ok.disabled = no.disabled = true; ok.textContent = 'กำลังดำเนินการ…';
    post({ action: 'confirm', token: p.token, sig: p.sig })
      .then(function (j) { p.state = 'done'; p.result = j.message; p.link = j.link || null; })
      .catch(function (e) { p.state = 'error'; p.result = e.message; })
      .then(function () { save(); render(); });
  }

  /* ---------- เปิด/ปิด ---------- */
  function open(v) {
    box.hidden = !v; fab.setAttribute('aria-expanded', v ? 'true' : 'false');
    document.body.classList.toggle('agent-on', v);
    store(OPEN, v ? '1' : null);
    if (v) { render(); setTimeout(function () { (cfg.setup ? box : ta).focus && ta.focus(); }, 30); }
  }
  fab.addEventListener('click', function () { open(box.hidden); });
  cls.addEventListener('click', function () { open(false); fab.focus(); });
  clr.addEventListener('click', function () {
    if (log.length && !window.confirm('ล้างบทสนทนานี้และเริ่มใหม่?')) return;
    log = []; save(); render(); ta.focus();
  });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !box.hidden) { open(false); fab.focus(); } });
  if (store(OPEN) === '1') open(true);
})();
