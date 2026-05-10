# egor-zvada.ru

Personal portfolio website for Egor Zvada.

Current version: `0.3-beta`.

The project is a modular PHP site with an internal admin panel. It is designed to run on a regular PHP-enabled web server without a build step.

## Features

- Modular PHP page assembly.
- Dark/light theme with system preference support and manual switching.
- Mobile navigation.
- Three.js/WebGL hero background with a canvas fallback.
- Editable skills, portfolio projects, tags, contacts, settings, and about text.
- Admin panel at `/admin`.
- Hidden admin entry from the footer version click counter.
- Image uploads to `/assets/img/uploads`.
- Project galleries with delete controls for uploaded files.
- Contact QR image generated from Telegram URL.
- Fetch-based admin saves: forms update without a full page reload.
- SEO basics: Open Graph, `robots.txt`, `sitemap.xml`, `site.webmanifest`.

## Requirements

- PHP 8.0 or newer is recommended.
- A web server such as Nginx or Apache.
- Write permissions for:
  - `data/`
  - `admin/config.php`
  - `assets/img/uploads/`
  - `assets/img/qr/`

No Node, npm, Composer, MariaDB, or PostgreSQL is required for the current version.

## Local Run

```bash
php -S 127.0.0.1:8080
```

Open:

```text
http://127.0.0.1:8080
```

## Deploy

On the server:

```bash
cd /var/www/egor-zvada.ru
git pull origin main
```

If uploads or data saving fail, check permissions:

```bash
sudo chown -R www-data:www-data data assets/img/uploads assets/img/qr admin/config.php
```

Use the web server user that matches your server setup. On some systems it may be `nginx`, `apache`, or another user instead of `www-data`.

## Admin Panel

URL:

```text
/admin
```

The admin panel currently manages:

- Skills.
- Portfolio projects.
- About section text.
- Tags and project categories.
- Contacts.
- Footer version.
- Hidden admin click count.
- Admin login and password.

The admin interface supports dark/light theme and uses fetch-based saving, so most edits do not trigger a full browser reload.

## Content Data

Current data is stored in PHP array files:

```text
data/skills.php
data/projects.php
data/about.php
data/contacts.php
data/settings.php
data/tags.php
```

This is intentional for the beta stage. A future version can migrate this data to SQLite.

## Media

Uploaded files are stored here:

```text
assets/img/uploads/
```

The Tavrida static image should be placed manually here:

```text
assets/img/tavrida/tavrida.jpg
```

The About section image should be placed manually here:

```text
assets/img/about/about.jpg
```

If these files are missing, the site uses its fallback visuals.

## Project Structure

```text
index.php                 Main page assembly
partials/                 Head, header, footer, scripts, indicators
sections/                 Hero, about, skills, projects, Tavrida, contacts
admin/                    Admin panel and config
data/                     Editable PHP array data
assets/css/               Theme, layout, components, sections, responsive styles
assets/js/                Theme, navigation, admin behavior, project UI, motion
assets/img/               Images, uploads, QR, project visuals
assets/svg/               Logo and skill icons
```

## Notes

- The site is still in beta.
- Uploaded media should be backed up before destructive server operations.
- The current admin is file-based. SQLite is the recommended next storage step when the data model grows.
- Three.js is loaded from CDN for the hero background. If it fails, the site falls back to canvas animation.
