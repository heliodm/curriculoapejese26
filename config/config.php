<?php
session_start();

define('APP_NAME',    'Sistema de Currículos');
define('APP_VERSION', '1.0.0');
define('SITE_ROOT',   dirname(__DIR__));

// BASE_URL is derived from DOCUMENT_ROOT vs SITE_ROOT so it never duplicates
// the directory when config.php is included from sub-folders (e.g. /admin/).
(function () {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $siteRoot = SITE_ROOT;
    $urlPath  = '';
    if ($docRoot !== false && $docRoot !== '' && strncmp($siteRoot, $docRoot, strlen($docRoot)) === 0) {
        $urlPath = substr($siteRoot, strlen($docRoot));
        $urlPath = str_replace('\\', '/', $urlPath);
    } else {
        // Fallback: strip current script's sub-path from SCRIPT_NAME
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir  = str_replace('\\', '/', realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '');
        $diff       = str_replace('\\', '/', $siteRoot);
        if ($scriptDir !== '' && strncmp($diff, $scriptDir, strlen($diff)) !== 0) {
            // Calculate how many levels deep the current script is from SITE_ROOT
            $rel   = ltrim(str_replace('\\', '/', substr($scriptDir, strlen($siteRoot))), '/');
            $depth = $rel === '' ? 0 : count(explode('/', $rel));
            $parts = explode('/', trim($scriptName, '/'));
            $urlPath = '/' . implode('/', array_slice($parts, 0, count($parts) - $depth - 1));
        } else {
            $urlPath = dirname($scriptName);
        }
    }
    define('BASE_URL', rtrim($protocol . '://' . $host . $urlPath, '/'));
})();

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
