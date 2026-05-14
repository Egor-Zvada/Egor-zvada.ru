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
require_once $root . '/lib/content.php';

$config = require __DIR__ . '/config.php';
$skillsFile = 'skills.php';
$projectsFile = 'projects.php';
$settingsFile = 'settings.php';
$contactsFile = 'contacts.php';
$aboutFile = 'about.php';
$tagsFile = 'tags.php';
$skillUploadWebDir = '/assets/img/uploads/skills';
$skillUploadFsDir = $root . $skillUploadWebDir;
$projectUploadWebDir = '/assets/img/uploads/projects';
$projectUploadFsDir = $root . $projectUploadWebDir;
$aboutUploadWebDir = '/assets/img/uploads/about';
$aboutUploadFsDir = $root . $aboutUploadWebDir;
$projectVideoUploadWebDir = '/assets/video/uploads/projects';
$projectVideoUploadFsDir = $root . $projectVideoUploadWebDir;

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

function redirect_admin_project(int $index, string $message = ''): void {
  $url = '/admin/?tab=projects&edit_project=' . $index;
  if ($message !== '') {
    $url .= '&message=' . urlencode($message);
  }
  header('Location: ' . $url);
  exit;
}

function size_from_ini(string $value): int {
  $value = trim($value);
  if ($value === '') {
    return 0;
  }

  $unit = strtolower($value[strlen($value) - 1]);
  $size = (float) $value;

  return match ($unit) {
    'g' => (int) ($size * 1024 * 1024 * 1024),
    'm' => (int) ($size * 1024 * 1024),
    'k' => (int) ($size * 1024),
    default => (int) $size,
  };
}

function human_size(int $bytes): string {
  if ($bytes >= 1024 * 1024 * 1024) {
    return round($bytes / 1024 / 1024 / 1024, 1) . ' ГБ';
  }

  if ($bytes >= 1024 * 1024) {
    return round($bytes / 1024 / 1024, 1) . ' МБ';
  }

  if ($bytes >= 1024) {
    return round($bytes / 1024, 1) . ' КБ';
  }

  return $bytes . ' Б';
}

function assert_post_was_parsed(string $action): void {
  if ($action !== '' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return;
  }

  $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
  if ($contentLength <= 0 || !empty($_POST) || !empty($_FILES)) {
    return;
  }

  $postMax = size_from_ini((string) ini_get('post_max_size'));
  $uploadMax = size_from_ini((string) ini_get('upload_max_filesize'));
  $limit = $postMax > 0 ? human_size($postMax) : (string) ini_get('post_max_size');

  throw new RuntimeException(
    'Сервер не принял загрузку: общий размер запроса ' . human_size($contentLength)
    . ' больше лимита PHP post_max_size ' . $limit
    . '. Сейчас upload_max_filesize: ' . ($uploadMax > 0 ? human_size($uploadMax) : (string) ini_get('upload_max_filesize')) . '.'
  );
}

function load_items(string $file): array {
  if (in_array(basename($file), ['skills.php', 'projects.php'], true)) {
    return ez_load_items_for_file($file);
  }

  $items = require $file;
  return is_array($items) ? array_values($items) : [];
}

function load_assoc(string $file): array {
  if (in_array(basename($file), ['settings.php', 'contacts.php', 'about.php', 'tags.php'], true)) {
    return ez_load_assoc_for_file($file);
  }

  $items = require $file;
  return is_array($items) ? $items : [];
}

function save_items(string $file, array $items): void {
  if (in_array(basename($file), ['skills.php', 'projects.php'], true)) {
    if (ez_save_items_for_file($file, $items)) {
      return;
    }

    throw new RuntimeException('Не получилось сохранить данные в SQLite.');
  }

  $export = var_export(array_values($items), true);
  $php = "<?php\n\nreturn " . $export . ";\n";
  if (file_put_contents($file, $php, LOCK_EX) === false) {
    throw new RuntimeException('Не получилось сохранить файл: ' . basename($file));
  }
}

function save_assoc(string $file, array $items): void {
  if (in_array(basename($file), ['settings.php', 'contacts.php', 'about.php', 'tags.php'], true)) {
    if (ez_save_assoc_for_file($file, $items)) {
      return;
    }

    throw new RuntimeException('Не получилось сохранить данные в SQLite.');
  }

  $export = var_export($items, true);
  $php = "<?php\n\nreturn " . $export . ";\n";
  if (file_put_contents($file, $php, LOCK_EX) === false) {
    throw new RuntimeException('Не получилось сохранить файл: ' . basename($file));
  }
}

function is_uploaded_asset(string $path): bool {
  return strpos($path, '/assets/img/uploads/') === 0
    || strpos($path, '/assets/video/uploads/') === 0;
}

function is_project_placeholder_asset(string $path): bool {
  return in_array($path, [
    ez_default_project_image_path(),
    '/assets/img/projects/broadcast.svg',
    '/assets/img/projects/esports.svg',
    '/assets/img/projects/kvn.svg',
    '/assets/img/projects/light.svg',
    '/assets/img/projects/network.svg',
    '/assets/img/projects/resolume.svg',
    '/assets/img/projects/systems.svg',
    '/assets/img/projects/touchdesigner.svg',
  ], true);
}

function is_video_asset(string $path): bool {
  $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
  return in_array($extension, ['mp4', 'webm', 'mov', 'm4v'], true);
}

function is_default_skill_icon(string $path): bool {
  return ez_normalize_asset_path($path) === ez_default_skill_icon_path();
}

function is_default_project_image(string $path): bool {
  return ez_normalize_asset_path($path) === ez_default_project_image_path();
}

function delete_uploaded_asset(string $path, string $root): void {
  if (!is_uploaded_asset($path)) {
    return;
  }

  $relativePath = ltrim($path, '/');
  $fullPath = realpath($root . '/' . $relativePath);
  $allowedRoots = [
    realpath($root . '/assets/img/uploads'),
    realpath($root . '/assets/video/uploads'),
  ];

  foreach ($allowedRoots as $uploadsPath) {
    if ($fullPath && $uploadsPath && strpos($fullPath, $uploadsPath) === 0 && is_file($fullPath)) {
      unlink($fullPath);
      return;
    }
  }
}

function remove_deleted_assets(array $paths, array $deletedPaths, string $root): array {
  $deletedPaths = array_values(array_unique(array_filter($deletedPaths, 'is_string')));
  foreach ($deletedPaths as $path) {
    delete_uploaded_asset($path, $root);
  }

  return array_values(array_filter($paths, static fn($path) => !in_array($path, $deletedPaths, true)));
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

function upload_error_message(int $errorCode): string {
  return match ($errorCode) {
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл больше лимита сервера. Проверь upload_max_filesize, post_max_size и client_max_body_size.',
    UPLOAD_ERR_PARTIAL => 'Файл загрузился не полностью. Попробуй ещё раз.',
    UPLOAD_ERR_NO_TMP_DIR => 'На сервере нет временной папки для загрузок.',
    UPLOAD_ERR_CANT_WRITE => 'Сервер не смог записать файл на диск.',
    UPLOAD_ERR_EXTENSION => 'PHP-расширение остановило загрузку файла.',
    default => 'Ошибка загрузки файла.',
  };
}

function save_upload(
  string $field,
  string $uploadFsDir,
  string $uploadWebDir,
  string $fallback = '',
  bool $allowVideo = false,
  ?string $videoUploadFsDir = null,
  ?string $videoUploadWebDir = null,
  int $maxImageSize = 8 * 1024 * 1024
): string {
  if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return $fallback;
  }

  $file = $_FILES[$field];
  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    throw new RuntimeException(upload_error_message((int) ($file['error'] ?? UPLOAD_ERR_OK)));
  }

  $tmpName = (string) $file['tmp_name'];
  $originalName = (string) $file['name'];
  $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
  $videoExtensions = ['mp4', 'webm', 'mov', 'm4v'];
  if ($allowVideo) {
    $allowedExtensions = array_merge($allowedExtensions, $videoExtensions);
  }
  if (!in_array($extension, $allowedExtensions, true)) {
    throw new RuntimeException($allowVideo
      ? 'Можно загружать изображения и видео: jpg, png, webp, gif, svg, mp4, webm, mov, m4v.'
      : 'Можно загружать только изображения: jpg, png, webp, gif, svg.');
  }

  $isVideo = in_array($extension, $videoExtensions, true);
  $maxSize = $isVideo && $allowVideo ? 80 * 1024 * 1024 : $maxImageSize;
  if (($file['size'] ?? 0) > $maxSize) {
    throw new RuntimeException('Файл слишком большой. Максимум ' . human_size($maxSize) . '.');
  }

  if ($isVideo) {
    $mime = '';
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime = (string) finfo_file($finfo, $tmpName);
        finfo_close($finfo);
      }
    }
    if ($mime !== '' && strpos($mime, 'video/') !== 0 && $mime !== 'application/octet-stream') {
      throw new RuntimeException('Файл не похож на видео.');
    }
  } elseif ($extension === 'svg') {
    validate_svg_upload($tmpName);
  } else {
    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
      throw new RuntimeException('Файл не похож на изображение.');
    }
  }

  $targetFsDir = $uploadFsDir;
  $targetWebDir = $uploadWebDir;
  if ($isVideo && $allowVideo) {
    $targetFsDir = $videoUploadFsDir ?: $uploadFsDir;
    $targetWebDir = $videoUploadWebDir ?: $uploadWebDir;
  }

  if (!is_dir($targetFsDir) && !mkdir($targetFsDir, 0775, true)) {
    throw new RuntimeException('Не получилось создать папку загрузок.');
  }

  $name = slugify(pathinfo($originalName, PATHINFO_FILENAME));
  $targetName = $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
  $targetPath = $targetFsDir . '/' . $targetName;

  if (!move_uploaded_file($tmpName, $targetPath)) {
    throw new RuntimeException('Не получилось сохранить загруженный файл.');
  }

  return $targetWebDir . '/' . $targetName;
}

function save_multiple_uploads(
  string $field,
  string $uploadFsDir,
  string $uploadWebDir,
  bool $allowVideo = false,
  ?string $videoUploadFsDir = null,
  ?string $videoUploadWebDir = null,
  int $maxImageSize = 8 * 1024 * 1024
): array {
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
    $saved[] = save_upload(
      '_single_gallery_upload',
      $uploadFsDir,
      $uploadWebDir,
      '',
      $allowVideo,
      $videoUploadFsDir,
      $videoUploadWebDir,
      $maxImageSize
    );
    unset($_FILES['_single_gallery_upload']);
  }

  return $saved;
}

function normalize_project_media(array $project, array $deletedGallery = []): array {
  $defaultImage = ez_default_project_image_path();
  $project['default_image'] = $defaultImage;
  $project['gallery'] = array_values(array_unique(array_filter($project['gallery'] ?? [])));

  $hasUploadedProjectMedia = false;
  foreach ($project['gallery'] as $galleryItem) {
    if (is_uploaded_asset((string) $galleryItem)) {
      $hasUploadedProjectMedia = true;
      break;
    }
  }

  if ($hasUploadedProjectMedia) {
    $project['gallery'] = array_values(array_filter($project['gallery'], static function ($path) {
      return !is_project_placeholder_asset((string) $path);
    }));
  }

  $deletedGallery = array_values(array_filter($deletedGallery, 'is_string'));
  $imageCandidates = array_values(array_filter($project['gallery'], static fn($path) => !is_video_asset((string) $path)));
  $currentImage = (string) ($project['image'] ?? '');

  if ($currentImage === '' || is_project_placeholder_asset($currentImage) || in_array($currentImage, $deletedGallery, true) || !in_array($currentImage, $imageCandidates, true)) {
    $project['image'] = $imageCandidates[0] ?? $defaultImage;
  }

  $videoCandidates = array_values(array_filter($project['gallery'], static fn($path) => is_video_asset((string) $path)));
  $currentVideo = (string) ($project['video'] ?? '');
  $project['video'] = $currentVideo !== '' && !in_array($currentVideo, $deletedGallery, true) && in_array($currentVideo, $videoCandidates, true)
    ? $currentVideo
    : ($videoCandidates[0] ?? null);

  if ($project['image'] !== '' && !is_default_project_image($project['image']) && !in_array($project['image'], $project['gallery'], true)) {
    array_unshift($project['gallery'], $project['image']);
  }

  if (!empty($project['video']) && !in_array($project['video'], $project['gallery'], true)) {
    $project['gallery'][] = $project['video'];
  }

  if (empty($project['gallery'])) {
    $project['gallery'] = [$project['image']];
  }

  return $project;
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
    assert_post_was_parsed(is_string($action) ? $action : '');

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
      $defaultIcon = ez_default_skill_icon_path();
      $icon = $oldIcon !== '' ? $oldIcon : $defaultIcon;

      if (!empty($_POST['delete_icon'])) {
        delete_uploaded_asset($oldIcon, $GLOBALS['root']);
        $icon = $defaultIcon;
      }

      $icon = save_upload('icon_upload', $GLOBALS['skillUploadFsDir'], $GLOBALS['skillUploadWebDir'], $icon);

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
      $deletedGallery = $_POST['delete_gallery'] ?? [];
      $existingGallery = remove_deleted_assets(list_from_array($_POST['gallery_existing'] ?? []), $deletedGallery, $GLOBALS['root']);
      $uploadedGallery = save_multiple_uploads(
        'gallery_uploads',
        $GLOBALS['projectUploadFsDir'],
        $GLOBALS['projectUploadWebDir'],
        true,
        $GLOBALS['projectVideoUploadFsDir'],
        $GLOBALS['projectVideoUploadWebDir'],
        40 * 1024 * 1024
      );
      $oldImage = (string) ($old['image'] ?? '');
      $oldVideo = (string) ($old['video'] ?? '');
      $defaultImage = ez_default_project_image_path();
      $selectedImage = trim((string) ($_POST['primary_image'] ?? ''));
      $imageUpload = save_upload(
        'image_upload',
        $GLOBALS['projectUploadFsDir'],
        $GLOBALS['projectUploadWebDir'],
        '',
        false,
        null,
        null,
        40 * 1024 * 1024
      );
      $videoUpload = save_upload(
        'video_upload',
        $GLOBALS['projectUploadFsDir'],
        $GLOBALS['projectUploadWebDir'],
        '',
        true,
        $GLOBALS['projectVideoUploadFsDir'],
        $GLOBALS['projectVideoUploadWebDir']
      );

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
        'image' => '',
        'default_image' => $defaultImage,
        'gallery' => array_values(array_unique(array_filter(array_merge(
          $existingGallery,
          $uploadedGallery,
          $imageUpload !== '' ? [$imageUpload] : [],
          $videoUpload !== '' ? [$videoUpload] : []
        )))),
        'video' => null,
        'tags' => array_values(array_unique(array_merge(
          list_from_array($_POST['tags_select'] ?? []),
          list_from_text((string) ($_POST['tags'] ?? ''))
        ))),
        'tools' => list_from_text((string) ($_POST['tools'] ?? '')),
      ];

      if ($item['title'] === '') {
        throw new RuntimeException('У проекта должно быть название.');
      }

      $imageCandidates = array_values(array_filter($item['gallery'], static fn($path) => !is_video_asset((string) $path)));
      if ($imageUpload !== '') {
        $item['image'] = $imageUpload;
      } elseif ($selectedImage !== '' && in_array($selectedImage, $imageCandidates, true)) {
        $item['image'] = $selectedImage;
      } elseif ($oldImage !== '' && !in_array($oldImage, (array) $deletedGallery, true) && in_array($oldImage, $imageCandidates, true)) {
        $item['image'] = $oldImage;
      } else {
        $item['image'] = $imageCandidates[0] ?? $defaultImage;
      }

      $videoCandidates = array_values(array_filter($item['gallery'], static fn($path) => is_video_asset((string) $path)));
      if ($videoUpload !== '') {
        $item['video'] = $videoUpload;
      } elseif ($oldVideo !== '' && !in_array($oldVideo, (array) $deletedGallery, true) && in_array($oldVideo, $videoCandidates, true)) {
        $item['video'] = $oldVideo;
      } else {
        $item['video'] = $videoCandidates[0] ?? null;
      }

      $item = normalize_project_media($item, (array) $deletedGallery);

      if ($index === null) {
        $projects[] = $item;
      } else {
        $projects[$index] = $item;
      }

      save_items($GLOBALS['projectsFile'], $projects);
      redirect_admin('projects', 'Проект сохранён.');
    } elseif ($action === 'upload_project_media') {
      require_login();
      check_csrf();

      $projects = load_items($GLOBALS['projectsFile']);
      $index = (int) ($_POST['index'] ?? -1);
      if (!isset($projects[$index])) {
        throw new RuntimeException('Сначала сохрани проект, потом загружай в него медиа.');
      }

      $uploadedMedia = save_multiple_uploads(
        'project_media_uploads',
        $GLOBALS['projectUploadFsDir'],
        $GLOBALS['projectUploadWebDir'],
        true,
        $GLOBALS['projectVideoUploadFsDir'],
        $GLOBALS['projectVideoUploadWebDir'],
        40 * 1024 * 1024
      );

      if (empty($uploadedMedia)) {
        throw new RuntimeException('Выбери фото или видео для загрузки.');
      }

      $project = $projects[$index];
      $project['gallery'] = array_values(array_unique(array_filter(array_merge(
        $project['gallery'] ?? [],
        $uploadedMedia
      ))));

      $imageCandidates = array_values(array_filter($uploadedMedia, static fn($path) => !is_video_asset((string) $path)));
      $videoCandidates = array_values(array_filter($uploadedMedia, static fn($path) => is_video_asset((string) $path)));

      if (!empty($imageCandidates) && (empty($project['image']) || is_project_placeholder_asset((string) $project['image']))) {
        $project['image'] = $imageCandidates[0];
      }

      if (!empty($videoCandidates) && empty($project['video'])) {
        $project['video'] = $videoCandidates[0];
      }

      $projects[$index] = normalize_project_media($project);
      save_items($GLOBALS['projectsFile'], $projects);
      redirect_admin_project($index, 'Медиа проекта загружены.');
    } elseif ($action === 'delete_project') {
      require_login();
      check_csrf();
      $projects = load_items($GLOBALS['projectsFile']);
      $index = (int) ($_POST['index'] ?? -1);
      if (isset($projects[$index])) {
        delete_uploaded_asset((string) ($projects[$index]['image'] ?? ''), $GLOBALS['root']);
        delete_uploaded_asset((string) ($projects[$index]['video'] ?? ''), $GLOBALS['root']);
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

      $oldAbout = load_assoc($GLOBALS['aboutFile']);
      $existingGallery = remove_deleted_assets(list_from_array($_POST['about_gallery_existing'] ?? []), $_POST['delete_about_gallery'] ?? [], $GLOBALS['root']);
      $uploadedGallery = save_multiple_uploads('about_gallery_uploads', $GLOBALS['aboutUploadFsDir'], $GLOBALS['aboutUploadWebDir']);

      $about = [
        'lead' => trim((string) ($_POST['about_lead'] ?? '')),
        'paragraphs' => list_from_lines((string) ($_POST['about_paragraphs'] ?? '')),
        'focus' => trim((string) ($_POST['about_focus'] ?? '')),
        'visual_top_left' => trim((string) ($_POST['visual_top_left'] ?? '')),
        'visual_top_right' => trim((string) ($_POST['visual_top_right'] ?? '')),
        'visual_tags' => list_from_text((string) ($_POST['visual_tags'] ?? '')),
        'gallery' => array_values(array_unique(array_merge($existingGallery, $uploadedGallery))),
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
    .file-list { display: grid; gap: 10px; margin: -4px 0 12px; }
    .file-list__item { display: grid; grid-template-columns: 72px 1fr auto; gap: 12px; align-items: center; padding: 10px; border: 1px solid var(--line); background: var(--row); }
    .file-list__item img, .file-list__item video, .file-list__video-thumb { width: 72px; height: 54px; object-fit: contain; border: 1px solid var(--line); background: var(--image-bg); }
    .file-list__video-thumb { display: grid; place-items: center; color: var(--muted); font: 10px/1 var(--font-mono, monospace); text-transform: uppercase; }
    .file-list__body { display: grid; gap: 6px; color: var(--text); }
    .file-list__meta { color: var(--muted); font-size: 12px; }
    .file-list__empty { padding: 14px; border: 1px dashed var(--line); color: var(--muted); background: var(--field); }
    .upload-preview { display: grid; gap: 8px; margin: -4px 0 12px; }
    .upload-preview:empty { display: none; }
    .media-dropzone { display: grid; gap: 8px; padding: 16px; border: 1px dashed var(--line); background: var(--field); color: var(--muted); cursor: pointer; }
    .media-dropzone strong { color: var(--text); }
    .media-dropzone input { margin-top: 4px; }
    .media-dropzone.is-dragover { border-color: var(--text); background: var(--soft); color: var(--text); }
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
      .file-list__item { grid-template-columns: 72px 1fr; }
      .file-list__item .check, .file-list__item .media-choice { grid-column: 1 / -1; }
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
            <?php if (!empty($about['gallery'])): ?>
              <div class="file-list" aria-label="Текущие фото блока обо мне">
                <?php foreach ($about['gallery'] as $aboutImageIndex => $image): ?>
                  <label class="file-list__item">
                    <img src="<?= h($image) ?>" alt="">
                    <span class="file-list__body">
                      <strong>Фото <?= $aboutImageIndex + 1 ?></strong>
                      <span class="file-list__meta">Загружено</span>
                    </span>
                    <input type="hidden" name="about_gallery_existing[]" value="<?= h($image) ?>">
                    <?php if (is_uploaded_asset((string) $image)): ?>
                      <span class="check"><input name="delete_about_gallery[]" type="checkbox" value="<?= h($image) ?>"> Удалить с сервера</span>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
                <div class="file-list__empty">Фото пока не загружены.</div>
              <?php endif; ?>
              <label>Загрузить фото <input name="about_gallery_uploads[]" type="file" accept="image/*,.svg" multiple></label>
              <div class="upload-preview" data-upload-preview></div>
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
              <label>Загрузить главную картинку <input name="image_upload" type="file" accept="image/*,.svg"></label>
              <div class="upload-preview" data-upload-preview></div>
              <?php
                $projectGallery = array_values($editProject['gallery'] ?? []);
                $projectImage = (string) ($editProject['image'] ?? '');
                $projectVideo = (string) ($editProject['video'] ?? '');
                if ($projectImage !== '' && !in_array($projectImage, $projectGallery, true)) {
                  array_unshift($projectGallery, $projectImage);
                }
                if ($projectVideo !== '' && !in_array($projectVideo, $projectGallery, true)) {
                  $projectGallery[] = $projectVideo;
                }
              ?>
              <?php if (!empty($projectGallery)): ?>
                <div class="file-list" aria-label="Текущая галерея проекта">
                  <?php foreach ($projectGallery as $mediaIndex => $image): ?>
                    <label class="file-list__item">
                      <?php if (is_video_asset((string) $image)): ?>
                        <span class="file-list__video-thumb">video</span>
                      <?php else: ?>
                        <img src="<?= h($image) ?>" alt="">
                      <?php endif; ?>
                      <span class="file-list__body">
                        <strong><?= is_video_asset((string) $image) ? 'Видео' : 'Фото' ?> <?= $mediaIndex + 1 ?></strong>
                        <?php if (!is_video_asset((string) $image)): ?>
                          <span class="media-choice"><input name="primary_image" type="radio" value="<?= h($image) ?>" <?= ($projectImage === $image || ($projectImage === '' && $mediaIndex === 0)) ? 'checked' : '' ?>> Главная картинка</span>
                        <?php else: ?>
                          <span class="file-list__meta">Видео в галерее</span>
                        <?php endif; ?>
                      </span>
                      <input type="hidden" name="gallery_existing[]" value="<?= h($image) ?>">
                      <span class="check"><input name="delete_gallery[]" type="checkbox" value="<?= h($image) ?>" <?= !is_uploaded_asset((string) $image) ? 'disabled' : '' ?>> Удалить</span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="file-list__empty">Медиа пока не загружены. Будет показана дефолтная картинка проекта.</div>
              <?php endif; ?>
              <label class="media-dropzone" data-dropzone>
                <strong>Дозагрузить фото/видео в галерею</strong>
                <span>Можно выбрать файлы или перетащить их сюда. Эти файлы сохранятся вместе с кнопкой "Сохранить проект".</span>
                <input name="gallery_uploads[]" type="file" accept="image/*,.svg,video/mp4,video/webm,video/quicktime,.mov,.m4v" multiple>
              </label>
              <div class="upload-preview" data-upload-preview></div>
              <label>Загрузить видео <input name="video_upload" type="file" accept="video/mp4,video/webm,video/quicktime,.mov,.m4v"></label>
              <div class="upload-preview" data-upload-preview></div>
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
            <?php if ($editProject): ?>
              <form method="post" action="/admin/?tab=projects&edit_project=<?= h($editProjectIndex) ?>" enctype="multipart/form-data" class="media-upload-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_project_media">
                <input type="hidden" name="index" value="<?= h($editProjectIndex) ?>">
                <label class="media-dropzone" data-dropzone>
                  <strong>Быстро загрузить медиа проекта</strong>
                  <span>Перетащи сюда фото/видео или выбери файлы. Текст проекта не трогаем.</span>
                  <input name="project_media_uploads[]" type="file" accept="image/*,.svg,video/mp4,video/webm,video/quicktime,.mov,.m4v" multiple>
                </label>
                <div class="upload-preview" data-upload-preview></div>
                <div class="form-actions">
                  <button class="primary" type="submit">Загрузить медиа</button>
                </div>
              </form>
            <?php endif; ?>
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
              <?php $skillIcon = (string) ($editSkill['icon'] ?? ez_default_skill_icon_path()); ?>
              <div class="file-list" aria-label="Иконка навыка">
                <label class="file-list__item">
                  <img src="<?= h($skillIcon) ?>" alt="">
                  <span class="file-list__body">
                    <strong><?= is_default_skill_icon($skillIcon) ? 'Дефолтная иконка' : 'Загруженная иконка' ?></strong>
                    <span class="file-list__meta"><?= is_default_skill_icon($skillIcon) ? 'Можно загрузить свою' : 'Отображается на сайте' ?></span>
                  </span>
                </label>
              </div>
              <?php if (!empty($editSkill['icon']) && is_uploaded_asset((string) $editSkill['icon'])): ?>
                <label class="check"><input name="delete_icon" type="checkbox" value="1"> Удалить загруженную иконку с сервера и вернуть дефолт</label>
              <?php endif; ?>
              <label>Загрузить иконку/картинку <input name="icon_upload" type="file" accept="image/*,.svg"></label>
              <div class="upload-preview" data-upload-preview></div>
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
      const renderPreview = (input) => {
        const label = input.closest('label');
        const preview = label?.nextElementSibling?.matches('[data-upload-preview]')
          ? label.nextElementSibling
          : null;
        if (!preview) return;

        preview.innerHTML = '';
        [...input.files].forEach((file, index) => {
          const item = document.createElement('div');
          item.className = 'file-list__item';

          const isVideo = file.type.startsWith('video/');
          if (isVideo) {
            const thumb = document.createElement('span');
            thumb.className = 'file-list__video-thumb';
            thumb.textContent = 'video';
            item.append(thumb);
          } else {
            const image = document.createElement('img');
            image.alt = '';
            image.src = URL.createObjectURL(file);
            image.onload = () => URL.revokeObjectURL(image.src);
            item.append(image);
          }

          const body = document.createElement('span');
          body.className = 'file-list__body';

          const title = document.createElement('strong');
          title.textContent = `${isVideo ? 'Видео' : 'Фото'} ${index + 1}`;

          const meta = document.createElement('span');
          meta.className = 'file-list__meta';
          meta.textContent = 'Будет загружено после сохранения';

          body.append(title, meta);
          item.append(body);
          preview.append(item);
        });
      };

      document.addEventListener('change', (event) => {
        const input = event.target.closest('input[type="file"]');
        if (input) renderPreview(input);
      });

      document.addEventListener('dragover', (event) => {
        const zone = event.target.closest('[data-dropzone]');
        if (!zone) return;

        event.preventDefault();
        zone.classList.add('is-dragover');
      });

      document.addEventListener('dragleave', (event) => {
        const zone = event.target.closest('[data-dropzone]');
        if (!zone || zone.contains(event.relatedTarget)) return;

        zone.classList.remove('is-dragover');
      });

      document.addEventListener('drop', (event) => {
        const zone = event.target.closest('[data-dropzone]');
        if (!zone) return;

        event.preventDefault();
        zone.classList.remove('is-dragover');

        const input = zone.querySelector('input[type="file"]');
        if (!input || !event.dataTransfer?.files?.length) return;

        input.files = event.dataTransfer.files;
        renderPreview(input);
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
