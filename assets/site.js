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
  /* ---------- Skeleton: แสดงแถบโหลดจนกว่ารูป/เอกสารจะพร้อม ---------- */
  function skDone(el) {
    if (!el || !el.hasAttribute('data-sk') || el.classList.contains('sk-done')) return;
    el.classList.add('sk-done');
    setTimeout(function () { el.removeAttribute('data-sk'); el.classList.remove('sk-done'); }, 450);
  }
  document.querySelectorAll('[data-sk]').forEach(function (el) {
    if (el.hasAttribute('data-pdf') || el.hasAttribute('data-pdf-view')) return;   // ส่วน PDF จัดการด้านล่าง
    var src = el.tagName === 'IMG' ? el.currentSrc || el.src : (/url\(["']?([^"')]+)["']?\)/.exec(el.style.backgroundImage) || [])[1];
    if (!src) { skDone(el); return; }
    if (el.tagName === 'IMG' && el.complete && el.naturalWidth) { skDone(el); return; }
    var im = el.tagName === 'IMG' ? el : new Image();
    im.addEventListener('load', function () { skDone(el); });
    im.addEventListener('error', function () { skDone(el); });
    if (im !== el) im.src = src;
    setTimeout(function () { skDone(el); }, 15000);   // ไม่ค้างตลอดไปถ้าเครือข่ายช้ามาก
  });

  /* ---------- เอกสาร PDF: ภาพหน้าปกจากหน้าแรก + กรอบแสดงเอกสาร ---------- */
  var PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
  var pdfLib = null;
  function loadPdfJs() {
    if (pdfLib) return pdfLib;
    pdfLib = new Promise(function (ok, fail) {
      var sc = document.createElement('script');
      sc.src = PDFJS + 'pdf.min.js';
      sc.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js'; ok(window.pdfjsLib); };
      sc.onerror = fail;
      document.head.appendChild(sc);
    });
    return pdfLib;
  }
  // โหลดเฉพาะส่วนที่ต้องใช้ของไฟล์ (Range request) ไม่ต้องดาวน์โหลดทั้งไฟล์
  function openPdf(url) {
    return loadPdfJs().then(function (lib) {
      return lib.getDocument({ url: url, disableAutoFetch: true, disableStream: true, isEvalSupported: false }).promise;
    });
  }
  function drawPage(doc, no, width) {
    return doc.getPage(no).then(function (page) {
      var base = page.getViewport({ scale: 1 });
      var vp = page.getViewport({ scale: width / base.width });
      var cv = document.createElement('canvas');
      cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
      var ctx = cv.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cv.width, cv.height);
      return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () { return cv; });
    });
  }

  // รูปย่อข่าว: วาดหน้าแรกของ PDF เมื่อเลื่อนมาใกล้ (ทีละ 2 ไฟล์) · จำผลไว้ในแท็บนี้
  var thumbs = [].slice.call(document.querySelectorAll('[data-pdf]'));
  if (thumbs.length) {
    var ss = { get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
               set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* เต็ม */ } } };
    var queue = [], running = 0;
    var show = function (el, src) { el.style.backgroundImage = 'url(' + src + ')'; el.classList.add('pdf-cover'); el.removeAttribute('data-doc'); skDone(el); };
    var next = function () {
      if (running >= 2 || !queue.length) return;
      var el = queue.shift(), url = el.getAttribute('data-pdf');
      running++;
      var w = Math.min(900, Math.max(240, el.clientWidth * (window.devicePixelRatio || 1)));
      openPdf(url)
        .then(function (doc) { return drawPage(doc, 1, w).then(function (cv) { doc.destroy(); return cv; }); })
        .then(function (cv) { var src = cv.toDataURL('image/jpeg', 0.8); show(el, src); ss.set('pdfthumb:' + url + ':' + Math.round(w), src); })
        .catch(function () { skDone(el); /* เปิดไฟล์ไม่ได้ — คงภาพสัญลักษณ์เอกสารไว้ */ })
        .then(function () { running--; next(); });
    };
    var want = function (el) {
      var w = Math.round(Math.min(900, Math.max(240, el.clientWidth * (window.devicePixelRatio || 1))));
      var hit = ss.get('pdfthumb:' + el.getAttribute('data-pdf') + ':' + w);
      if (hit) { show(el, hit); return; }
      queue.push(el); next();
    };
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); want(e.target); } });
      }, { rootMargin: '300px' });
      thumbs.forEach(function (el) { io.observe(el); });
    } else {
      thumbs.forEach(want);
    }
  }

  // กรอบแสดง PDF ในหน้าอ่านข่าว
  document.querySelectorAll('.pdfv').forEach(function (box) {
    var frame = box.querySelector('[data-pdf-view]'), fs = box.querySelector('[data-pdf-fs]');
    var canFs = document.fullscreenEnabled || document.webkitFullscreenEnabled;
    var ifr = frame.querySelector('iframe');
    if (ifr) ifr.addEventListener('load', function () { setTimeout(function () { skDone(frame); }, 250); });
    setTimeout(function () { skDone(frame); }, 15000);
    if (fs && canFs) {
      fs.hidden = false;
      fs.addEventListener('click', function () {
        if (document.fullscreenElement || document.webkitFullscreenElement) {
          (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } else {
          (frame.requestFullscreen || frame.webkitRequestFullscreen).call(frame);
        }
      });
    }
    // เบราว์เซอร์ที่แสดง PDF ในหน้าเว็บไม่ได้ (มือถือส่วนใหญ่) → วาดทุกหน้าด้วย PDF.js แทน
    if (navigator.pdfViewerEnabled !== false) return;
    var url = frame.getAttribute('data-pdf-view');
    frame.innerHTML = '';
    frame.classList.add('pages');
    var note = document.createElement('p'); note.className = 'pdfv-note'; note.textContent = 'กำลังโหลดเอกสาร…';
    frame.appendChild(note);
    openPdf(url).then(function (doc) {
      note.remove();
      skDone(frame);
      var max = Math.min(doc.numPages, 60), w = Math.min(1400, frame.clientWidth * (window.devicePixelRatio || 1));
      var chain = Promise.resolve();
      for (var i = 1; i <= max; i++) {
        (function (no) {
          chain = chain.then(function () { return drawPage(doc, no, w).then(function (cv) { cv.setAttribute('aria-label', 'หน้า ' + no); frame.appendChild(cv); }); });
        })(i);
      }
      if (doc.numPages > max) {
        chain.then(function () { var m = document.createElement('p'); m.className = 'pdfv-note'; m.textContent = 'แสดง ' + max + ' จาก ' + doc.numPages + ' หน้า — กด "เปิดในแท็บใหม่" เพื่อดูทั้งหมด'; frame.appendChild(m); });
      }
    }).catch(function () { skDone(frame); note.textContent = 'แสดงเอกสารในหน้านี้ไม่ได้ — กด "เปิดในแท็บใหม่" เพื่อดูหรือดาวน์โหลด'; });
  });
})();
