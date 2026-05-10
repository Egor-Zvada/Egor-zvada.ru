<footer class="site-footer" id="footer" data-footer>
  <div class="container site-footer__inner">
    <div class="site-footer__brand-block">
      <a class="brand brand--footer" href="#top" aria-label="egor_zvada — наверх">
        <img class="brand__mark" src="/assets/svg/logo.svg" alt="" width="32" height="32">
        <span class="brand__text">egor_zvada</span>
      </a>
      <p class="site-footer__phrase">Техника. Сцена. Системы.</p>
    </div>

    <div class="site-footer__meta">
      <p class="site-footer__copy">© <?= date('Y') ?> Егор Звада. Все права защищены.</p>
      <button class="site-footer__version" type="button" data-admin-trigger data-admin-clicks="<?= htmlspecialchars((string) ($siteSettings['admin_clicks'] ?? 10), ENT_QUOTES, 'UTF-8') ?>" aria-label="Версия сайта">
        &gt; build: <?= htmlspecialchars($pageVersion ?? '0.2-beta', ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>

    <button class="button button--ghost site-footer__top" type="button" data-scroll-top>
      Наверх <span aria-hidden="true">↑</span>
    </button>
  </div>
</footer>
