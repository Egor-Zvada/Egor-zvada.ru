function updateSakhalinTime() {
  const el = document.getElementById('time');

  if (!el) {
    return;
  }

  const formatter = new Intl.DateTimeFormat('ru-RU', {
    timeZone: 'Asia/Sakhalin',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });

  el.textContent = 'TIME ' + formatter.format(new Date());
}

const systemThemeMedia = window.matchMedia('(prefers-color-scheme: light)');

function getSystemTheme() {
  return systemThemeMedia.matches ? 'light' : 'dark';
}

function getSavedTheme() {
  const savedTheme = localStorage.getItem('theme');

  if (savedTheme === 'dark' || savedTheme === 'light') {
    return savedTheme;
  }

  return null;
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);

  const button = document.getElementById('theme-toggle');

  if (button) {
    const text = button.querySelector('.theme-toggle__text');

    if (text) {
      text.textContent = theme;
    }
  }
}

function initThemeToggle() {
  const button = document.getElementById('theme-toggle');
  const savedTheme = getSavedTheme();
  const initialTheme = savedTheme || getSystemTheme();

  applyTheme(initialTheme);

  if (!button) {
    return;
  }

  button.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || getSystemTheme();
    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

    localStorage.setItem('theme', nextTheme);
    applyTheme(nextTheme);
  });

  systemThemeMedia.addEventListener('change', () => {
    if (!getSavedTheme()) {
      applyTheme(getSystemTheme());
    }
  });
}

function initBackButton() {
  const button = document.querySelector('[data-go-back]');

  if (!button) {
    return;
  }

  button.addEventListener('click', () => {
    if (window.history.length > 1) {
      window.history.back();
      return;
    }

    window.location.href = '/';
  });
}

initThemeToggle();
initBackButton();
updateSakhalinTime();
setInterval(updateSakhalinTime, 1000);
