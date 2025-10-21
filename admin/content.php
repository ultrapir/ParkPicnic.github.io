<?php
require_once __DIR__ . '/../init.php';
require_login();

$msg = '';
$errorDetails = [];

// Проверим наличие столбца gazebo_id в gallery_images (SQLite совместимо)
$hasGazeboId = false;
try {
    $cols = $pdo->query('PRAGMA table_info(gallery_images)')->fetchAll();
    foreach ($cols as $c) {
        $name = $c['name'] ?? ($c[1] ?? '');
        if (is_string($name) && strcasecmp($name, 'gazebo_id') === 0) { $hasGazeboId = true; break; }
    }
} catch (Throwable $e) {
    // Тихо игнорируем — будем работать без gazebo_id
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = 'Ошибка безопасности. Обновите страницу.';
    } else {
        try {
            // ===== 1) ТЕКСТЫ САЙТА (kv) =====
            if (isset($_POST['kv']) && is_array($_POST['kv'])) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO content (ckey, cvalue, updated_at)
                     VALUES (?, ?, CURRENT_TIMESTAMP)
                     ON CONFLICT(ckey) DO UPDATE SET
                       cvalue = excluded.cvalue,
                       updated_at = CURRENT_TIMESTAMP'
                );
                foreach ($_POST['kv'] as $k => $v) {
                    $k = trim((string)$k);
                    $v = trim((string)$v);
                    if ($k === '') continue;
                    $stmt->execute([$k, $v]);
                }
                $pdo->commit();
                $msg = ($msg ? $msg . '; ' : '') . 'Тексты сохранены';
            }

            // ===== 2) ГАЛЕРЕЯ (URL + ФАЙЛЫ) =====
            $hasGalleryPost = isset($_POST['gallery']) || (!empty($_FILES['gallery_files']) && is_array($_FILES['gallery_files']));
            if ($hasGalleryPost) {
                $pdo->beginTransaction();

                // Полностью пересобираем галерею, сохраняя порядок
                $pdo->exec('DELETE FROM gallery_images');
                $order = 0;

                // Инсертер с/без gazebo_id
                if ($hasGazeboId) {
                    $insert = $pdo->prepare(
                        'INSERT INTO gallery_images (src, alt, aos, gazebo_id, sort_order, is_active, created_at)
                         VALUES (?,?,?,?,?,1, CURRENT_TIMESTAMP)'
                    );
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO gallery_images (src, alt, aos, sort_order, is_active, created_at)
                         VALUES (?,?,?, ?,1, CURRENT_TIMESTAMP)'
                    );
                }

                // 2.1. URL-записи из формы
                if (isset($_POST['gallery']) && is_array($_POST['gallery'])) {
                    foreach ($_POST['gallery'] as $row) {
                        $src = trim((string)($row['src'] ?? ''));
                        if ($src === '') continue;
                        $alt = trim((string)($row['alt'] ?? ''));
                        $aos = trim((string)($row['aos'] ?? '')) ?: 'fade-up';
                        $gid = (int)($row['gid'] ?? 0);
                        $gazeboId = ($hasGazeboId && $gid > 0) ? $gid : null;
                        $order++;
                        if ($hasGazeboId) {
                            $insert->execute([$src, $alt, $aos, $gazeboId, $order]);
                        } else {
                            $insert->execute([$src, $alt, $aos, $order]);
                        }
                    }
                }

                // 2.2. Файлы (мультизагрузка). Не валим процесс при ошибке — копим сообщения.
                $filesAdded = 0;
                if (!empty($_FILES['gallery_files']) && is_array($_FILES['gallery_files'])) {
                    // Опциональная привязка всех загружаемых файлов к одной беседке (если колонка есть)
                    $filesGid = (int)($_POST['gallery_files_gid'] ?? 0);
                    $filesGazeboId = ($hasGazeboId && $filesGid > 0) ? $filesGid : null;

                    foreach (files_normalize($_FILES['gallery_files']) as $f) {
                        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
                        if ($err === UPLOAD_ERR_NO_FILE) continue;
                        if ($err !== UPLOAD_ERR_OK) {
                            $map = [
                                UPLOAD_ERR_INI_SIZE   => 'Превышен upload_max_filesize',
                                UPLOAD_ERR_FORM_SIZE  => 'Превышен MAX_FILE_SIZE формы',
                                UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
                                UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории',
                                UPLOAD_ERR_CANT_WRITE => 'Нет прав записи',
                                UPLOAD_ERR_EXTENSION  => 'PHP-расширение прервало загрузку',
                            ];
                            $errorDetails[] = ($f['name'] ?? 'файл') . ': ' . ($map[$err] ?? ('Ошибка ' . $err));
                            continue;
                        }
                        try {
                            // Сохраняем в папку галереи
                            $webPath = upload_image_save($f, 'gallery');
                            $order++;
                            if ($hasGazeboId) {
                                $insert->execute([$webPath, '', 'fade-up', $filesGazeboId, $order]);
                            } else {
                                $insert->execute([$webPath, '', 'fade-up', $order]);
                            }
                            $filesAdded++;
                        } catch (Throwable $fe) {
                            $errorDetails[] = ($f['name'] ?? 'файл') . ': ' . $fe->getMessage();
                        }
                    }
                    if ($filesAdded > 0) {
                        $msg = ($msg ? $msg . '; ' : '') . 'Добавлено файлов: ' . $filesAdded;
                    }
                }

                $pdo->commit();
                $msg = ($msg ? $msg . '; ' : '') . 'Галерея обновлена';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Ошибка сохранения';
            $errorDetails[] = $e->getMessage();
        }
    }
}

// Данные для формы
$kv = $pdo->query('SELECT ckey, cvalue FROM content ORDER BY ckey')->fetchAll();
$map = [];
foreach ($kv as $r) { $map[$r['ckey']] = $r['cvalue']; }

// Выборка галереи с/без gazebo_id
if ($hasGazeboId) {
    $gallery = $pdo->query('SELECT id, src, alt, aos, gazebo_id FROM gallery_images ORDER BY sort_order, id')->fetchAll();
} else {
    $gallery = $pdo->query('SELECT id, src, alt, aos, NULL AS gazebo_id FROM gallery_images ORDER BY sort_order, id')->fetchAll();
}

include __DIR__ . '/partials/header.php';
?>

<h1>Контент сайта</h1>

<?php if ($msg): ?>
  <div class="card" style="border-left:4px solid #10b981; margin-bottom:12px;">
    <strong><?= e($msg) ?></strong>
    <?php if (!empty($errorDetails)): ?>
      <div class="muted" style="margin-top:6px;">
        <?= e(implode(' | ', $errorDetails)) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- ===== ТЕКСТЫ СТРАНИЦЫ ===== -->
<div class="card">
  <h3>Тексты страницы</h3>
  <form method="post">
    <?= csrf_input() ?>

    <div class="grid grid2">
      <div class="card" style="padding:12px">
        <h4>Hero</h4>
        <label>Заголовок<br>
          <input name="kv[hero_title]" value="<?= e($map['hero_title'] ?? '') ?>" placeholder="Например: Отдых в сосновом бору">
        </label>
        <label>Подзаголовок<br>
          <input name="kv[hero_subtitle]" value="<?= e($map['hero_subtitle'] ?? '') ?>" placeholder="Насыщенный уикенд с друзьями и семьёй">
        </label>
      </div>

      <div class="card" style="padding:12px">
        <h4>Почему мы</h4>
        <label>Текст блока<br>
          <textarea name="kv[why_us_text]" rows="5" placeholder="Коротко о преимуществах"><?= e($map['why_us_text'] ?? '') ?></textarea>
        </label>
      </div>
    </div>

    <div class="grid grid2">
      <div class="card" style="padding:12px">
        <h4>Бронирование</h4>
        <label>Заголовок<br>
          <input name="kv[book_title]" value="<?= e($map['book_title'] ?? '') ?>" placeholder="Оставьте заявку">
        </label>
        <label>Текст под заголовком<br>
          <textarea name="kv[book_text]" rows="4" placeholder="Мы свяжемся с вами и подтвердим бронирование"><?= e($map['book_text'] ?? '') ?></textarea>
        </label>
      </div>

      <div class="card" style="padding:12px">
        <h4>Футер / Контакты</h4>
        <label>Текст футера<br>
          <textarea name="kv[footer_text]" rows="4" placeholder="© 2025 Название. Все права защищены."><?= e($map['footer_text'] ?? '') ?></textarea>
        </label>
        <div class="grid">
          <label>Телефон<br><input name="kv[contacts_phone]" value="<?= e($map['contacts_phone'] ?? '') ?>" placeholder="+7 (900) 000-00-00"></label>
          <label>Адрес<br><input name="kv[contacts_address]" value="<?= e($map['contacts_address'] ?? '') ?>" placeholder="г. …, ул. …, д. …"></label>
        </div>
      </div>
    </div>

    <p><button class="btn primary" type="submit">Сохранить тексты</button></p>
  </form>
</div>

<!-- ===== ГАЛЕРЕЯ (URL + ФАЙЛЫ) ===== -->
<div class="card">
  <h3>Галерея</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_input() ?>

    <div class="muted" style="margin:6px 0 12px 0">Можно добавлять и URL, и файлы. Порядок сверху вниз — это порядок вывода на сайте.</div>

    <div id="gallery-list" class="grid">
      <?php foreach ($gallery as $i => $g): ?>
        <div class="grid grid2" style="align-items:end; border:1px solid #2d2d2d; border-radius:10px; padding:10px;">
          <label>Src (URL)<br><input name="gallery[<?= $i ?>][src]" value="<?= e($g['src']) ?>" placeholder="/img/gallery/1.jpg"></label>
          <label>Alt<br><input name="gallery[<?= $i ?>][alt]" value="<?= e($g['alt']) ?>" placeholder="Описание изображения"></label>
          <label>AOS<br><input name="gallery[<?= $i ?>][aos]" value="<?= e($g['aos']) ?>" placeholder="fade-up"></label>
          <?php if ($hasGazeboId): ?>
            <label>Gazebo ID (опц.)<br><input name="gallery[<?= $i ?>][gid]" value="<?= e((string)($g['gazebo_id'] ?? '')) ?>" type="number" min="0" placeholder="0"></label>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex; gap:10px; margin:12px 0; flex-wrap:wrap;">
      <button type="button" class="btn secondary" id="addRowBtn">+ Добавить строку</button>
      <button type="button" class="btn secondary" id="add5RowsBtn">+ Добавить 5 строк</button>
      <span class="muted">Всего строк: <span id="rowCount">0</span></span>
    </div>

    <hr style="border:none; border-top:1px solid #2d2d2d; margin:14px 0;">

    <div class="grid grid2" style="align-items:end">
      <label>Загрузить файлы в галерею<br>
        <input type="file" name="gallery_files[]" accept="image/*" multiple>
      </label>
      <?php if ($hasGazeboId): ?>
        <label>Привязать загружаемые файлы к беседке (ID, опц.)<br>
          <input type="number" name="gallery_files_gid" min="0" placeholder="0 = без привязки">
        </label>
      <?php endif; ?>
    </div>

    <p><button class="btn primary" type="submit">Сохранить галерею</button></p>
  </form>
</div>

<script>
(function(){
  const list = document.getElementById('gallery-list');
  const addBtn = document.getElementById('addRowBtn');
  const add5Btn = document.getElementById('add5RowsBtn');
  const rowCount = document.getElementById('rowCount');
  const hasGazeboId = <?= $hasGazeboId ? 'true' : 'false' ?>;

  function row(){
    const w = document.createElement('div');
    w.className = 'grid grid2';
    w.style.alignItems = 'end';
    w.style.border = '1px solid #2d2d2d';
    w.style.borderRadius = '10px';
    w.style.padding = '10px';
    w.style.position = 'relative';

    const key = 'new' + Date.now() + Math.floor(Math.random()*1000);

    w.innerHTML = `
      <label>Src (URL)<br><input name="gallery[${key}][src]" placeholder="/img/gallery/?.jpg"></label>
      <label>Alt<br><input name="gallery[${key}][alt]" placeholder="Описание изображения"></label>
      <label>AOS<br><input name="gallery[${key}][aos]" placeholder="fade-up"></label>
      ${hasGazeboId ? '<label>Gazebo ID (опц.)<br><input name="gallery['+key+'][gid]" type="number" min="0" placeholder="0"></label>' : ''}
      <button type="button" class="btn danger" style="position:absolute; top:8px; right:8px;" title="Удалить строку">×</button>
    `;

    w.querySelector('button.btn.danger').addEventListener('click', () => { w.remove(); updateCount(); });
    return w;
  }

  function updateCount(){
    rowCount.textContent = list ? list.children.length : 0;
  }

  if (addBtn) addBtn.addEventListener('click', () => { list.appendChild(row()); updateCount(); });
  if (add5Btn) add5Btn.addEventListener('click', () => { for(let i=0;i<5;i++){ list.appendChild(row()); } updateCount(); });

  updateCount();
})();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>