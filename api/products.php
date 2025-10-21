<?php
require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

try {
    
    $imgsByGazebo = [];
    try {
        $imgRows = $pdo->query('SELECT gazebo_id, url, is_primary FROM gazebo_images WHERE is_active = 1 ORDER BY is_primary DESC, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($imgRows as $ir) {
            $gid = (int)$ir['gazebo_id'];
            if (!isset($imgsByGazebo[$gid])) $imgsByGazebo[$gid] = [];
            $imgsByGazebo[$gid][] = $ir['url'];
        }
    } catch (Throwable $e) {
        
        $imgsByGazebo = [];
    }

    $stmt = $pdo->query('SELECT id, title, price, description, image_url, stock_total FROM gazebos WHERE is_active = 1 ORDER BY sort_order, id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = array_map(function($r) use ($imgsByGazebo) {
        $gid = (int)$r['id'];
        $images = $imgsByGazebo[$gid] ?? [];

    
        $cover = ($r['image_url'] ?? '') !== '' ? $r['image_url'] : ($images[0] ?? '');


        if ($cover && (empty($images) || $images[0] !== $cover)) {
            array_unshift($images, $cover);
        }

        return [
            'id'          => (string)$gid,
            'gazeboId'    => $gid,
            'title'       => $r['title'],
            'price'       => $r['price'],
            'description' => $r['description'],
            'cover'       => $cover,
            'images'      => $images,
            'stockTotal'  => (int)($r['stock_total'] ?? 1),
        ];
    }, $rows);

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
