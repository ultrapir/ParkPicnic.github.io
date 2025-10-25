<?php
require_once __DIR__ . '/../init.php';
require_login();


try {
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_gazebo_images_gid_url ON gazebo_images(gazebo_id, url)');
} catch (Throwable $e) {}


$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    redirect('/admin/products.php');
}

$errorMsg = '';


function valid_image_url(?string $url): bool {
    if ($url === null) return false;
    $u = trim($url);
    if ($u === '') return false;
    if (preg_match('~^https?://~i', $u)) return filter_var($u, FILTER_VALIDATE_URL) !== false;
    if (preg_match('~^/[-a-zA-Z0-9_./%]+$~', $u)) return true;
    return false;
}


function update_gazebo_fallback_image(PDO $pdo, int $gazeboId): void {
    
    $stmt = $pdo->prepare('SELECT url FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 AND is_primary = 1 ORDER BY id LIMIT 1');
    $stmt->execute([$gazeboId]);
    $url = $stmt->fetchColumn();

    
    if (!$url) {
        $stmt = $pdo->prepare('SELECT url FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1');
        $stmt->execute([$gazeboId]);
        $url = $stmt->fetchColumn() ?: '';
    }

    $pdo->prepare('UPDATE gazebos SET image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$url, $gazeboId]);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check()) {
            throw new RuntimeException('Ошибка безопасности. Обновите страницу и попробуйте снова.');
        }

        
        if (isset($_POST['update_gazebo'])) {
           
            $title       = trim((string)($_POST['title'] ?? ''));
            $price       = trim((string)($_POST['price'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $sort_order  = (int)($_POST['sort_order'] ?? 0);
            $stock_total = max(0, (int)($_POST['stock_total'] ?? 1));
            $is_active   = isset($_POST['is_active']) ? 1 : 0;

            if ($title === '') $title = 'Без названия';
            if ($price === '') $price = '—';

            $stmt = $pdo->prepare('UPDATE gazebos
                SET title = ?, price = ?, description = ?, is_active = ?, sort_order = ?, stock_total = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?');
            $stmt->execute([$title, $price, $description, $is_active, $sort_order, $stock_total, $id]);

            redirect('/admin/product-edit.php?id=' . $id);
        }

        if (isset($_POST['delete_gazebo'])) {
            
            $stmt = $pdo->prepare('DELETE FROM gazebos WHERE id = ?');
            $stmt->execute([$id]);
            redirect('/admin/products.php');
        }

        
if (isset($_POST['add_image_url'])) {

  @file_put_contents(__DIR__.'/../data/upload-debug.log',
  sprintf("[%s] POST:%s\nFILES:%s\n\n",
    date('Y-m-d H:i:s'),
    json_encode($_POST, JSON_UNESCAPED_UNICODE),
    json_encode($_FILES, JSON_UNESCAPED_UNICODE)
  ),
  FILE_APPEND
);

    $url  = trim((string)($_POST['image_url_new'] ?? ''));
    $sort = (int)($_POST['image_sort_new'] ?? 0);

    
    if ($url !== '' && !preg_match('~^(https?://|/)~i', $url)) {
        throw new InvalidArgumentException('Некорректный URL (добавьте http(s):// или начните с /)');
    }
    if ($url === '' || !valid_image_url($url)) {
        throw new InvalidArgumentException('Укажите корректный URL изображения');
    }

    $pdo->beginTransaction();
    try{
        $q = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM gazebo_images WHERE gazebo_id = ?');
        $q->execute([$id]);
        $nextSort = (int)$q->fetchColumn();
        $nextSort = $sort !== 0 ? $sort : $nextSort + 10;

        $pdo->prepare('INSERT OR IGNORE INTO gazebo_images (gazebo_id, url, alt, sort_order, is_primary, is_active, created_at)
                              VALUES (?,?,?,?,0,1, CURRENT_TIMESTAMP)')
            ->execute([$id, $url, '', $nextSort]);

        
        $q = $pdo->prepare('SELECT COUNT(*) FROM gazebo_images WHERE gazebo_id=? AND is_primary=1');
        $q->execute([$id]);
        if ((int)$q->fetchColumn() === 0) {
            $pdo->prepare('UPDATE gazebo_images SET is_primary=1 WHERE id = (SELECT id FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1)')->execute([$id]);
        }

        update_gazebo_fallback_image($pdo, $id);
        $pdo->commit();
        redirect('/admin/product-edit.php?id=' . $id);
    } catch(Throwable $e){
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errorMsg = 'Ошибка добавления по URL: ' . $e->getMessage();
    }
}


if (isset($_POST['add_image_files'])) {

  @file_put_contents(__DIR__.'/../data/upload-debug.log',
  sprintf("[%s] POST:%s\nFILES:%s\n\n",
    date('Y-m-d H:i:s'),
    json_encode($_POST, JSON_UNESCAPED_UNICODE),
    json_encode($_FILES, JSON_UNESCAPED_UNICODE)
  ),
  FILE_APPEND
);

    
    $once = (string)($_POST['once'] ?? '');
    $okOnce = $once && isset($_SESSION['upload_once'][$once]);
    if (!$okOnce) {
      
      redirect('/admin/product-edit.php?id=' . $id);
    }
    unset($_SESSION['upload_once'][$once]);

    $sort = (int)($_POST['image_sort_new_files'] ?? 0);

    $files = (!empty($_FILES['image_file_new']) && is_array($_FILES['image_file_new']))
        ? files_normalize($_FILES['image_file_new'])
        : [];

    
    $files = array_values(array_filter($files, function($f){
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && !empty($f['tmp_name'])
            && is_uploaded_file($f['tmp_name'])
            && ($f['size'] ?? 0) > 0;
    }));

    if (!$files) {
        $errorMsg = 'Файлы не выбраны';
    } else {
        $pdo->beginTransaction();
        $added = 0; $errors = [];
        try{
            $q = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM gazebo_images WHERE gazebo_id = ?');
            $q->execute([$id]);
            $nextSort = (int)$q->fetchColumn();
            $nextSort = $sort !== 0 ? $sort - 10 : $nextSort; 

            foreach ($files as $f) {
                try{
                    $webPath = upload_image_save($f, 'gazebos'); 
                    $nextSort += 10;
                    $pdo->prepare('INSERT OR IGNORE INTO gazebo_images (gazebo_id, url, alt, sort_order, is_primary, is_active, created_at)
                                          VALUES (?,?,?,?,0,1, CURRENT_TIMESTAMP)')
                        ->execute([$id, $webPath, '', $nextSort]);
                    $added++;
                } catch (Throwable $fe) {
                    $errors[] = $fe->getMessage();
                }
            }

            
            $q = $pdo->prepare('SELECT COUNT(*) FROM gazebo_images WHERE gazebo_id=? AND is_primary=1');
            $q->execute([$id]);
            if ((int)$q->fetchColumn() === 0) {
                $pdo->prepare('UPDATE gazebo_images SET is_primary=1 WHERE id = (SELECT id FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1)')->execute([$id]);
            }

            update_gazebo_fallback_image($pdo, $id);
            $pdo->commit();

            if ($errors) $errorMsg = 'Добавлено: '.$added.'. Ошибки: '.e(implode('; ', $errors));
            redirect('/admin/product-edit.php?id=' . $id);
        } catch(Throwable $e){
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMsg = 'Ошибка загрузки файлов: ' . $e->getMessage();
        }
    }
}



        if (isset($_POST['make_primary'])) {
            
            $imgId = (int)$_POST['make_primary'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE gazebo_images SET is_primary = 0 WHERE gazebo_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE gazebo_images SET is_primary = 1 WHERE id = ? AND gazebo_id = ?')->execute([$imgId, $id]);
            update_gazebo_fallback_image($pdo, $id);
            $pdo->commit();
            redirect('/admin/product-edit.php?id=' . $id);
        }

        if (isset($_POST['delete_image'])) {
            
            $imgId = (int)$_POST['delete_image'];

            $pdo->beginTransaction();

            
            $stmt = $pdo->prepare('SELECT is_primary FROM gazebo_images WHERE id = ? AND gazebo_id = ?');
            $stmt->execute([$imgId, $id]);
            $wasPrimary = (int)$stmt->fetchColumn();

            
            $pdo->prepare('DELETE FROM gazebo_images WHERE id = ? AND gazebo_id = ?')->execute([$imgId, $id]);

            
            if ($wasPrimary === 1) {
                $pdo->prepare('UPDATE gazebo_images SET is_primary = 0 WHERE gazebo_id = ?')->execute([$id]);
                $pdo->prepare('UPDATE gazebo_images SET is_primary=1 WHERE id = (SELECT id FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1)')
                    ->execute([$id]);
            }

            update_gazebo_fallback_image($pdo, $id);

            $pdo->commit();
            redirect('/admin/product-edit.php?id=' . $id);
        }

        if (isset($_POST['save_images'])) {
            
            $ids   = $_POST['img_id'] ?? [];
            $sorts = $_POST['img_sort'] ?? [];
            $acts  = $_POST['img_active'] ?? [];

            $pdo->beginTransaction();

            foreach ($ids as $imgId) {
                $imgId = (int)$imgId;
                $sort  = isset($sorts[$imgId]) ? (int)$sorts[$imgId] : 0;
                $act   = isset($acts[$imgId]) ? 1 : 0;
                $pdo->prepare('UPDATE gazebo_images SET sort_order = ?, is_active = ? WHERE id = ? AND gazebo_id = ?')
                    ->execute([$sort, $act, $imgId, $id]);
            }

            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM gazebo_images WHERE gazebo_id = ? AND is_primary = 1 AND is_active = 1');
            $stmt->execute([$id]);
            $primaryActive = (int)$stmt->fetchColumn();

            if ($primaryActive === 0) {
                $pdo->prepare('UPDATE gazebo_images SET is_primary = 0 WHERE gazebo_id = ?')->execute([$id]);
                $pdo->prepare('UPDATE gazebo_images SET is_primary=1 WHERE id = (SELECT id FROM gazebo_images WHERE gazebo_id = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1)')
                    ->execute([$id]);
            }

            update_gazebo_fallback_image($pdo, $id);

            $pdo->commit();
            redirect('/admin/product-edit.php?id=' . $id);
        }

        
        redirect('/admin/product-edit.php?id=' . $id);

    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}


$stmt = $pdo->prepare('SELECT * FROM gazebos WHERE id = ?');
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item) {
    redirect('/admin/products.php');
}


$imgs = $pdo->prepare('SELECT id, url, is_primary, is_active, sort_order FROM gazebo_images WHERE gazebo_id = ? ORDER BY is_primary DESC, sort_order, id');
$imgs->execute([$id]);
$images = $imgs->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:960px;">
  <?php if (!empty($errorMsg)): ?>
    <p style="color:#f87171"><?= e($errorMsg) ?></p>
  <?php endif; ?>
  <h3>Редактирование беседки #<?= (int)$id ?></h3>

  
  <form method="post">
    <?= csrf_input() ?>
    <div class="grid">
      <label>Название<br><input name="title" value="<?= e($item['title']) ?>" required></label>
      <label>Цена<br><input name="price" value="<?= e($item['price']) ?>" required></label>
      <label>Описание<br><textarea name="description" rows="4"><?= e($item['description']) ?></textarea></label>
      <label>Порядок<br><input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>"></label>
      <label>Всего беседок (шт.)<br><input type="number" name="stock_total" value="<?= (int)($item['stock_total'] ?? 1) ?>" min="0" required></label>
      <label><input type="checkbox" name="is_active" <?= ((int)$item['is_active'] === 1) ? 'checked' : '' ?>> Активна</label>
      <div>
        <button class="btn" name="update_gazebo" value="1">Сохранить</button>
        <button class="btn danger" name="delete_gazebo" value="1" onclick="return confirm('Удалить беседку и все её фото?')">Удалить</button>
        <a class="btn secondary" href="/admin/products.php">Назад</a>
      </div>
    </div>
  </form>
</div>

<div class="card" style="max-width:960px; margin-top:16px;">
  <h3>Фотографии</h3>

  
  <form method="post" style="overflow:auto">
    <?= csrf_input() ?>
    <?php if (!$images): ?>
      <p class="muted">Пока нет фотографий. Добавьте первое фото ниже.</p>
    <?php else: ?>
      <table>
        <tr><th>Превью</th><th>URL</th><th>Порядок</th><th>Активна</th><th>Обложка</th><th></th></tr>
        <?php foreach ($images as $img): $imgId = (int)$img['id']; ?>
          <tr>
            <td style="width:120px">
              <div style="width:110px;height:70px;border:1px solid #222;border-radius:6px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#0f1115">
                <img src="<?= e($img['url']) ?>" alt="" style="max-width:110px;max-height:70px;object-fit:cover">
              </div>
            </td>
            <td><?= e($img['url']) ?></td>
            <td style="width:110px">
              <input type="hidden" name="img_id[]" value="<?= $imgId ?>">
              <input type="number" name="img_sort[<?= $imgId ?>]" value="<?= (int)$img['sort_order'] ?>" style="width:90px">
            </td>
            <td style="width:90px; text-align:center">
              <input type="checkbox" name="img_active[<?= $imgId ?>]" <?= ((int)$img['is_active'] === 1) ? 'checked' : '' ?>>
            </td>
            <td style="width:140px">
              <?php if ((int)$img['is_primary'] === 1): ?>
                <span class="muted">Текущая</span>
              <?php else: ?>
                <button class="btn secondary" name="make_primary" value="<?= $imgId ?>">Сделать обложкой</button>
              <?php endif; ?>
            </td>
            <td style="width:120px">
              <button class="btn danger" name="delete_image" value="<?= $imgId ?>" onclick="return confirm('Удалить фото?')">Удалить</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div style="margin-top:10px">
        <button class="btn" name="save_images" value="1">Сохранить порядок/статус</button>
      </div>
    <?php endif; ?>
  </form>

  <hr style="border-color:#222; margin:16px 0">

  
<form method="post" class="grid" style="grid-template-columns:1fr 160px 140px; gap:8px; align-items:end">
  <?= csrf_input() ?>
  <label>URL нового фото<br>
    <input name="image_url_new" placeholder="/img/gazebos/1.jpg" autocomplete="off">
  </label>
  <label>Порядок<br><input type="number" name="image_sort_new" value="0"></label>
  <div><button class="btn" name="add_image_url" value="1">Добавить по URL</button></div>
</form>


<?php
  $once = bin2hex(random_bytes(16));
  $_SESSION['upload_once'][$once] = time();
?>
<form method="post" enctype="multipart/form-data" class="grid" style="grid-template-columns:1fr 160px 140px; gap:8px; align-items:end; margin-top:8px">
  <?= csrf_input() ?>
  <input type="hidden" name="once" value="<?= e($once) ?>">
  <label>Файлы (можно несколько)<br>
    <input type="file" name="image_file_new[]" accept="image/*" multiple>
  </label>
  <label>Порядок<br><input type="number" name="image_sort_new_files" value="0"></label>
  <div><button class="btn" name="add_image_files" value="1">Загрузить файлы</button></div>
</form>


<script>
  document.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-once]');
    if (!b) return;
    if (b.dataset.locked === '1') { e.preventDefault(); e.stopPropagation(); }
    b.dataset.locked = '1'; b.disabled = true;
  }, { capture:true });
</script>

</div>

<?php include __DIR__ . '/partials/footer.php';