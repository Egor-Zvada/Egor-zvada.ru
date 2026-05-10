(() => {
  const root = document.documentElement;
  const body = document.body;

  root.classList.add('js-ready');

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    root.classList.add('reduced-motion');
  }

  window.addEventListener('load', () => {
    body.classList.add('is-loaded');
  }, { once: true });
})();
