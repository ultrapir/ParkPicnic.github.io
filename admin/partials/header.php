<?php
// if (empty($_SESSION['csrf'])) {
//   $_SESSION['csrf'] = bin2hex(random_bytes(32));
// }
require_once dirname(__DIR__, 2) . '/init.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <!-- <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES) ?>"> -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — ParkPicnic</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0;background:#0f1115;color:#e6e6e6}
    header{background:#151924;border-bottom:1px solid #222;padding:12px 20px;display:flex;justify-content:space-between;align-items:center}
    a{color:#60a5fa;text-decoration:none}
    nav a{margin-right:14px}
    .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
    table{width:100%;border-collapse:collapse;background:#0f1115}
    th,td{border-bottom:1px solid #222;padding:10px;text-align:left}
    input,select,textarea{background:#0b0d12;border:1px solid #2a2f3a;color:#e6e6e6;border-radius:6px;padding:8px}
    button,.btn{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 12px;cursor:pointer}
    .btn.secondary{background:#374151}
    .btn.danger{background:#dc2626}
    .muted{color:#9aa0a6}
    .grid{display:grid;gap:12px}
    .grid2{grid-template-columns:1fr 1fr}
    .card{background:#0b0d12;border:1px solid #222;border-radius:10px;padding:16px}
  </style>
</head>
<body>
<header>
  <div><strong>ParkPicnic — Admin</strong></div>
  <nav>
    <a href="/admin/products.php">Беседки</a>
    <a href="/admin/orders.php">Заявки</a>
    <a href="/admin/content.php">Контент</a>
    <?php if ($user): ?>
      <span class="muted">| <?= e($user['login']) ?></span>
      <a style="margin-left:10px" href="/admin/auth/logout.php">Выйти</a>
    <?php endif; ?>
  </nav>
</header>
<div class="wrap">
