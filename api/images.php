<?php
require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $gid = (int)($_GET['gazebo_id'] ?? 0);

    
    $hasGazeboId = false;
    try {
        $cols = $pdo->query('PRAGMA table_info(gallery_images)')->fetchAll();
        foreach ($cols as $c) {
            $name = $c['name'] ?? ($c[1] ?? '');
            if (is_string($name) && strcasecmp($name, 'gazebo_id') === 0) { $hasGazeboId = true; break; }
        }
    } catch (Throwable $e) {
        
    }

    if ($hasGazeboId) {
        if ($gid > 0) {
            $stmt = $pdo->prepare('SELECT src, alt, aos, gazebo_id
                                   FROM gallery_images
                                   WHERE is_active = 1 AND gazebo_id = ?
                                   ORDER BY sort_order, id');
            $stmt->execute([$gid]);
        } else {
            $stmt = $pdo->query('SELECT src, alt, aos, gazebo_id
                                 FROM gallery_images
                                 WHERE is_active = 1
                                 ORDER BY sort_order, id');
        }
    } else {
       
        $stmt = $pdo->query('SELECT src, alt, aos
                             FROM gallery_images
                             WHERE is_active = 1
                             ORDER BY sort_order, id');
    }

    $rows = $stmt->fetchAll();
    $out = array_map(function($r) use ($hasGazeboId){
        return [
            'src' => $r['src'],
            'alt' => $r['alt'] ?? '',
            'aos' => $r['aos'] ?? 'fade-up',
            'gazebo_id' => $hasGazeboId ? (isset($r['gazebo_id']) ? (int)$r['gazebo_id'] : null) : null,
        ];
    }, $rows);

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(200);
    echo '[]';
}


