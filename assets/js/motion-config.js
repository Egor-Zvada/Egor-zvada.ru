(() => {
  const reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  const finePointerQuery = window.matchMedia('(pointer: fine)');

  const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
  const lerp = (from, to, amount) => from + (to - from) * amount;
  const map = (value, inMin, inMax, outMin, outMax) => {
    if (inMax === inMin) return outMin;
    return outMin + ((value - inMin) / (inMax - inMin)) * (outMax - outMin);
  };

  const isLightTheme = () => {
    const forced = document.documentElement.getAttribute('data-theme');
    if (forced === 'light') return true;
    if (forced === 'dark') return false;
    return window.matchMedia('(prefers-color-scheme: light)').matches;
  };

  const ink = (alpha = 1) => isLightTheme()
    ? `rgba(9, 9, 9, ${alpha})`
    : `rgba(244, 244, 241, ${alpha})`;

  const surface = (alpha = 1) => isLightTheme()
    ? `rgba(245, 245, 242, ${alpha})`
    : `rgba(5, 5, 5, ${alpha})`;

  const setupCanvas = (canvas, maxDpr = 2) => {
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(1, rect.width);
    const height = Math.max(1, rect.height);
    const dpr = Math.min(window.devicePixelRatio || 1, maxDpr);
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx, width, height, dpr };
  };

  const createVisibilityController = (element, callback) => {
    let visible = true;
    if (!('IntersectionObserver' in window)) {
      callback(true);
      return () => true;
    }

    const observer = new IntersectionObserver((entries) => {
      visible = entries.some((entry) => entry.isIntersecting);
      callback(visible);
    }, { root: null, threshold: 0.01 });

    observer.observe(element);
    return () => visible;
  };

  const motionLevel = () => {
    if (reduceQuery.matches) return 'reduced';
    if (window.innerWidth < 700) return 'low';
    if (window.innerWidth < 1100) return 'medium';
    return 'high';
  };

  document.documentElement.classList.add('motion-ready');

  window.EZMotion = {
    clamp,
    lerp,
    map,
    ink,
    surface,
    setupCanvas,
    createVisibilityController,
    motionLevel,
    prefersReduced: () => reduceQuery.matches,
    hasFinePointer: () => finePointerQuery.matches,
    isLightTheme,
    constants: {
      ease: 'cubic-bezier(.22, 1, .36, 1)',
      fast: 180,
      normal: 420,
      slow: 820,
    },
  };
})();
