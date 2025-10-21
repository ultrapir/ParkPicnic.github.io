<?php
require_once dirname(__DIR__, 2) . '/init.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $errors[] = 'Ошибка безопасности. Обновите страницу.'; }
    else {
        if (isset($_POST['create']) && users_count($pdo) === 0) {
            $login = trim((string)($_POST['login'] ?? ''));
            $pass  = trim((string)($_POST['password'] ?? ''));
            if (!$login || !$pass) $errors[] = 'Укажите логин и пароль';
            else {
                create_admin($pdo, $login, $pass);
                attempt_login($pdo, $login, $pass);
                redirect('/admin');
            }
        } else {
            $login = trim((string)($_POST['login'] ?? ''));
            $pass  = trim((string)($_POST['password'] ?? ''));
            if (!attempt_login($pdo, $login, $pass)) $errors[] = 'Неверный логин или пароль';
            else redirect('/admin');
        }
    }
}

include __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:480px; margin:40px auto;">
  <h2><?= users_count($pdo) === 0 ? 'Создание администратора' : 'Вход' ?></h2>
  <?php foreach ($errors as $e): ?><p class="muted" style="color:#f87171;">• <?= e($e) ?></p><?php endforeach; ?>
  <form method="post">
    <?= csrf_input() ?>
    <div class="grid">
      <label>Логин<br><input type="text" name="login" required></label>
      <label>Пароль<br><input type="password" name="password" required></label>
      <div>
        <?php if (users_count($pdo) === 0): ?>
          <button name="create" value="1">Создать администратора</button>
        <?php else: ?>
          <button>Войти</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../partials/footer.php';