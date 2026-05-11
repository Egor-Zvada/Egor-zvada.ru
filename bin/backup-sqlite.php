<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/content.php';

$backupDir = ez_root() . '/storage/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
  fwrite(STDERR, "Could not create backup directory.\n");
  exit(1);
}

$backup = [
  'created_at' => date(DATE_ATOM),
  'settings' => ez_get_settings(),
  'contacts' => ez_get_contacts(),
  'about' => ez_get_about(),
  'skills' => ez_get_skills(),
  'projects' => ez_get_projects(),
  'tags' => ez_get_tags(),
];

$path = $backupDir . '/content-' . date('Ymd-His') . '.json';
$json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
  fwrite(STDERR, "Could not write backup.\n");
  exit(1);
}

echo "SQLite content backup: " . $path . PHP_EOL;
