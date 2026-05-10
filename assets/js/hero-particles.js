(() => {
  const canvas = document.querySelector('[data-hero-particles]');
  if (!canvas || !window.EZMotion) return;

  const hero = canvas.closest('.hero') || canvas;
  const M = window.EZMotion;
  let ctx;
  let width = 1;
  let height = 1;
  let cols = 0;
  let rows = 0;
  let points = [];
  let frame = null;
  let time = Math.random() * 100;
  let visible = true;
  let lastScrollY = window.scrollY;
  let scrollVelocity = 0;

  const pointer = {
    x: 0,
    y: 0,
    tx: 0,
    ty: 0,
    active: false,
    strength: 0,
  };

  const qualityPreset = () => {
    const level = M.motionLevel();
    if (level === 'reduced') return { density: 0.28, speed: 0.35, dpr: 1 };
    if (level === 'low') return { density: 0.55, speed: 0.75, dpr: 1.35 };
    if (level === 'medium') return { density: 0.82, speed: 0.88, dpr: 1.6 };
    return { density: 1, speed: 1, dpr: 2 };
  };

  function resize() {
    const preset = qualityPreset();
    const fit = M.setupCanvas(canvas, preset.dpr);
    ctx = fit.ctx;
    width = fit.width;
    height = fit.height;

    cols = Math.floor(M.clamp(width / 25, 26, 66) * preset.density);
    rows = Math.floor(M.clamp(height / 22, 20, 48) * preset.density);
    cols = Math.max(12, cols);
    rows = Math.max(10, rows);
    points = [];

    const xStart = width * (width < 760 ? 0.02 : 0.36);
    const xEnd = width * 1.07;
    const yStart = height * 0.10;
    const yEnd = height * 0.92;

    for (let y = 0; y < rows; y += 1) {
      for (let x = 0; x < cols; x += 1) {
        const u = cols <= 1 ? 0 : x / (cols - 1);
        const v = rows <= 1 ? 0 : y / (rows - 1);
        const focus = Math.sin(u * Math.PI) * Math.sin(v * Math.PI);
        points.push({
          u,
          v,
          focus,
          bx: xStart + (xEnd - xStart) * u,
          by: yStart + (yEnd - yStart) * v,
          seed: Math.random() * 900 + x * 0.3 + y * 0.7,
        });
      }
    }
  }

  function updateScrollVelocity() {
    const current = window.scrollY;
    const diff = current - lastScrollY;
    lastScrollY = current;
    scrollVelocity = M.lerp(scrollVelocity, M.clamp(diff, -70, 70), 0.08);
  }

  function project(point) {
    const preset = qualityPreset();
    const u = point.u;
    const v = point.v;
    const focus = point.focus;
    const t = time * preset.speed;

    // Pseudo-3D cloth: depth bends the grid and gives it a tunnel-like feel
    // without loading external WebGL dependencies in this iteration.
    const depth = 0.55 + Math.sin(u * Math.PI) * 0.32 + Math.cos(v * Math.PI * 2 + t * 0.23) * 0.06;
    const perspective = M.map(depth, 0.35, 1, 0.84, 1.15);
    const centerX = width * 0.72;
    const centerY = height * 0.48;

    const waveA = Math.sin(u * 10.5 + t * 0.48 + point.seed * 0.019);
    const waveB = Math.cos(v * 12.2 + t * 0.42 + point.seed * 0.013);
    const waveC = Math.sin((u + v) * 15.8 + t * 0.31 + point.seed * 0.006);
    const drift = Math.sin(t * 0.18 + point.seed * 0.01) * focus;

    let x = point.bx + waveA * 34 + waveB * 16 + drift * 58;
    let y = point.by + waveB * 28 + waveC * 19 + Math.cos(t * 0.2 + u * 8) * focus * 34;

    x = centerX + (x - centerX) * perspective;
    y = centerY + (y - centerY) * (0.88 + perspective * 0.12);

    if (Math.abs(scrollVelocity) > 0.1) {
      x += scrollVelocity * focus * 0.52;
      y -= scrollVelocity * (0.04 + v * 0.12);
    }

    if (M.hasFinePointer()) {
      pointer.x = M.lerp(pointer.x, pointer.tx, 0.12);
      pointer.y = M.lerp(pointer.y, pointer.ty, 0.12);
      pointer.strength = M.lerp(pointer.strength, pointer.active ? 1 : 0, 0.055);

      if (pointer.strength > 0.01) {
        const dx = x - pointer.x;
        const dy = y - pointer.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 1;
        const radius = width < 760 ? 190 : 360;
        if (dist < radius) {
          const force = Math.pow((radius - dist) / radius, 2) * pointer.strength;
          const push = 150 + Math.abs(scrollVelocity) * 0.9;
          x += (dx / dist) * force * push;
          y += (dy / dist) * force * 105;
        }

        const wake = Math.sin(dist * 0.018 - time * 2.1 + point.seed * 0.01) * pointer.strength;
        const falloff = Math.max(0, 1 - dist / Math.max(width, height));
        x += wake * falloff * focus * 18;
        y += Math.cos(dist * 0.014 - time * 1.7) * falloff * focus * 14 * pointer.strength;
      }
    }

    return { x, y, focus, depth };
  }

  function draw() {
    if (!visible) {
      frame = requestAnimationFrame(draw);
      return;
    }

    updateScrollVelocity();
    ctx.clearRect(0, 0, width, height);

    const level = M.motionLevel();
    const reduced = level === 'reduced';
    const inkSoft = M.ink(reduced ? 0.08 : 0.105);
    const inkMedium = M.ink(reduced ? 0.18 : 0.28);
    const inkStrong = M.ink(0.52);
    const bgGlow = M.ink(M.isLightTheme() ? 0.035 : 0.07);

    const glow = ctx.createRadialGradient(width * 0.74, height * 0.45, 0, width * 0.74, height * 0.45, Math.max(width, height) * 0.52);
    glow.addColorStop(0, bgGlow);
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, width, height);

    const mapped = points.map(project);

    ctx.save();
    ctx.globalCompositeOperation = 'source-over';

    // Row strands — main liquid cloth.
    for (let y = 0; y < rows; y += 1) {
      ctx.beginPath();
      for (let x = 0; x < cols; x += 1) {
        const p = mapped[y * cols + x];
        if (x === 0) ctx.moveTo(p.x, p.y);
        else ctx.lineTo(p.x, p.y);
      }
      const isGuide = y % 6 === 0;
      ctx.strokeStyle = isGuide ? inkMedium : inkSoft;
      ctx.lineWidth = isGuide ? 1.1 : 0.6;
      ctx.globalAlpha = isGuide ? 0.46 : 0.30;
      ctx.stroke();
    }

    // Sparse vertical strands for mesh/tunnel reading.
    for (let x = 0; x < cols; x += 3) {
      ctx.beginPath();
      for (let y = 0; y < rows; y += 1) {
        const p = mapped[y * cols + x];
        if (y === 0) ctx.moveTo(p.x, p.y);
        else ctx.lineTo(p.x, p.y);
      }
      ctx.strokeStyle = inkSoft;
      ctx.lineWidth = 0.55;
      ctx.globalAlpha = 0.20;
      ctx.stroke();
    }

    // Drifting point accents.
    ctx.fillStyle = inkStrong;
    for (let i = 0; i < mapped.length; i += level === 'low' ? 11 : 7) {
      const p = mapped[i];
      const pulse = (Math.sin(time * 0.8 + i * 0.35) + 1) * 0.5;
      if (pulse < 0.34) continue;
      ctx.globalAlpha = 0.14 + pulse * 0.42;
      ctx.beginPath();
      ctx.arc(p.x, p.y, 0.55 + pulse * 1.2, 0, Math.PI * 2);
      ctx.fill();
    }

    // Scanning guide line.
    if (!reduced) {
      const scanX = width * 0.38 + ((Math.sin(time * 0.13) + 1) * 0.5) * width * 0.56;
      ctx.strokeStyle = M.ink(0.24);
      ctx.globalAlpha = 0.16;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(scanX, height * 0.13);
      ctx.lineTo(scanX + scrollVelocity * 0.22, height * 0.86);
      ctx.stroke();

      const scanY = height * 0.17 + ((Math.cos(time * 0.105) + 1) * 0.5) * height * 0.66;
      ctx.globalAlpha = 0.10;
      ctx.beginPath();
      ctx.moveTo(width * 0.34, scanY);
      ctx.lineTo(width * 0.98, scanY + scrollVelocity * 0.10);
      ctx.stroke();
    }

    ctx.restore();

    scrollVelocity *= 0.92;
    time += reduced ? 0.003 : 0.016;
    frame = requestAnimationFrame(draw);
  }

  function setPointer(event) {
    const rect = canvas.getBoundingClientRect();
    pointer.tx = event.clientX - rect.left;
    pointer.ty = event.clientY - rect.top;
    pointer.active = true;
  }

  function setPointerFromWindow(event) {
    const heroRect = hero.getBoundingClientRect();
    const isInsideHero = event.clientX >= heroRect.left
      && event.clientX <= heroRect.right
      && event.clientY >= heroRect.top
      && event.clientY <= heroRect.bottom;

    if (!isInsideHero) {
      pointer.active = false;
      return;
    }

    setPointer(event);
  }

  function start() {
    if (frame) cancelAnimationFrame(frame);
    frame = requestAnimationFrame(draw);
  }

  M.createVisibilityController(canvas, (isVisible) => { visible = isVisible; });
  window.addEventListener('resize', resize, { passive: true });
  window.addEventListener('scroll', updateScrollVelocity, { passive: true });
  window.addEventListener('pointermove', setPointerFromWindow, { passive: true });
  canvas.addEventListener('mousemove', setPointer, { passive: true });
  canvas.addEventListener('mouseenter', setPointer, { passive: true });
  hero.addEventListener('pointerleave', () => { pointer.active = false; }, { passive: true });

  resize();
  start();

  document.addEventListener('visibilitychange', () => {
    if (document.hidden && frame) cancelAnimationFrame(frame);
    if (!document.hidden) start();
  });
})();
