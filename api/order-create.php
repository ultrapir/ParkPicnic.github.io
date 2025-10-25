<?php
// require __DIR__ . '/../init.php';
// header('Content-Type: application/json; charset=utf-8');
// header('Cache-Control: no-store');

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     http_response_code(405);
//     echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
//     exit;
// }


// $input = [
//     'gazebo_id' => (int)($_POST['gazebo_id'] ?? 0),
//     'date'      => trim((string)($_POST['date'] ?? '')),
//     'name'      => trim((string)($_POST['name'] ?? '')),
//     'phone'     => trim((string)($_POST['phone'] ?? '')),
//     'email'     => trim((string)($_POST['email'] ?? '')),
//     'comment'   => trim((string)($_POST['comment'] ?? '')),
//     'qty'       => max(1, (int)($_POST['qty'] ?? 1)),
// ];


// if (!$input['gazebo_id'] || !$input['date'] || !$input['name'] || (!$input['phone'] && !$input['email'])) {
//     http_response_code(422);
//     echo json_encode(['error' => 'Заполните обязательные поля'], JSON_UNESCAPED_UNICODE);
//     exit;
// }


// $hasRequestKey = false;
// try {
    
//     $hasRequestKey = sqlite_has_column($pdo, 'orders', 'request_key');
//     if ($hasRequestKey) {
//         $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_request_key ON orders(request_key)');
//     }
// } catch (Throwable $e) {
//     $hasRequestKey = false; 
// }

// $requestKey = '';
// if ($hasRequestKey) {
//     $sid = session_id() ?: '';
//     $bucket = (int)floor(time() / 30); 
//     $requestKey = hash('sha256', implode('|', [
//         (int)$input['gazebo_id'],
//         (string)$input['date'],
//         (string)$input['phone'],
//         (int)$input['qty'],
//         $sid,
//         $bucket
//     ]));
// }

// try {
//     $pdo->exec('BEGIN IMMEDIATE');

//     $stmt = $pdo->prepare('SELECT stock_total FROM gazebos WHERE id=? AND is_active=1');
//     $stmt->execute([$input['gazebo_id']]);
//     $total = (int)$stmt->fetchColumn();
//     if ($total <= 0) {
//         $pdo->rollBack();
//         http_response_code(422);
//         echo json_encode(['error' => 'Беседка недоступна'], JSON_UNESCAPED_UNICODE);
//         exit;
//     }

//     $statuses = ['new','confirmed','done'];
//     $in = implode(',', array_fill(0, count($statuses), '?'));

//     $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM orders WHERE gazebo_id=? AND date=? AND status IN ($in)");
//     $params = array_merge([$input['gazebo_id'], $input['date']], $statuses);
//     $stmt->execute($params);
//     $booked = (int)$stmt->fetchColumn();

//     $available = max(0, $total - $booked);
//     if ($input['qty'] > $available) {
//         $pdo->rollBack();
//         http_response_code(422);
//         echo json_encode(['error' => 'Недостаточно свободных беседок', 'available' => $available, 'total' => $total], JSON_UNESCAPED_UNICODE);
//         exit;
//     }

//     $orderId = 0;
//     $createdNow = false;

//     if ($hasRequestKey) {
//         $sql = 'INSERT INTO orders (gazebo_id, date, customer_name, phone, email, comment, qty, status, request_key, created_at, updated_at)
//                 VALUES (?,?,?,?,?,?,?,"new",?, datetime("now"), datetime("now"))
//                 ON CONFLICT(request_key) DO NOTHING';
//         $stmt = $pdo->prepare($sql);
//         $stmt->execute([
//             $input['gazebo_id'], $input['date'], $input['name'],
//             $input['phone'], $input['email'], $input['comment'], $input['qty'],
//             $requestKey
//         ]);

//         $orderId = (int)$pdo->lastInsertId();
//         if ($orderId > 0) {
//             $createdNow = true;
//         } else {
//             $q = $pdo->prepare('SELECT id FROM orders WHERE request_key = ? LIMIT 1');
//             $q->execute([$requestKey]);
//             $orderId = (int)$q->fetchColumn();
//         }
//     } else {
//         $stmt = $pdo->prepare('INSERT INTO orders (gazebo_id, date, customer_name, phone, email, comment, qty, status, created_at, updated_at)
//                                VALUES (?,?,?,?,?,?,?,"new", datetime("now"), datetime("now"))');
//         $stmt->execute([
//             $input['gazebo_id'], $input['date'], $input['name'],
//             $input['phone'], $input['email'], $input['comment'], $input['qty']
//         ]);
//         $orderId = (int)$pdo->lastInsertId();
//         $createdNow = true;
//     }

//     $pdo->commit();

//     echo json_encode(['success' => true, 'order_id' => $orderId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
// } catch (Throwable $e) {
//     if ($pdo->inTransaction()) { $pdo->rollBack(); }
//     http_response_code(500);
//     echo json_encode(['error' => 'DB error'], JSON_UNESCAPED_UNICODE);
// }


require __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Нормально отвечаем на OPTIONS (на всякий случай)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

// Читаем вход: поддерживаем application/json и form-data/x-www-form-urlencoded
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
$read = function(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
};

if (stripos($ct, 'application/json') !== false) {
  $in = $read();
  $src = [
    'gazebo_id' => $in['gazebo_id'] ?? $in['gazeboId'] ?? 0,
    'date'      => $in['date'] ?? '',
    'name'      => $in['name'] ?? ($in['customer_name'] ?? ''),
    'phone'     => $in['phone'] ?? '',
    'email'     => $in['email'] ?? '',
    'comment'   => $in['comment'] ?? '',
  ];
} else {
  $src = [
    'gazebo_id' => $_POST['gazebo_id'] ?? $_POST['gazeboId'] ?? 0,
    'date'      => $_POST['date'] ?? '',
    'name'      => $_POST['name'] ?? ($_POST['customer_name'] ?? ''),
    'phone'     => $_POST['phone'] ?? '',
    'email'     => $_POST['email'] ?? '',
    'comment'   => $_POST['comment'] ?? '',
  ];
}

$input = [
  'gazebo_id' => (int)$src['gazebo_id'],
  'date'      => trim((string)$src['date']),
  'name'      => trim((string)$src['name']),
  'phone'     => trim((string)$src['phone']),
  'email'     => trim((string)$src['email']),
  'comment'   => trim((string)$src['comment']),
];

if (!$input['gazebo_id'] || !$input['date'] || !$input['name'] || (!$input['phone'] && !$input['email'])) {
  http_response_code(422);
  echo json_encode(['error' => 'Заполните обязательные поля', 'fields' => $input], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo->beginTransaction();
  $stmt = $pdo->prepare('INSERT INTO orders (gazebo_id, date, customer_name, phone, email, comment, status, created_at, updated_at)
                         VALUES (?,?,?,?,?,?,"new", datetime("now"), datetime("now"))');
  $stmt->execute([$input['gazebo_id'], $input['date'], $input['name'], $input['phone'], $input['email'], $input['comment']]);
  $orderId = (int)$pdo->lastInsertId();
  $pdo->commit();

  $cfg = require __DIR__ . '/../config.php';

  echo json_encode(['success' => true, 'order_id' => $orderId], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}