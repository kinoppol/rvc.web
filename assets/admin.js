// ระบบจัดการ — ส่วนเสริมเล็กน้อย (ทำงานได้แม้ปิด JavaScript)
(function () {
  'use strict';

  // ยืนยันก่อนส่งฟอร์มที่มี data-confirm
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
  // ยืนยันก่อนกดปุ่มที่มี data-confirm-click
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest && ev.target.closest('[data-confirm-click]');
    if (b && !window.confirm(b.getAttribute('data-confirm-click'))) ev.preventDefault();
  });

  // แสดงตัวอย่างรูปก่อนอัปโหลด: <input type=file data-preview="#id">
  document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var target = document.querySelector(inp.getAttribute('data-preview'));
      if (!target || !inp.files || !inp.files[0]) return;
      var url = URL.createObjectURL(inp.files[0]);
      if (target.tagName === 'IMG') { target.src = url; target.hidden = false; }
      else { target.style.backgroundImage = 'url(' + url + ')'; target.textContent = ''; }
    });
  });

  // แถบเครื่องมือข้อความ: แทรกรูปแบบลงใน textarea
  document.querySelectorAll('[data-insert]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ta = document.querySelector(btn.getAttribute('data-target'));
      if (!ta) return;
      var tpl = btn.getAttribute('data-insert');
      var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e) || btn.getAttribute('data-sample') || '';
      var text = tpl.replace('{}', sel);
      ta.setRangeText(text, s, e, 'end');
      ta.focus();
    });
  });

  // ตั้งช่วงเวลาเร็ว: <button data-range="7" data-start="#s" data-end="#e">
  function iso(d) { return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
  document.querySelectorAll('[data-range]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var days = parseInt(btn.getAttribute('data-range'), 10);
      var s = new Date(); s.setHours(0, 0, 0, 0);
      var e = new Date(s); e.setDate(e.getDate() + days - 1); e.setHours(23, 59, 0, 0);
      var si = document.querySelector(btn.getAttribute('data-start'));
      var ei = document.querySelector(btn.getAttribute('data-end'));
      si.value = iso(s); ei.value = iso(e);
      si.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });

  // ตัวอย่างป๊อปอัพแบบสด
  var form = document.getElementById('popup-form');
  if (form) {
    var $ = function (s) { return form.querySelector(s); };
    var months = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    var fmt = function (v) {
      var d = new Date(v); if (!v || isNaN(d)) return '—';
      return d.getDate() + ' ' + months[d.getMonth()] + ' ' + (d.getFullYear() + 543) + ' เวลา ' +
        String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0') + ' น.';
    };
    var upd = function () {
      var t = $('#pt').value || '[หัวข้อประกาศ]';
      document.getElementById('pv-t').textContent = t;
      document.getElementById('pv-b').textContent = $('#pb').value;
      var a = new Date($('#sd').value), b = new Date($('#ed').value), now = new Date();
      var st = ['ตั้งเวลาไว้', '#fcefd9', '#9a5800'];
      if (!$('#en').checked) st = ['ปิดใช้งาน', '#e9eef6', '#4a5a74'];
      else if (b < a) st = ['เวลาไม่ถูกต้อง', '#fbe5e3', '#b42318'];
      else if (now > b) st = ['หมดเวลาแสดง', '#fbe5e3', '#b42318'];
      else if (now >= a) st = ['กำลังแสดง', '#e2f3e8', '#1d7a3e'];
      var pill = document.getElementById('pv-st');
      pill.textContent = st[0]; pill.style.background = st[1]; pill.style.color = st[2];
      document.getElementById('pv-sum').textContent = b < a
        ? 'เวลาหยุดแสดงต้องอยู่หลังเวลาเริ่มแสดง กรุณาตรวจสอบอีกครั้ง'
        : 'จะแสดงตั้งแต่ ' + fmt($('#sd').value) + ' ถึง ' + fmt($('#ed').value) + ' ผู้เข้าชมแต่ละคนเห็นวันละ 1 ครั้ง';
    };
    form.addEventListener('input', upd);
    form.addEventListener('change', upd);
    upd();
  }
})();
