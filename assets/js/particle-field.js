(() => {
  const canvas = document.querySelector('[data-particle-field]');
  if (!canvas || !window.EZMotion) return;

  const M = window.EZMotion;
  let ctx;
  let width = 1;
  let height = 1;
  let frame = null;
  let time = Math.random() * 10;
  let visible = true;
  let lastScrollY = window.scrollY;
  let scrollVelocity = 0;
  let particles = [];

  const pointer = {
    x: 0,
    y: 0,
    tx: 0,
    ty: 0,
    active: false,
    strength: 0,
  };

  const preset = () => {
    const level = M.motionLevel();
    if (level === 'reduced') return { count: 42, dpr: 1, speed: 0.25, links: 48 };
    if (level === 'low') return { count: 64, dpr: 1.2, speed: 0.62, links: 58 };
    if (level === 'medium') return { count: 92, dpr: 1.5, speed: 0.75, links: 70 };
    return { count: 128, dpr: 1.8, speed: 0.86, links: 78 };
  };

  function resize() {
    const p = preset();
    const fit = M.setupCanvas(canvas, p.dpr);
    ctx = fit.ctx;
    width = fit.width;
    height = fit.height;
    particles = [];

    for (let i = 0; i < p.count; i += 1) {
      const angle = Math.random() * Math.PI * 2;
      const radius = Math.random();
      particles.push({
        u: Math.random(),
        v: Math.random(),
        angle,
        radius,
        seed: Math.random() * 5000,
        size: 0.45 + Math.random() * 1.5,
      });
    }
  }

  function updateScrollVelocity() {
    const current = window.scrollY;
    const diff = current - lastScrollY;
    lastScrollY = current;
    scrollVelocity = M.lerp(scrollVelocity, M.clamp(diff, -60, 60), 0.075);
  }

  function position(particle, index) {
    const t = time * preset().speed;
    const cx = width * 0.52;
    const cy = height * 0.50;
    const spreadX = width * 0.47;
    const spreadY = height * 0.40;

    const n1 = Math.sin(particle.u * 8 + t * 0.45 + particle.seed * 0.01);
    const n2 = Math.cos(particle.v * 10 + t * 0.36 + particle.seed * 0.014);
    const n3 = Math.sin((particle.u - particle.v) * 12 + t * 0.25);
    const fold = Math.sin(particle.u * Math.PI) * Math.sin(particle.v * Math.PI);

    let x = cx + (particle.u - 0.5) * spreadX + n1 * 42 + n3 * fold * 72;
    let y = cy + (particle.v - 0.5) * spreadY + n2 * 38 + Math.cos(t * 0.18 + particle.u * 7) * fold * 54;

    // Turns the flat cloud into a soft liquid ribbon.
    x += Math.sin(t * 0.12 + particle.v * 9) * 32;
    y += Math.cos(t * 0.16 + particle.u * 8) * 22;

    if (Math.abs(scrollVelocity) > 0.1) {
      x += scrollVelocity * (0.12 + fold * 0.45);
      y -= scrollVelocity * (0.10 + particle.v * 0.06);
    }

    if (M.hasFinePointer()) {
      pointer.x = M.lerp(pointer.x, pointer.tx, 0.13);
      pointer.y = M.lerp(pointer.y, pointer.ty, 0.13);
      pointer.strength = M.lerp(pointer.strength, pointer.active ? 1 : 0, 0.06);

      if (pointer.strength > 0.01) {
        const dx = x - pointer.x;
        const dy = y - pointer.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 1;
        const radius = Math.min(width, height) * 0.42;
        if (dist < radius) {
          const force = Math.pow((radius - dist) / radius, 2) * pointer.strength;
          x += (dx / dist) * force * 62;
          y += (dy / dist) * force * 46;
        }
      }
    }

    return { x, y, fold, index };
  }

  function draw() {
    if (!visible) {
      frame = requestAnimationFrame(draw);
      return;
    }

    updateScrollVelocity();
    const p = preset();
    ctx.clearRect(0, 0, width, height);

    const glow = ctx.createRadialGradient(width * 0.50, height * 0.48, 0, width * 0.50, height * 0.48, Math.max(width, height) * 0.52);
    glow.addColorStop(0, M.ink(M.isLightTheme() ? 0.025 : 0.055));
    glow.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, width, height);

    const mapped = particles.map(position);

    ctx.save();
    ctx.strokeStyle = M.ink(0.11);
    ctx.lineWidth = 0.65;

    for (let i = 0; i < mapped.length; i += 1) {
      const a = mapped[i];
      for (let j = i + 1; j < mapped.length; j += 1) {
        const b = mapped[j];
        const dx = a.x - b.x;
        const dy = a.y - b.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        if (distance > p.links) continue;
        const alpha = Math.pow((p.links - distance) / p.links, 1.8) * 0.24;
        ctx.globalAlpha = alpha;
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.stroke();
      }
    }

    ctx.fillStyle = M.ink(0.56);
    for (let i = 0; i < mapped.length; i += 1) {
      const point = mapped[i];
      const flicker = (Math.sin(time * 0.9 + i * 1.7) + 1) * 0.5;
      ctx.globalAlpha = 0.24 + flicker * 0.42;
      ctx.beginPath();
      ctx.arc(point.x, point.y, particles[i].size * (0.72 + flicker * 0.48), 0, Math.PI * 2);
      ctx.fill();
    }

    // Slow contour lines over the particle field.
    ctx.globalAlpha = 0.11;
    ctx.strokeStyle = M.ink(0.26);
    ctx.lineWidth = 1;
    const bands = 4;
    for (let b = 0; b < bands; b += 1) {
      ctx.beginPath();
      const baseY = height * (0.22 + b * 0.16);
      for (let x = width * 0.10; x <= width * 0.92; x += 8) {
        const y = baseY
          + Math.sin(x * 0.012 + time * 0.22 + b * 1.8) * 18
          + Math.cos(x * 0.021 + time * 0.18) * 8
          - scrollVelocity * 0.18;
        if (x === width * 0.10) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      }
      ctx.stroke();
    }

    ctx.restore();

    scrollVelocity *= 0.92;
    time += M.prefersReduced() ? 0.002 : 0.016;
    frame = requestAnimationFrame(draw);
  }

  function setPointer(event) {
    const rect = canvas.getBoundingClientRect();
    pointer.tx = event.clientX - rect.left;
    pointer.ty = event.clientY - rect.top;
    pointer.active = true;
  }

  function start() {
    if (frame) cancelAnimationFrame(frame);
    frame = requestAnimationFrame(draw);
  }

  M.createVisibilityController(canvas, (isVisible) => { visible = isVisible; });
  window.addEventListener('resize', resize, { passive: true });
  window.addEventListener('scroll', updateScrollVelocity, { passive: true });
  canvas.addEventListener('mousemove', setPointer, { passive: true });
  canvas.addEventListener('mouseenter', setPointer, { passive: true });
  canvas.addEventListener('mouseleave', () => { pointer.active = false; }, { passive: true });

  resize();
  start();

  document.addEventListener('visibilitychange', () => {
    if (document.hidden && frame) cancelAnimationFrame(frame);
    if (!document.hidden) start();
  });
})();
