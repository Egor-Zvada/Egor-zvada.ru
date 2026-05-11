<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/content.php';

function is_cli_video_asset(string $path): bool {
  $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
  return in_array($extension, ['mp4', 'webm', 'mov', 'm4v'], true);
}

function move_uploaded_asset_to(string $path, string $targetWebDir): string {
  $path = ez_normalize_asset_path($path);
  if ($path === '' || strpos($path, $targetWebDir . '/') === 0) {
    return $path;
  }

  $isKnownUpload = strpos($path, '/assets/img/uploads/') === 0
    || strpos($path, '/assets/video/uploads/') === 0;
  if (!$isKnownUpload) {
    return $path;
  }

  $root = ez_root();
  $source = realpath($root . '/' . ltrim($path, '/'));
  if (!$source || !is_file($source)) {
    return $path;
  }

  $targetDir = $root . $targetWebDir;
  if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
    return $path;
  }

  $filename = basename($source);
  $target = $targetDir . '/' . $filename;
  if ($source === realpath($target)) {
    return $targetWebDir . '/' . $filename;
  }

  if (is_file($target)) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $filename = $name . '-' . bin2hex(random_bytes(3)) . ($extension !== '' ? '.' . $extension : '');
    $target = $targetDir . '/' . $filename;
  }

  return rename($source, $target) ? $targetWebDir . '/' . $filename : $path;
}

if (!ez_db_available()) {
  fwrite(STDERR, "PDO SQLite is not available. Install/enable php-sqlite3.\n");
  exit(1);
}

if (!ez_db()) {
  fwrite(STDERR, "Could not open SQLite database.\n");
  exit(1);
}

$skills = ez_get_skills();
foreach ($skills as &$skill) {
  $skill['icon'] = move_uploaded_asset_to((string) ($skill['icon'] ?? ''), '/assets/img/uploads/skills')
    ?: ez_default_skill_icon_path();
  $skill['default_icon'] = ez_default_skill_icon_path();
}
unset($skill);
ez_save_skills($skills);

$about = ez_get_about();
$about['gallery'] = array_values(array_map(
  static fn($path) => move_uploaded_asset_to((string) $path, '/assets/img/uploads/about'),
  $about['gallery'] ?? []
));
ez_save_about($about);

$projects = ez_get_projects();
foreach ($projects as &$project) {
  $project['image'] = move_uploaded_asset_to((string) ($project['image'] ?? ''), '/assets/img/uploads/projects')
    ?: ez_default_project_image_path();
  $project['default_image'] = ez_default_project_image_path();

  $gallery = [];
  foreach (($project['gallery'] ?? []) as $path) {
    $path = (string) $path;
    $gallery[] = is_cli_video_asset($path)
      ? move_uploaded_asset_to($path, '/assets/video/uploads/projects')
      : move_uploaded_asset_to($path, '/assets/img/uploads/projects');
  }
  $project['gallery'] = array_values(array_unique(array_filter($gallery)));

  $video = (string) ($project['video'] ?? '');
  if ($video !== '') {
    $project['video'] = move_uploaded_asset_to($video, '/assets/video/uploads/projects');
  }
}
unset($project);
ez_save_projects($projects);

echo "Assets normalized.\n";
echo "Skill uploads:   /assets/img/uploads/skills\n";
echo "About uploads:   /assets/img/uploads/about\n";
echo "Project images:  /assets/img/uploads/projects\n";
echo "Project videos:  /assets/video/uploads/projects\n";
