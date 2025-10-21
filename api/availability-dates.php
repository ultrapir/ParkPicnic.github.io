<?php
// /api/availability-dates.php
require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

$gazeboId = (int)($_GET['gazebo_id'] ?? 0);
$days     = max(1, min(365, (int)($_GET['days'] ?? 180))); // период вперёд

if ($gazeboId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Укажите gazebo_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

// total
$stmt = $pdo->prepare('SELECT stock_total FROM gazebos WHERE id=? AND is_active=1');
$stmt->execute([$gazeboId]);
$total = (int)$stmt->fetchColumn();
if ($total <= 0) {
    echo json_encode(['total'=>0,'fully_booked'=>[], 'booked_map'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
}

// агрегируем бронирования по датам
$statuses = ['new','confirmed','done'];
$in = implode(',', array_fill(0, count($statuses), '?'));

$start = (new DateTime('today'))->format('Y-m-d');
$end   = (new DateTime("+$days days"))->format('Y-m-d');

$stmt = $pdo->prepare("SELECT date, COALESCE(SUM(qty),0) AS booked
                       FROM orders
                       WHERE gazebo_id=? AND date BETWEEN ? AND ? AND status IN ($in)
                       GROUP BY date");
$params = array_merge([$gazeboId, $start, $end], $statuses);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$bookedMap = [];
foreach ($rows as $r) $bookedMap[$r['date']] = (int)$r['booked'];

$fully = [];
$dt = new DateTime($start);
$endDt = new DateTime($end);
while ($dt <= $endDt) {
    $d = $dt->format('Y-m-d');
    $b = $bookedMap[$d] ?? 0;
    if ($b >= $total) $fully[] = $d;
    $dt->modify('+1 day');
}

echo json_encode([
    'gazebo_id'    => $gazeboId,
    'total'        => $total,
    'fully_booked' => $fully,
    'booked_map'   => $bookedMap,
    'start'        => $start,
    'end'          => $end,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);