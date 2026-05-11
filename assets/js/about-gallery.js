(() => {
  const gallery = document.querySelector('[data-about-gallery]');
  if (!gallery) return;

  const slides = [...gallery.querySelectorAll('[data-about-slide]')];
  if (slides.length <= 1) return;

  const prev = gallery.querySelector('[data-about-prev]');
  const next = gallery.querySelector('[data-about-next]');
  const counter = gallery.querySelector('[data-about-counter]');
  let active = 0;

  const render = () => {
    slides.forEach((slide, index) => {
      const isActive = index === active;
      slide.classList.toggle('is-active', isActive);
      slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });

    if (counter) {
      counter.textContent = `${String(active + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
    }
  };

  const move = (direction) => {
    active = (active + direction + slides.length) % slides.length;
    render();
  };

  prev?.addEventListener('click', () => move(-1));
  next?.addEventListener('click', () => move(1));
})();
