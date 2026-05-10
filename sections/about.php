<?php $about = include __DIR__ . '/../data/about.php'; ?>
<?php $aboutGallery = array_values(array_filter($about['gallery'] ?? [])); ?>

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

    <div class="about-visual" data-reveal data-about-gallery aria-label="Визуальный блок: рабочая среда, сцена и инфраструктура">
      <div class="about-visual__topline">
        <span><?= htmlspecialchars($aboutGallery ? 'gallery' : ($about['visual_top_left'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <span><?= htmlspecialchars($aboutGallery ? 'about / photos' : ($about['visual_top_right'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="about-visual__screen">
        <?php if ($aboutGallery): ?>
          <?php foreach ($aboutGallery as $galleryIndex => $galleryImage): ?>
            <img
              class="about-visual__photo <?= $galleryIndex === 0 ? 'is-active' : '' ?>"
              src="<?= htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8') ?>"
              alt=""
              loading="lazy"
              data-about-gallery-image
            >
          <?php endforeach; ?>

          <?php if (count($aboutGallery) > 1): ?>
            <div class="about-visual__controls" aria-label="Листание фотографий">
              <button class="about-visual__control" type="button" data-about-prev aria-label="Предыдущее фото">←</button>
              <button class="about-visual__control" type="button" data-about-next aria-label="Следующее фото">→</button>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="about-visual__stage" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
          </div>
          <div class="about-visual__console" aria-hidden="true">
            <i></i><i></i><i></i><i></i><i></i><i></i>
          </div>
        <?php endif; ?>
      </div>

      <div class="about-visual__grid" aria-hidden="true">
        <?php foreach (($aboutGallery ? array_slice($aboutGallery, 0, 4) : ($about['visual_tags'] ?? [])) as $tagIndex => $tag): ?>
          <span><?= htmlspecialchars($aboutGallery ? 'photo ' . str_pad((string) ($tagIndex + 1), 2, '0', STR_PAD_LEFT) : $tag, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
