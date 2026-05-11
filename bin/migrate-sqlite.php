<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/content.php';

if (!ez_db_available()) {
  fwrite(STDERR, "PDO SQLite is not available. Install/enable php-sqlite3.\n");
  exit(1);
}

$pdo = ez_db();
if (!$pdo) {
  fwrite(STDERR, "Could not open SQLite database.\n");
  exit(1);
}

$counts = [
  'skills' => (int) $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn(),
  'projects' => (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
  'settings' => (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn(),
  'contacts' => (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
  'tags' => (int) $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn(),
];

echo "SQLite database: " . ez_db_path() . PHP_EOL;
foreach ($counts as $table => $count) {
  echo str_pad($table, 12) . $count . PHP_EOL;
}
