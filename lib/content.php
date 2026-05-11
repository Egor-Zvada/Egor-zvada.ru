<?php
declare(strict_types=1);

function ez_root(): string {
  return dirname(__DIR__);
}

function ez_db_path(): string {
  $envPath = getenv('SITE_DB_PATH');
  if (is_string($envPath) && trim($envPath) !== '') {
    return $envPath;
  }

  return ez_root() . '/storage/site.sqlite';
}

function ez_json_encode(array $value): string {
  return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function ez_json_decode(?string $value): array {
  if (!is_string($value) || trim($value) === '') {
    return [];
  }

  $decoded = json_decode($value, true);
  return is_array($decoded) ? array_values($decoded) : [];
}

function ez_default_skill_icon_path(): string {
  return '/assets/svg/icons/ai.svg';
}

function ez_default_project_image_path(): string {
  return '/assets/img/projects/ai.svg';
}

function ez_normalize_asset_path(?string $path): string {
  $path = trim((string) $path);
  if ($path === '') {
    return '';
  }

  $oldProjectDefaults = [
    '/assets/img/projects/broadcast.svg',
    '/assets/img/projects/esports.svg',
    '/assets/img/projects/kvn.svg',
    '/assets/img/projects/light.svg',
    '/assets/img/projects/network.svg',
    '/assets/img/projects/resolume.svg',
    '/assets/img/projects/systems.svg',
    '/assets/img/projects/touchdesigner.svg',
  ];

  if (in_array($path, $oldProjectDefaults, true)) {
    return ez_default_project_image_path();
  }

  if (preg_match('#^/assets/svg/icons/[a-z0-9_-]+\.svg$#i', $path)) {
    return ez_default_skill_icon_path();
  }

  return match ($path) {
    '/assets/svg/logo.svg' => '/assets/img/brand/logo.svg',
    '/assets/img/og-image.svg' => '/assets/img/brand/og-image.svg',
    default => $path,
  };
}

function ez_is_local_asset_path(string $path): bool {
  return strpos($path, '/assets/') === 0;
}

function ez_is_uploaded_asset_path(string $path): bool {
  return strpos($path, '/assets/img/uploads/') === 0
    || strpos($path, '/assets/video/uploads/') === 0;
}

function ez_public_asset_exists(string $path): bool {
  return !ez_is_local_asset_path($path) || is_file(ez_root() . $path);
}

function ez_asset_or_default(string $path, string $default): string {
  $path = ez_normalize_asset_path($path);
  if ($path === '') {
    return $default;
  }

  if (ez_is_uploaded_asset_path($path) && !ez_public_asset_exists($path)) {
    return $default;
  }

  return $path;
}

function ez_normalize_asset_list(array $paths): array {
  $normalized = [];
  foreach ($paths as $path) {
    $path = ez_normalize_asset_path(is_string($path) ? $path : '');
    if ($path !== '' && ez_public_asset_exists($path)) {
      $normalized[] = $path;
    }
  }

  return array_values(array_unique($normalized));
}

function ez_db_available(): bool {
  return class_exists(PDO::class)
    && in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function ez_db(): ?PDO {
  static $pdo = null;
  static $initialized = false;

  if ($initialized) {
    return $pdo;
  }

  $initialized = true;
  if (!ez_db_available()) {
    return null;
  }

  $path = ez_db_path();
  $dir = dirname($path);
  if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    return null;
  }

  $pdo = new PDO('sqlite:' . $path);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec('PRAGMA foreign_keys = ON');
  $pdo->exec('PRAGMA journal_mode = WAL');
  ez_db_migrate($pdo);
  ez_db_seed_if_empty($pdo);

  return $pdo;
}

function ez_db_migrate(PDO $pdo): void {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
      item_key TEXT PRIMARY KEY,
      value TEXT NOT NULL DEFAULT '',
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS contacts (
      item_key TEXT PRIMARY KEY,
      value TEXT NOT NULL DEFAULT '',
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS about (
      id INTEGER PRIMARY KEY CHECK (id = 1),
      lead TEXT NOT NULL DEFAULT '',
      paragraphs_json TEXT NOT NULL DEFAULT '[]',
      focus TEXT NOT NULL DEFAULT '',
      visual_top_left TEXT NOT NULL DEFAULT '',
      visual_top_right TEXT NOT NULL DEFAULT '',
      visual_tags_json TEXT NOT NULL DEFAULT '[]',
      gallery_json TEXT NOT NULL DEFAULT '[]',
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS skills (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      description TEXT NOT NULL DEFAULT '',
      icon TEXT NOT NULL DEFAULT '',
      default_icon TEXT NOT NULL DEFAULT '',
      invert_icon INTEGER NOT NULL DEFAULT 0,
      sort_order INTEGER NOT NULL DEFAULT 999,
      level TEXT NOT NULL DEFAULT '',
      category TEXT NOT NULL DEFAULT '',
      stack_json TEXT NOT NULL DEFAULT '[]',
      is_hidden INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS projects (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      slug TEXT NOT NULL,
      title TEXT NOT NULL,
      date TEXT NOT NULL DEFAULT '',
      category TEXT NOT NULL DEFAULT '',
      category_label TEXT NOT NULL DEFAULT '',
      description TEXT NOT NULL DEFAULT '',
      full_description TEXT NOT NULL DEFAULT '',
      image TEXT NOT NULL DEFAULT '',
      default_image TEXT NOT NULL DEFAULT '',
      gallery_json TEXT NOT NULL DEFAULT '[]',
      video TEXT DEFAULT NULL,
      tags_json TEXT NOT NULL DEFAULT '[]',
      tools_json TEXT NOT NULL DEFAULT '[]',
      is_hidden INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS tags (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      type TEXT NOT NULL,
      item_key TEXT NOT NULL,
      label TEXT NOT NULL,
      sort_order INTEGER NOT NULL DEFAULT 999,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE(type, item_key)
    );

    CREATE TABLE IF NOT EXISTS media (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      path TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL DEFAULT 'image',
      title TEXT NOT NULL DEFAULT '',
      alt TEXT NOT NULL DEFAULT '',
      caption TEXT NOT NULL DEFAULT '',
      meta_json TEXT NOT NULL DEFAULT '{}',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS news (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      slug TEXT NOT NULL UNIQUE,
      title TEXT NOT NULL,
      summary TEXT NOT NULL DEFAULT '',
      body TEXT NOT NULL DEFAULT '',
      image TEXT NOT NULL DEFAULT '',
      status TEXT NOT NULL DEFAULT 'draft',
      published_at TEXT DEFAULT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS services (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      slug TEXT NOT NULL UNIQUE,
      title TEXT NOT NULL,
      summary TEXT NOT NULL DEFAULT '',
      body TEXT NOT NULL DEFAULT '',
      status TEXT NOT NULL DEFAULT 'draft',
      sort_order INTEGER NOT NULL DEFAULT 999,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      slug TEXT NOT NULL UNIQUE,
      title TEXT NOT NULL,
      event_date TEXT DEFAULT NULL,
      status TEXT NOT NULL DEFAULT 'draft',
      settings_json TEXT NOT NULL DEFAULT '{}',
      content_json TEXT NOT NULL DEFAULT '{}',
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
  ");
}

function ez_seed_file(string $name, array $fallback = []): array {
  $file = ez_root() . '/data/' . $name . '.php';
  if (!is_file($file)) {
    return $fallback;
  }

  $items = require $file;
  return is_array($items) ? $items : $fallback;
}

function ez_db_seed_if_empty(PDO $pdo): void {
  $settingsCount = (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
  $skillsCount = (int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn();
  $projectsCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
  if ($settingsCount > 0 || $skillsCount > 0 || $projectsCount > 0) {
    return;
  }

  ez_save_settings(ez_seed_file('settings'), $pdo);
  ez_save_contacts(ez_seed_file('contacts'), $pdo);
  ez_save_about(ez_seed_file('about'), $pdo);
  ez_save_skills(ez_seed_file('skills'), $pdo);
  ez_save_projects(ez_seed_file('projects'), $pdo);
  ez_save_tags(ez_seed_file('tags'), $pdo);
}

function ez_kv_all(string $table, ?PDO $pdo = null): array {
  $pdo ??= ez_db();
  if (!$pdo) return [];

  $rows = $pdo->query("SELECT item_key, value FROM {$table}")->fetchAll();
  $items = [];
  foreach ($rows as $row) {
    $items[$row['item_key']] = $row['value'];
  }
  return $items;
}

function ez_replace_kv(string $table, array $items, ?PDO $pdo = null): void {
  $pdo ??= ez_db();
  if (!$pdo) return;

  $pdo->beginTransaction();
  $pdo->exec("DELETE FROM {$table}");
  $statement = $pdo->prepare("INSERT INTO {$table}(item_key, value, updated_at) VALUES(:key, :value, CURRENT_TIMESTAMP)");
  foreach ($items as $key => $value) {
    $statement->execute([
      ':key' => (string) $key,
      ':value' => is_array($value) ? ez_json_encode($value) : (string) $value,
    ]);
  }
  $pdo->commit();
}

function ez_get_settings(): array {
  $pdo = ez_db();
  return $pdo ? ez_kv_all('settings', $pdo) : ez_seed_file('settings');
}

function ez_save_settings(array $items, ?PDO $pdo = null): void {
  ez_replace_kv('settings', $items, $pdo);
}

function ez_get_contacts(): array {
  $pdo = ez_db();
  return $pdo ? ez_kv_all('contacts', $pdo) : ez_seed_file('contacts');
}

function ez_save_contacts(array $items, ?PDO $pdo = null): void {
  ez_replace_kv('contacts', $items, $pdo);
}

function ez_get_about(): array {
  $pdo = ez_db();
  if (!$pdo) return ez_seed_file('about');

  $row = $pdo->query('SELECT * FROM about WHERE id = 1')->fetch();
  if (!$row) return [];

  return [
    'lead' => $row['lead'] ?? '',
    'paragraphs' => ez_json_decode($row['paragraphs_json'] ?? '[]'),
    'focus' => $row['focus'] ?? '',
    'visual_top_left' => $row['visual_top_left'] ?? '',
    'visual_top_right' => $row['visual_top_right'] ?? '',
    'visual_tags' => ez_json_decode($row['visual_tags_json'] ?? '[]'),
    'gallery' => ez_json_decode($row['gallery_json'] ?? '[]'),
  ];
}

function ez_save_about(array $about, ?PDO $pdo = null): void {
  $pdo ??= ez_db();
  if (!$pdo) return;

  $statement = $pdo->prepare('
    INSERT INTO about(id, lead, paragraphs_json, focus, visual_top_left, visual_top_right, visual_tags_json, gallery_json, updated_at)
    VALUES(1, :lead, :paragraphs, :focus, :visual_top_left, :visual_top_right, :visual_tags, :gallery, CURRENT_TIMESTAMP)
    ON CONFLICT(id) DO UPDATE SET
      lead = excluded.lead,
      paragraphs_json = excluded.paragraphs_json,
      focus = excluded.focus,
      visual_top_left = excluded.visual_top_left,
      visual_top_right = excluded.visual_top_right,
      visual_tags_json = excluded.visual_tags_json,
      gallery_json = excluded.gallery_json,
      updated_at = CURRENT_TIMESTAMP
  ');
  $statement->execute([
    ':lead' => (string) ($about['lead'] ?? ''),
    ':paragraphs' => ez_json_encode($about['paragraphs'] ?? []),
    ':focus' => (string) ($about['focus'] ?? ''),
    ':visual_top_left' => (string) ($about['visual_top_left'] ?? ''),
    ':visual_top_right' => (string) ($about['visual_top_right'] ?? ''),
    ':visual_tags' => ez_json_encode($about['visual_tags'] ?? []),
    ':gallery' => ez_json_encode($about['gallery'] ?? []),
  ]);
}

function ez_get_skills(): array {
  $pdo = ez_db();
  if (!$pdo) return array_values(ez_seed_file('skills'));

  $rows = $pdo->query('SELECT * FROM skills ORDER BY sort_order ASC, id ASC')->fetchAll();
  return array_map(static function (array $row): array {
    return [
      'title' => $row['title'] ?? '',
      'description' => $row['description'] ?? '',
      'icon' => ez_asset_or_default((string) ($row['icon'] ?? ''), ez_default_skill_icon_path()),
      'default_icon' => ez_normalize_asset_path($row['default_icon'] ?? '') ?: ez_default_skill_icon_path(),
      'invert_icon' => (bool) ($row['invert_icon'] ?? 0),
      'order' => (int) ($row['sort_order'] ?? 999),
      'level' => $row['level'] ?? '',
      'category' => $row['category'] ?? '',
      'stack' => ez_json_decode($row['stack_json'] ?? '[]'),
      'is_hidden' => (bool) ($row['is_hidden'] ?? 0),
    ];
  }, $rows);
}

function ez_save_skills(array $skills, ?PDO $pdo = null): void {
  $pdo ??= ez_db();
  if (!$pdo) return;

  $pdo->beginTransaction();
  $pdo->exec('DELETE FROM skills');
  $statement = $pdo->prepare('
    INSERT INTO skills(title, description, icon, default_icon, invert_icon, sort_order, level, category, stack_json, is_hidden, updated_at)
    VALUES(:title, :description, :icon, :default_icon, :invert_icon, :sort_order, :level, :category, :stack_json, :is_hidden, CURRENT_TIMESTAMP)
  ');
  foreach (array_values($skills) as $index => $skill) {
    $statement->execute([
      ':title' => (string) ($skill['title'] ?? ''),
      ':description' => (string) ($skill['description'] ?? ''),
      ':icon' => ez_normalize_asset_path($skill['icon'] ?? '') ?: ez_default_skill_icon_path(),
      ':default_icon' => ez_normalize_asset_path($skill['default_icon'] ?? '') ?: ez_default_skill_icon_path(),
      ':invert_icon' => !empty($skill['invert_icon']) ? 1 : 0,
      ':sort_order' => max(1, (int) ($skill['order'] ?? ($index + 1))),
      ':level' => (string) ($skill['level'] ?? ''),
      ':category' => (string) ($skill['category'] ?? ''),
      ':stack_json' => ez_json_encode($skill['stack'] ?? []),
      ':is_hidden' => !empty($skill['is_hidden']) ? 1 : 0,
    ]);
  }
  $pdo->commit();
}

function ez_get_projects(): array {
  $pdo = ez_db();
  if (!$pdo) return array_values(ez_seed_file('projects'));

  $rows = $pdo->query('SELECT * FROM projects ORDER BY date DESC, id DESC')->fetchAll();
  return array_map(static function (array $row): array {
    return [
      'id' => $row['slug'] ?? '',
      'title' => $row['title'] ?? '',
      'date' => $row['date'] ?? '',
      'category' => $row['category'] ?? '',
      'category_label' => $row['category_label'] ?? '',
      'description' => $row['description'] ?? '',
      'full_description' => $row['full_description'] ?? '',
      'image' => ez_asset_or_default((string) ($row['image'] ?? ''), ez_default_project_image_path()),
      'default_image' => ez_normalize_asset_path($row['default_image'] ?? '') ?: ez_default_project_image_path(),
      'gallery' => ez_normalize_asset_list(ez_json_decode($row['gallery_json'] ?? '[]')),
      'video' => ez_asset_or_default((string) ($row['video'] ?: ''), '') ?: null,
      'tags' => ez_json_decode($row['tags_json'] ?? '[]'),
      'tools' => ez_json_decode($row['tools_json'] ?? '[]'),
      'is_hidden' => (bool) ($row['is_hidden'] ?? 0),
    ];
  }, $rows);
}

function ez_save_projects(array $projects, ?PDO $pdo = null): void {
  $pdo ??= ez_db();
  if (!$pdo) return;

  $pdo->beginTransaction();
  $pdo->exec('DELETE FROM projects');
  $statement = $pdo->prepare('
    INSERT INTO projects(slug, title, date, category, category_label, description, full_description, image, default_image, gallery_json, video, tags_json, tools_json, is_hidden, updated_at)
    VALUES(:slug, :title, :date, :category, :category_label, :description, :full_description, :image, :default_image, :gallery_json, :video, :tags_json, :tools_json, :is_hidden, CURRENT_TIMESTAMP)
  ');
  foreach (array_values($projects) as $project) {
    $statement->execute([
      ':slug' => (string) ($project['id'] ?? ''),
      ':title' => (string) ($project['title'] ?? ''),
      ':date' => (string) ($project['date'] ?? ''),
      ':category' => (string) ($project['category'] ?? ''),
      ':category_label' => (string) ($project['category_label'] ?? ''),
      ':description' => (string) ($project['description'] ?? ''),
      ':full_description' => (string) ($project['full_description'] ?? ''),
      ':image' => ez_normalize_asset_path($project['image'] ?? '') ?: ez_default_project_image_path(),
      ':default_image' => ez_normalize_asset_path($project['default_image'] ?? '') ?: ez_default_project_image_path(),
      ':gallery_json' => ez_json_encode(ez_normalize_asset_list($project['gallery'] ?? [])),
      ':video' => ez_normalize_asset_path($project['video'] ?? '') ?: null,
      ':tags_json' => ez_json_encode($project['tags'] ?? []),
      ':tools_json' => ez_json_encode($project['tools'] ?? []),
      ':is_hidden' => !empty($project['is_hidden']) ? 1 : 0,
    ]);
  }
  $pdo->commit();
}

function ez_get_tags(): array {
  $pdo = ez_db();
  if (!$pdo) return ez_seed_file('tags');

  $rows = $pdo->query('SELECT * FROM tags ORDER BY type ASC, sort_order ASC, id ASC')->fetchAll();
  $tags = [
    'skill_tags' => [],
    'project_tags' => [],
    'project_categories' => [],
  ];

  foreach ($rows as $row) {
    if ($row['type'] === 'skill') {
      $tags['skill_tags'][] = $row['label'];
    } elseif ($row['type'] === 'project') {
      $tags['project_tags'][] = $row['label'];
    } elseif ($row['type'] === 'project_category') {
      $tags['project_categories'][$row['item_key']] = $row['label'];
    }
  }

  return $tags;
}

function ez_save_tags(array $tags, ?PDO $pdo = null): void {
  $pdo ??= ez_db();
  if (!$pdo) return;

  $pdo->beginTransaction();
  $pdo->exec('DELETE FROM tags');
  $statement = $pdo->prepare('
    INSERT INTO tags(type, item_key, label, sort_order, updated_at)
    VALUES(:type, :key, :label, :sort_order, CURRENT_TIMESTAMP)
  ');
  $insertList = static function (string $type, array $items) use ($statement): void {
    foreach (array_values($items) as $index => $label) {
      $label = trim((string) $label);
      if ($label === '') continue;
      $statement->execute([
        ':type' => $type,
        ':key' => $label,
        ':label' => $label,
        ':sort_order' => $index + 1,
      ]);
    }
  };

  $insertList('skill', $tags['skill_tags'] ?? []);
  $insertList('project', $tags['project_tags'] ?? []);
  $categoryOrder = 1;
  foreach (($tags['project_categories'] ?? []) as $key => $label) {
    $key = trim((string) $key);
    if ($key === '') continue;
    $statement->execute([
      ':type' => 'project_category',
      ':key' => $key,
      ':label' => trim((string) $label) ?: $key,
      ':sort_order' => $categoryOrder,
    ]);
    $categoryOrder += 1;
  }
  $pdo->commit();
}

function ez_load_items_for_file(string $file): array {
  if (basename($file) === 'skills.php') {
    return ez_get_skills();
  }

  if (basename($file) === 'projects.php') {
    return ez_get_projects();
  }

  if (!is_file($file)) {
    return [];
  }

  $items = require $file;
  return is_array($items) ? array_values($items) : [];
}

function ez_load_assoc_for_file(string $file): array {
  switch (basename($file)) {
    case 'settings.php':
      return ez_get_settings();
    case 'contacts.php':
      return ez_get_contacts();
    case 'about.php':
      return ez_get_about();
    case 'tags.php':
      return ez_get_tags();
    default:
      if (!is_file($file)) {
        return [];
      }
      $items = require $file;
      return is_array($items) ? $items : [];
  }
}

function ez_save_items_for_file(string $file, array $items): bool {
  $pdo = ez_db();
  if (!$pdo) {
    return false;
  }

  switch (basename($file)) {
    case 'skills.php':
      ez_save_skills($items, $pdo);
      return true;
    case 'projects.php':
      ez_save_projects($items, $pdo);
      return true;
    default:
      return false;
  }
}

function ez_save_assoc_for_file(string $file, array $items): bool {
  $pdo = ez_db();
  if (!$pdo) {
    return false;
  }

  switch (basename($file)) {
    case 'settings.php':
      ez_save_settings($items, $pdo);
      return true;
    case 'contacts.php':
      ez_save_contacts($items, $pdo);
      return true;
    case 'about.php':
      ez_save_about($items, $pdo);
      return true;
    case 'tags.php':
      ez_save_tags($items, $pdo);
      return true;
    default:
      return false;
  }
}
