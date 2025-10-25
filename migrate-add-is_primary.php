<?php
$dbPath = __DIR__ . '/data/parkpicnic.sqlite';
if (!is_file($dbPath)) { die("DB not found: $dbPath\n"); }

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$cols = $pdo->query('PRAGMA table_info(gallery_images)')->fetchAll(PDO::FETCH_ASSOC);
$has = false;
foreach ($cols as $c) { if (strcasecmp($c['name'], 'is_primary') === 0) { $has = true; break; } }

if (!$has) {
  $pdo->exec('ALTER TABLE gallery_images ADD COLUMN is_primary INTEGER NOT NULL DEFAULT 0');
  echo "Column is_primary added.\n";
} else {
  echo "Column is_primary already exists.\n";
}
