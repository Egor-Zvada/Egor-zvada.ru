<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="<?= htmlspecialchars($pageDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="author" content="Егор Звада">
  <meta name="robots" content="index, follow">
  <meta name="color-scheme" content="dark light">
  <meta name="theme-color" content="#050505" media="(prefers-color-scheme: dark)">
  <meta name="theme-color" content="#f5f5f2" media="(prefers-color-scheme: light)">
  <link rel="canonical" href="<?= htmlspecialchars($pageUrl ?? 'https://egor-zvada.ru/', ENT_QUOTES, 'UTF-8') ?>">

  <meta property="og:type" content="website">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Егор Звада — портфолио', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:url" content="<?= htmlspecialchars($pageUrl ?? 'https://egor-zvada.ru/', ENT_QUOTES, 'UTF-8') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($pageImage ?? 'https://egor-zvada.ru/assets/img/brand/og-image.svg', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? 'Егор Звада — портфолио', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($pageImage ?? 'https://egor-zvada.ru/assets/img/brand/og-image.svg', ENT_QUOTES, 'UTF-8') ?>">

  <title><?= htmlspecialchars($pageTitle ?? 'egor_zvada', ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="icon" href="/assets/img/brand/logo.svg" type="image/svg+xml">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="preload" href="/assets/css/theme.css" as="style">
  <link rel="stylesheet" href="/assets/css/base.css">
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
  <link rel="stylesheet" href="/assets/css/components.css">
  <link rel="stylesheet" href="/assets/css/sections.css">
  <link rel="stylesheet" href="/assets/css/animations.css">
  <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="site-body" data-version="<?= htmlspecialchars($pageVersion ?? 'dev', ENT_QUOTES, 'UTF-8') ?>">
<a class="skip-link" href="#hero">Перейти к содержимому</a>
