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
  function skInit(root) { root.querySelectorAll('[data-sk]').forEach(function (el) {
    if (el.hasAttribute('data-pdf') || el.hasAttribute('data-pdf-view')) return;   // ส่วน PDF จัดการด้านล่าง
    var src = el.tagName === 'IMG' ? el.currentSrc || el.src : (/url\(["']?([^"')]+)["']?\)/.exec(el.style.backgroundImage) || [])[1];
    if (!src) { skDone(el); return; }
    if (el.tagName === 'IMG' && el.complete && el.naturalWidth) { skDone(el); return; }
    var im = el.tagName === 'IMG' ? el : new Image();
    im.addEventListener('load', function () { skDone(el); });
    im.addEventListener('error', function () { skDone(el); });
    if (im !== el) im.src = src;
    setTimeout(function () { skDone(el); }, 15000);   // ไม่ค้างตลอดไปถ้าเครือข่ายช้ามาก
  }); }
  skInit(document);

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
  var ss, queue, running, show, next, want, io;
  function thumbsInit(root) {
    var thumbs = [].slice.call(root.querySelectorAll('[data-pdf]'));
    if (!thumbs.length) return;
    if (ss) { thumbs.forEach(function (el) { io ? io.observe(el) : want(el); }); return; }
    ss = { get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
               set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* เต็ม */ } } };
    queue = []; running = 0;
    show = function (el, src) { el.style.backgroundImage = 'url(' + src + ')'; el.classList.add('pdf-cover'); el.removeAttribute('data-doc'); skDone(el); };
    next = function () {
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
    want = function (el) {
      var w = Math.round(Math.min(900, Math.max(240, el.clientWidth * (window.devicePixelRatio || 1))));
      var hit = ss.get('pdfthumb:' + el.getAttribute('data-pdf') + ':' + w);
      if (hit) { show(el, hit); return; }
      queue.push(el); next();
    };
    if ('IntersectionObserver' in window) {
      io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); want(e.target); } });
      }, { rootMargin: '300px' });
      thumbs.forEach(function (el) { io.observe(el); });
    } else {
      thumbs.forEach(want);
    }
  }
  thumbsInit(document);

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
  /* ---------- ส่วนบนหน้าแรกแบบสไลด์ ---------- */
  var hero = document.querySelector('.hero.has-slides');
  if (hero) (function () {
    var slides = [].slice.call(hero.querySelectorAll('.hero-slide'));
    var dots = [].slice.call(hero.querySelectorAll('.hc-dot'));
    var playBtn = hero.querySelector('.hc-play');
    var DUR = 6000, cur = 0, timer = null, left = DUR, started = 0;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var userPaused = reduce, hoverPaused = false, focusPaused = false, offscreen = false;
    var track = hero.querySelector('.hero-track');
    var narrow = window.matchMedia ? matchMedia('(max-width: 760px)') : { matches: false };
    // รอบแรกรอฉากเปิดตราวิทยาลัยจบก่อน
    var intro = document.documentElement.classList.contains('no-intro') || reduce ? 0 : 2300;
    left = DUR + intro;
    hero.style.setProperty('--hc-dur', DUR + 'ms');
    hero.style.setProperty('--hc-delay', intro + 'ms');

    function preload(n) {
      var im = slides[(n + slides.length) % slides.length].querySelector('img');
      if (im && im.loading === 'lazy') im.loading = 'eager';
    }
    function setState(el, on) {
      el.classList.toggle('is-on', on);
      if (el.getAttribute('data-slide') !== '0' || slides.length > 1) el.setAttribute('aria-hidden', on ? 'false' : 'true');
      if ('inert' in el) el.inert = !on;
      var a = el.querySelector('.hs-link'); if (a) a.tabIndex = on ? 0 : -1;
    }
    function replayIntro() {
      hero.style.setProperty('--intro', '0s');
      slides[0].querySelectorAll('.aff,.rule,h1,.lead,.cta,.arches div').forEach(function (el) {
        el.style.animation = 'none'; void el.offsetWidth; el.style.animation = '';
      });
    }
    function go(n) {
      n = (n + slides.length) % slides.length;
      if (n === cur) return;
      setState(slides[cur], false);
      setState(slides[n], true);
      if (n === 0) replayIntro();
      dots.forEach(function (d, i) { d.classList.toggle('on', i === n); if (i === n) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
      hero.style.setProperty('--hc-delay', '0s');
      cur = n; left = DUR;
      preload(n + 1);
      fit();
      schedule();
    }
    // จอแคบ: ปรับความสูงตามสไลด์ที่แสดง (ภาพแนวนอนจะไม่ลอยอยู่กลางพื้นที่สูง ๆ) + เว้นที่ให้ปุ่มควบคุมด้านล่าง
    function fit() {
      if (!narrow.matches) { hero.classList.remove('fit'); track.style.height = ''; return; }
      hero.classList.add('fit');
      var el = slides[cur], h;
      if (cur === 0) h = el.firstElementChild.offsetHeight;
      else {
        var im = el.querySelector('img'), r = im && im.naturalWidth ? im.naturalWidth / im.naturalHeight : 1920 / 710;
        h = Math.round(track.clientWidth / r) + 56;
      }
      track.style.height = h + 'px';
    }
    slides.forEach(function (el) { var im = el.querySelector('img'); if (im) im.addEventListener('load', function () { if (slides[cur] === el) fit(); }); });
    window.addEventListener('resize', fit);
    function paused() { return userPaused || hoverPaused || focusPaused || offscreen || document.hidden; }
    function schedule() {
      clearTimeout(timer);
      hero.classList.toggle('paused', paused());
      if (paused()) return;
      started = Date.now();
      timer = setTimeout(function () { go(cur + 1); }, left);
    }
    function hold() {   // หยุดชั่วคราว: จำเวลาที่เหลือไว้
      if (timer && !hero.classList.contains('paused')) left = Math.max(400, left - (Date.now() - started));
      schedule();
    }
    slides.forEach(function (el, i) { setState(el, i === 0); });
    hero.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-hero],[data-go]');
      if (!b) return;
      if (b.hasAttribute('data-go')) go(parseInt(b.getAttribute('data-go'), 10));
      else if (b.getAttribute('data-hero') === 'prev') go(cur - 1);
      else if (b.getAttribute('data-hero') === 'next') go(cur + 1);
      else {
        userPaused = !userPaused;
        playBtn.setAttribute('aria-label', userPaused ? 'เล่นสไลด์อัตโนมัติ' : 'หยุดเลื่อนอัตโนมัติ');
        if (!userPaused) left = Math.min(left, DUR);
        hold();
      }
    });
    if (userPaused) playBtn.setAttribute('aria-label', 'เล่นสไลด์อัตโนมัติ');
    hero.addEventListener('mouseenter', function () { hoverPaused = true; hold(); });
    hero.addEventListener('mouseleave', function () { hoverPaused = false; hold(); });
    hero.addEventListener('focusin', function (e) {   // หยุดเฉพาะเมื่อใช้คีย์บอร์ด (คลิกเมาส์ไม่ต้องหยุดค้าง)
      var kb = true; try { kb = e.target.matches(':focus-visible'); } catch (x) { /* เบราว์เซอร์เก่า */ }
      if (kb) { focusPaused = true; hold(); }
    });
    hero.addEventListener('focusout', function (e) { if (!hero.contains(e.relatedTarget)) { focusPaused = false; hold(); } });
    document.addEventListener('visibilitychange', hold);
    hero.addEventListener('keydown', function (e) {
      if (e.target.closest && e.target.closest('.hero-ctl')) {
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(cur - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); go(cur + 1); }
      }
    });
    // หยุดเมื่อเลื่อนพ้นจอ — ไม่เปลี่ยนสไลด์ (และความสูงบนจอแคบ) ขณะผู้ใช้อ่านเนื้อหาด้านล่าง
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) { offscreen = !es[0].isIntersecting; hold(); }, { threshold: 0.35 }).observe(track);
    }
    // ปัดซ้าย/ขวาบนจอสัมผัส
    var sx = null, sy = 0;
    track.style.touchAction = 'pan-y';
    track.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') { sx = e.clientX; sy = e.clientY; } });
    track.addEventListener('pointerup', function (e) {
      if (sx === null) return;
      var dx = e.clientX - sx, dy = e.clientY - sy; sx = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) go(cur + (dx < 0 ? 1 : -1));
    });
    track.addEventListener('pointercancel', function () { sx = null; });
    preload(1);
    fit();
    schedule();
  })();
  /* ---------- แท็บหมวดข่าวหน้าแรก: เปลี่ยนเนื้อหาในที่เดิม + เลื่อนนุ่ม ๆ ไม่โหลดทั้งหน้า ---------- */
  (function () {
    var sec = document.getElementById('news');
    if (!sec || !window.fetch || !window.DOMParser || !history.pushState) return;
    var calm = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var busy = null, anim = 0;
    // เลื่อนหน้าแบบค่อย ๆ เร่ง–ค่อย ๆ หยุด ช้ากว่าค่าเริ่มต้นของเบราว์เซอร์เล็กน้อย
    function glide(to) {
      var from = window.pageYOffset, d = to - from;
      cancelAnimationFrame(anim);
      if (Math.abs(d) < 2) return;
      if (calm) { window.scrollTo(0, to); return; }
      var dur = Math.min(900, 500 + Math.abs(d) * 0.15), t0 = null;
      var stop = function () { cancelAnimationFrame(anim); off(); };
      var off = function () { ['wheel', 'touchstart', 'keydown'].forEach(function (ev) { window.removeEventListener(ev, stop); }); };
      ['wheel', 'touchstart', 'keydown'].forEach(function (ev) { window.addEventListener(ev, stop, { passive: true }); });   // ผู้ใช้เลื่อนเอง = หยุด
      (function step(ts) {
        if (t0 === null) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var k = p < .5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
        window.scrollTo(0, from + d * k);
        if (p < 1) anim = requestAnimationFrame(step); else off();
      })(performance.now());
    }
    function newsTop() { return sec.getBoundingClientRect().top + window.pageYOffset - (parseFloat(getComputedStyle(sec).scrollMarginTop) || 0); }
    function body() { return sec.querySelector('.news-lead-wrap, .empty'); }
    function load(href, push) {
      var ctl = window.AbortController ? new AbortController() : null;
      if (busy && busy.abort) busy.abort();
      busy = ctl;
      var old = body();
      sec.setAttribute('aria-busy', 'true');
      if (old) old.classList.add('nx-out');
      // เลื่อนให้หัวข้อข่าวชิดบนแบบนุ่มนวล ระหว่างที่โหลดเนื้อหาใหม่
      glide(newsTop());
      return fetch(href, { credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var fresh = doc.getElementById('news');
          if (!fresh) throw new Error('no section');
          var nb = fresh.querySelector('.news-lead-wrap, .empty');
          var tabs = sec.querySelector('.tabs'), ntabs = fresh.querySelector('.tabs');
          if (tabs && ntabs) tabs.innerHTML = ntabs.innerHTML;
          var cur = body();
          if (nb) {
            nb = document.importNode(nb, true);
            var wrap = sec.querySelector('.wrap');
            wrap.style.minHeight = wrap.offsetHeight + 'px';   // กันหน้ากระตุกระหว่างสลับเนื้อหา
            if (cur) cur.replaceWith(nb); else wrap.appendChild(nb);
            nb.classList.add('nx-in');
            skInit(nb); thumbsInit(nb);
            requestAnimationFrame(function () { requestAnimationFrame(function () { nb.classList.remove('nx-in'); }); });
            setTimeout(function () { wrap.style.minHeight = ''; }, 400);
          }
          if (push) history.pushState({ newsTab: 1 }, '', href);
          sec.removeAttribute('aria-busy');
          busy = null;
        })
        .catch(function (e) {
          if (e && e.name === 'AbortError') return;
          location.href = href;   // ผิดพลาด — กลับไปใช้การโหลดหน้าแบบปกติ
        });
    }
    sec.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('.tabs a');
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button) return;
      e.preventDefault();
      if (a.classList.contains('on')) {
        glide(newsTop());
        return;
      }
      load(a.href, true);
    });
    // ปุ่มย้อนกลับ/ไปข้างหน้าของเบราว์เซอร์ระหว่างแท็บ
    function catOf(u) { return new URL(u, location.href).searchParams.get('cat') || 'pr'; }
    window.addEventListener('popstate', function () {
      var on = sec.querySelector('.tabs a.on');
      if (on && catOf(on.href) !== catOf(location.href)) load(location.href, false);
    });
    history.replaceState({ newsTab: 1 }, '', location.href);
  })();
})();
