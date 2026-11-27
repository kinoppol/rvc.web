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

  /* ---------- ดึงข้อมูลจากเว็บเดิม / ลิงก์ ---------- */
  function api(params) {
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf);
    Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
    return fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (HTTP ' + r.status + ')' }; }); })
      .then(function (d) { if (!d.ok) throw new Error(d.error || 'เกิดข้อผิดพลาด'); return d; });
  }
  var legacy = null, lgPage = 1, lgLoaded = false;
  var lgSrc = h('select', { id: 'lg-src', 'aria-label': 'ชนิดข้อมูลในเว็บเดิม' });
  var lgQ = h('input', { type: 'search', id: 'lg-q', placeholder: 'ค้นหาหัวข้อ', 'aria-label': 'ค้นหาในเว็บเดิม' });
  var lgUrl = h('input', { type: 'url', id: 'lg-url', placeholder: (cfg.legacy || 'https://') + '/…', 'aria-label': 'ลิงก์หน้าเว็บ', hidden: '' });
  var lgGo = h('button', { type: 'button', class: 'btn ghost sm', text: 'ค้นหา', on: { click: function () { lgPage = 1; lgFind(); } } });
  var lgList = h('div', { class: 'lg-list' });
  var lgPager = h('div', { class: 'lg-pager' });
  var lgMsg = h('div', { class: 'ai-status' });
  var lgCard = h('div', { class: 'lg-card', hidden: '' });
  var lgBox = h('details', { class: 'lg-box' }, [
    h('summary', { text: '📥 ดึงข้อมูลจากเว็บไซต์เดิม / ลิงก์' }),
    h('div', { class: 'lg-row' }, [lgSrc]),
    h('div', { class: 'lg-row' }, [lgQ, lgUrl, lgGo]),
    lgMsg, lgList, lgPager
  ]);
  lgQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lgPage = 1; lgFind(); } });
  lgUrl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lgFind(); } });
  lgBox.addEventListener('toggle', function () {
    if (!lgBox.open || lgLoaded) return;
    lgLoaded = true; lgMsg.className = 'ai-status'; lgMsg.textContent = 'กำลังโหลดรายการจากเว็บเดิม…';
    api({ action: 'legacy_sources' }).then(function (d) {
      Object.keys(d.sources).forEach(function (k) { lgSrc.appendChild(h('option', { value: k, text: d.sources[k] })); });
      if (d.pages.length) {
        var g = h('optgroup', { label: 'หน้าเนื้อหาเว็บเดิม' });
        d.pages.forEach(function (p) { g.appendChild(h('option', { value: 'page:' + p.path, text: p.label === p.path ? p.path : p.label + '  (' + p.path + ')' })); });
        lgSrc.appendChild(g);
      }
      lgSrc.appendChild(h('option', { value: 'url', text: '🔗 วางลิงก์หน้าเว็บ (เว็บเดิมหรือเว็บอื่น)' }));
      if (cfg.kind === 'page') { var first = lgSrc.querySelector('optgroup option'); lgSrc.value = first ? first.value : 'url'; }
      lgSrc.dispatchEvent(new Event('change'));
    }).catch(function (e) { lgLoaded = false; lgMsg.className = 'ai-status err'; lgMsg.textContent = e.message; });
  });
  lgSrc.addEventListener('change', function () {
    var v = lgSrc.value, isUrl = v === 'url', isPage = v.indexOf('page:') === 0;
    lgUrl.hidden = !isUrl; lgQ.hidden = isUrl || isPage;
    lgGo.textContent = isUrl ? 'ดึงข้อมูล' : isPage ? 'ดึงหน้านี้' : 'ค้นหา';
    lgList.innerHTML = ''; lgPager.innerHTML = ''; lgMsg.textContent = '';
    if (!isUrl && !isPage) { lgPage = 1; lgFind(); }
  });
  function lgFind() {
    var v = lgSrc.value;
    if (v === 'url') { if (lgUrl.value.trim()) lgLoad({ action: 'fetch_url', url: lgUrl.value.trim() }); else lgUrl.focus(); return; }
    if (v.indexOf('page:') === 0) { lgLoad({ action: 'legacy_item', source: 'page', path: v.slice(5) }); return; }
    lgMsg.className = 'ai-status'; lgMsg.textContent = 'กำลังค้นหา…';
    api({ action: 'legacy_list', source: v, q: lgQ.value, page: lgPage }).then(function (d) {
      lgMsg.textContent = d.total ? 'พบ ' + d.total + ' รายการ — เลือกรายการที่ต้องการ' : 'ไม่พบรายการ';
      lgList.innerHTML = '';
      d.items.forEach(function (it) {
        lgList.appendChild(h('button', { type: 'button', class: 'lg-item' + (it.imported ? ' done' : ''), on: { click: function () { lgLoad({ action: 'legacy_item', source: v, id: it.id }); } } }, [
          h('span', { text: it.title || '(ไม่มีหัวข้อ)' }),
          h('small', { text: it.date + (it.image ? ' · มีรูป' : '') + (it.imported ? ' · นำเข้าแล้ว' : '') })
        ]));
      });
      lgPager.innerHTML = '';
      if (d.pages > 1) {
        if (d.page > 1) lgPager.appendChild(h('button', { type: 'button', class: 'btn ghost sm', text: '‹ ก่อนหน้า', on: { click: function () { lgPage = d.page - 1; lgFind(); } } }));
        lgPager.appendChild(h('span', { class: 'small mute', text: 'หน้า ' + d.page + ' / ' + d.pages }));
        if (d.page < d.pages) lgPager.appendChild(h('button', { type: 'button', class: 'btn ghost sm', text: 'ถัดไป ›', on: { click: function () { lgPage = d.page + 1; lgFind(); } } }));
      }
    }).catch(function (e) { lgMsg.className = 'ai-status err'; lgMsg.textContent = e.message; });
  }
  function lgLoad(params) {
    lgMsg.className = 'ai-status'; lgMsg.textContent = 'กำลังดึงข้อมูลและทำสำเนาไฟล์แนบ…';
    api(params).then(function (d) {
      legacy = d; lgMsg.textContent = ''; lgBox.open = false;
      renderCard();
      if (cfg.tasks['import']) pick('import'); else updSrc();
    }).catch(function (e) { lgMsg.className = 'ai-status err'; lgMsg.textContent = e.message; });
  }
  function renderCard() {
    lgCard.innerHTML = '';
    if (!legacy) { lgCard.hidden = true; updSrc(); return; }
    lgCard.hidden = false;
    var pre = h('textarea', { class: 'lg-pre', 'aria-label': 'เนื้อหาที่ดึงมา (แก้ไขได้)' });
    pre.value = legacy.text;
    pre.addEventListener('input', function () { legacy.text = pre.value; updSrc(); });
    lgCard.appendChild(h('div', { class: 'lg-head' }, [
      h('b', { text: 'ข้อมูลที่ดึงมา: ' + (legacy.title || '(ไม่มีหัวข้อ)') }),
      h('button', { type: 'button', class: 'ai-x dark', 'aria-label': 'เลิกใช้ข้อมูลนี้', text: '✕', on: { click: function () { legacy = null; renderCard(); } } })
    ]));
    if (legacy.url) lgCard.appendChild(h('div', { class: 'small mute', text: 'ต้นฉบับ: ' + legacy.url }));
    lgCard.appendChild(pre);
    (legacy.warnings || []).forEach(function (w) { lgCard.appendChild(h('div', { class: 'ai-status err', text: w })); });
    lgCard.appendChild(h('div', { class: 'ai-acts' }, [
      h('button', { type: 'button', class: 'btn sm ghost', text: 'ใส่ตามต้นฉบับ (ไม่ผ่าน AI)', on: { click: function () { applyLegacy(legacy.text); } } })
    ]));
    updSrc();
  }
  function ensureHidden(name, value) {
    var form = (F.body || F.title).form, el = form.querySelector('input[type=hidden][name="' + name + '"]');
    if (!el) { el = h('input', { type: 'hidden', name: name }); form.appendChild(el); }
    el.value = value;
  }
  function setCover() {
    if (!legacy || !legacy.image || !cfg.cover) return;
    flashMsg('กำลังทำสำเนาภาพหน้าปกจากเว็บเดิม…');
    api({ action: 'legacy_cover', url: legacy.image }).then(function (d) {
      ensureHidden('cover_legacy', d.path);
      var img = document.getElementById('cv');
      if (img) { img.src = d.url; img.hidden = false; }
      flashMsg('ใส่เนื้อหาและภาพหน้าปกจากเว็บเดิมแล้ว — ตรวจทานแล้วกดบันทึก');
    }).catch(function (e) { status.className = 'ai-status err'; status.textContent = 'ภาพหน้าปก: ' + e.message; });
  }
  /** ใส่ข้อมูลเว็บเดิมลงฟอร์ม: เนื้อหา + หัวข้อ (ถ้าว่าง) + หมวด + ภาพหน้าปก + อ้างอิงต้นฉบับ */
  function applyLegacy(body) {
    if (F.body) {
      if (F.body.value.trim() && !window.confirm('แทนที่เนื้อหาเดิมทั้งหมดด้วยข้อมูลนี้?')) return;
      var max = F.body.maxLength > 0 ? F.body.maxLength : 0;
      setField(F.body, max ? oneLine(body.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')) : body, max);
    }
    if (F.title && !F.title.value.trim() && legacy.title) setField(F.title, legacy.title, 255);
    var cat = document.querySelector('select[name=category]');
    if (cat && legacy.category && cat.querySelector('option[value="' + legacy.category + '"]')) cat.value = legacy.category;
    if (legacy.ref) ensureHidden('source_ref', legacy.ref);
    flashMsg('ใส่ข้อมูลจากเว็บเดิมแล้ว — ตรวจทานก่อนบันทึก');
    setCover();
  }

  var panel = h('aside', { class: 'ai-panel', id: 'ai-panel', role: 'dialog', 'aria-label': 'ผู้ช่วย AI', 'aria-modal': 'false', hidden: '' }, [
    h('div', { class: 'ai-head' }, [
      h('b', { text: '✦ ผู้ช่วย AI' }),
      h('button', { type: 'button', class: 'ai-x', 'aria-label': 'ปิดผู้ช่วย', text: '✕', on: { click: close } })
    ]),
    h('div', { class: 'ai-body' }, [
      h('label', { class: 'f', for: 'ai-model', text: 'โมเดล' }), sel,
      lgBox, lgCard,
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
    if (legacy) { srcNote.textContent = 'จะใช้ข้อมูลที่ดึงมา (' + legacy.text.length + ' ตัวอักษร)'; return; }
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
    var s = legacy ? null : selection();
    range = s ? { s: s.s, e: s.e } : null;
    var text = legacy ? legacy.text : s ? s.text : (F.body ? F.body.value : '');
    var ins = instr.value;
    if (task === 'draft' && !text.trim()) { text = ins; ins = ''; }
    var fd = new FormData();
    fd.append('_csrf', cfg.csrf); fd.append('task', task); fd.append('model', sel.value); fd.append('kind', cfg.kind);
    fd.append('text', text); fd.append('instruction', ins);
    fd.append('title', legacy && legacy.title ? legacy.title : (F.title ? F.title.value : ''));
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
    if (legacy && task !== 'titles' && task !== 'excerpt') {
      action('ใส่ในฟอร์ม' + (legacy.image && cfg.cover ? ' (พร้อมภาพหน้าปก)' : ''), function () { applyLegacy(out.value); }, true);
    } else if (body && task !== 'titles' && task !== 'excerpt') {
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
