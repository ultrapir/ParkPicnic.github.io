<?php
require_once dirname(__DIR__, 2) . '/init.php';
session_destroy();
redirect('/admin/auth/login.php');
