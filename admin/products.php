<?php
require_once __DIR__ . '/../init.php';
require_login();

$errorMsg = '';
$okMsg    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['gazebo_id'])) {
    try {
        if (!csrf_check()) { throw new RuntimeException('CSRF'); }

        $gid = (int)$_POST['gazebo_id'];
        if ($gid <= 0) { throw new InvalidArgumentException('Некорректный ID беседки'); }

        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM gazebo_images WHERE gazebo_id = ?')->execute([$gid]);
        $pdo->prepare('DELETE FROM gazebos WHERE id = ?')->execute([$gid]);
        $pdo->commit();

        $okMsg = 'Беседка #' . $gid . ' удалена.';
        redirect('/admin/products.php'); // можно раскомментировать, если хотите редирект после удаления
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMsg = 'Ошибка удаления: ' . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    if (!csrf_check()) { die('CSRF'); }

    $title       = trim((string)($_POST['title'] ?? ''));
    $price       = trim((string)($_POST['price'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $stock_total = max(0, (int)($_POST['stock_total'] ?? 1));

    if ($title === '') $title = 'Без названия';
    if ($price === '') $price = '—';

    
    $images = $_POST['images'] ?? [];
    if (!is_array($images)) { $images = []; }
    $images = array_values(array_filter(array_map('trim', $images), fn($v) => $v !== ''));

    $filePaths = [];
    if (!empty($_FILES['images_files']) && is_array($_FILES['images_files'])) {
      foreach (files_normalize($_FILES['images_files']) as $f) {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        try {
            $filePaths[] = upload_image_save($f, 'gazebos');
        } catch (Throwable $fe) {
            
        }
      }
    }
    $images = array_merge($images, $filePaths);

    $firstImage = $images[0] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO gazebos(title, price, description, image_url, is_active, sort_order, stock_total, created_at)
                               VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $stmt->execute([$title, $price, $description, $firstImage, $is_active, $sort_order, $stock_total]);
        $gazeboId = (int)$pdo->lastInsertId();

        if ($images) {
            $ins = $pdo->prepare('INSERT INTO gazebo_images (gazebo_id, url, alt, sort_order, is_primary, is_active, created_at)
                                  VALUES (?,?,?,?,?,1,CURRENT_TIMESTAMP)');
            foreach ($images as $idx => $url) {
                $isPrimary = ($idx === 0) ? 1 : 0;
                $ins->execute([$gazeboId, $url, '', $idx * 10, $isPrimary]);
            }
        }

        $pdo->commit();
        redirect('/admin/products.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMsg = 'Ошибка создания: ' . $e->getMessage();
    }
}

// Список
$rows = $pdo->query('SELECT * FROM gazebos ORDER BY sort_order, id')->fetchAll();
include __DIR__ . '/partials/header.php';
?>
<div class="grid grid2">
  <div class="card">
    <?php if (!empty($errorMsg)): ?>
      <p style="color:#f87171"><?= e($errorMsg) ?></p>
    <?php endif; ?>
    <h3>Добавить беседку</h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div class="grid">
        <label>Название<br><input name="title" required></label>
        <label>Цена<br><input name="price" required></label>
        <label>Описание<br><textarea name="description" rows="3"></textarea></label>
        <label>Фотографии (URL)</label>
        <div id="imagesList"></div>
        <button type="button" class="btn secondary" id="addImageBtn">+ Добавить фото</button>
        <div class="muted" style="margin-top:6px">Всего фото: <span id="imgCount">0</span></div>
        <label>Файлы изображений (можно несколько)<br>
          <input type="file" name="images_files[]" accept="image/*" multiple>
        </label>
        <label>Порядок<br><input type="number" name="sort_order" value="0"></label>
        <label>Всего беседок (шт.)<br><input type="number" name="stock_total" value="1" min="0" required></label>
        <label><input type="checkbox" name="is_active" checked> Активна</label>
        <div><button class="btn" name="create" value="1">Сохранить</button></div>
      </div>
    </form>
  </div>
  <div class="card">
    <h3>Беседки</h3>
    <table>
      <tr>
        <th>ID</th>
        <th>Название</th>
        <th>Цена</th>
        <th>Статус</th>
        <th>Всего (шт.)</th>
        <th>Действия</th>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e($r['price']) ?></td>
          <td><?= ((int)$r['is_active'] === 1) ? 'Активна' : 'Скрыта' ?></td>
          <td><?= (int)($r['stock_total'] ?? 1) ?></td>
            <td>
              <a href="/admin/product-edit.php?id=<?= (int)$r['id'] ?>">Изменить</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить беседку и все её фото?')">
                <?= csrf_input() ?>
                <input type="hidden" name="gazebo_id" value="<?= (int)$r['id'] ?>">
                <button class="btn danger" name="delete" value="1">Удалить</button>
              </form>
            </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<script>
(function(){
  const list = document.getElementById('imagesList');
  const addBtn = document.getElementById('addImageBtn');
  const cnt = document.getElementById('imgCount');

  function row(value=''){
    const w = document.createElement('div');
    w.style.display='flex';
    w.style.gap='8px';
    w.style.margin='6px 0';
    w.innerHTML = `
      <input name="images[]" placeholder="/img/gazebos/1.jpg" value="${value.replace(/"/g,'&quot;')}" style="flex:1">
      <button type="button" class="btn danger" title="Удалить">×</button>
    `;
    w.querySelector('button').addEventListener('click', () => { w.remove(); updateCount(); });
    return w;
  }
  function updateCount(){
    const values = [...list.querySelectorAll('input[name="images[]"]')].map(i=>i.value.trim()).filter(Boolean);
    cnt.textContent = values.length;
  }
  addBtn.addEventListener('click', () => { list.appendChild(row()); updateCount(); });
  list.addEventListener('input', updateCount);

  list.appendChild(row());
  updateCount();
})();
</script>

<?php include __DIR__ . '/partials/footer.php';