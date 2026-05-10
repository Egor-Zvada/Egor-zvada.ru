(() => {
  const body = document.body;
  const menuToggle = document.querySelector('[data-menu-toggle]');
  const siteNav = document.querySelector('[data-site-nav]');

  const setMenuState = (open) => {
    body.classList.toggle('is-menu-open', open);
    menuToggle?.setAttribute('aria-expanded', String(open));
    menuToggle?.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
  };

  menuToggle?.addEventListener('click', () => {
    setMenuState(!body.classList.contains('is-menu-open'));
  });

  siteNav?.addEventListener('click', (event) => {
    if (event.target.closest('a')) setMenuState(false);
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenuState(false);
  });

  document.addEventListener('click', (event) => {
    const expandButton = event.target.closest('[data-expand-toggle]');
    if (expandButton) {
      const key = expandButton.dataset.expandToggle;
      const target = document.querySelector(`[data-expandable="${key}"]`);
      if (!target) return;

      target.classList.toggle('is-expanded');
      const isExpanded = target.classList.contains('is-expanded');
      setExpandButtonsState(key, isExpanded);
      return;
    }

    const readMoreButton = event.target.closest('[data-read-more-toggle]');
    if (readMoreButton) {
      const block = readMoreButton.closest('[data-read-more]');
      if (!block) return;
      block.classList.toggle('is-expanded');
      const expanded = block.classList.contains('is-expanded');
      readMoreButton.setAttribute('aria-expanded', String(expanded));
      readMoreButton.innerHTML = expanded
        ? 'Скрыть <span aria-hidden="true">↑</span>'
        : 'Читать больше <span aria-hidden="true">→</span>';
      return;
    }

    const toastButton = event.target.closest('[data-toast]');
    if (toastButton) {
      showToast(toastButton.dataset.toast);
    }
  });

  function setExpandButtonsState(key, isExpanded) {
    const buttons = document.querySelectorAll(`[data-expand-toggle="${key}"]`);
    buttons.forEach((button) => {
      button.setAttribute('aria-expanded', String(isExpanded));

      const labelTarget = button.querySelector('.skill-card__control-title');
      const textTarget = button.querySelector('.skill-card__control-text');
      const arrowTarget = button.querySelector('.skill-card__arrow');

      if (labelTarget && textTarget && arrowTarget) {
        const isCollapse = button.classList.contains('skill-card--collapse');
        labelTarget.textContent = isCollapse ? 'Скрыть' : 'Показать больше';
        textTarget.textContent = isCollapse
          ? 'Вернуть компактный список навыков'
          : textTarget.textContent;
        arrowTarget.textContent = isCollapse ? '←' : '→';
        return;
      }

      const label = isExpanded ? button.dataset.closeLabel : button.dataset.openLabel;
      button.innerHTML = `${label} <span aria-hidden="true">${isExpanded ? '↑' : '→'}</span>`;
    });
  }

  function showToast(message) {
    const old = document.querySelector('.site-toast');
    if (old) old.remove();

    const toast = document.createElement('div');
    toast.className = 'site-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    setTimeout(() => {
      toast.classList.remove('is-visible');
      setTimeout(() => toast.remove(), 300);
    }, 3200);
  }
})();

