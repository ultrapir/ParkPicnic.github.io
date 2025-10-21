<?php
require_once __DIR__ . '/../init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    redirect('/admin/orders.php');
}

$rows = $pdo->query('
  SELECT o.id, o.date, o.customer_name, o.status, o.qty, g.title AS gazebo_title
  FROM orders o
  LEFT JOIN gazebos g ON g.id=o.gazebo_id
  ORDER BY o.id DESC
  LIMIT 200
')->fetchAll();
include __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h3>Заявки</h3>
  <table>
    <tr><th>ID</th><th>Дата</th><th>Клиент</th><th>Беседка</th><th>Статус</th><th>Количество</th><th>Действие</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e($r['date']) ?></td>
        <td><?= e($r['customer_name']) ?></td>
        <td><?= e($r['gazebo_title'] ?? '') ?></td>
        <td><?= e(order_statuses()[$r['status']] ?? $r['status']) ?></td>
        <td><?= e($r['qty']) ?></td>
        <td style="white-space:nowrap">
          <a href="/admin/order-view.php?id=<?= (int)$r['id'] ?>">Открыть</a>
          <form method="post" action="/admin/orders.php" style="display:inline" onsubmit="return confirm('Удалить заявку #<?= (int)$r['id'] ?>? Это действие необратимо.')">
            <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
            <button class="btn danger" style="margin-left:6px">Удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php include __DIR__ . '/partials/footer.php';