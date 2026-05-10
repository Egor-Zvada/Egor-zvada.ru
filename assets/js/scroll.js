(() => {
  const header = document.querySelector('[data-site-header]');
  const sections = [...document.querySelectorAll('[data-section]')];
  const navLinks = [...document.querySelectorAll('.site-nav__link')];
  const sideIndicator = document.querySelector('[data-side-indicator]');
  const sideCurrent = document.querySelector('[data-side-current]');
  const sideLinks = [...document.querySelectorAll('[data-indicator-link]')];
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const indicatorOrder = ['about', 'skills', 'projects', 'tavrida', 'contacts'];

  const setHeaderState = () => {
    if (!header) return;
    const scrolled = window.scrollY > 40;
    const menuReady = window.scrollY > 140;
    header.classList.toggle('is-scrolled', scrolled);
    header.classList.toggle('is-at-top', !menuReady);
  };

  const setIndicatorState = (activeId) => {
    if (!sideIndicator) return;
    const index = indicatorOrder.indexOf(activeId);
    const visible = index >= 0;

    sideIndicator.classList.toggle('is-visible', visible);
    if (visible && sideCurrent) sideCurrent.textContent = String(index + 1).padStart(2, '0');

    sideLinks.forEach((link) => {
      link.classList.toggle('is-active', link.dataset.indicatorLink === activeId);
    });
  };

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) entry.target.classList.add('is-visible');
    });
  }, { threshold: 0.16 });

  sections.forEach((section) => revealObserver.observe(section));

  const activeObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const id = entry.target.id;

      navLinks.forEach((link) => {
        link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
      });

      setIndicatorState(id);
    });
  }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });

  sections.forEach((section) => activeObserver.observe(section));

  document.addEventListener('click', (event) => {
    const scrollTopTarget = event.target.closest('[data-scroll-top]');
    if (scrollTopTarget) {
      window.scrollTo({ top: 0, behavior: prefersReduced ? 'auto' : 'smooth' });
      return;
    }

    const anchor = event.target.closest('a[href^="#"]');
    if (!anchor) return;
    const id = anchor.getAttribute('href');
    if (!id || id === '#') return;
    const target = document.querySelector(id);
    if (!target) return;

    event.preventDefault();
    const offset = header ? header.offsetHeight + 12 : 0;
    const top = target.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: prefersReduced ? 'auto' : 'smooth' });
  });

  setHeaderState();
  setIndicatorState('hero');
  window.addEventListener('scroll', setHeaderState, { passive: true });
})();
