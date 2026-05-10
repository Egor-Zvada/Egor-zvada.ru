<?php
declare(strict_types=1);

session_start();

$root = dirname(__DIR__);
$config = require __DIR__ . '/config.php';
$skillsFile = $root . '/data/skills.php';
$projectsFile = $root . '/data/projects.php';
$settingsFile = $root . '/data/settings.php';
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

function list_from_text(string $value): array {
  $parts = preg_split('/[\r\n,]+/u', $value) ?: [];
  $parts = array_map('trim', $parts);
  return array_values(array_filter($parts, static fn($part) => $part !== ''));
}

function slugify(string $value): string {
  $value = function_exists('mb_strtolower')
    ? mb_strtolower(trim($value), 'UTF-8')
    : strtolower(trim($value));
  $value = preg_replace('/[^a-z0-9а-яё]+/u', '-', $value) ?? '';
  $value = trim($value, '-');
  return $value !== '' ? $value : 'item';
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

  if ($extension !== 'svg') {
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
      $passwordHash = hash('sha256', $password);

      if (hash_equals($config['username'], $username) && hash_equals($config['password_sha256'], $passwordHash)) {
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

      $item = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'icon' => save_upload('icon_upload', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir'], trim((string) ($_POST['icon'] ?? $old['icon'] ?? ''))),
        'level' => trim((string) ($_POST['level'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'stack' => list_from_text((string) ($_POST['stack'] ?? '')),
        'is_hidden' => !empty($_POST['is_hidden']),
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
      $uploadedGallery = save_multiple_uploads('gallery_uploads', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir']);

      $item = [
        'id' => trim((string) ($_POST['id'] ?? '')) ?: slugify($title),
        'title' => $title,
        'date' => trim((string) ($_POST['date'] ?? '')) ?: date('Y-m-d'),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'category_label' => trim((string) ($_POST['category_label'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'full_description' => trim((string) ($_POST['full_description'] ?? '')),
        'image' => save_upload('image_upload', $GLOBALS['uploadFsDir'], $GLOBALS['uploadWebDir'], trim((string) ($_POST['image'] ?? $old['image'] ?? ''))),
        'gallery' => array_values(array_unique(array_merge($manualGallery, $uploadedGallery))),
        'video' => trim((string) ($_POST['video'] ?? '')) ?: null,
        'tags' => list_from_text((string) ($_POST['tags'] ?? '')),
        'tools' => list_from_text((string) ($_POST['tools'] ?? '')),
        'is_hidden' => !empty($_POST['is_hidden']),
      ];

      if ($item['title'] === '') {
        throw new RuntimeException('У проекта должно быть название.');
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
        array_splice($projects, $index, 1);
        save_items($GLOBALS['projectsFile'], $projects);
      }
      redirect_admin('projects', 'Проект удалён.');
    } elseif ($action === 'save_settings') {
      require_login();
      check_csrf();

      $settings = load_assoc($GLOBALS['settingsFile']);
      $settings['version'] = trim((string) ($_POST['version'] ?? '')) ?: '0.2-beta';
      save_assoc($GLOBALS['settingsFile'], $settings);
      redirect_admin('settings', 'Настройки сохранены.');
    }
  }
} catch (Throwable $exception) {
  $error = $exception->getMessage();
}

$loggedIn = is_logged_in();
$skills = $loggedIn ? load_items($skillsFile) : [];
$projects = $loggedIn ? load_items($projectsFile) : [];
$settings = $loggedIn ? load_assoc($settingsFile) : [];
$editSkill = isset($_GET['edit_skill'], $skills[(int) $_GET['edit_skill']]) ? $skills[(int) $_GET['edit_skill']] : null;
$editSkillIndex = $editSkill === null ? '' : (string) ((int) $_GET['edit_skill']);
$editProject = isset($_GET['edit_project'], $projects[(int) $_GET['edit_project']]) ? $projects[(int) $_GET['edit_project']] : null;
$editProjectIndex = $editProject === null ? '' : (string) ((int) $_GET['edit_project']);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Админка — egor_zvada</title>
  <style>
    :root { color-scheme: dark; --bg: #080808; --panel: #111; --line: #303030; --text: #f3f3f3; --muted: #aaa; --soft: #1a1a1a; }
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
    .panel { border: 1px solid var(--line); background: var(--panel); padding: 18px; }
    .panel h2 { margin: 0 0 14px; font-size: 22px; letter-spacing: -.025em; }
    .list { display: grid; gap: 8px; }
    .row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; padding: 12px; border: 1px solid var(--line); background: #0d0d0d; }
    .row strong { display: block; }
    .row span { display: block; color: var(--muted); font-size: 13px; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    form { margin: 0; }
    label { display: grid; gap: 6px; margin-bottom: 12px; color: var(--muted); font-size: 13px; }
    input, textarea, select { width: 100%; border: 1px solid var(--line); background: #090909; color: var(--text); padding: 10px 12px; font: inherit; }
    textarea { min-height: 92px; resize: vertical; }
    input[type="checkbox"] { width: auto; }
    .check { display: flex; align-items: center; gap: 9px; }
    .message { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #315f37; background: #102315; color: #c9ffd0; }
    .error { margin-bottom: 14px; padding: 12px 14px; border: 1px solid #733; background: #2a1010; color: #ffd0d0; }
    .hint { color: var(--muted); font-size: 13px; margin-top: -4px; }
    .login { max-width: 420px; margin: 14vh auto 0; }
    .danger { border-color: #5c2b2b; color: #ffd3d3; }
    @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } .top { align-items: flex-start; flex-direction: column; } .row { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <main class="admin">
    <?php if (!$loggedIn): ?>
      <section class="panel login">
        <h1 class="brand">egor_zvada / admin</h1>
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
          <a class="<?= $tab === 'settings' ? 'is-active' : '' ?>" href="/admin/?tab=settings">Настройки</a>
          <a href="/" target="_blank" rel="noopener">Открыть сайт</a>
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
        <section class="panel">
          <h2>Настройки сайта</h2>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">
            <label>Версия в футере <input name="version" value="<?= h($settings['version'] ?? '0.2-beta') ?>" required></label>
            <button class="primary" type="submit">Сохранить настройки</button>
          </form>
        </section>
      <?php elseif ($tab === 'projects'): ?>
        <div class="grid">
          <section class="panel">
            <h2>Проекты</h2>
            <div class="list">
              <?php foreach ($projects as $index => $project): ?>
                <div class="row">
                  <div>
                    <strong><?= h($project['title'] ?? '') ?></strong>
                    <span><?= h($project['category_label'] ?? $project['category'] ?? '') ?> · <?= h($project['date'] ?? '') ?><?= !empty($project['is_hidden']) ? ' · скрыт' : '' ?></span>
                  </div>
                  <div class="actions">
                    <a class="button" href="/admin/?tab=projects&edit_project=<?= $index ?>">Редактировать</a>
                    <form method="post" onsubmit="return confirm('Удалить проект?')">
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
            <h2><?= $editProject ? 'Редактировать проект' : 'Добавить проект' ?></h2>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_project">
              <input type="hidden" name="index" value="<?= h($editProjectIndex) ?>">
              <label>ID <input name="id" value="<?= h($editProject['id'] ?? '') ?>" placeholder="auto-from-title"></label>
              <label>Название <input name="title" value="<?= h($editProject['title'] ?? '') ?>" required></label>
              <label>Дата <input name="date" type="date" value="<?= h($editProject['date'] ?? date('Y-m-d')) ?>"></label>
              <label>Категория <input name="category" value="<?= h($editProject['category'] ?? '') ?>" placeholder="event"></label>
              <label>Название категории <input name="category_label" value="<?= h($editProject['category_label'] ?? '') ?>" placeholder="event tech"></label>
              <label>Краткое описание <textarea name="description" required><?= h($editProject['description'] ?? '') ?></textarea></label>
              <label>Полное описание <textarea name="full_description"><?= h($editProject['full_description'] ?? '') ?></textarea></label>
              <label>Главная картинка, путь <input name="image" value="<?= h($editProject['image'] ?? '') ?>" placeholder="/assets/img/projects/example.svg"></label>
              <label>Загрузить главную картинку <input name="image_upload" type="file" accept="image/*,.svg"></label>
              <label>Галерея, пути через запятую или с новой строки <textarea name="gallery"><?= h(implode("\n", $editProject['gallery'] ?? [])) ?></textarea></label>
              <label>Дозагрузить картинки в галерею <input name="gallery_uploads[]" type="file" accept="image/*,.svg" multiple></label>
              <label>Видео, путь или URL <input name="video" value="<?= h($editProject['video'] ?? '') ?>"></label>
              <label>Теги <textarea name="tags"><?= h(implode("\n", $editProject['tags'] ?? [])) ?></textarea></label>
              <label>Инструменты <textarea name="tools"><?= h(implode("\n", $editProject['tools'] ?? [])) ?></textarea></label>
              <label class="check"><input name="is_hidden" type="checkbox" value="1" <?= !empty($editProject['is_hidden']) ? 'checked' : '' ?>> Скрыть на сайте до кнопки "Показать ещё"</label>
              <button class="primary" type="submit">Сохранить проект</button>
            </form>
          </section>
        </div>
      <?php else: ?>
        <div class="grid">
          <section class="panel">
            <h2>Навыки</h2>
            <div class="list">
              <?php foreach ($skills as $index => $skill): ?>
                <div class="row">
                  <div>
                    <strong><?= h($skill['title'] ?? '') ?></strong>
                    <span><?= h($skill['category'] ?? '') ?> · <?= h($skill['level'] ?? '') ?><?= !empty($skill['is_hidden']) ? ' · скрыт' : '' ?></span>
                  </div>
                  <div class="actions">
                    <a class="button" href="/admin/?tab=skills&edit_skill=<?= $index ?>">Редактировать</a>
                    <form method="post" onsubmit="return confirm('Удалить навык?')">
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
            <h2><?= $editSkill ? 'Редактировать навык' : 'Добавить навык' ?></h2>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_skill">
              <input type="hidden" name="index" value="<?= h($editSkillIndex) ?>">
              <label>Название <input name="title" value="<?= h($editSkill['title'] ?? '') ?>" required></label>
              <label>Описание <textarea name="description" required><?= h($editSkill['description'] ?? '') ?></textarea></label>
              <label>Иконка, путь <input name="icon" value="<?= h($editSkill['icon'] ?? '') ?>" placeholder="/assets/svg/icons/example.svg"></label>
              <label>Загрузить иконку/картинку <input name="icon_upload" type="file" accept="image/*,.svg"></label>
              <label>Уровень / подпись <input name="level" value="<?= h($editSkill['level'] ?? '') ?>" placeholder="system admin"></label>
              <label>Категория <input name="category" value="<?= h($editSkill['category'] ?? '') ?>" placeholder="Системы"></label>
              <label>Стек, через запятую или с новой строки <textarea name="stack"><?= h(implode("\n", $editSkill['stack'] ?? [])) ?></textarea></label>
              <label class="check"><input name="is_hidden" type="checkbox" value="1" <?= !empty($editSkill['is_hidden']) ? 'checked' : '' ?>> Скрыть на сайте до кнопки "Показать больше"</label>
              <button class="primary" type="submit">Сохранить навык</button>
            </form>
          </section>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>
