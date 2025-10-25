<?php
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function csrf_token(): string {
    $cfg = require __DIR__ . '/config.php';
    $name = $cfg['security']['csrf_token_name'];
    if (empty($_SESSION[$name])) {
        $_SESSION[$name] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$name];
}

function csrf_input(): string {
    $cfg = require __DIR__ . '/config.php';
    $name = $cfg['security']['csrf_token_name'];
    return '<input type="hidden" name="' . e($name) . '" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool {
    $cfg = require __DIR__ . '/config.php';
    $name = $cfg['security']['csrf_token_name'];
    return isset($_POST[$name], $_SESSION[$name]) && hash_equals($_SESSION[$name], $_POST[$name]);
}

function redirect(string $path) {
    $cfg = require __DIR__ . '/config.php';
    $base = rtrim($cfg['app']['base_url'], '/');
    header('Location: ' . $base . '/' . ltrim($path, '/'));
    exit;
}


function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login() { if (!current_user()) redirect('admin/auth/login.php'); }

function users_count(PDO $pdo): int {
    $q = $pdo->query('SELECT COUNT(*) FROM users');
    return (int)$q->fetchColumn();
}

function attempt_login(PDO $pdo, string $login, string $password): bool {
    $stmt = $pdo->prepare('SELECT id, login, password_hash FROM users WHERE login = ? LIMIT 1');
    $stmt->execute([$login]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($password, $u['password_hash'])) {
        $_SESSION['user'] = ['id' => (int)$u['id'], 'login' => $u['login']];
        return true;
    }
    return false;
}

function create_admin(PDO $pdo, string $login, string $password): int {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (login, password_hash, created_at) VALUES (?,?,CURRENT_TIMESTAMP)');
    $stmt->execute([$login, $hash]);
    return (int)$pdo->lastInsertId();
}

function send_mail(string $to, string $subject, string $html, string $text = ''): bool {
    $cfg = require __DIR__ . '/config.php';

    
    $line = sprintf("[%s] To:%s | Subj:%s\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $html);
    @file_put_contents($cfg['mail']['log_path'], $line, FILE_APPEND);

    
    $transport = $cfg['mail']['transport'] ?? 'log';
    if ($transport === 'phpmail') {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        if (!empty($cfg['mail']['from'])) $headers .= "From: ".$cfg['mail']['from']."\r\n";
        $headers .= "Reply-To: ".($cfg['mail']['from'] ?? $to)."\r\n";
        $encSubj = '=?UTF-8?B?'.base64_encode($subject).'?=';
        return @mail($to, $encSubj, $html, $headers);
    }

    
    return true;
}

function order_statuses(): array {
    return [
        'new'       => 'Новая',
        'confirmed' => 'Подтверждена',
        'canceled'  => 'Отменена',
        'done'      => 'Завершена',
    ];
}


function sqlite_has_column(PDO $pdo, string $table, string $column): bool {
    
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) { throw new InvalidArgumentException('Invalid table name'); }
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) { throw new InvalidArgumentException('Invalid column name'); }
    $stmt = $pdo->query('PRAGMA table_info("' . $table . '")');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (isset($c['name']) && strcasecmp($c['name'], $column) === 0) return true;
    }
    return false;
}

function sqlite_ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!sqlite_has_column($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE "' . $table . '" ADD COLUMN "' . $column . '" ' . $definition);
    }
}


function make_order_request_key(int $gazeboId, string $date, string $phone, int $qty = 1): string {
    
    $bucket = (int)floor(time() / 30);
    $sid = session_id() ?: '';
    return hash('sha256', implode('|', [$gazeboId, $date, $phone, $qty, $sid, $bucket]));
}

 
 
function insert_order_idempotent(PDO $pdo, array $data): int {
    $reqKey = make_order_request_key(
        (int)$data['gazebo_id'],
        (string)$data['date'],
        (string)$data['phone'],
        (int)($data['qty'] ?? 1)
    );

    $stmt = $pdo->prepare('
        INSERT INTO orders (gazebo_id, date, customer_name, phone, email, comment, status, qty, request_key, created_at)
        VALUES (:gazebo_id, :date, :customer_name, :phone, :email, :comment, :status, :qty, :request_key, CURRENT_TIMESTAMP)
        ON CONFLICT(request_key) DO NOTHING
    ');
    $data['status'] = $data['status'] ?? 'new';
    $data['qty'] = (int)($data['qty'] ?? 1);
    $stmt->execute([
        ':gazebo_id'     => (int)$data['gazebo_id'],
        ':date'          => (string)$data['date'],
        ':customer_name' => (string)($data['customer_name'] ?? ''),
        ':phone'         => (string)($data['phone'] ?? ''),
        ':email'         => (string)($data['email'] ?? ''),
        ':comment'       => (string)($data['comment'] ?? ''),
        ':status'        => (string)$data['status'],
        ':qty'           => (int)$data['qty'],
        ':request_key'   => $reqKey,
    ]);

    if ((int)$pdo->lastInsertId() === 0) {
        $q = $pdo->prepare('SELECT id FROM orders WHERE request_key = ? LIMIT 1');
        $q->execute([$reqKey]);
        return (int)$q->fetchColumn();
    }

    return (int)$pdo->lastInsertId();
}





function uploads_cfg(): array {
    static $c;
    if (!$c) {
        $cfg = require __DIR__ . '/config.php';
        $c = $cfg['uploads'] ?? [
            'dir' => realpath(__DIR__ . '/..') . '/uploads',
            'url' => '/uploads',
        ];
    }
    return $c;
}
function upload_image_save(array $file, string $section = 'gazebos'): string {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Ошибка загрузки файла');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'Превышен upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'Превышен MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать на диск',
            UPLOAD_ERR_EXTENSION  => 'PHP-расширение прервало загрузку',
        ];
        $msg = $map[$file['error']] ?? 'Неизвестная ошибка загрузки';
        throw new RuntimeException($msg);
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Недопустимый размер файла (макс. 8 МБ)');
    }

    $tmp = $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Недопустимый тип файла');
    }
    
    if (@getimagesize($tmp) === false) {
        throw new RuntimeException('Файл не распознан как изображение');
    }

    $u = uploads_cfg();
    $baseDir = rtrim($u['dir'], '/\\');
    $baseUrl = rtrim($u['url'], '/');

    $dir = $baseDir . '/' . trim($section, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать папку загрузки');
    }

    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Не удалось сохранить файл');
    }
    @chmod($dest, 0644);

    
    return $baseUrl . '/' . trim($section, '/') . '/' . $name;
}


function files_normalize(array $files): array {
    $norm = [];
    if (!is_array($files['name'])) {
        
        return [$files];
    }
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $norm[] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }
    return $norm;
}




function ensure_sqlite_schema(PDO $pdo): void {
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS gazebos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        price TEXT NOT NULL,
        description TEXT,
        image_url TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        stock_total INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT
    )');
    
    sqlite_ensure_column($pdo, 'gazebos', 'stock_total', 'INTEGER NOT NULL DEFAULT 1');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS gazebo_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gazebo_id INTEGER NOT NULL,
        url TEXT NOT NULL,
        alt TEXT DEFAULT "",
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_primary INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(gazebo_id) REFERENCES gazebos(id) ON DELETE CASCADE ON UPDATE CASCADE
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_gi_gazebo ON gazebo_images(gazebo_id, sort_order, id)');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS gallery_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        src TEXT NOT NULL,
        alt TEXT DEFAULT "",
        aos TEXT DEFAULT "fade-up",
        is_active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gazebo_id INTEGER,
        date TEXT NOT NULL,
        customer_name TEXT NOT NULL,
        phone TEXT DEFAULT "",
        email TEXT DEFAULT "",
        comment TEXT,
        status TEXT NOT NULL DEFAULT "new",
        qty INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT,
        FOREIGN KEY(gazebo_id) REFERENCES gazebos(id) ON DELETE SET NULL ON UPDATE CASCADE
    )');
    
    sqlite_ensure_column($pdo, 'orders', 'qty', 'INTEGER NOT NULL DEFAULT 1');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_orders_gazebo_date ON orders(gazebo_id, date)');

    
    $pdo->exec('CREATE TABLE IF NOT EXISTS content (
        ckey TEXT PRIMARY KEY,
        cvalue TEXT,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    
    $pdo->exec('
        INSERT INTO gazebo_images (gazebo_id, url, alt, sort_order, is_primary, is_active, created_at)
        SELECT g.id, g.image_url, "", 0, 1, 1, CURRENT_TIMESTAMP
        FROM gazebos g
        WHERE IFNULL(g.image_url, "") <> ""
          AND NOT EXISTS (
              SELECT 1 FROM gazebo_images gi WHERE gi.gazebo_id = g.id
          )
    ');

    
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM gazebos')->fetchColumn();
    if ($cnt === 0) {
        $stmt = $pdo->prepare('INSERT INTO gazebos(title, price, description, image_url, is_active, sort_order, stock_total, created_at)
                               VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $stmt->execute(['Беседка №1', '1500 ₽/день', 'Уютная беседка у озера', '/img/gazebos/1.jpg', 1, 10, 6]);
        $stmt->execute(['Беседка №2', '2000 ₽/день', 'Просторная, рядом мангал', '/img/gazebos/2.jpg', 1, 20, 6]);
    }

    $gcnt = (int)$pdo->query('SELECT COUNT(*) FROM gazebo_images')->fetchColumn();
    if ($gcnt === 0) {
        $stmt = $pdo->prepare('INSERT INTO gazebo_images(src, alt, aos, is_active, sort_order)
                               VALUES(?,?,?,?,?)');
        $stmt->execute(['/img/gallery/1.jpg', 'Вид на озеро', 'fade-up', 1, 10]);
        $stmt->execute(['/img/gallery/2.jpg', 'Мангал', 'fade-up', 1, 20]);
        $stmt->execute(['/img/gallery/3.jpg', 'Терраса', 'fade-up', 1, 30]);
    }

    
    sqlite_ensure_column($pdo, 'orders', 'request_key', 'TEXT');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_request_key ON orders(request_key)');
}


