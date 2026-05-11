<?php
require_once __DIR__ . '/../lib/content.php';
$projects = ez_get_projects();
usort($projects, static fn($a, $b) => strcmp($b['date'], $a['date']));

$isProjectVideo = static function (string $path): bool {
  $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
  return in_array($extension, ['mp4', 'webm', 'mov', 'm4v'], true);
};

$categories = [];
foreach ($projects as $project) {
  $categories[$project['category']] = $project['category_label'] ?? $project['category'];
}
$categoryOrder = ['all' => 'Все'] + $categories;
$hiddenCount = max(0, count($projects) - 4);
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
          $rawImage = $project['image'] ?? '';
          $image = htmlspecialchars($rawImage, ENT_QUOTES, 'UTF-8');
          $rawVideo = (string) ($project['video'] ?? '');
          $video = htmlspecialchars($rawVideo, ENT_QUOTES, 'UTF-8');
          $gallery = array_values(array_filter($project['gallery'] ?? []));
          if ($rawImage && !in_array($rawImage, $gallery, true)) {
            array_unshift($gallery, $rawImage);
          }
          if ($rawVideo && !in_array($rawVideo, $gallery, true)) {
            $gallery[] = $rawVideo;
          }
          $initialMedia = $gallery[0] ?? ($rawImage !== '' ? $rawImage : $rawVideo);
          $initialIsVideo = is_string($initialMedia) && $isProjectVideo($initialMedia);
          $tools = $project['tools'] ?? [];
        ?>
        <article class="project-card <?= $index >= 4 ? 'is-hidden' : '' ?>" data-expandable-item data-project-card data-category="<?= $category ?>" data-project-gallery='<?= htmlspecialchars(json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
          <div class="project-card__media">
            <div class="project-media" data-project-media>
              <?php if ($initialMedia): ?>
                <video class="project-media__video <?= $initialIsVideo ? 'is-active' : '' ?>" src="<?= $initialIsVideo ? htmlspecialchars((string) $initialMedia, ENT_QUOTES, 'UTF-8') : '' ?>" muted loop playsinline preload="metadata" poster="<?= $image ?>" data-project-main-video <?= $initialIsVideo ? '' : 'hidden' ?>></video>
                <img class="project-media__image <?= $initialIsVideo ? '' : 'is-active' ?>" src="<?= !$initialIsVideo ? htmlspecialchars((string) $initialMedia, ENT_QUOTES, 'UTF-8') : '' ?>" alt="<?= $title ?>" loading="lazy" data-project-main-image <?= $initialIsVideo ? 'hidden' : '' ?>>
              <?php endif; ?>
              <?php if (!$initialMedia): ?>
                <div class="media-card__placeholder">project media</div>
              <?php endif; ?>
              <?php if (count($gallery) > 1): ?>
                <div class="project-media__controls" aria-label="Листание изображений проекта">
                  <button class="project-media__control" type="button" data-project-prev aria-label="Предыдущее изображение">←</button>
                  <button class="project-media__control" type="button" data-project-next aria-label="Следующее изображение">→</button>
                </div>
              <?php endif; ?>
              <button class="project-media__sound <?= $initialIsVideo ? 'is-visible' : '' ?>" type="button" data-project-sound aria-label="Включить звук" <?= $initialIsVideo ? '' : 'hidden' ?>>sound off</button>
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
                <?php foreach ($gallery as $galleryIndex => $galleryImage): ?>
                  <button class="project-gallery__item <?= $galleryIndex === 0 ? 'is-active' : '' ?>" type="button" data-project-gallery-item data-gallery-index="<?= $galleryIndex ?>" aria-label="Показать изображение <?= $galleryIndex + 1 ?>">
                    <?php if (is_string($galleryImage) && $isProjectVideo($galleryImage)): ?>
                      <span class="project-gallery__video-thumb">video</span>
                    <?php else: ?>
                      <img src="<?= htmlspecialchars($galleryImage, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                    <?php endif; ?>
                  </button>
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
