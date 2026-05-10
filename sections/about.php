<?php $about = include __DIR__ . '/../data/about.php'; ?>

<section class="section about" id="about" data-section="about">
  <div class="container about__layout">
    <div class="section__heading about__heading" data-reveal>
      <p class="section-kicker">01 / about</p>
      <h2>Обо мне</h2>
      <p class="about__lead">
        <?= htmlspecialchars($about['lead'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>

    <div class="about__content" data-reveal>
      <?php foreach (($about['paragraphs'] ?? []) as $paragraph): ?>
        <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endforeach; ?>

      <p class="terminal-line">&gt; focus: <?= htmlspecialchars($about['focus'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="about-visual" data-reveal aria-label="Визуальный блок: рабочая среда, сцена и инфраструктура">
      <div class="about-visual__topline">
        <span><?= htmlspecialchars($about['visual_top_left'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <span><?= htmlspecialchars($about['visual_top_right'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="about-visual__screen">
        <div class="about-visual__stage" aria-hidden="true">
          <span></span><span></span><span></span><span></span><span></span>
        </div>
        <div class="about-visual__console" aria-hidden="true">
          <i></i><i></i><i></i><i></i><i></i><i></i>
        </div>
      </div>

      <div class="about-visual__grid" aria-hidden="true">
        <?php foreach (($about['visual_tags'] ?? []) as $tag): ?>
          <span><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
