<?php
$skills = include __DIR__ . '/../data/skills.php';
foreach ($skills as $skillIndex => &$skill) {
  $skill['_source_index'] = $skillIndex;
}
unset($skill);
usort($skills, static function ($a, $b) {
  $aOrder = (int) ($a['order'] ?? (($a['_source_index'] ?? 0) + 1));
  $bOrder = (int) ($b['order'] ?? (($b['_source_index'] ?? 0) + 1));
  if ($aOrder === $bOrder) {
    return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
  }
  return $aOrder <=> $bOrder;
});

$totalSkills = count($skills);
$visibleCount = min(6, $totalSkills);
$hiddenCount = max(0, $totalSkills - 6);
?>
<section class="section skills" id="skills" data-section="skills">
  <div class="container">
    <div class="skills__top">
      <div class="section__heading">
        <p class="section-kicker">02 / skills</p>
        <h2>Навыки и инструменты</h2>
      </div>
      <div class="skills__intro">
        <p>
          Здесь не абстрактные “soft skills”, а реальные инструменты, с которыми я работаю:
          от системного администрирования и Python до сценического света, видео, TouchDesigner и ИИ-пайплайнов.
        </p>
        <span class="terminal-line">&gt; skills_loaded: <?= $visibleCount ?> / <?= $totalSkills ?></span>
      </div>
    </div>

    <div class="skills-panel" data-skills-panel>
      <div class="skills-panel__rail" aria-hidden="true">
        <span>systems</span>
        <span>stage</span>
        <span>media</span>
        <span>ai</span>
      </div>

      <div class="skills-grid" data-expandable="skills">
        <button class="skill-card skill-card--control skill-card--collapse" type="button" data-expand-toggle="skills" data-open-label="Показать больше" data-close-label="Скрыть" aria-expanded="false">
          <span class="skill-card__index">00</span>
          <span class="skill-card__control-title">Скрыть</span>
          <span class="skill-card__control-text">Вернуть компактный список навыков</span>
          <span class="skill-card__arrow" aria-hidden="true">←</span>
        </button>

        <?php foreach ($skills as $index => $skill): ?>
          <article class="skill-card <?= $index >= 6 ? 'is-hidden' : '' ?>" data-expandable-item>
            <div class="skill-card__head">
              <span class="skill-card__index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
              <span class="skill-card__category"><?= htmlspecialchars($skill['category'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <img class="skill-card__icon <?= strpos((string) ($skill['icon'] ?? ''), '/assets/img/uploads/') === 0 ? 'skill-card__icon--uploaded' : '' ?>" src="<?= htmlspecialchars($skill['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="48" height="48">
            <div class="skill-card__body">
              <h3><?= htmlspecialchars($skill['title'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars($skill['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="skill-card__meta">
              <span class="tag">&gt; <?= htmlspecialchars($skill['level'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if (!empty($skill['stack'])): ?>
                <div class="skill-card__stack" aria-label="Ключевые технологии">
                  <?php foreach ($skill['stack'] as $item): ?>
                    <span><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>

        <?php if ($hiddenCount > 0): ?>
          <button class="skill-card skill-card--control skill-card--more" type="button" data-expand-toggle="skills" data-open-label="Показать больше" data-close-label="Скрыть" aria-expanded="false">
            <span class="skill-card__index">+</span>
            <span class="skill-card__control-title">Показать больше</span>
            <span class="skill-card__control-text">Открыть ещё <?= $hiddenCount ?> направлений и инструментов</span>
            <span class="skill-card__arrow" aria-hidden="true">→</span>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
