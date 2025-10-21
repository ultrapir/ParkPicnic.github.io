<?php
return [
    'sqlite' => [
        'path' => __DIR__ . '/data/parkpicnic.sqlite',
    ],
    'security' => [
        'session_name' => 'pp_admin_sess',
        'session_cookie_lifetime' => 60*60*4, // 4 часа
        'csrf_token_name' => 'csrf_token',
    ],
    'mail' => [
  'admin_email' => 'admin@your-domain',
  'log_path'    => __DIR__ . '/data/mail.log',
  'transport'   =>  'log' | 'phpmail' | 'smtp',
  'from'        => 'ParkPicnic <no-reply@your-domain>',
    ],
    'app' => [
        'base_url' => '/',
        'timezone' => 'Europe/Moscow',
    ],
    'uploads' => [
        'dir' => __DIR__ . '/public/uploads', // абсолютный путь на диске
        'url' => '/uploads',                  // веб‑путь
    ],
];
