// ผู้ช่วย AI — แผงด้านขวาในหน้าแก้ไขเนื้อหา (ข่าว / หน้าเว็บ / ป๊อปอัพ)
(function () {
  'use strict';
  var btn = document.querySelector('.ai-open');
  if (!btn) return;
  var cfg = JSON.parse(btn.getAttribute('data-ai'));
  var F = {};
  Object.keys(cfg.fields).forEach(function (k) { F[k] = document.querySelector(cfg.fields[k]); });
  var LS = 'rvcweb_ai_model';

  function h(tag, attrs, kids) {
    var el = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') el.textContent = attrs[k];
      else if (k === 'on') Object.keys(attrs.on).forEach(function (ev) { el.addEventListener(ev, attrs.on[ev]); });
      else el.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) el.appendChild(c); });
    return el;
  }

  /* ---------- โครงแผง ---------- */
  var sel = h('select', { id: 'ai-model' });
  var saved = localStorage.getItem(LS);
  cfg.models.forEach(function (m) {
    var o = h('option', { value: m.id, text: m.name + ' · ' + m.provider });
    if (saved ? String(m.id) === saved : m['default']) o.selected = true;
    sel.appendChild(o);
  });
  sel.addEventListener('change', function () { localStorage.setItem(LS, sel.value); });

  var task = Object.keys(cfg.tasks)[0];
  var chips = h('div', { class: 'ai-chips', role: 'radiogroup', 'aria-label': 'งานที่ต้องการ' });
  Object.keys(cfg.tasks).forEach(function (k) {
    var c = h('button', { type: 'button', class: 'chip', role: 'radio', 'aria-checked': 'false', 'data-task': k, text: cfg.tasks[k],
      on: { click: function () { pick(k); } } });
    chips.appendChild(c);
  });
  var instr = h('textarea', { id: 'ai-instr', rows: '3', placeholder: '' });
  var srcNote = h('div', { class: 'hint', id: 'ai-src' });
  var go = h('button', { type: 'button', class: 'btn block', text: 'ให้ผู้ช่วยทำงาน', on: { click: run } });
  var status = h('div', { class: 'ai-status', role: 'status', 'aria-live': 'polite' });
  var out = h('textarea', { id: 'ai-out', class: 'ai-out', 'aria-label': 'ผลลัพธ์จากผู้ช่วย (แก้ไขได้)' });
  var titles = h('div', { class: 'ai-titles' });
  var acts = h('div', { class: 'ai-acts' });
  var result = h('div', { class: 'ai-result', hidden: '' }, [
    h('div', { class: 'ai-lbl', text: 'ผลลัพธ์ (แก้ไขได้ก่อนนำไปใช้)' }), titles, out, acts,
    h('p', { class: 'hint', text: 'ตรวจทานข้อเท็จจริง ชื่อ ตัวเลข และวันที่ทุกครั้งก่อนเผยแพร่' })
  ]);

  var panel = h('aside', { class: 'ai-panel', id: 'ai-panel', role: 'dialog', 'aria-label': 'ผู้ช่วย AI', 'aria-modal': 'false', hidden: '' }, [
    h('div', { class: 'ai-head' }, [
      h('b', { text: '✦ ผู้ช่วย AI' }),
      h('button', { type: 'button', class: 'ai-x', 'aria-label': 'ปิดผู้ช่วย', text: '✕', on: { click: close } })
    ]),
    h('div', { class: 'ai-body' }, [
      h('label', { class: 'f', for: 'ai-model', text: 'โมเดล' }), sel,
      h('div', { class: 'f', text: 'ต้องการให้ช่วยอะไร' }), chips,
      h('label', { class: 'f', for: 'ai-instr', id: 'ai-instr-l', text: 'คำสั่งเพิ่มเติม' }), instr, srcNote,
      go, status, result
    ])
  ]);
  document.body.appendChild(panel);

  function open() { panel.hidden = false; document.body.classList.add('ai-on'); btn.setAttribute('aria-expanded', 'true'); updSrc(); instr.focus(); }
  function close() { panel.hidden = true; document.body.classList.remove('ai-on'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
  btn.addEventListener('click', function () { panel.hidden ? open() : close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });

  /* ---------- ข้อความต้นทาง: ใช้ข้อความที่เลือกในช่องเนื้อหาก่อน ---------- */
  var range = null;
  function selection() {
    var b = F.body;
    if (b && typeof b.selectionStart === 'number' && b.selectionEnd > b.selectionStart) {
      return { s: b.selectionStart, e: b.selectionEnd, text: b.value.slice(b.selectionStart, b.selectionEnd) };
    }
    return null;
  }
  function updSrc() {
    var s = selection(), len = F.body ? F.body.value.trim().length : 0;
    srcNote.textContent = task === 'free' && !len ? 'พิมพ์สิ่งที่ต้องการ เช่น "เขียนประกาศรับสมัครนักศึกษาใหม่ ปวช. ปีการศึกษา 2570"'
      : s ? 'จะใช้เฉพาะข้อความที่เลือกไว้ (' + s.text.length + ' ตัวอักษร)'
      : len ? 'จะใช้เนื้อหาทั้งหมดในช่องเนื้อหา (' + len + ' ตัวอักษร) · เลือกข้อความก่อนถ้าต้องการเฉพาะบางส่วน'
      : task === 'draft' ? 'พิมพ์ประเด็นสำคัญในช่องเนื้อหาหรือในช่องด้านบน เช่น งาน วันที่ สถานที่ ผู้เข้าร่วม' : 'ช่องเนื้อหายังว่าง';
  }
  if (F.body) ['select', 'keyup', 'mouseup', 'input'].forEach(function (ev) { F.body.addEventListener(ev, updSrc); });

  function pick(k) {
    task = k;
    chips.querySelectorAll('.chip').forEach(function (c) {
      var on = c.getAttribute('data-task') === k;
      c.classList.toggle('on', on); c.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    document.getElementById('ai-instr-l').textContent = k === 'free' ? 'คำสั่งถึงผู้ช่วย' : k === 'draft' ? 'ประเด็น/ข้อมูลเพิ่มเติม (ไม่บังคับ)' : 'คำสั่งเพิ่มเติม (ไม่บังคับ)';
    instr.placeholder = k === 'free' ? 'เช่น เขียนประกาศเชิญชวนร่วมงานวันวิชาการ' : k === 'draft' ? 'เช่น เน้นบรรยากาศ ความยาว 3 ย่อหน้า' : 'เช่น ใช้ภาษาเป็นกันเองขึ้น';
    updSrc();
  }
  pick(task);

  /* ---------- เรียก API ---------- */
  var busy = false;
  function run() {
    if (busy) return;
    var s = selection();
    range = s ? { s: s.s, e: s.e } : null;
    var text = s ? s.text : (F.body ? F.body.value : '');
    var ins = instr.value;
    if (task === 'draft' && !text.trim()) { text = ins; ins = ''; }
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf); fd.append('task', task); fd.append('model', sel.value); fd.append('kind', cfg.kind);
    fd.append('text', text); fd.append('instruction', ins);
    fd.append('title', F.title ? F.title.value : '');
    busy = true; go.disabled = true; go.textContent = 'กำลังทำงาน…';
    status.className = 'ai-status'; status.textContent = 'กำลังรอคำตอบจาก ' + sel.options[sel.selectedIndex].text.split(' · ')[0] + ' — อาจใช้เวลาสักครู่';
    var t0 = Date.now();
    fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + r.status + ')' }; }); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'เกิดข้อผิดพลาด');
        status.className = 'ai-status ok';
        status.textContent = 'เสร็จใน ' + ((Date.now() - t0) / 1000).toFixed(1) + ' วินาที · ' + d.model;
        show(d.text);
      })
      .catch(function (e) { status.className = 'ai-status err'; status.textContent = e.message || 'เชื่อมต่อไม่ได้'; })
      .then(function () { busy = false; go.disabled = false; go.textContent = 'ให้ผู้ช่วยทำงาน'; });
  }

  /* ---------- นำผลลัพธ์ไปใช้ ---------- */
  function fire(el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.focus(); }
  function setField(el, v, max) {
    if (!el) return;
    el.value = max ? v.slice(0, max) : v; fire(el);
  }
  function flashMsg(m) { status.className = 'ai-status ok'; status.textContent = m; }
  function action(label, fn, primary) {
    acts.appendChild(h('button', { type: 'button', class: 'btn sm' + (primary ? '' : ' ghost'), text: label, on: { click: fn } }));
  }
  function oneLine(v) { return v.replace(/\s+/g, ' ').trim(); }

  function show(text) {
    result.hidden = false; out.value = text; acts.innerHTML = ''; titles.innerHTML = '';
    if (task === 'titles' && F.title) {
      text.split('\n').map(function (l) { return l.replace(/^\s*(?:[-*•]|\d+[.)])\s*/, '').replace(/^["“]|["”]$/g, '').trim(); })
        .filter(Boolean).forEach(function (t) {
          titles.appendChild(h('button', { type: 'button', class: 'ai-title', text: t, on: { click: function () { setField(F.title, t, 255); flashMsg('ใส่หัวข้อแล้ว'); } } }));
        });
      out.hidden = !!titles.children.length;
    } else { out.hidden = false; }
    var body = F.body, max = body && body.maxLength > 0 ? body.maxLength : 0;
    if (body && task !== 'titles' && task !== 'excerpt') {
      if (range) {
        action('แทนที่ข้อความที่เลือก', function () {
          body.setRangeText(out.value, range.s, range.e, 'select'); range = { s: body.selectionStart, e: body.selectionEnd }; fire(body); flashMsg('แทนที่ข้อความที่เลือกแล้ว');
        }, true);
      } else {
        action(body.value.trim() ? 'แทนที่เนื้อหาทั้งหมด' : 'ใส่ในช่องเนื้อหา', function () {
          if (body.value.trim() && !window.confirm('แทนที่เนื้อหาเดิมทั้งหมดด้วยผลลัพธ์นี้?')) return;
          setField(body, max ? oneLine(out.value) : out.value, max); flashMsg('ใส่เนื้อหาแล้ว');
        }, true);
      }
      if (!max) action('แทรกที่เคอร์เซอร์', function () {
        var p = body.selectionEnd || body.value.length;
        body.setRangeText((p ? '\n\n' : '') + out.value, p, p, 'end'); fire(body); flashMsg('แทรกแล้ว');
      });
    }
    if (F.excerpt && (task === 'excerpt' || task === 'shorten')) {
      action('ใช้เป็นคำโปรย', function () { setField(F.excerpt, oneLine(out.value), 500); flashMsg('ใส่คำโปรยแล้ว'); }, task === 'excerpt');
    }
    if (F.title && task === 'titles' && !titles.children.length) {
      action('ใช้เป็นหัวข้อ', function () { setField(F.title, oneLine(out.value.split('\n')[0]), 255); flashMsg('ใส่หัวข้อแล้ว'); }, true);
    }
    action('คัดลอก', function () {
      (navigator.clipboard ? navigator.clipboard.writeText(out.value) : Promise.reject())
        .then(function () { flashMsg('คัดลอกแล้ว'); }, function () { out.hidden = false; out.select(); document.execCommand('copy'); flashMsg('คัดลอกแล้ว'); });
    });
    result.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
})();
