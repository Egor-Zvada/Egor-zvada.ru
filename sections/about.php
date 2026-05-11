<?php $about = include __DIR__ . '/../data/about.php'; ?>
<?php
$aboutGallery = array_values(array_filter($about['gallery'] ?? [], static fn($path) => is_string($path) && trim($path) !== ''));
$fallbackAboutPhoto = '/assets/img/about/about.jpg';
$fallbackAboutPhotoPath = __DIR__ . '/../assets/img/about/about.jpg';
if (empty($aboutGallery) && is_file($fallbackAboutPhotoPath)) {
  $aboutGallery[] = $fallbackAboutPhoto;
}
$hasAboutPhoto = !empty($aboutGallery);
?>

<section class="section about" id="about" data-section="about">
  <div class="container about__layout">
    <div class="about__copy" data-reveal>
      <div class="section__heading about__heading">
        <p class="section-kicker">01 / about</p>
        <h2>Обо мне</h2>
        <p class="about__lead">
          <?= htmlspecialchars($about['lead'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <div class="about__content">
        <?php foreach (($about['paragraphs'] ?? []) as $paragraph): ?>
          <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>

        <p class="terminal-line">&gt; focus: <?= htmlspecialchars($about['focus'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <div class="about-visual" data-reveal aria-label="Фотография в блоке обо мне">
      <div class="about-visual__topline">
        <span>portrait</span>
        <span>about / photo</span>
      </div>

      <div class="about-visual__photo-frame" data-about-gallery>
        <?php if ($hasAboutPhoto): ?>
          <?php foreach ($aboutGallery as $index => $aboutPhoto): ?>
            <img
              class="about-visual__single-photo <?= $index === 0 ? 'is-active' : '' ?>"
              src="<?= htmlspecialchars($aboutPhoto, ENT_QUOTES, 'UTF-8') ?>"
              alt="Егор Звада"
              loading="lazy"
              data-about-slide
              aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
            >
          <?php endforeach; ?>
          <?php if (count($aboutGallery) > 1): ?>
            <div class="about-visual__controls" aria-label="Листать фото обо мне">
              <button type="button" data-about-prev aria-label="Предыдущее фото">←</button>
              <span data-about-counter>01 / <?= str_pad((string) count($aboutGallery), 2, '0', STR_PAD_LEFT) ?></span>
              <button type="button" data-about-next aria-label="Следующее фото">→</button>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="about-visual__photo-placeholder">
            <span>upload</span>
            <strong>admin / about / photos</strong>
          </div>
        <?php endif; ?>
      </div>

      <div class="about-visual__grid" aria-hidden="true">
        <?php foreach (($about['visual_tags'] ?? []) as $tag): ?>
          <span><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
