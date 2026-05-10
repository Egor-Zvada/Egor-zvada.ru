(() => {
  const list = document.querySelector('[data-project-list]');
  if (!list) return;

  const filterButtons = [...document.querySelectorAll('[data-project-filter]')];
  const cards = [...document.querySelectorAll('[data-project-card]')];
  const moreButton = document.querySelector('[data-expand-toggle="projects"]');
  const collapseButton = document.querySelector('[data-project-collapse]');

  const closeCards = () => {
    cards.forEach((card) => {
      card.classList.remove('is-open');
      const toggle = card.querySelector('[data-project-toggle]');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = 'Подробнее <span aria-hidden="true">→</span>';
      }
      const video = card.querySelector('video');
      if (video) {
        video.pause();
        video.currentTime = 0;
      }
    });
  };

  const toggleCard = (card) => {
    const wasOpen = card.classList.contains('is-open');
    closeCards();
    if (wasOpen) return;

    card.classList.add('is-open');
    const toggle = card.querySelector('[data-project-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
      toggle.innerHTML = 'Свернуть <span aria-hidden="true">↑</span>';
    }

    const video = card.querySelector('video');
    if (video) video.play().catch(() => {});
  };

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-project-toggle]');
    if (toggle) {
      const card = toggle.closest('[data-project-card]');
      if (!card) return;
      toggleCard(card);
      return;
    }

    const collapse = event.target.closest('[data-project-collapse]');
    if (collapse) {
      closeCards();
      list.classList.remove('is-expanded');
      if (moreButton) {
        moreButton.setAttribute('aria-expanded', 'false');
        moreButton.innerHTML = `${moreButton.dataset.openLabel} <span aria-hidden="true">→</span>`;
      }
      document.getElementById('projects')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
  });

  const applyFilter = (category) => {
    closeCards();

    const filtering = category !== 'all';
    list.classList.toggle('is-filtered', filtering);

    cards.forEach((card) => {
      const match = !filtering || card.dataset.category === category;
      card.classList.toggle('is-filtered-out', !match);
    });

    filterButtons.forEach((button) => {
      button.classList.toggle('is-active', button.dataset.projectFilter === category);
    });

    if (moreButton) moreButton.hidden = filtering;
  };

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => applyFilter(button.dataset.projectFilter || 'all'));
  });
})();
