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
- PHP SQLite extension (`pdo_sqlite` / `php-sqlite3`).
- A web server such as Nginx or Apache.
- Write permissions for:
  - `storage/`
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
sudo chown -R www-data:www-data storage assets/img/uploads assets/img/qr admin/config.php
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

Current editable content is stored in SQLite:

```text
storage/site.sqlite
```

On first run the database is created and seeded from the legacy PHP array files:

```text
data/skills.php
data/projects.php
data/about.php
data/contacts.php
data/settings.php
data/tags.php
```

Those files are kept as a readable backup and fallback. If `pdo_sqlite` is missing or the database cannot be opened, the site can still read the old files.

To create or refresh the SQLite file manually on the server:

```bash
php bin/migrate-sqlite.php
```

If you want the database outside the web root, set `SITE_DB_PATH` in PHP-FPM/Nginx/Apache, for example:

```text
SITE_DB_PATH=/var/lib/egor-zvada/site.sqlite
```

For Nginx, deny direct access to `/storage`:

```nginx
location ^~ /storage/ {
  deny all;
  return 404;
}
```

Apache reads `storage/.htaccess`, which denies direct access there.

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
data/                     Legacy seed/fallback PHP array data
storage/                  SQLite database directory
assets/css/               Theme, layout, components, sections, responsive styles
assets/js/                Theme, navigation, admin behavior, project UI, motion
assets/img/               Images, uploads, QR, project visuals
assets/svg/               Logo and skill icons
```

## Notes

- The site is still in beta.
- Uploaded media should be backed up before destructive server operations.
- The current admin writes content to SQLite when `pdo_sqlite` is available.
- Three.js is loaded from CDN for the hero background. If it fails, the site falls back to canvas animation.
