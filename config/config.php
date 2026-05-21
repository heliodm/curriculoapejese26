<?php
session_start();

define('APP_NAME',    'Sistema de Currículos');
define('APP_VERSION', '1.0.0');
define('SITE_ROOT',   dirname(__DIR__));
define('BASE_URL',    rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));

define('UPLOAD_DIR',       SITE_ROOT . '/assets/uploads/');
define('UPLOAD_URL',       BASE_URL  . '/assets/uploads/');
define('MAX_UPLOAD_SIZE',  5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once SITE_ROOT . '/config/database.php';
require_once SITE_ROOT . '/includes/functions.php';
require_once SITE_ROOT . '/includes/auth.php';
