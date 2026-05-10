<?php

return array (
  0 => 
  array (
    'title' => 'GrandMA 2/3',
    'description' => 'Программирование и работа со сценическим светом: шоу, сцены, эффекты и управление световой логикой.',
    'icon' => '/assets/img/uploads/ma-20260510-073741-ee7181.png',
    'default_icon' => '/assets/svg/icons/grandma3.svg',
    'invert_icon' => true,
    'order' => 1,
    'level' => 'light control',
    'category' => 'Свет',
    'stack' => 
    array (
      0 => 'MA Lighting',
      1 => 'DMX',
      2 => 'Art-Net',
      3 => 'sACN',
    ),
  ),
  1 => 
  array (
    'title' => 'TouchDesigner',
    'description' => 'Интерактивные визуальные системы, генеративная графика, real-time визуализация и эксперименты с медиа.',
    'icon' => '/assets/img/uploads/images-20260510-060851-f29e46.png',
    'level' => 'visual systems',
    'category' => 'Интерактив',
    'stack' => 
    array (
      0 => 'Realtime',
      1 => 'OSC',
      2 => 'NDI',
      3 => 'Generative',
    ),
    'is_hidden' => false,
  ),
  2 => 
  array (
    'title' => 'Resolume Arena',
    'description' => 'Видеоконтент, VJ-подача, мэппинг, работа с экранами и медиасерверная логика для мероприятий.',
    'icon' => '/assets/img/uploads/arena-logo-20260510-073923-8866ba.svg',
    'default_icon' => '/assets/svg/icons/resolume.svg',
    'order' => 3,
    'level' => 'media server',
    'category' => 'Видео',
    'stack' => 
    array (
      0 => 'VJ',
      1 => 'Mapping',
      2 => 'LED',
      3 => 'Media',
    ),
  ),
  3 => 
  array (
    'title' => 'Final Cut Pro',
    'description' => 'Монтаж, подготовка роликов, бэкстейджей, промо и видеоматериалов для экранов и соцсетей.',
    'icon' => '/assets/img/uploads/final-cut-pro-20260510-074103-92b07c.png',
    'default_icon' => '/assets/svg/icons/finalcut.svg',
    'order' => 4,
    'level' => 'video editing',
    'category' => 'Монтаж',
    'stack' => 
    array (
      0 => 'Video',
      1 => 'Cut',
      2 => 'Content',
    ),
  ),
  4 => 
  array (
    'title' => 'Linux / Windows',
    'description' => 'Администрирование серверов, рабочих станций, сетей, служб, резервирования и базовой инфраструктуры.',
    'icon' => '/assets/img/uploads/terminal-icon-500x500-20260510-075848-9e07a6.svg',
    'default_icon' => '/assets/svg/icons/systems.svg',
    'invert_icon' => true,
    'order' => 5,
    'level' => 'system admin',
    'category' => 'Системы',
    'stack' => 
    array (
      0 => 'Debian',
      1 => 'Windows',
      2 => 'Network',
      3 => 'Backup',
    ),
  ),
  5 => 
  array (
    'title' => 'Nginx / Apache / PHP',
    'description' => 'Веб-серверы, reverse proxy, домены, HTTPS, PHP-проекты и базовая серверная архитектура для сайтов.',
    'icon' => '/assets/svg/icons/infrastructure.svg',
    'level' => 'web infra',
    'category' => 'Инфраструктура',
    'stack' => 
    array (
      0 => 'Nginx',
      1 => 'Apache',
      2 => 'PHP',
      3 => 'TLS',
    ),
    'is_hidden' => true,
  ),
  6 => 
  array (
    'title' => 'Трансляции / NDI',
    'description' => 'Работа с видеоисточниками, сигналами, потоками, экранами и технической частью live-подачи.',
    'icon' => '/assets/svg/icons/broadcast.svg',
    'level' => 'broadcast',
    'category' => 'Live',
    'stack' => 
    array (
      0 => 'NDI',
      1 => 'OBS',
      2 => 'SRT/RTMP',
      3 => 'Screens',
    ),
    'is_hidden' => true,
  ),
  7 => 
  array (
    'title' => 'Локальные ИИ',
    'description' => 'Запуск и настройка локальных моделей, генеративных пайплайнов и ИИ-инструментов без лишней зависимости от облака.',
    'icon' => '/assets/svg/icons/ai.svg',
    'level' => 'local ai',
    'category' => 'ИИ',
    'stack' => 
    array (
      0 => 'LLM',
      1 => 'Stable Diffusion',
      2 => 'ComfyUI',
    ),
    'is_hidden' => true,
  ),
  8 => 
  array (
    'title' => 'Генерация фото / видео',
    'description' => 'Создание референсов, визуальных материалов, фото, роликов, идей и контента для мероприятий и проектов.',
    'icon' => '/assets/svg/icons/generation.svg',
    'level' => 'ai content',
    'category' => 'Контент',
    'stack' => 
    array (
      0 => 'Images',
      1 => 'Video',
      2 => 'Refs',
      3 => 'Concepts',
    ),
    'is_hidden' => true,
  ),
  9 => 
  array (
    'title' => 'Чат-боты / AI API',
    'description' => 'Интеграция ИИ в рабочие процессы: боты, подсказки, автоматизация рутинных действий и генерация материалов.',
    'icon' => '/assets/svg/icons/chatbot.svg',
    'level' => 'ai workflow',
    'category' => 'Автоматизация',
    'stack' => 
    array (
      0 => 'ChatGPT',
      1 => 'API',
      2 => 'Bots',
      3 => 'Prompts',
    ),
    'is_hidden' => true,
  ),
  10 => 
  array (
    'title' => 'Сети / шоу-протоколы',
    'description' => 'Понимание сетевой связки площадки: устройства, адресация, сигналы, протоколы и стабильность передачи.',
    'icon' => '/assets/svg/icons/network.svg',
    'level' => 'signal flow',
    'category' => 'Сети',
    'stack' => 
    array (
      0 => 'VLAN',
      1 => 'Art-Net',
      2 => 'sACN',
      3 => 'NDI',
    ),
    'is_hidden' => true,
  ),
);
