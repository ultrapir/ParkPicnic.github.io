<?php
require_once __DIR__ . '/../init.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/admin/orders.php');




if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  
  if (isset($_POST['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id=?');
    $stmt->execute([$id]);
    redirect('/admin/orders.php');
  }

  
  $status = $_POST['status'] ?? 'new';
  if (!isset(order_statuses()[$status])) $status = 'new';
  $stmt = $pdo->prepare('UPDATE orders SET status=?, updated_at=datetime("now") WHERE id=?');
  $stmt->execute([$status, $id]);
  redirect('/admin/order-view.php?id=' . $id);
}

$stmt = $pdo->prepare('
  SELECT o.*, g.title AS gazebo_title
  FROM orders o
  LEFT JOIN gazebos g ON g.id=o.gazebo_id
  WHERE o.id=?
');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect('/admin/orders.php');

include __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:720px;">
  <h3>Заявка #<?= (int)$order['id'] ?></h3>
  <p><strong>Дата:</strong> <?= e($order['date']) ?></p>
  <p><strong>Клиент:</strong> <?= e($order['customer_name']) ?></p>
  <p><strong>Телефон:</strong> <?= e($order['phone']) ?></p>
  <p><strong>E‑mail:</strong> <?= e($order['email']) ?></p>
  <p><strong>Беседка:</strong> <?= e($order['gazebo_title'] ?? ('ID ' . (int)$order['gazebo_id'])) ?></p>
  <p><strong>Кол-во беседок:</strong> <?= (int)$order['qty'] ?></p>
  <p><strong>Комментарий:</strong><br><?= nl2br(e($order['comment'])) ?></p>

  <form method="post" style="margin-top:16px;">
    <?= csrf_input() ?>
    <label>Статус
      <select name="status">
        <?php foreach (order_statuses() as $k=>$v): ?>
          <option value="<?= e($k) ?>" <?= $k===$order['status']?'selected':'' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" style="margin-left:8px">Сохранить</button>
    <button class="btn danger" name="delete" value="1" style="margin-left:6px"
      onclick="return confirm('Удалить заявку #<?= (int)$order['id'] ?>? Это действие необратимо.')">
      Удалить
  </button>
    <a class="btn secondary" href="/admin/orders.php">Назад</a>
  </form>
</div>
<?php include __DIR__ . '/partials/footer.php';