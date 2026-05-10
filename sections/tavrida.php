<?php
$tavridaPhoto = '/assets/img/tavrida/tavrida.jpg';
$tavridaPhotoPath = __DIR__ . '/../assets/img/tavrida/tavrida.jpg';
$hasTavridaPhoto = is_file($tavridaPhotoPath);
?>
<section class="section tavrida" id="tavrida" data-section="tavrida">
  <div class="container tavrida__layout">
    <div class="tavrida__copy">
      <div class="section__heading tavrida__heading">
        <p class="section-kicker">04 / tavrida</p>
        <h2>Почему Таврида</h2>
        <p class="tavrida__lead">Хочу попасть в среду, где технологии, творчество и люди собираются в реальные проекты.</p>
      </div>

      <div class="tavrida__story" data-read-more>
        <div class="tavrida__text">
          <p>Для меня Таврида — возможность выйти за пределы привычной технической роли и развить продюсерское мышление в ИИ-проектах.</p>
          <p class="read-more__extra" data-read-more-extra>Я хочу научиться точнее упаковывать идеи, видеть проект не только как набор технологий, а как цельный результат для аудитории: сцена, медиа, интерактив, команда, смысл и реализация.</p>
          <p class="read-more__extra" data-read-more-extra>Мне важно найти единомышленников, обменяться опытом и понять, как из технических экспериментов делать проекты, которые можно показывать людям и развивать дальше.</p>
        </div>

        <div class="tavrida__actions">
          <button class="button button--ghost" type="button" data-read-more-toggle>Читать больше <span aria-hidden="true">→</span></button>
        </div>
      </div>
    </div>

    <div class="tavrida-visual" aria-label="Фотография Тавриды">
      <?php if ($hasTavridaPhoto): ?>
        <img class="tavrida-visual__photo" src="<?= htmlspecialchars($tavridaPhoto, ENT_QUOTES, 'UTF-8') ?>" alt="Таврида" loading="lazy">
      <?php else: ?>
        <div class="tavrida-visual__placeholder">
          <span>upload</span>
          <strong>/assets/img/tavrida/tavrida.jpg</strong>
        </div>
      <?php endif; ?>
      <div class="tavrida-visual__overlay">
        <span>&gt; people</span>
        <span>&gt; ai_projects</span>
        <span>&gt; stage_media</span>
      </div>
    </div>
  </div>
</section>
