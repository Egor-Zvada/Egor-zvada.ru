<?php
$projects = include __DIR__ . '/../data/projects.php';
usort($projects, static fn($a, $b) => strcmp($b['date'], $a['date']));

$categories = [];
foreach ($projects as $project) {
  $categories[$project['category']] = $project['category_label'] ?? $project['category'];
}
$categoryOrder = ['all' => 'Все'] + $categories;
$hiddenCount = count(array_filter($projects, static fn($project) => !empty($project['is_hidden'])));
?>
<section class="section projects" id="projects" data-section="projects">
  <div class="container">
    <div class="projects__top">
      <div class="section__heading">
        <p class="section-kicker">03 / portfolio</p>
        <h2>Портфолио</h2>
      </div>
      <div class="projects__intro">
        <p>
          Здесь собраны направления, с которыми я работаю: мероприятия, свет, видео,
          интерактивные визуальные системы, инфраструктура и ИИ-инструменты.
        </p>
        <span class="terminal-line">&gt; projects_sorted: newest_first</span>
      </div>
    </div>

    <div class="project-filters" aria-label="Фильтр проектов">
      <?php foreach ($categoryOrder as $key => $label): ?>
        <button class="project-filter <?= $key === 'all' ? 'is-active' : '' ?>" type="button" data-project-filter="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="project-list" data-expandable="projects" data-project-list>
      <button class="project-card project-card--control project-card--collapse" type="button" data-project-collapse aria-expanded="true">
        <span class="project-card__index">00</span>
        <span class="project-card__control-title">Скрыть проекты</span>
        <span class="project-card__control-text">Вернуть компактный список портфолио</span>
        <span class="project-card__control-arrow" aria-hidden="true">←</span>
      </button>

      <?php foreach ($projects as $index => $project): ?>
        <?php
          $title = htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8');
          $description = htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8');
          $fullDescription = htmlspecialchars($project['full_description'] ?? $project['description'], ENT_QUOTES, 'UTF-8');
          $category = htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8');
          $categoryLabel = htmlspecialchars($project['category_label'] ?? $project['category'], ENT_QUOTES, 'UTF-8');
          $image = htmlspecialchars($project['image'] ?? '', ENT_QUOTES, 'UTF-8');
          $video = htmlspecialchars($project['video'] ?? '', ENT_QUOTES, 'UTF-8');
          $gallery = $project['gallery'] ?? [];
          $tools = $project['tools'] ?? [];
        ?>
        <article class="project-card <?= !empty($project['is_hidden']) ? 'is-hidden' : '' ?>" data-expandable-item data-project-card data-category="<?= $category ?>">
          <div class="project-card__media">
            <div class="project-media" data-project-media>
              <?php if ($video): ?>
                <video class="project-media__video" src="<?= $video ?>" muted loop playsinline preload="metadata" poster="<?= $image ?>"></video>
              <?php endif; ?>
              <?php if ($image): ?>
                <img class="project-media__image" src="<?= $image ?>" alt="<?= $title ?>" loading="lazy">
              <?php else: ?>
                <div class="media-card__placeholder">project media</div>
              <?php endif; ?>
              <span class="project-media__label"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
            </div>
          </div>

          <div class="project-card__body">
            <div class="project-card__meta-row">
              <p class="project-card__category">// <?= $categoryLabel ?></p>
              <time datetime="<?= htmlspecialchars($project['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(substr($project['date'], 0, 4), ENT_QUOTES, 'UTF-8') ?></time>
            </div>
            <h3><?= $title ?></h3>
            <p class="project-card__summary"><?= $description ?></p>
            <p class="project-card__full"><?= $fullDescription ?></p>

            <?php if (!empty($tools)): ?>
              <div class="project-card__tools" aria-label="Инструменты проекта">
                <?php foreach ($tools as $tool): ?>
                  <span><?= htmlspecialchars($tool, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="tags">
              <?php foreach ($project['tags'] as $tag): ?>
                <span class="tag">#<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endforeach; ?>
            </div>

            <?php if (!empty($gallery)): ?>
              <div class="project-gallery" aria-label="Дополнительные изображения">
                <?php foreach (array_slice($gallery, 0, 3) as $galleryImage): ?>
                  <img src="<?= htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <button class="project-card__toggle" type="button" data-project-toggle aria-expanded="false">
              Подробнее <span aria-hidden="true">→</span>
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($hiddenCount > 0): ?>
      <button class="button button--ghost section-action projects__more" type="button" data-expand-toggle="projects" data-open-label="Показать ещё проекты" data-close-label="Скрыть проекты" aria-expanded="false">
        Показать ещё проекты <span aria-hidden="true">→</span>
      </button>
    <?php endif; ?>
  </div>
</section>
