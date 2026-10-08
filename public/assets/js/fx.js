/* MédiRDV — effets visuels : curseur, inclinaison 3D, révélations, compteurs, ECG, scène du hero */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---- Inclinaison 3D (cartes) ---- */
  function bindTilt(el, max) {
    if (el._tilt) return;
    el._tilt = true;
    const glare = document.createElement('span');
    glare.className = 'glare';
    glare.setAttribute('aria-hidden', 'true');
    el.appendChild(glare);
    el.addEventListener('pointermove', (e) => {
      if (e.pointerType === 'touch') return;
      const r = el.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width;
      const py = (e.clientY - r.top) / r.height;
      el.classList.add('tilting');
      el.style.transform = 'perspective(900px) rotateX(' + ((0.5 - py) * max).toFixed(2) + 'deg) rotateY(' + ((px - 0.5) * max * 1.2).toFixed(2) + 'deg) translateY(-3px)';
      el.style.setProperty('--gx', (px * 100) + '%');
      el.style.setProperty('--gy', (py * 100) + '%');
    });
    el.addEventListener('pointerleave', () => { el.classList.remove('tilting'); el.style.transform = ''; });
  }
  function initTilt(root) {
    if (reduced || !finePointer) return;
    $$('[data-tilt]', root).forEach((el) => bindTilt(el, 10));
    $$('[data-tilt-soft]', root).forEach((el) => bindTilt(el, 4));
  }

  /* ---- Révélation au scroll ---- */
  let io = null;
  function initReveal(root) {
    const els = $$('[data-reveal]:not(.in)', root);
    if (reduced || !('IntersectionObserver' in window)) { els.forEach((el) => el.classList.add('in')); return; }
    io = io || new IntersectionObserver((entries) => {
      entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    els.forEach((el) => io.observe(el));
  }

  /* ---- Compteurs ---- */
  function initCount(root) {
    const els = $$('[data-count]', root);
    if (!els.length) return;
    const run = (el) => {
      const target = parseInt(el.dataset.count, 10) || 0;
      if (reduced || target === 0) { el.textContent = target; return; }
      const dur = 900, t0 = performance.now();
      const step = (t) => {
        const p = Math.min(1, (t - t0) / dur);
        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };
    if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
    const o = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) { run(en.target); o.unobserve(en.target); } }), { threshold: 0.4 });
    els.forEach((el) => o.observe(el));
  }

  /* ---- Curseur lumineux ---- */
  function initCursor() {
    const glow = document.querySelector('.cursor-glow');
    if (!glow || reduced || !finePointer) return;
    let x = innerWidth / 2, y = innerHeight / 2, cx = x, cy = y, raf = null;
    const loop = () => {
      cx += (x - cx) * 0.12; cy += (y - cy) * 0.12;
      glow.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0)';
      raf = (Math.abs(x - cx) + Math.abs(y - cy) > 0.5) ? requestAnimationFrame(loop) : null;
    };
    window.addEventListener('pointermove', (e) => { x = e.clientX; y = e.clientY; glow.classList.add('on'); if (!raf) raf = requestAnimationFrame(loop); }, { passive: true });
    document.addEventListener('pointerleave', () => glow.classList.remove('on'));
  }

  /* ---- Ondes sur les boutons ---- */
  function initRipple() {
    if (reduced) return;
    document.addEventListener('pointerdown', (e) => {
      const b = e.target.closest('.btn');
      if (!b || b.disabled) return;
      const r = b.getBoundingClientRect(), size = Math.max(r.width, r.height);
      const s = document.createElement('span');
      s.className = 'ripple';
      s.style.cssText = 'width:' + size + 'px;height:' + size + 'px;left:' + (e.clientX - r.left - size / 2) + 'px;top:' + (e.clientY - r.top - size / 2) + 'px';
      b.appendChild(s);
      setTimeout(() => s.remove(), 650);
    });
  }

  /* ---- Scène 3D du hero : parallaxe à la souris ---- */
  function initScene() {
    const scene = document.querySelector('[data-scene]');
    if (!scene || reduced) return;
    const stage = scene.querySelector('.scene-stage');
    const area = scene.closest('.hero') || scene;
    area.addEventListener('pointermove', (e) => {
      if (e.pointerType === 'touch') return;
      const r = scene.getBoundingClientRect();
      const px = (e.clientX - (r.left + r.width / 2)) / innerWidth;
      const py = (e.clientY - (r.top + r.height / 2)) / innerHeight;
      stage.style.setProperty('--ry', (-16 + px * 26).toFixed(2) + 'deg');
      stage.style.setProperty('--rx', (8 - py * 22).toFixed(2) + 'deg');
    });
    area.addEventListener('pointerleave', () => { stage.style.removeProperty('--ry'); stage.style.removeProperty('--rx'); });
  }

  /* ---- Fond ECG (canvas) ---- */
  function initEcg() {
    const cv = document.getElementById('ecg-bg');
    if (!cv || reduced || getComputedStyle(cv).display === 'none') return;
    const ctx = cv.getContext('2d');
    let W, H, dpr, running = true;
    const resize = () => {
      dpr = Math.min(devicePixelRatio || 1, 2);
      W = cv.width = innerWidth * dpr; H = cv.height = innerHeight * dpr;
    };
    resize(); window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', () => { running = !document.hidden; if (running) requestAnimationFrame(frame); });

    // Un battement : forme normalisée (x en 0..1, y en -1..1)
    const beat = [[0, 0], [.12, 0], [.18, -.18], [.24, 0], [.34, 0], [.38, .2], [.43, -1], [.49, .75], [.54, 0], [.64, 0], [.72, -.28], [.82, 0], [1, 0]];
    const lines = [{ y: .30, speed: 150, amp: 60, hue: '94,234,212', a: .34 }, { y: .72, speed: 105, amp: 44, hue: '118,126,234', a: .22 }];
    let t0 = performance.now();
    function frame(t) {
      if (!running) return;
      const dt = (t - t0) / 1000; t0 = t;
      ctx.clearRect(0, 0, W, H);
      lines.forEach((ln) => {
        ln.off = ((ln.off || 0) + ln.speed * dt * dpr);
        const beatW = 520 * dpr, base = ln.y * H, head = ln.off % (W + beatW);
        ctx.beginPath();
        for (let x = 0; x <= head; x += 3 * dpr) {
          const u = (((x - 0) % beatW) / beatW), seg = beat.findIndex((p, i) => i < beat.length - 1 && u >= p[0] && u <= beat[i + 1][0]);
          const a = beat[seg] || beat[0], b = beat[seg + 1] || beat[beat.length - 1];
          const k = (u - a[0]) / ((b[0] - a[0]) || 1);
          const y = base + (a[1] + (b[1] - a[1]) * k) * ln.amp * dpr;
          x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        const g = ctx.createLinearGradient(Math.max(0, head - 700 * dpr), 0, head, 0);
        g.addColorStop(0, 'rgba(' + ln.hue + ',0)'); g.addColorStop(1, 'rgba(' + ln.hue + ',' + ln.a + ')');
        ctx.strokeStyle = g; ctx.lineWidth = 2 * dpr; ctx.lineJoin = 'round'; ctx.stroke();
      });
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  /* ---- Intro (accueil, une fois par session) ---- */
  function initIntro() {
    const intro = document.getElementById('intro');
    if (!intro) return;
    let seen = false;
    try { seen = sessionStorage.getItem('medirdv-intro') === '1'; } catch (e) { /* stockage indisponible */ }
    if (reduced || seen) { intro.remove(); return; }
    intro.classList.add('run');
    const close = () => { intro.classList.add('done'); setTimeout(() => intro.remove(), 800); };
    setTimeout(close, 1900);
    intro.addEventListener('click', close);
    try { sessionStorage.setItem('medirdv-intro', '1'); } catch (e) { /* ignore */ }
  }

  window.MediFX = { initTilt: initTilt, initReveal: initReveal, initCount: initCount };
  initIntro(); initTilt(); initReveal(); initCount(); initCursor(); initRipple(); initScene(); initEcg();
})();
