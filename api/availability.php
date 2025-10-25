<?php
// /api/availability.php
require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

$gazeboId = (int)($_GET['gazebo_id'] ?? 0);
$date     = trim((string)($_GET['date'] ?? ''));

if ($gazeboId <= 0 || !$date) {
    http_response_code(422);
    echo json_encode(['error' => 'Укажите gazebo_id и date (YYYY-MM-DD)'], JSON_UNESCAPED_UNICODE);
    exit;
}


$stmt = $pdo->prepare('SELECT stock_total FROM gazebos WHERE id=? AND is_active=1');
$stmt->execute([$gazeboId]);
$total = (int)$stmt->fetchColumn();

if ($total <= 0) {
    echo json_encode(['total'=>0,'booked'=>0,'available'=>0], JSON_UNESCAPED_UNICODE);
    exit;
}


$statuses = ['new','confirmed','done']; 
$in = implode(',', array_fill(0, count($statuses), '?'));

$stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM orders WHERE gazebo_id=? AND date=? AND status IN ($in)");
$params = array_merge([$gazeboId, $date], $statuses);
$stmt->execute($params);
$booked = (int)$stmt->fetchColumn();

$available = max(0, $total - $booked);

echo json_encode([
    'gazebo_id' => $gazeboId,
    'date'      => $date,
    'total'     => $total,
    'booked'    => $booked,
    'available' => $available,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);