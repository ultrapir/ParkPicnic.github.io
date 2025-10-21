<?php 
// declare(strict_types=1);
// mb_internal_encoding('UTF-8');
// header('Content-Type: application/json; charset=UTF-8');

// require_once __DIR__ . '/../init.php'; 

// if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// $postedToken = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
// if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$postedToken)) {
//   http_response_code(403);
//   echo json_encode(['ok' => false, 'message' => 'Токен CSRF недействителен']);
//   exit;
// }


// 1.1) Автосоздание схемы: если в init.php есть ensure_sqlite_schema($pdo), вызываем.
// if (function_exists('ensure_sqlite_schema')) {
//   try { ensure_sqlite_schema($pdo); } catch (Throwable $e) { /* не фаталим */ }
// }

// -------------------- НАСТРОЙКИ --------------------
// $ENV = [
//   'ADMIN_EMAIL' => 'admin@example.com',     
//   'FROM_EMAIL'  => 'test@example.com',   
//   'FROM_NAME'   => 'ParkPicnic',                
//   'SMTP_HOST'   => '127.0.0.1',                          
//   'SMTP_USER'   => '',
//   'SMTP_PASS'   => '',
//   'SMTP_PORT'   => 1025,
//   'SMTP_SECURE' => '',                          
//   'SMTP_AUTH'   => false,                      
//   'SMTP_DEBUG'  => 3,                       
// ];


// (function(){
//   $autoload = __DIR__ . '/../vendor/autoload.php';
//   if (is_file($autoload)) { require_once $autoload; }
// })();

// -------------------- ВСПОМОГАТЕЛЬНЫЕ --------------------
// function json_error($code, $message, $http = 400): void {
//   http_response_code($http);
//   echo json_encode(['ok' => false, 'code' => $code, 'message' => $message], JSON_UNESCAPED_UNICODE);
//   exit;
// }

// function sanitize_line(string $s): string {
//   return str_replace(["\r", "\n"], ' ', trim($s));
// }

// function send_mail_smtp_or_native(string $to, string $subject, string $html, string $altText, array $env): bool {
//   $fromEmail = $env['FROM_EMAIL'] ?? 'no-reply@localhost';
//   $fromName  = $env['FROM_NAME']  ?? 'Site Robot';
//   $admin     = $env['ADMIN_EMAIL'] ?? '';

//   $smtpHost  = trim((string)($env['SMTP_HOST'] ?? ''));
//   $smtpUser  = trim((string)($env['SMTP_USER'] ?? ''));
//   $smtpPass  = (string)($env['SMTP_PASS'] ?? '');
//   $smtpPort  = (int)($env['SMTP_PORT'] ?? 25);
//   $smtpSecure= trim((string)($env['SMTP_SECURE'] ?? ''));
//   $smtpAuth   = (bool)($env['SMTP_AUTH'] ?? false) || ($smtpUser !== '' || $smtpPass !== '');
//   $smtpDebug  = (int)($env['SMTP_DEBUG'] ?? 0);

//   if ($smtpHost !== '' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
//     try {
//       $mail = new PHPMailer\PHPMailer\PHPMailer(true);
//       $mail->isSMTP();
//       $mail->Host       = $smtpHost;
//       $mail->Port       = $smtpPort ?: 25;
//       $mail->SMTPDebug  = $smtpDebug;           
//       $mail->Debugoutput = 'error_log';
//       $mail->SMTPAuth    = $smtpAuth;           
//       if ($smtpAuth) { $mail->Username = $smtpUser; $mail->Password = $smtpPass; }

      
//       $mail->SMTPAutoTLS = false;             
//       if ($smtpSecure === 'ssl') {
//         $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
//       } elseif ($smtpSecure === 'tls') {
//         $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
//       } else {
//         $mail->SMTPSecure = '';
//       }

//       $mail->CharSet = 'UTF-8';
//       $mail->setFrom($fromEmail, $fromName);
//       if ($admin) $mail->addReplyTo($admin, $fromName);
//       $mail->addAddress($to);

//       $mail->isHTML(true);
//       $mail->Subject = $subject;
//       $mail->Body    = $html;
//       $mail->AltBody = $altText !== '' ? $altText : strip_tags($html);

//       $mail->send();
//       return true;
//     } catch (Throwable $e) {
//       error_log('[book.php] PHPMailer SMTP error: ' . $e->getMessage());
//     }
//   }

  // Фоллбэк: mb_send_mail()/mail()
//   $headers = [];
//   $headers[] = 'MIME-Version: 1.0';
//   $headers[] = 'Content-Type: text/html; charset=UTF-8';
//   $headers[] = 'From: ' . sprintf('%s <%s>', sanitize_line($fromName), sanitize_line($fromEmail));
//   if ($admin) $headers[] = 'Reply-To: ' . sanitize_line($admin);
//   $headersStr = implode("\r\n", $headers);

//   if (function_exists('mb_send_mail')) {
//     return @mb_send_mail($to, $subject, $html, $headersStr);
//   } else {
//     return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headersStr);
//   }
// }

// -------------------- ВАЛИДАЦИЯ ЗАПРОСА --------------------
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//   json_error('method_not_allowed', 'Use POST', 405);
// }

// CSRF
// $tokenHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
// $tokenPost   = $_POST['csrf_token'] ?? ($_POST['csrf'] ?? ($_POST['_token'] ?? ''));
// $token       = $tokenHeader !== '' ? $tokenHeader : $tokenPost;

// $sessionToken = $_SESSION['csrf_token'] ?? '';

// if ($sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token)) {
//     // ok
// } else {
//     $valid = false;

//     if (function_exists('csrf_check')) {
//         try {
//             $ref  = new ReflectionFunction('csrf_check');
//             $args = $ref->getNumberOfParameters() >= 1 ? [$token] : [];
//             $valid = (bool)$ref->invokeArgs($args);
//         } catch (Throwable $e) {
//             $valid = false;
//         }
//     }

//     if (!$valid) {
//         http_response_code(403);
//         header('Content-Type: application/json; charset=utf-8');
//         echo json_encode(['ok' => false, 'message' => 'Token CSRF недействителен'], JSON_UNESCAPED_UNICODE);
//         exit;
//     }
// }



// Honeypot от ботов: обычным пользователям это поле невидимо и пусто
// if (!empty($_POST['website'])) {
//   echo json_encode(['ok' => true]);
//   exit;
// }

// $name       = sanitize_line((string)($_POST['name'] ?? ''));
// $phone      = sanitize_line((string)($_POST['phone'] ?? ''));
// $email      = sanitize_line((string)($_POST['email'] ?? ''));
// $gid        = isset($_POST['gazebo_id']) ? (int)$_POST['gazebo_id'] : (isset($_POST['gazeboId']) ? (int)$_POST['gazeboId'] : 0);
// $gazeboName = sanitize_line((string)($_POST['gazebo_name'] ?? ($_POST['gazeboName'] ?? '')));
// $date       = sanitize_line((string)($_POST['date'] ?? ''));
// $message    = trim((string)($_POST['message'] ?? ''));

// if ($name === '' || $phone === '' || $email === '') {
//   json_error('validation', 'Необходимо указать имя, телефон и email');
// }
// if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//   json_error('validation_email', 'Некорректный email');
// }

// -------------------- СОХРАНЕНИЕ В БАЗУ (ОПЦИОНАЛЬНО) --------------------
// $bookingId = null;
// try {
//   if (isset($pdo) && $pdo instanceof PDO) {
//     $sql = 'INSERT INTO orders(name, phone, email, gazebo_id, gazebo_name, date, message, created_at) ' .
//            'VALUES(?,?,?,?,?,?,?, CURRENT_TIMESTAMP)';
//     $stmt = $pdo->prepare($sql);
//     $stmt->execute([
//       $name,
//       $phone,
//       $email,
//       $gid ?: null,
//       $gazeboName !== '' ? $gazeboName : null,
//       $date !== '' ? $date : null,
//       $message,
//     ]);
//     $bookingId = (int)$pdo->lastInsertId();
//   }
// } catch (Throwable $e) {
  // База не должна мешать отправке писем: продолжаем
// }

// -------------------- ПИСЬМА --------------------
// $subjectClient = 'Ваше бронирование принято';
// $subjectAdmin  = 'Новая заявка на бронирование';

// $detailsHtml = '<ul style="margin:0; padding-left:18px;">'
//   . ($bookingId ? '<li><b>ID заявки:</b> ' . htmlspecialchars((string)$bookingId, ENT_QUOTES, 'UTF-8') . '</li>' : '')
//   . '<li><b>Имя:</b> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</li>'
//   . '<li><b>Телефон:</b> ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</li>'
//   . '<li><b>Email:</b> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</li>'
//   . ($gid ? '<li><b>Беседка ID:</b> ' . $gid . '</li>' : '')
//   . ($gazeboName !== '' ? '<li><b>Беседка:</b> ' . htmlspecialchars($gazeboName, ENT_QUOTES, 'UTF-8') . '</li>' : '')
//   . ($date !== '' ? '<li><b>Дата:</b> ' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</li>' : '')
//   . ($message !== '' ? '<li><b>Сообщение:</b> ' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</li>' : '')
//   . '</ul>';

// $htmlClient = '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; line-height:1.5; color:#222;">'
//   . '<p>Здравствуйте, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!</p>'
//   . '<p>Мы получили вашу заявку на бронирование. Менеджер свяжется с вами в ближайшее время.</p>'
//   . '<h3 style="margin:16px 0 8px;">Детали заявки</h3>'
//   . $detailsHtml
//   . '<p style="margin-top:18px; color:#666; font-size:13px;">Если вы не оставляли эту заявку — просто проигнорируйте письмо.</p>'
//   . '</div>';

// $txtClient = "Здравствуйте, $name!\n\n" .
//              "Мы получили вашу заявку на бронирование. Менеджер свяжется с вами в ближайшее время.\n\n" .
//              "Детали:\n" .
//              ($bookingId ? ("ID заявки: $bookingId\n") : '') .
//              "Имя: $name\nТелефон: $phone\nEmail: $email\n" .
//              ($gid ? ("Беседка ID: $gid\n") : '') .
//              ($gazeboName !== '' ? ("Беседка: $gazeboName\n") : '') .
//              ($date !== '' ? ("Дата: $date\n") : '') .
//              ($message !== '' ? ("Сообщение: $message\n") : '') .
//              "\nЕсли вы не оставляли эту заявку — просто игнорируйте письмо.";

// $htmlAdmin = '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; line-height:1.5; color:#222;">'
//   . '<h3 style="margin:0 0 8px;">Новая заявка на бронирование</h3>'
//   . $detailsHtml
//   . '</div>';

// $txtAdmin = "Новая заявка на бронирование\n\n" .
//             ($bookingId ? ("ID заявки: $bookingId\n") : '') .
//             "Имя: $name\nТелефон: $phone\nEmail: $email\n" .
//             ($gid ? ("Беседка ID: $gid\n") : '') .
//             ($gazeboName !== '' ? ("Беседка: $gazeboName\n") : '') .
//             ($date !== '' ? ("Дата: $date\n") : '') .
//             ($message !== '' ? ("Сообщение: $message\n") : '');

// $sentClient = send_mail_smtp_or_native($email, $subjectClient, $htmlClient, $txtClient, $ENV);
// $sentAdmin  = true;
// if (!empty($ENV['ADMIN_EMAIL'])) {
//   $sentAdmin = send_mail_smtp_or_native($ENV['ADMIN_EMAIL'], $subjectAdmin, $htmlAdmin, $txtAdmin, $ENV);
// }

// if (!$sentClient) {
//   json_error('mail_failed', 'Не удалось отправить письмо клиенту. Попробуйте позже.', 500);
// }

// echo json_encode([
//   'ok' => true,
//   'bookingId' => $bookingId,
//   'mail' => [ 'client' => $sentClient, 'admin' => $sentAdmin ],
// ], JSON_UNESCAPED_UNICODE);