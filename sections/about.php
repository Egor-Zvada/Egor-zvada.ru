<?php $about = include __DIR__ . '/../data/about.php'; ?>
<?php
$aboutPhoto = '/assets/img/about/about.jpg';
$aboutPhotoPath = __DIR__ . '/../assets/img/about/about.jpg';
$hasAboutPhoto = is_file($aboutPhotoPath);
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

      <div class="about-visual__photo-frame">
        <?php if ($hasAboutPhoto): ?>
          <img class="about-visual__single-photo" src="<?= htmlspecialchars($aboutPhoto, ENT_QUOTES, 'UTF-8') ?>" alt="Егор Звада" loading="lazy">
        <?php else: ?>
          <div class="about-visual__photo-placeholder">
            <span>upload</span>
            <strong>/assets/img/about/about.jpg</strong>
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
