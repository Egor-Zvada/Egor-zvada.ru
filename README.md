# egor-zvada.ru — v09-release

Финальная релизная итерация модульного PHP-лендинга. Версия готова к загрузке на сервер как первая рабочая версия сайта-портфолио.

## Запуск локально

```bash
cd egor-zvada-v09-release
php -S 127.0.0.1:8080
```

Открыть:

```text
http://127.0.0.1:8080
```

## Структура

```text
index.php                       собирает страницу из модулей
partials/                       head, header, footer, scripts, side-indicator
sections/                       hero, about, skills, projects, tavrida, contacts
data/                           редактируемые данные навыков, проектов и контактов
assets/css/                     базовые стили, тема, секции, анимации, адаптив
assets/js/                      тема, скролл, интерактив, проекты, canvas motion
assets/img/                     изображения и Open Graph
assets/video/                   будущие видео-loop для проектов
assets/svg/                     логотип, иконки, декор
errors/                         заготовка под страницы ошибок
deploy/                         примеры конфигов nginx/apache
```

## Что уже работает

- модульная PHP-сборка одной цельной страницы;
- dark/light mode по теме устройства + ручной переключатель;
- sticky header с мобильным меню;
- плавная навигация по секциям;
- боковой индикатор активного раздела на desktop;
- блок навыков с раскрытием;
- блок портфолио с фильтрами, раскрытием карточек и кнопкой «Показать ещё»;
- подготовка карточек проектов под 3 фото и video-loop;
- блок «Почему Таврида» с раскрытием текста;
- контакты и toast-сообщение вместо обычного alert;
- canvas-анимация на hero и в блоке Tavrida;
- упрощение motion при `prefers-reduced-motion`;
- SEO/Open Graph, `robots.txt`, `sitemap.xml`, `site.webmanifest`;
- базовые deploy-примеры для nginx/apache.

## Как редактировать данные

### Навыки

Файл:

```text
data/skills.php
```

### Проекты

Файл:

```text
data/projects.php
```

Проекты можно сортировать датой. Закрытое состояние карточки показывает основное изображение затемнённым. При раскрытии изображение показывается без затемнения. Если указано поле `video`, видео можно использовать как loop-превью.

### Контакты

Файл:

```text
data/contacts.php
```

## Загрузка на сервер

Минимально:

```bash
sudo mkdir -p /var/www/egor-zvada.ru
sudo rsync -av --delete ./ /var/www/egor-zvada.ru/
```

Для nginx можно использовать как основу:

```text
deploy/nginx-site.conf.sample
```

Для Apache:

```text
deploy/apache-vhost.conf.sample
```

## Важно

- Это первая релизная версия лендинга, не финальный «полноценный сайт с админкой».
- Реальные фотографии и видео проектов пока нужно заменить вручную в `assets/img/projects/` и `assets/video/projects/`.
- Open Graph image сейчас SVG-заглушка: `assets/img/og-image.svg`.
- Для продакшена лучше проверить страницу в браузере на реальном сервере и пройтись по мобильной версии.
# Egor-zvada.ru
