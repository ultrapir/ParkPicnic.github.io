<?php
require __DIR__ . '/../init.php';
$q = $pdo->query('SELECT id FROM gazebos');
$ids = $q->fetchAll(PDO::FETCH_COLUMN);
$upd = $pdo->prepare('UPDATE gazebos
    SET image_url = COALESCE(
        (SELECT url FROM gazebo_images gi
         WHERE gi.gazebo_id = gazebos.id AND gi.is_active = 1 AND gi.is_primary = 1
         ORDER BY gi.id LIMIT 1),
        (SELECT url FROM gazebo_images gi
         WHERE gi.gazebo_id = gazebos.id AND gi.is_active = 1
         ORDER BY gi.sort_order, gi.id LIMIT 1),
        ""
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE id = ?');
foreach ($ids as $gid) { $upd->execute([$gid]); echo "fixed #$gid\n"; }
echo "done\n";
