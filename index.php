<?php
require_once __DIR__ . '/lib/content.php';

$siteSettings = ez_get_settings();
$pageTitle = 'Егор Звада — портфолио';
$pageDescription = 'Системный администратор и технический специалист мероприятий. Сцена, медиа, ИИ и цифровая инфраструктура.';
$pageVersion = $siteSettings['version'] ?? '0.2-beta';
$pageUrl = 'https://egor-zvada.ru/';
$pageImage = 'https://egor-zvada.ru/assets/img/og-image.svg';
?>
<?php include __DIR__ . '/partials/head.php'; ?>
<?php include __DIR__ . '/partials/header.php'; ?>
<?php include __DIR__ . '/partials/side-indicator.php'; ?>

<main class="site-main" id="top">
  <?php include __DIR__ . '/sections/hero.php'; ?>
  <?php include __DIR__ . '/sections/about.php'; ?>
  <?php include __DIR__ . '/sections/skills.php'; ?>
  <?php include __DIR__ . '/sections/projects.php'; ?>
  <?php include __DIR__ . '/sections/tavrida.php'; ?>
  <?php include __DIR__ . '/sections/contacts.php'; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
<?php include __DIR__ . '/partials/scripts.php'; ?>
