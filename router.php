<?php
$pathUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$pathUrl = str_replace('\\', '/', $pathUrl);
$path    = rawurldecode($pathUrl);  

// Алиасы на создание заявки (оба пути будут работать)
if ($path === '/api/order-create' || $path === '/api/order-create.json') {
  require __DIR__ . '/api/order-create.php';
  return true;
}

if (strpos($path, '..') !== false) {          
  http_response_code(400); echo 'Bad path'; return true;
}

$root   = __DIR__;
$public = $root . '/public';


if ($path === '/api/products.json') {
  header('Content-Type: application/json; charset=utf-8');
  require __DIR__ . '/api/products.php';
  return true;
}
if ($path === '/api/images.json') {
  header('Content-Type: application/json; charset=utf-8');
  require __DIR__ . '/api/images.php';
  return true;
}


$apiAliases = ['products', 'images'];
if (preg_match('#^/(?:api/)?([a-z0-9\-]+)\.json$#i', $path, $m)) {
  $name = strtolower($m[1]);
  if (in_array($name, $apiAliases, true)) {
    header('Content-Type: application/json; charset=utf-8');
    require $root . "/api/{$name}.php";
    return true;
  }
}

if (preg_match('#^/api/([a-z0-9\-]+)$#i', $path, $m)) {
  $name = strtolower($m[1]);
  if (in_array($name, $apiAliases, true)) {
    header('Content-Type: application/json; charset=utf-8');
    require $root . "/api/{$name}.php";
    return true;
  }
}


if (preg_match('#^/data/#i', $path)) {
  http_response_code(403); echo 'Forbidden'; return true;
}


if (preg_match('#^/(admin|api)(/.*)?$#i', $path)) {
  $target = $root . $path;
  if (is_dir($target) && is_file($target . '/index.php')) { require $target . '/index.php'; return true; }
  if (is_file($target)) return false; // php или статик
  http_response_code(404); echo 'Not Found'; return true;
}


$publicFile = $public . $path;
if (is_dir($publicFile) && is_file($publicFile . '/index.html')) { readfile($publicFile . '/index.html'); return true; }
if (is_file($publicFile)) {
  $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
  $mimes = [
    'css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8',
    'json'=>'application/json; charset=utf-8','html'=>'text/html; charset=utf-8',
    'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','bmp'=>'image/bmp',
    'svg'=>'image/svg+xml','webp'=>'image/webp','ico'=>'image/x-icon',
    'woff2'=>'font/woff2','woff'=>'font/woff','ttf'=>'font/ttf','eot'=>'application/vnd.ms-fontobject'
  ];
  if (isset($mimes[$ext])) header('Content-Type: '.$mimes[$ext]);
  readfile($publicFile);
  return true;
}


if ($path === '/' || $path === '') {
  $index = $public . '/index.html';
  if (is_file($index)) { readfile($index); return true; }
}


if (is_file($root . $path)) return false;


http_response_code(404);
echo 'Not Found';
return true;