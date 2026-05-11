<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $isHttps,
  'httponly' => true,
  'samesite' => 'Strict',
]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$root = dirname(__DIR__);
$config = require __DIR__ . '/config.php';
$skillsFile = $root . '/data/skills.php';
$projectsFile = $root . '/data/projects.php';
$settingsFile = $root . '/data/settings.php';
$contactsFile = $root . '/data/contacts.php';
$aboutFile = $root . '/data/about.php';
$tagsFile = $root . '/data/tags.php';
$uploadWebDir = '/assets/img/uploads';
$uploadFsDir = $root . $uploadWebDir;

function h($value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
  return !empty($_SESSION['admin_logged_in']);
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function check_csrf(): void {
  $token = $_POST['csrf_token'] ?? '';
  if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    exit('Bad CSRF token');
  }
}

function verify_admin_password(array $config, string $username, string $password): bool {
  if (!hash_equals((string) ($config['username'] ?? ''), $username)) {
    return false;
  }

  $passwordHash = (string) ($config['password_hash'] ?? '');
  if ($passwordHash !== '' && password_verify($password, $passwordHash)) {
    return true;
  }

  $legacyHash = (string) ($config['password_sha256'] ?? '');
  return $legacyHash !== '' && hash_equals($legacyHash, hash('sha256', $password));
}

function redirect_admin(string $tab = 'skills', string $message = ''): void {
  $url = '/admin/?tab=' . urlencode($tab);
  if ($message !== '') {
    $url .= '&message=' . urlencode($message);
  }
  header('Location: ' . $url);
  exit;
}

function load_items(string $file): array {
  $items = require $file;
  return is_array($items) ? array_values($items) : [];
}

function load_assoc(string $file): array {
  $items = require $file;
  return is_array($items) ? $items : [];
}

function save_items(string $file, array $items): void {
  $export = var_export(array_values($items), true);
  $php = "<?php\n\nreturn " . $export . ";\n";
  if (file_put_contents($file, $php, LOCK_EX) === false) {
    throw new RuntimeException('Не получилось сохранить файл: ' . basename($file));
  }
}

function save_assoc(string $file, array $items): void {
  $export = var_export($items, true);
  $php = "<?php\n\nreturn " . $export . ";\n";
  if (file_put_contents($file, $php, LOCK_EX) === false) {
    throw new RuntimeException('Не получилось сохранить файл: ' . basename($file));
  }
}

function is_uploaded_asset(string $path): bool {
  return strpos($path, '/assets/img/uploads/') === 0;
}

function is_project_placeholder_asset(string $path): bool {
  return strpos($path, '/assets/img/projects/') === 0;
}

function delete_uploaded_asset(string $path, string $root): void {
  if (!is_uploaded_asset($path)) {
    return;
  }

  $relativePath = ltrim($path, '/');
  $fullPath = realpath($root . '/' . $relativePath);
  $uploadsPath = realpath($root . '/assets/img/uploads');
  if ($fullPath && $uploadsPath && strpos($fullPath, $uploadsPath) === 0 && is_file($fullPath)) {
    unlink($fullPath);
  }
}

function remove_deleted_assets(array $paths, array $deletedPaths, string $root): array {
  $deletedPaths = array_values(array_unique(array_filter($deletedPaths, 'is_string')));
  foreach ($deletedPaths as $path) {
    delete_uploaded_asset($path, $root);
  }

  return array_values(array_filter($paths, static fn($path) => !in_array($path, $deletedPaths, true)));
}

function default_skill_icon(string $title, string $fallback = ''): string {
  if ($fallback !== '') {
    return $fallback;
  }

  $normalized = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
  $map = [
    'grandma' => '/assets/svg/icons/grandma3.svg',
    'touchdesigner' => '/assets/svg/icons/touchdesigner.svg',
    'resolume' => '/assets/svg/icons/resolume.svg',
    'final cut' => '/assets/svg/icons/finalcut.svg',
    'linux' => '/assets/svg/icons/systems.svg',
    'windows' => '/assets/svg/icons/systems.svg',
    'nginx' => '/assets/svg/icons/infrastructure.svg',
    'apache' => '/assets/svg/icons/infrastructure.svg',
    'трансля' => '/assets/svg/icons/broadcast.svg',
    'ndi' => '/assets/svg/icons/broadcast.svg',
    'ии' => '/assets/svg/icons/ai.svg',
    'ai' => '/assets/svg/icons/ai.svg',
    'генерац' => '/assets/svg/icons/generation.svg',
    'чат' => '/assets/svg/icons/chatbot.svg',
    'сети' => '/assets/svg/icons/network.svg',
  ];

  foreach ($map as $needle => $icon) {
    if (strpos($normalized, $needle) !== false) {
      return $icon;
    }
  }

  return '';
}

function list_from_text(string $value): array {
  $parts = preg_split('/[\r\n,]+/u', $value) ?: [];
  $parts = array_map('trim', $parts);
  return array_values(array_filter($parts, static fn($part) => $part !== ''));
}

function list_from_lines(string $value): array {
  $parts = preg_split('/\r\n|\r|\n/u', $value) ?: [];
  $parts = array_map('trim', $parts);
  return array_values(array_filter($parts, static fn($part) => $part !== ''));
}

function list_from_array($value): array {
  if (!is_array($value)) {
    return [];
  }
  $value = array_map(static fn($item) => trim((string) $item), $value);
  return array_values(array_filter($value, static fn($item) => $item !== ''));
}

function categories_from_lines(string $value): array {
  $categories = [];
  foreach (list_from_lines($value) as $line) {
    $parts = array_map('trim', explode('|', $line, 2));
    $key = $parts[0] ?? '';
    if ($key === '') {
      continue;
    }
    $categories[$key] = $parts[1] ?? $key;
  }
  return $categories;
}

function categories_to_lines(array $categories): string {
  $lines = [];
  foreach ($categories as $key => $label) {
    $lines[] = $key . ' | ' . $label;
  }
  return implode("\n", $lines);
}

function list_from_mixed($value): array {
  return is_array($value) ? list_from_array($value) : list_from_lines((string) $value);
}

function categories_from_post($keys, $labels): array {
  $keys = is_array($keys) ? array_values($keys) : [];
  $labels = is_array($labels) ? array_values($labels) : [];
  $categories = [];

  foreach ($keys as $index => $rawKey) {
    $key = trim((string) $rawKey);
    if ($key === '') {
      continue;
    }
    $label = trim((string) ($labels[$index] ?? $key));
    $categories[$key] = $label !== '' ? $label : $key;
  }

  return $categories;
}

function slugify(string $value): string {
  $value = function_exists('mb_strtolower')
    ? mb_strtolower(trim($value), 'UTF-8')
    : strtolower(trim($value));
  $value = preg_replace('/[^a-z0-9а-яё]+/u', '-', $value) ?? '';
  $value = trim($value, '-');
  return $value !== '' ? $value : 'item';
}

function validate_svg_upload(string $tmpName): void {
  $contents = file_get_contents($tmpName, false, null, 0, 512 * 1024);
  if ($contents === false || stripos($contents, '<svg') === false) {
    throw new RuntimeException('SVG-файл не похож на SVG.');
  }

  $blockedPatterns = [
    '/<\s*script\b/i',
    '/<\s*foreignObject\b/i',
    '/\son[a-z]+\s*=/i',
    '/javascript\s*:/i',
    '/data\s*:\s*text\/html/i',
  ];

  foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $contents)) {
      throw new RuntimeException('SVG содержит потенциально опасный код.');
    }
  }
}

function save_upload(string $field, string $uploadFsDir, string $uploadWebDir, string $fallback = ''): string {
  if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return $fallback;
  }

  $file = $_FILES[$field];
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    throw new RuntimeException('Ошибка загрузки файла.');
  }

  if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
    throw new RuntimeException('Файл слишком большой. Максимум 8 МБ.');
  }

  if (!is_dir($uploadFsDir) && !mkdir($uploadFsDir, 0775, true)) {
    throw new RuntimeException('Не получилось создать папку загрузок.');
  }

  $tmpName = (string) $file['tmp_name'];
  $originalName = (string) $file['name'];
  $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
  if (!in_array($extension, $allowedExtensions, true)) {
    throw new RuntimeException('Можно загружать только изображения: jpg, png, webp, gif, svg.');
  }

  if ($extension === 'svg') {
    validate_svg_upload($tmpName);
  } else {
    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
      throw new RuntimeException('Файл не похож на изображение.');
    }
  }

  $name = slugify(pathinfo($originalName, PATHINFO_FILENAME));
  $targetName = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
  $targetPath = $uploadFsDir . '/' . $targetName;

  if (!move_uploaded_file($tmpName, $targetPath)) {
    throw new RuntimeException('Не получилось сохранить загруженный файл.');
  }

  return $uploadWebDir . '/' . $targetName;
}

function save_multiple_uploads(string $field, string $uploadFsDir, string $uploadWebDir): array {
  if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
    return [];
  }

  $saved = [];
  $files = $_FILES[$field];
  foreach ($files['name'] as $index => $name) {
    if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      continue;
    }

    $_FILES['_single_gallery_upload'] = [
      'name' => $name,
      'type' => $files['type'][$index] ?? '',
      'tmp_name' => $files['tmp_name'][$index] ?? '',
      'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
      'size' => $files['size'][$index] ?? 0,
    ];
    $saved[] = save_upload('_single_gallery_upload', $uploadFsDir, $uploadWebDir);
    unset($_FILES['_single_gallery_upload']);
  }

  return $saved;
}

function require_login(): void {
  if (!is_logged_in()) {
    redirect_admin('login');
  }
}

$tab = $_GET['tab'] ?? 'skills';
$message = $_GET['message'] ?? '';
$error = '';

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
      $username = trim((string) ($_POST['username'] ?? ''));
      $password = (string) ($_POST['password'] ?? '');
      if (verify_admin_password($config, $username, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        csrf_token();
        redirect_admin('skills');
      }

      $error = 'Неверный логин или пароль.';
    } elseif ($action === 'logout') {
      check_csrf();
      session_destroy();
      redirect_admin('login');
    } elseif ($action === 'save_skill') {
      require_login();
      check_csrf();

      $skills = load_items($GLOBALS['skillsFile']);
      $indexValue = (string) ($_POST['index'] ?? '');
      $index = $indexValue === '' ? null : (int) $indexValue;
      $old = $index !== null && isset($skills[$index]) ? $skills[$index] : [];
      $oldIcon = (string) ($old['icon'] ?? '');
      $title = trim((string) ($_POST['title'] ?? ''));
      $defaultIcon = default_skill_icon($title, (string) ($old['default_icon'] ?? (is_uploaded_asset($oldIcon) ? '' : $oldIcon)));
      $icon = trim((string) ($_POST['icon'] ?? $oldIcon));

      if (!empty($_POST['delete_icon'])) {
        delete_uploaded_asset($oldIcon, $GLOBALS['root']);
        $icon = $defaultIcon ?: default_skill_icon($title);
      }

      $icon = save_upload('icon_upload', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir'], $icon);

      $item = [
        'title' => $title,
        'description' => trim((string) ($_POST['description'] ?? '')),
        'icon' => $icon,
        'default_icon' => $defaultIcon,
        'invert_icon' => !empty($_POST['invert_icon']),
        'order' => max(1, (int) ($_POST['order'] ?? ($old['order'] ?? count($skills) + 1))),
        'level' => trim((string) ($_POST['level'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'stack' => array_values(array_unique(array_merge(
          list_from_array($_POST['stack_select'] ?? []),
          list_from_text((string) ($_POST['stack'] ?? ''))
        ))),
      ];

      if ($item['title'] === '') {
        throw new RuntimeException('У навыка должно быть название.');
      }

      if ($index === null) {
        $skills[] = $item;
      } else {
        $skills[$index] = $item;
      }

      save_items($GLOBALS['skillsFile'], $skills);
      redirect_admin('skills', 'Навык сохранён.');
    } elseif ($action === 'delete_skill') {
      require_login();
      check_csrf();
      $skills = load_items($GLOBALS['skillsFile']);
      $index = (int) ($_POST['index'] ?? -1);
      if (isset($skills[$index])) {
        delete_uploaded_asset((string) ($skills[$index]['icon'] ?? ''), $GLOBALS['root']);
        array_splice($skills, $index, 1);
        save_items($GLOBALS['skillsFile'], $skills);
      }
      redirect_admin('skills', 'Навык удалён.');
    } elseif ($action === 'save_project') {
      require_login();
      check_csrf();

      $projects = load_items($GLOBALS['projectsFile']);
      $indexValue = (string) ($_POST['index'] ?? '');
      $index = $indexValue === '' ? null : (int) $indexValue;
      $old = $index !== null && isset($projects[$index]) ? $projects[$index] : [];
      $title = trim((string) ($_POST['title'] ?? ''));
      $manualGallery = list_from_text((string) ($_POST['gallery'] ?? ''));
      $manualGallery = remove_deleted_assets($manualGallery, $_POST['delete_gallery'] ?? [], $GLOBALS['root']);
      $uploadedGallery = save_multiple_uploads('gallery_uploads', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir']);
      $oldImage = (string) ($old['image'] ?? '');
      $defaultImage = (string) ($old['default_image'] ?? (is_uploaded_asset($oldImage) ? '' : $oldImage));
      $image = trim((string) ($_POST['image'] ?? $oldImage));

      if (!empty($_POST['delete_image'])) {
        delete_uploaded_asset($oldImage, $GLOBALS['root']);
        $image = $defaultImage;
        $manualGallery = array_values(array_filter($manualGallery, static fn($path) => $path !== $oldImage));
      }

      if ($oldImage !== '' && in_array($oldImage, $_POST['delete_gallery'] ?? [], true)) {
        $image = $defaultImage;
      }

      $image = save_upload('image_upload', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir'], $image);

      $tagsData = load_assoc($GLOBALS['tagsFile']);
      $projectCategories = $tagsData['project_categories'] ?? [];
      $category = trim((string) ($_POST['category'] ?? ''));

      $item = [
        'id' => trim((string) ($_POST['id'] ?? '')) ?: slugify($title),
        'title' => $title,
        'date' => trim((string) ($_POST['date'] ?? '')) ?: date('Y-m-d'),
        'category' => $category,
        'category_label' => trim((string) ($_POST['category_label'] ?? '')) ?: ($projectCategories[$category] ?? $category),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'full_description' => trim((string) ($_POST['full_description'] ?? '')),
        'image' => $image,
        'default_image' => $defaultImage,
        'gallery' => array_values(array_unique(array_merge($manualGallery, $uploadedGallery))),
        'video' => trim((string) ($_POST['video'] ?? '')) ?: null,
        'tags' => array_values(array_unique(array_merge(
          list_from_array($_POST['tags_select'] ?? []),
          list_from_text((string) ($_POST['tags'] ?? ''))
        ))),
        'tools' => list_from_text((string) ($_POST['tools'] ?? '')),
      ];

      if ($item['title'] === '') {
        throw new RuntimeException('У проекта должно быть название.');
      }

      $hasUploadedProjectMedia = is_uploaded_asset((string) $item['image']);
      foreach ($item['gallery'] as $galleryImage) {
        if (is_uploaded_asset((string) $galleryImage)) {
          $hasUploadedProjectMedia = true;
          break;
        }
      }

      if ($hasUploadedProjectMedia) {
        $item['gallery'] = array_values(array_filter($item['gallery'], static function ($path) {
          return !is_project_placeholder_asset((string) $path);
        }));

        if (is_project_placeholder_asset((string) $item['image'])) {
          $item['image'] = $item['gallery'][0] ?? '';
        }
      }

      if ($item['image'] !== '' && !in_array($item['image'], $item['gallery'], true)) {
        array_unshift($item['gallery'], $item['image']);
      }

      if ($index === null) {
        $projects[] = $item;
      } else {
        $projects[$index] = $item;
      }

      save_items($GLOBALS['projectsFile'], $projects);
      redirect_admin('projects', 'Проект сохранён.');
    } elseif ($action === 'delete_project') {
      require_login();
      check_csrf();
      $projects = load_items($GLOBALS['projectsFile']);
      $index = (int) ($_POST['index'] ?? -1);
      if (isset($projects[$index])) {
        delete_uploaded_asset((string) ($projects[$index]['image'] ?? ''), $GLOBALS['root']);
        foreach (($projects[$index]['gallery'] ?? []) as $galleryImage) {
          delete_uploaded_asset((string) $galleryImage, $GLOBALS['root']);
        }
        array_splice($projects, $index, 1);
        save_items($GLOBALS['projectsFile'], $projects);
      }
      redirect_admin('projects', 'Проект удалён.');
    } elseif ($action === 'save_general_settings') {
      require_login();
      check_csrf();

      $settings = load_assoc($GLOBALS['settingsFile']);
      $settings['version'] = trim((string) ($_POST['version'] ?? '')) ?: '0.3-beta';
      $settings['admin_clicks'] = max(1, (int) ($_POST['admin_clicks'] ?? 10));
      save_assoc($GLOBALS['settingsFile'], $settings);
      redirect_admin('settings', 'Общие настройки сохранены.');
    } elseif ($action === 'save_contacts') {
      require_login();
      check_csrf();

      $contacts = [
        'email' => trim((string) ($_POST['email'] ?? '')),
        'telegram' => trim((string) ($_POST['telegram'] ?? '')),
        'telegram_url' => trim((string) ($_POST['telegram_url'] ?? '')),
        'location' => trim((string) ($_POST['location'] ?? '')),
        'timezone' => trim((string) ($_POST['timezone'] ?? '')),
        'site' => trim((string) ($_POST['site'] ?? '')),
        'qr_label' => trim((string) ($_POST['qr_label'] ?? '')),
      ];
      save_assoc($GLOBALS['contactsFile'], $contacts);
      redirect_admin('settings', 'Контакты сохранены.');
    } elseif ($action === 'save_about') {
      require_login();
      check_csrf();

      $about = [
        'lead' => trim((string) ($_POST['about_lead'] ?? '')),
        'paragraphs' => list_from_lines((string) ($_POST['about_paragraphs'] ?? '')),
        'focus' => trim((string) ($_POST['about_focus'] ?? '')),
        'visual_top_left' => trim((string) ($_POST['visual_top_left'] ?? '')),
        'visual_top_right' => trim((string) ($_POST['visual_top_right'] ?? '')),
        'visual_tags' => list_from_text((string) ($_POST['visual_tags'] ?? '')),
      ];

      save_assoc($GLOBALS['aboutFile'], $about);
      redirect_admin('about', 'Блок "Обо мне" сохранён.');
    } elseif ($action === 'save_password') {
      require_login();
      check_csrf();

      $newPassword = (string) ($_POST['new_password'] ?? '');
      $newPasswordRepeat = (string) ($_POST['new_password_repeat'] ?? '');
      if ($newPassword !== '' || $newPasswordRepeat !== '') {
        if ($newPassword !== $newPasswordRepeat) {
          throw new RuntimeException('Пароли не совпадают.');
        }
        if (strlen($newPassword) < 8) {
          throw new RuntimeException('Пароль должен быть минимум 8 символов.');
        }
      }

      if ($newPassword !== '' || $newPasswordRepeat !== '') {
        $adminConfig = load_assoc(__DIR__ . '/config.php');
        $adminConfig['username'] = trim((string) ($_POST['admin_username'] ?? $adminConfig['username'] ?? 'admin')) ?: 'admin';
        $adminConfig['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        unset($adminConfig['password_sha256']);
        save_assoc(__DIR__ . '/config.php', $adminConfig);
      } else {
        $adminConfig = load_assoc(__DIR__ . '/config.php');
        $adminConfig['username'] = trim((string) ($_POST['admin_username'] ?? $adminConfig['username'] ?? 'admin')) ?: 'admin';
        save_assoc(__DIR__ . '/config.php', $adminConfig);
      }

      redirect_admin('settings', 'Доступ к админке сохранён.');
    } elseif ($action === 'save_tags') {
      require_login();
      check_csrf();

      $tags = [
        'skill_tags' => list_from_mixed($_POST['skill_tags'] ?? []),
        'project_tags' => list_from_mixed($_POST['project_tags'] ?? []),
        'project_categories' => categories_from_post($_POST['project_category_keys'] ?? [], $_POST['project_category_labels'] ?? []),
      ];
      save_assoc($GLOBALS['tagsFile'], $tags);
      redirect_admin('tags', 'Теги сохранены.');
    }
  }
} catch (Throwable $exception) {
  $error = $exception->getMessage();
}

$loggedIn = is_logged_in();
$skills = $loggedIn ? load_items($skillsFile) : [];
$projects = $loggedIn ? load_items($projectsFile) : [];
$settings = $loggedIn ? load_assoc($settingsFile) : [];
$contacts = $loggedIn ? load_assoc($contactsFile) : [];
$about = $loggedIn ? load_assoc($aboutFile) : [];
$tags = $loggedIn ? load_assoc($tagsFile) : [];
$skillTags = $tags['skill_tags'] ?? [];
$projectTags = $tags['project_tags'] ?? [];
$projectCategories = $tags['project_categories'] ?? [];
$editSkill = isset($_GET['edit_skill'], $skills[(int) $_GET['edit_skill']]) ? $skills[(int) $_GET['edit_skill']] : null;
$editSkillIndex = $editSkill === null ? '' : (string) ((int) $_GET['edit_skill']);
$editProject = isset($_GET['edit_project'], $projects[(int) $_GET['edit_project']]) ? $projects[(int) $_GET['edit_project']] : null;
$editProjectIndex = $editProject === null ? '' : (string) ((int) $_GET['edit_project']);
$editSkillIcon = (string) ($editSkill['icon'] ?? '');
$editSkillInvertIcon = (bool) ($editSkill['invert_icon'] ?? !is_uploaded_asset($editSkillIcon));
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Админка — egor_zvada</title>
  <style>
    :root { color-scheme: dark light; --bg: #080808; --panel: #111; --line: #303030; --text: #f3f3f3; --muted: #aaa; --soft: #1a1a1a; --field: #090909; --row: #0d0d0d; --image-bg: #050505; --success-bg: #102315; --success-line: #315f37; --success-text: #c9ffd0; --error-bg: #2a1010; --error-line: #733; --error-text: #ffd0d0; --danger-line: #5c2b2b; --danger-text: #ffd3d3; }
    :root[data-theme="light"] { color-scheme: light; --bg: #f5f5f2; --panel: #fff; --line: #d9d9d2; --text: #090909; --muted: #63635f; --soft: #eeeeea; --field: #fff; --row: #fafaf7; --image-bg: #f0f0eb; --success-bg: #e8f5e9; --success-line: #9bc6a1; --success-text: #1d5a2a; --error-bg: #fff0f0; --error-line: #d6a1a1; --error-text: #7a2020; --danger-line: #cf8f8f; --danger-text: #8f2727; }
    :root[data-theme="dark"] { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    a { color: inherit; }
    .admin { width: min(1180px, calc(100vw - 28px)); margin: 0 auto; padding: 26px 0 56px; }
    .top { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 22px; }
    .brand { font-size: 24px; font-weight: 650; letter-spacing: -.03em; }
    .nav { display: flex; gap: 8px; flex-wrap: wrap; }
    .nav a, button, .button { min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 14px; border: 1px solid var(--line); background: var(--soft); color: var(--text); text-decoration: none; cursor: pointer; }
    .nav a.is-active, button.primary, .button.primary { background: var(--text); color: var(--bg); border-color: var(--text); }
    .grid { display: grid; grid-template-columns: .9fr 1.1fr; gap: 18px; align-items: start; }
    .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; align-items: stretch; }
    .settings-grid .panel { height: 100%; }
    .settings-grid .panel--wide { grid-column: 1 / -1; }
    .tags-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; align-items: start; }
    .tags-grid .panel--wide { grid-column: 1 / -1; }
    .panel { border: 1px solid var(--line); background: var(--panel); padding: 18px; }
    .panel h2 { margin: 0 0 14px; font-size: 22px; letter-spacing: -.025em; }
    .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .panel-head h2 { margin: 0; }
    .form-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .list { display: grid; gap: 8px; }
    .row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; padding: 12px; border: 1px solid var(--line); background: var(--row); }
    .row strong { display: block; }
    .row span { display: block; color: var(--muted); font-size: 13px; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .file-list { display: grid; gap: 8px; margin: -4px 0 12px; }
    .file-list__item { display: grid; grid-template-columns: 44px 1fr auto; gap: 10px; align-items: center; padding: 8px; border: 1px solid var(--line); background: var(--row); }
    .file-list__item img { width: 44px; height: 44px; object-fit: cover; border: 1px solid var(--line); background: var(--image-bg); }
    .file-list__item code { color: var(--muted); font-size: 12px; overflow-wrap: anywhere; }
    form { margin: 0; }
    label { display: grid; gap: 6px; margin-bottom: 12px; color: var(--muted); font-size: 13px; }
    input, textarea, select { width: 100%; border: 1px solid var(--line); background: var(--field); color: var(--text); padding: 10px 12px; font: inherit; }
    textarea { min-height: 92px; resize: vertical; }
    input[type="checkbox"] { width: auto; }
    .check { display: flex; align-items: center; gap: 9px; }
    .message { margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--success-line); background: var(--success-bg); color: var(--success-text); }
    .error { margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--error-line); background: var(--error-bg); color: var(--error-text); }
    .ajax-status { margin-bottom: 14px; padding: 12px 14px; border: 1px solid var(--line); background: var(--soft); color: var(--muted); }
    .hint { color: var(--muted); font-size: 13px; margin-top: -4px; }
    .login { max-width: 420px; margin: 14vh auto 0; }
    .danger { border-color: var(--danger-line); color: var(--danger-text); }
    .admin-theme-toggle { text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
    .admin.is-busy { cursor: progress; }
    .admin.is-busy button, .admin.is-busy .button { cursor: progress; }
    .tag-manager { display: grid; gap: 12px; }
    .tag-manager__add { display: grid; grid-template-columns: 1fr auto; gap: 8px; }
    .tag-cloud, .tag-picker { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; border: 1px solid var(--line); background: var(--field); }
    .tag-chip { min-height: 34px; display: inline-flex; align-items: center; gap: 8px; padding: 0 10px; border: 1px solid var(--line); background: var(--soft); color: var(--text); font-size: 13px; }
    .tag-chip button { min-height: 24px; width: 24px; padding: 0; border-color: transparent; background: transparent; color: var(--muted); }
    .tag-chip button:hover { color: var(--text); border-color: var(--line); }
    .tag-option { position: relative; display: inline-flex; align-items: center; margin: 0; cursor: pointer; }
    .tag-option input { position: absolute; opacity: 0; pointer-events: none; }
    .tag-option span { min-height: 34px; display: inline-flex; align-items: center; padding: 0 10px; border: 1px solid var(--line); background: var(--soft); color: var(--muted); }
    .tag-option input:checked + span { background: var(--text); color: var(--bg); border-color: var(--text); }
    .category-list { display: grid; gap: 8px; }
    .category-row { display: grid; grid-template-columns: minmax(120px, .7fr) minmax(140px, 1fr) auto; gap: 8px; align-items: center; }
    @media (max-width: 860px) {
      .admin { width: min(100% - 20px, 1180px); padding-top: 16px; }
      .grid, .settings-grid, .tags-grid { grid-template-columns: 1fr; }
      .settings-grid .panel--wide, .tags-grid .panel--wide { grid-column: auto; }
      .top { align-items: stretch; flex-direction: column; }
      .brand { font-size: 22px; }
      .nav { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
      .nav a, .nav button, .nav form, .nav form button { width: 100%; }
      .row { grid-template-columns: 1fr; }
      .actions { display: grid; grid-template-columns: 1fr; }
      .actions .button, .actions button { width: 100%; }
      .panel-head, .form-actions { align-items: stretch; flex-direction: column; }
      .panel-head .button, .form-actions .button, .form-actions button { width: 100%; }
      .file-list__item { grid-template-columns: 44px 1fr; }
      .file-list__item .check { grid-column: 1 / -1; }
      .tag-manager__add, .category-row { grid-template-columns: 1fr; }
      .panel { padding: 14px; }
    }
  </style>
</head>
<body>
  <main class="admin">
    <?php if (!$loggedIn): ?>
      <section class="panel login">
        <h1 class="brand">egor_zvada / admin</h1>
        <button class="admin-theme-toggle" type="button" data-admin-theme-toggle aria-label="Переключить тему">theme</button>
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <label>Логин <input name="username" autocomplete="username" required></label>
          <label>Пароль <input name="password" type="password" autocomplete="current-password" required></label>
          <button class="primary" type="submit">Войти</button>
        </form>
      </section>
    <?php else: ?>
      <header class="top">
        <div>
          <div class="brand">egor_zvada / admin</div>
          <div class="hint">Загрузка картинок сохраняет файлы в <?= h($uploadWebDir) ?></div>
        </div>
        <div class="nav">
          <a class="<?= $tab === 'skills' ? 'is-active' : '' ?>" href="/admin/?tab=skills">Навыки</a>
          <a class="<?= $tab === 'projects' ? 'is-active' : '' ?>" href="/admin/?tab=projects">Портфолио</a>
          <a class="<?= $tab === 'about' ? 'is-active' : '' ?>" href="/admin/?tab=about">Обо мне</a>
          <a class="<?= $tab === 'tags' ? 'is-active' : '' ?>" href="/admin/?tab=tags">Теги</a>
          <a class="<?= $tab === 'settings' ? 'is-active' : '' ?>" href="/admin/?tab=settings">Настройки</a>
          <a href="/" target="_blank" rel="noopener">Открыть сайт</a>
          <button class="admin-theme-toggle" type="button" data-admin-theme-toggle aria-label="Переключить тему">theme</button>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit">Выйти</button>
          </form>
        </div>
      </header>

      <?php if ($message): ?><div class="message"><?= h($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

      <?php if ($tab === 'settings'): ?>
        <div class="settings-grid">
          <section class="panel">
            <h2>Общие</h2>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_general_settings">
              <label>Версия в футере <input name="version" value="<?= h($settings['version'] ?? '0.3-beta') ?>" required></label>
              <label>Кликов для скрытого входа <input name="admin_clicks" type="number" min="1" max="50" value="<?= h($settings['admin_clicks'] ?? 10) ?>" required></label>
              <button class="primary" type="submit">Сохранить общие</button>
            </form>
          </section>

          <section class="panel">
            <h2>Доступ</h2>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_password">
              <label>Логин админки <input name="admin_username" value="<?= h($config['username'] ?? 'admin') ?>" autocomplete="username" required></label>
              <label>Новый пароль <input name="new_password" type="password" autocomplete="new-password" placeholder="Оставь пустым, если не менять"></label>
              <label>Повтор нового пароля <input name="new_password_repeat" type="password" autocomplete="new-password" placeholder="Оставь пустым, если не менять"></label>
              <button class="primary" type="submit">Сохранить доступ</button>
            </form>
          </section>

          <section class="panel panel--wide">
            <h2>Контакты</h2>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_contacts">
              <label>Email <input name="email" type="email" value="<?= h($contacts['email'] ?? '') ?>"></label>
              <label>Telegram <input name="telegram" value="<?= h($contacts['telegram'] ?? '') ?>"></label>
              <label>Telegram URL <input name="telegram_url" type="url" value="<?= h($contacts['telegram_url'] ?? '') ?>"></label>
              <label>Локация <input name="location" value="<?= h($contacts['location'] ?? '') ?>"></label>
              <label>Часовой пояс <input name="timezone" value="<?= h($contacts['timezone'] ?? '') ?>"></label>
              <label>Сайт <input name="site" value="<?= h($contacts['site'] ?? '') ?>"></label>
              <label>QR label <input name="qr_label" value="<?= h($contacts['qr_label'] ?? '') ?>"></label>
              <button class="primary" type="submit">Сохранить контакты</button>
            </form>
          </section>

        </div>
      <?php elseif ($tab === 'tags'): ?>
        <form method="post" class="tags-grid">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_tags">

          <section class="panel">
            <h2>Теги навыков</h2>
            <div class="tag-manager" data-tag-manager data-input-name="skill_tags[]">
              <div class="tag-manager__add">
                <input data-tag-input placeholder="Например: TouchDesigner">
                <button type="button" data-tag-add>Добавить</button>
              </div>
              <div class="tag-cloud" data-tag-cloud>
                <?php foreach ($skillTags as $tag): ?>
                  <span class="tag-chip" data-tag-chip>
                    <span><?= h($tag) ?></span>
                    <input type="hidden" name="skill_tags[]" value="<?= h($tag) ?>">
                    <button type="button" data-tag-remove aria-label="Удалить тег <?= h($tag) ?>">×</button>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="panel">
            <h2>Теги портфолио</h2>
            <div class="tag-manager" data-tag-manager data-input-name="project_tags[]">
              <div class="tag-manager__add">
                <input data-tag-input placeholder="Например: broadcast">
                <button type="button" data-tag-add>Добавить</button>
              </div>
              <div class="tag-cloud" data-tag-cloud>
                <?php foreach ($projectTags as $tag): ?>
                  <span class="tag-chip" data-tag-chip>
                    <span><?= h($tag) ?></span>
                    <input type="hidden" name="project_tags[]" value="<?= h($tag) ?>">
                    <button type="button" data-tag-remove aria-label="Удалить тег <?= h($tag) ?>">×</button>
                  </span>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="panel panel--wide">
            <h2>Категории портфолио</h2>
            <div class="category-list" data-category-list>
              <?php foreach ($projectCategories as $key => $label): ?>
                <div class="category-row">
                  <input name="project_category_keys[]" value="<?= h($key) ?>" placeholder="key">
                  <input name="project_category_labels[]" value="<?= h($label) ?>" placeholder="label">
                  <button type="button" data-category-remove>Удалить</button>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" data-category-add>Добавить категорию</button>
          </section>

          <section class="panel panel--wide">
            <button class="primary" type="submit">Сохранить теги</button>
          </section>
        </form>
      <?php elseif ($tab === 'about'): ?>
        <section class="panel">
          <h2>Обо мне</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_about">
            <label>Короткий лид <textarea name="about_lead"><?= h($about['lead'] ?? '') ?></textarea></label>
            <label>Основной текст, каждый абзац с новой строки <textarea name="about_paragraphs"><?= h(implode("\n", $about['paragraphs'] ?? [])) ?></textarea></label>
            <label>Focus строка <input name="about_focus" value="<?= h($about['focus'] ?? '') ?>"></label>
            <label>Подпись fallback-анимации слева <input name="visual_top_left" value="<?= h($about['visual_top_left'] ?? '') ?>"></label>
            <label>Подпись fallback-анимации справа <input name="visual_top_right" value="<?= h($about['visual_top_right'] ?? '') ?>"></label>
            <label>Теги fallback-анимации <textarea name="visual_tags"><?= h(implode("\n", $about['visual_tags'] ?? [])) ?></textarea></label>
            <p class="hint">Фото для правого блока загружается на сервер вручную: /assets/img/about/about.jpg</p>
            <button class="primary" type="submit">Сохранить "Обо мне"</button>
          </form>
        </section>
      <?php elseif ($tab === 'projects'): ?>
        <div class="grid">
          <section class="panel">
            <div class="panel-head">
              <h2>Проекты</h2>
              <a class="button primary" href="/admin/?tab=projects">Добавить мероприятие/проект</a>
            </div>
            <div class="list">
              <?php foreach ($projects as $index => $project): ?>
                <div class="row">
                  <div>
                    <strong><?= h($project['title'] ?? '') ?></strong>
                    <span><?= h($project['category_label'] ?? $project['category'] ?? '') ?> · <?= h($project['date'] ?? '') ?></span>
                  </div>
                  <div class="actions">
                    <a class="button" href="/admin/?tab=projects&edit_project=<?= $index ?>">Редактировать</a>
                    <form method="post" action="/admin/?tab=projects" onsubmit="return confirm('Удалить проект?')">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_project">
                      <input type="hidden" name="index" value="<?= $index ?>">
                      <button class="danger" type="submit">Удалить</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <h2><?= $editProject ? 'Редактировать проект' : 'Добавить мероприятие/проект' ?></h2>
              <?php if ($editProject): ?><a class="button" href="/admin/?tab=projects">Добавить новый</a><?php endif; ?>
            </div>
            <p class="hint">На сайте автоматически видны 4 самых свежих проекта по дате. Остальные уходят под кнопку "Показать ещё".</p>
            <form method="post" action="/admin/?tab=projects" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_project">
              <input type="hidden" name="index" value="<?= h($editProjectIndex) ?>">
              <label>ID <input name="id" value="<?= h($editProject['id'] ?? '') ?>" placeholder="auto-from-title"></label>
              <label>Название <input name="title" value="<?= h($editProject['title'] ?? '') ?>" required></label>
              <label>Дата <input name="date" type="date" value="<?= h($editProject['date'] ?? date('Y-m-d')) ?>"></label>
              <label>Категория
                <select name="category">
                  <?php if (empty($projectCategories)): ?>
                    <option value="">Сначала добавь категории во вкладке "Теги"</option>
                  <?php endif; ?>
                  <?php if (!empty($editProject['category']) && !isset($projectCategories[$editProject['category']])): ?>
                    <option value="<?= h($editProject['category']) ?>" selected><?= h($editProject['category']) ?></option>
                  <?php endif; ?>
                  <?php foreach ($projectCategories as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= ($editProject['category'] ?? '') === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Название категории <input name="category_label" value="<?= h($editProject['category_label'] ?? '') ?>" placeholder="event tech"></label>
              <label>Краткое описание <textarea name="description" required><?= h($editProject['description'] ?? '') ?></textarea></label>
              <label>Полное описание <textarea name="full_description"><?= h($editProject['full_description'] ?? '') ?></textarea></label>
              <label>Главная картинка, путь <input name="image" value="<?= h($editProject['image'] ?? '') ?>" placeholder="/assets/img/projects/example.svg"></label>
              <?php if (!empty($editProject['image']) && is_uploaded_asset((string) $editProject['image'])): ?>
                <label class="check"><input name="delete_image" type="checkbox" value="1"> Удалить главную картинку с сервера и вернуть дефолт</label>
              <?php endif; ?>
              <label>Загрузить главную картинку <input name="image_upload" type="file" accept="image/*,.svg"></label>
              <label>Галерея, пути через запятую или с новой строки <textarea name="gallery"><?= h(implode("\n", $editProject['gallery'] ?? [])) ?></textarea></label>
              <?php if (!empty($editProject['gallery'])): ?>
                <div class="file-list" aria-label="Текущая галерея проекта">
                  <?php foreach ($editProject['gallery'] as $image): ?>
                    <label class="file-list__item">
                      <img src="<?= h($image) ?>" alt="">
                      <code><?= h($image) ?></code>
                      <?php if (is_uploaded_asset((string) $image)): ?>
                        <span class="check"><input name="delete_gallery[]" type="checkbox" value="<?= h($image) ?>"> Удалить</span>
                      <?php endif; ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <label>Дозагрузить картинки в галерею <input name="gallery_uploads[]" type="file" accept="image/*,.svg" multiple></label>
              <label>Видео, путь или URL <input name="video" value="<?= h($editProject['video'] ?? '') ?>"></label>
              <label>Теги из списка</label>
              <div class="tag-picker" aria-label="Теги проекта">
                  <?php foreach ($projectTags as $tag): ?>
                    <label class="tag-option">
                      <input name="tags_select[]" type="checkbox" value="<?= h($tag) ?>" <?= in_array($tag, $editProject['tags'] ?? [], true) ? 'checked' : '' ?>>
                      <span><?= h($tag) ?></span>
                    </label>
                  <?php endforeach; ?>
              </div>
              <label>Дополнительные теги <textarea name="tags"><?= h(implode("\n", array_values(array_diff($editProject['tags'] ?? [], $projectTags)))) ?></textarea></label>
              <label>Инструменты <textarea name="tools"><?= h(implode("\n", $editProject['tools'] ?? [])) ?></textarea></label>
              <div class="form-actions">
                <button class="primary" type="submit">Сохранить проект</button>
                <?php if ($editProject): ?><a class="button" href="/admin/?tab=projects">Добавить новый</a><?php endif; ?>
              </div>
            </form>
          </section>
        </div>
      <?php else: ?>
        <div class="grid">
          <section class="panel">
            <div class="panel-head">
              <h2>Навыки</h2>
              <a class="button primary" href="/admin/?tab=skills">Добавить навык</a>
            </div>
            <div class="list">
              <?php foreach ($skills as $index => $skill): ?>
                <div class="row">
                  <div>
                    <strong><?= h($skill['title'] ?? '') ?></strong>
                    <span>№<?= h($skill['order'] ?? $index + 1) ?> · <?= h($skill['category'] ?? '') ?> · <?= h($skill['level'] ?? '') ?></span>
                  </div>
                  <div class="actions">
                    <a class="button" href="/admin/?tab=skills&edit_skill=<?= $index ?>">Редактировать</a>
                    <form method="post" action="/admin/?tab=skills" onsubmit="return confirm('Удалить навык?')">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_skill">
                      <input type="hidden" name="index" value="<?= $index ?>">
                      <button class="danger" type="submit">Удалить</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <h2><?= $editSkill ? 'Редактировать навык' : 'Добавить навык' ?></h2>
              <?php if ($editSkill): ?><a class="button" href="/admin/?tab=skills">Добавить новый</a><?php endif; ?>
            </div>
            <form method="post" action="/admin/?tab=skills" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_skill">
              <input type="hidden" name="index" value="<?= h($editSkillIndex) ?>">
              <label>Название <input name="title" value="<?= h($editSkill['title'] ?? '') ?>" required></label>
              <label>Номер в списке <input name="order" type="number" min="1" value="<?= h($editSkill['order'] ?? ($editSkillIndex !== '' ? ((int) $editSkillIndex + 1) : count($skills) + 1)) ?>" required></label>
              <label>Описание <textarea name="description" required><?= h($editSkill['description'] ?? '') ?></textarea></label>
              <label>Иконка, путь <input name="icon" value="<?= h($editSkill['icon'] ?? '') ?>" placeholder="/assets/svg/icons/example.svg"></label>
              <?php if (!empty($editSkill['icon']) && is_uploaded_asset((string) $editSkill['icon'])): ?>
                <label class="check"><input name="delete_icon" type="checkbox" value="1"> Удалить загруженную иконку с сервера и вернуть дефолт</label>
              <?php endif; ?>
              <label>Загрузить иконку/картинку <input name="icon_upload" type="file" accept="image/*,.svg"></label>
              <label class="check"><input name="invert_icon" type="checkbox" value="1" <?= $editSkillInvertIcon ? 'checked' : '' ?>> Инвертировать иконку под светлую/тёмную тему</label>
              <label>Уровень / подпись <input name="level" value="<?= h($editSkill['level'] ?? '') ?>" placeholder="system admin"></label>
              <label>Категория <input name="category" value="<?= h($editSkill['category'] ?? '') ?>" placeholder="Системы"></label>
              <label>Теги навыка из списка</label>
              <div class="tag-picker" aria-label="Теги навыка">
                  <?php foreach ($skillTags as $tag): ?>
                    <label class="tag-option">
                      <input name="stack_select[]" type="checkbox" value="<?= h($tag) ?>" <?= in_array($tag, $editSkill['stack'] ?? [], true) ? 'checked' : '' ?>>
                      <span><?= h($tag) ?></span>
                    </label>
                  <?php endforeach; ?>
              </div>
              <label>Дополнительные теги навыка <textarea name="stack"><?= h(implode("\n", array_values(array_diff($editSkill['stack'] ?? [], $skillTags)))) ?></textarea></label>
              <div class="form-actions">
                <button class="primary" type="submit">Сохранить навык</button>
                <?php if ($editSkill): ?><a class="button" href="/admin/?tab=skills">Добавить новый</a><?php endif; ?>
              </div>
            </form>
          </section>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
  <script>
    (() => {
      const createChip = (name, value) => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.dataset.tagChip = '';

        const label = document.createElement('span');
        label.textContent = value;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;

        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.tagRemove = '';
        button.setAttribute('aria-label', `Удалить тег ${value}`);
        button.textContent = '×';

        chip.append(label, input, button);
        return chip;
      };

      const addTagFromManager = (manager) => {
        const input = manager?.querySelector('[data-tag-input]');
        const cloud = manager.querySelector('[data-tag-cloud]');
        const inputName = manager.dataset.inputName || 'tags[]';
        const value = (input?.value || '').trim();
        if (!value || !cloud) return;

        const exists = [...cloud.querySelectorAll('input[type="hidden"]')]
          .some((field) => field.value.toLowerCase() === value.toLowerCase());
        if (exists) {
          input.value = '';
          return;
        }

        cloud.append(createChip(inputName, value));
        input.value = '';
        input.focus();
      };

      document.addEventListener('click', (event) => {
        const addTag = event.target.closest('[data-tag-add]');
        if (addTag) {
          addTagFromManager(addTag.closest('[data-tag-manager]'));
        }

        const removeTag = event.target.closest('[data-tag-remove]');
        if (removeTag) {
          removeTag.closest('[data-tag-chip]')?.remove();
        }

        const removeCategory = event.target.closest('[data-category-remove]');
        if (removeCategory) {
          removeCategory.closest('.category-row')?.remove();
        }

        const addCategory = event.target.closest('[data-category-add]');
        if (addCategory) {
          const list = document.querySelector('[data-category-list]');
          if (!list) return;

          const row = document.createElement('div');
          row.className = 'category-row';
          row.innerHTML = '<input name="project_category_keys[]" placeholder="key"><input name="project_category_labels[]" placeholder="label"><button type="button" data-category-remove>Удалить</button>';
          list.append(row);
          row.querySelector('input')?.focus();
        }
      });

      document.addEventListener('keydown', (event) => {
        const input = event.target.closest('[data-tag-input]');
        if (input && event.key === 'Enter') {
          event.preventDefault();
          addTagFromManager(input.closest('[data-tag-manager]'));
        }
      });
    })();

    (() => {
      const parser = new DOMParser();

      const setStatus = (admin, text, type = 'message') => {
        admin.querySelector('[data-ajax-status]')?.remove();
        const status = document.createElement('div');
        status.className = type === 'error' ? 'error' : 'ajax-status';
        status.dataset.ajaxStatus = '';
        status.textContent = text;
        admin.prepend(status);
      };

      const normalizeAdminUrl = (url) => {
        const nextUrl = new URL(url || window.location.href, window.location.origin);
        nextUrl.searchParams.delete('_');
        return nextUrl;
      };

      const replaceAdmin = (html, url) => {
        const nextDoc = parser.parseFromString(html, 'text/html');
        const nextAdmin = nextDoc.querySelector('.admin');
        const currentAdmin = document.querySelector('.admin');
        if (!nextAdmin || !currentAdmin) {
          window.location.href = url || window.location.href;
          return;
        }

        currentAdmin.innerHTML = nextAdmin.innerHTML;
        currentAdmin.classList.remove('is-busy');
        if (url) {
          const nextUrl = normalizeAdminUrl(url);
          window.history.replaceState(null, '', nextUrl.pathname + nextUrl.search);
        }
        document.dispatchEvent(new CustomEvent('admin:content-updated'));
      };

      document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form');
        if (!form || form.method.toLowerCase() !== 'post' || event.defaultPrevented) return;

        event.preventDefault();
        const admin = document.querySelector('.admin');
        if (!admin) return;

        admin.classList.add('is-busy');
        setStatus(admin, 'Сохраняю...');

        try {
          const actionUrl = form.getAttribute('action') || window.location.href;
          const response = await fetch(actionUrl, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' },
          });

          const html = await response.text();
          if (!response.ok) {
            throw new Error(html || 'Не получилось сохранить.');
          }

          const freshUrl = normalizeAdminUrl(response.url || actionUrl);
          freshUrl.searchParams.set('_', String(Date.now()));
          const freshResponse = await fetch(freshUrl.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'fetch' },
          });

          const freshHtml = await freshResponse.text();
          if (!freshResponse.ok) {
            throw new Error(freshHtml || 'Сохранено, но не получилось обновить вкладку.');
          }

          replaceAdmin(freshHtml, freshResponse.url);
        } catch (error) {
          admin.classList.remove('is-busy');
          setStatus(admin, error.message || 'Не получилось сохранить.', 'error');
        }
      });
    })();

    (() => {
      const root = document.documentElement;
      const storageKey = 'egor-zvada-admin-theme';
      const modeStorageKey = 'egor-zvada-admin-theme-mode';
      const mediaQuery = window.matchMedia('(prefers-color-scheme: light)');

      const getStoredValue = (key) => {
        try {
          return localStorage.getItem(key);
        } catch (error) {
          return null;
        }
      };

      const setStoredValue = (key, value) => {
        try {
          localStorage.setItem(key, value);
        } catch (error) {
          // Theme switching should still work when storage is blocked.
        }
      };

      const removeStoredValue = (key) => {
        try {
          localStorage.removeItem(key);
        } catch (error) {
          // Ignore blocked storage.
        }
      };

      const getSystemTheme = () => mediaQuery.matches ? 'light' : 'dark';

      const getInitialTheme = () => {
        const saved = getStoredValue(storageKey);
        const savedMode = getStoredValue(modeStorageKey);
        if (savedMode === 'manual' && (saved === 'light' || saved === 'dark')) return saved;
        return getSystemTheme();
      };

      const applyTheme = (theme, persist = false) => {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
        if (persist) {
          setStoredValue(storageKey, theme);
          setStoredValue(modeStorageKey, 'manual');
        }

        const nextTheme = theme === 'light' ? 'dark' : 'light';
        document.querySelectorAll('[data-admin-theme-toggle]').forEach((toggle) => {
          toggle.textContent = nextTheme;
          toggle.setAttribute('aria-label', nextTheme === 'dark' ? 'Включить тёмную тему' : 'Включить светлую тему');
        });
      };

      const syncWithSystemTheme = () => {
        removeStoredValue(modeStorageKey);
        applyTheme(getSystemTheme());
      };

      applyTheme(getInitialTheme());

      document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-admin-theme-toggle]');
        if (toggle) {
          const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light';
          applyTheme(nextTheme, true);
        }
      });

      document.addEventListener('admin:content-updated', () => applyTheme(root.dataset.theme || getInitialTheme()));

      if (typeof mediaQuery.addEventListener === 'function') {
        mediaQuery.addEventListener('change', syncWithSystemTheme);
      } else if (typeof mediaQuery.addListener === 'function') {
        mediaQuery.addListener(syncWithSystemTheme);
      }
    })();
  </script>
</body>
</html>
