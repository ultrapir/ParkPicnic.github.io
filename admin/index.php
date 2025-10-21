<?php
// if (empty($_SESSION['csrf_token'])) {
//   $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }
require_once __DIR__ . '/../init.php';
require_login();
include __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Добро пожаловать!</h2>
  <p>Используйте меню для управления беседками и заявками.</p>
</div>
<?php include __DIR__ . '/partials/footer.php';
