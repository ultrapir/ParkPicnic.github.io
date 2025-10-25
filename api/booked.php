<?php
require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $gazeboId = (int)($_GET['gazebo_id'] ?? 0);
    $start    = trim((string)($_GET['start'] ?? ''));
    $end      = trim((string)($_GET['end'] ?? ''));
    $qty      = max(1, (int)($_GET['qty'] ?? 1));

    if ($gazeboId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'gazebo_id required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    
    $re = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($re, $start) || !preg_match($re, $end)) {
       
        $first = new DateTime('first day of this month 00:00:00');
        $last  = new DateTime('last day of this month 00:00:00');
        $start = $first->format('Y-m-d');
        $end   = $last->format('Y-m-d');
    }

    
    $stmt = $pdo->prepare('SELECT stock_total FROM gazebos WHERE id = ? AND is_active = 1');
    $stmt->execute([$gazeboId]);
    $total = (int)$stmt->fetchColumn();

    if ($total <= 0) {
        echo json_encode(['disabled' => [], 'total' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    
    $statuses = ['new','confirmed','done'];
    $in = implode(',', array_fill(0, count($statuses), '?'));

    $sql = "SELECT date, COALESCE(SUM(qty),0) AS booked
            FROM orders
            WHERE gazebo_id = ?
              AND date BETWEEN ? AND ?
              AND status IN ($in)
            GROUP BY date";
    $params = array_merge([$gazeboId, $start, $end], $statuses);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $disabled = [];
    $bookedByDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ymd = (string)$row['date'];
        $booked = (int)$row['booked'];
        $bookedByDate[$ymd] = $booked;
    }

    
    $cur = new DateTime($start);
    $last = new DateTime($end);
    while ($cur <= $last) {
        $ymd = $cur->format('Y-m-d');
        $booked = $bookedByDate[$ymd] ?? 0;
        $available = max(0, $total - $booked);
        if ($available < $qty) {
            $disabled[] = $ymd;
        }
        $cur->modify('+1 day');
    }

    echo json_encode(['disabled' => $disabled, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error'], JSON_UNESCAPED_UNICODE);
}
