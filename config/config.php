<?php
// Load DB credentials from env.php (created by install.php; never committed to git)
$__envFile = __DIR__ . '/env.php';
if (is_file($__envFile)) require_once $__envFile;

$__isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);
if ($__isHttps) ini_set('session.cookie_secure', 1);

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

// Per-request CSP nonce — used in <script nonce="..."> tags and the CSP header below
define('CSP_NONCE', base64_encode(random_bytes(16)));

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($__isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
$__nonce = CSP_NONCE;
header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "script-src 'self' 'nonce-{$__nonce}' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
    . "img-src 'self' data: https://api.qrserver.com; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none';"
);

require_once SITE_ROOT . '/config/database.php';
require_once SITE_ROOT . '/includes/functions.php';
require_once SITE_ROOT . '/includes/totp.php';
require_once SITE_ROOT . '/includes/updater.php';
require_once SITE_ROOT . '/includes/auth.php';

// Auto-migrate: add missing columns to existing databases
try { ensureConsentColumn();       } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureAdimplenteColumn();    } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureUserProfileColumns();  } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureUserExtendedColumns(); } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureLogTables();           } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureRateLimitTable();      } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureLogIpColumn();         } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }
try { ensureUserTotpColumns();     } catch (\Exception $e) { /* DB not ready (e.g. during install) */ }

// Probabilistic cleanup (~1% of requests): expired tokens, old logs, stale rate-limit rows
if (mt_rand(1, 100) === 1) {
    try {
        db()->exec("DELETE FROM password_resets WHERE expires_at < NOW()");
        db()->exec("DELETE FROM user_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        db()->exec("DELETE FROM rate_limits WHERE window_start < " . (time() - 86400) . " AND blocked_until IS NULL");
    } catch (\Exception $e) {}
}
