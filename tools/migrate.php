<?php
$root = dirname(__DIR__);          
require $root . '/init.php';



function columnExists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare('PRAGMA table_info(' . $table . ')');
  $st->execute();
  foreach ($st as $r) if (strcasecmp($r['name'], $col) === 0) return true;
  return false;
}

if (!columnExists($pdo, 'gazebo_images', 'is_primary')) {
  $pdo->exec('ALTER TABLE gazebo_images ADD COLUMN is_primary INTEGER NOT NULL DEFAULT 0');
}
if (!columnExists($pdo, 'gazebo_images', 'sort_order')) {
  $pdo->exec('ALTER TABLE gazebo_images ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0');
}


$pdo->exec('INSERT INTO gazebo_images(gazebo_id, url, is_primary, sort_order)
            SELECT id, image_url, 1, 0 FROM gazebos
            WHERE image_url IS NOT NULL AND TRIM(image_url) <> ""
              AND NOT EXISTS (
                SELECT 1 FROM gazebo_images gi WHERE gi.gazebo_id = gazebos.id
              )');

echo "OK\n";