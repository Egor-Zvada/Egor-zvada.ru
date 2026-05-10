<?php $contacts = include __DIR__ . '/../data/contacts.php'; ?>

<section class="section contacts" id="contacts" data-section="contacts">
  <div class="container contacts__layout">

    <div class="section__heading contacts__heading">
      <p class="section-kicker">05 / contact</p>
      <h2>Контакты</h2>

      <div class="contact-list" aria-label="Контактные данные">
        <a class="contact-item" href="mailto:<?= htmlspecialchars($contacts['email'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="contact-item__label">Email</span>
          <span class="contact-item__value"><?= htmlspecialchars($contacts['email'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>

        <a class="contact-item" href="<?= htmlspecialchars($contacts['telegram_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noreferrer">
          <span class="contact-item__label">Telegram</span>
          <span class="contact-item__value"><?= htmlspecialchars($contacts['telegram'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>

        <div class="contact-item contact-item--static">
          <span class="contact-item__label">Location</span>
          <span class="contact-item__value"><?= htmlspecialchars($contacts['location'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="contact-item contact-item--static">
          <span class="contact-item__label">Timezone</span>
          <span class="contact-item__value"><?= htmlspecialchars($contacts['timezone'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </div>

    <div class="contacts__statement">
      <p>Давайте</p>
      <p>создавать</p>
      <p>вместе</p>
    </div>

<div class="contact-connect">
  <div class="contact-qr" aria-label="QR-код для связи в Telegram">
    <a
      class="contact-qr__link"
      href="<?= htmlspecialchars($contacts['telegram_url'], ENT_QUOTES, 'UTF-8') ?>"
      target="_blank"
      rel="noreferrer"
      aria-label="Открыть Telegram"
    >
      <img
        src="/assets/img/qr/telegram-qr.png"
        alt="QR-код Telegram"
        class="contact-qr__image"
        loading="lazy"
      >
    </a>

    <a
      class="button button--ghost contact-connect__button"
      href="<?= htmlspecialchars($contacts['telegram_url'], ENT_QUOTES, 'UTF-8') ?>"
      target="_blank"
      rel="noreferrer"
    >
      Связаться
      <span aria-hidden="true">→</span>
    </a>
  </div>
</div>

  </div>
</section>
