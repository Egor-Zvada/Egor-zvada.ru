(() => {
  const root = document.documentElement;
  const toggle = document.querySelector('[data-theme-toggle]');
  const storageKey = 'egor-zvada-theme';
  const modeStorageKey = 'egor-zvada-theme-mode';
  const mediaQuery = window.matchMedia('(prefers-color-scheme: light)');

  const getStoredValue = (key) => {
    try {
      return localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  };

  const setStoredValue = (key, value) => {
    try {
      localStorage.setItem(key, value);
    } catch (error) {
      // Storage can be blocked in private mode; the theme should still switch.
    }
  };

  const removeStoredValue = (key) => {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      // Ignore blocked storage.
    }
  };

  const getSystemTheme = () => mediaQuery.matches ? 'light' : 'dark';

  const getInitialTheme = () => {
    const saved = getStoredValue(storageKey);
    const savedMode = getStoredValue(modeStorageKey);
    if (savedMode === 'manual' && (saved === 'light' || saved === 'dark')) return saved;
    return getSystemTheme();
  };

  const applyTheme = (theme, persist = false) => {
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    if (persist) {
      setStoredValue(storageKey, theme);
      setStoredValue(modeStorageKey, 'manual');
    }
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', theme === 'light' ? '#f5f5f2' : '#050505');
    if (toggle) {
      const nextTheme = theme === 'light' ? 'dark' : 'light';
      toggle.dataset.currentTheme = theme;
      toggle.setAttribute('aria-label', nextTheme === 'dark' ? 'Включить тёмную тему' : 'Включить светлую тему');
      const text = toggle.querySelector('.theme-toggle__text');
      if (text) text.textContent = nextTheme;
    }
  };

  const syncWithSystemTheme = () => {
    removeStoredValue(modeStorageKey);
    applyTheme(getSystemTheme());
  };

  applyTheme(getInitialTheme());

  toggle?.addEventListener('click', () => {
    const next = root.dataset.theme === 'light' ? 'dark' : 'light';
    applyTheme(next, true);
  });

  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', syncWithSystemTheme);
  } else if (typeof mediaQuery.addListener === 'function') {
    mediaQuery.addListener(syncWithSystemTheme);
  }
})();
