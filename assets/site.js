// เว็บไซต์สาธารณะ — เมนู ขนาดตัวอักษร และป๊อปอัพ (เว็บใช้งานได้แม้ปิด JavaScript)
(function () {
  'use strict';
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* ไม่มี storage */ } }
  };

  // เมนูหน่วยงาน (mega menu)
  var megaBtn = document.querySelector('.mega-btn'), mega = document.getElementById('mega');
  if (megaBtn && mega) {
    var setMega = function (open) { mega.hidden = !open; megaBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    megaBtn.addEventListener('click', function (e) { e.stopPropagation(); setMega(mega.hidden); });
    document.addEventListener('click', function (e) { if (!mega.hidden && !mega.contains(e.target)) setMega(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMega(false); });
  }

  // เมนูมือถือ
  var menuBtn = document.querySelector('.menu-btn'), drawer = document.getElementById('drawer');
  if (menuBtn && drawer) {
    var setDrawer = function (open) { drawer.hidden = !open; menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); document.body.style.overflow = open ? 'hidden' : ''; };
    menuBtn.addEventListener('click', function () { setDrawer(true); });
    drawer.addEventListener('click', function (e) { if (e.target === drawer) setDrawer(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) setDrawer(false); });
  }

  // ขนาดตัวอักษร ก- ก ก+
  var sizes = ['90%', '100%', '112.5%', '125%'], idx = parseInt(store.get('fs') || '1', 10);
  var applyFs = function () {
    idx = Math.max(0, Math.min(sizes.length - 1, isNaN(idx) ? 1 : idx));
    document.documentElement.style.setProperty('--fs', sizes[idx]);
    document.querySelectorAll('[data-fs]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-fs') === '0' && idx === 1); });
  };
  document.querySelectorAll('[data-fs]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = parseInt(b.getAttribute('data-fs'), 10);
      idx = d === 0 ? 1 : idx + d; applyFs(); store.set('fs', String(idx));
    });
  });
  applyFs();

  // ป๊อปอัพ: แสดงวันละ 1 ครั้งต่อผู้เข้าชม
  var pop = document.getElementById('popup');
  if (pop) {
    var today = new Date().toISOString().slice(0, 10), key = pop.getAttribute('data-key');
    if (store.get(key) !== today) {
      var last = document.activeElement;
      var close = function () { pop.hidden = true; store.set(key, today); document.removeEventListener('keydown', onKey); if (last && last.focus) last.focus(); };
      var onKey = function (e) { if (e.key === 'Escape') close(); };
      pop.hidden = false;
      store.set(key, today);
      pop.querySelector('[data-close]').focus();
      pop.addEventListener('click', function (e) { if (e.target === pop || e.target.hasAttribute('data-close')) close(); });
      document.addEventListener('keydown', onKey);
    }
  }
})();
