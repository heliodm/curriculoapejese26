<?php
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize($input): string {
    return trim(strip_tags((string)$input));
}

function generateSlug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[áàãâä]/u', 'a', $text);
    $text = preg_replace('/[éèêë]/u', 'e', $text);
    $text = preg_replace('/[íìîï]/u', 'i', $text);
    $text = preg_replace('/[óòõôö]/u', 'o', $text);
    $text = preg_replace('/[úùûü]/u', 'u', $text);
    $text = preg_replace('/[ç]/u', 'c', $text);
    $text = preg_replace('/[ñ]/u', 'n', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return $text;
}

function uniqueSlug(string $base, string $table, int $excludeId = 0): string {
    $slug     = generateSlug($base);
    $original = $slug;
    $counter  = 1;
    while (true) {
        $stmt = db()->prepare("SELECT id FROM `{$table}` WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $original . '-' . $counter++;
    }
    return $slug;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function renderFlash(): string {
    $html = '';
    foreach (getFlash() as $f) {
        $type = in_array($f['type'], ['success','danger','warning','info']) ? $f['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
               . e($f['message'])
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return $html;
}

/** @return string|false */
function uploadFile(array $file, string $subdir, array $allowedTypes = [], int $maxSize = 0) {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    $maxSize  = $maxSize ?: MAX_UPLOAD_SIZE;
    if ($file['size'] > $maxSize) return false;
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowed  = $allowedTypes ?: ALLOWED_IMG_TYPES;
    if (!in_array($mimeType, $allowed)) return false;
    // Extensão derivada do MIME real do conteúdo — nunca do nome enviado
    // pelo cliente, que poderia terminar em .php e ser executado no servidor.
    $extMap = [
        'image/jpeg' => 'jpg',  'image/png'  => 'png',
        'image/gif'  => 'gif',  'image/webp' => 'webp',
        'image/svg+xml' => 'svg', 'application/pdf' => 'pdf',
    ];
    $ext = $extMap[$mimeType] ?? null;
    if ($ext === null) return false;
    $subdir = basename(trim($subdir, '/')); // sem path traversal no subdiretório
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir  = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) return false;
    return $subdir . '/' . $filename;
}

function deleteUpload(string $relativePath): void {
    if (!$relativePath) return;
    $full = realpath(UPLOAD_DIR . ltrim($relativePath, '/'));
    $base = realpath(UPLOAD_DIR);
    // Só remove arquivos que realmente estão dentro de uploads/
    if ($full !== false && $base !== false && strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0) {
        unlink($full);
    }
}

function getSetting(string $key, string $default = ''): string {
    try {
        $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_value'] : $default;
    } catch (\Exception $e) {
        return $default;
    }
}

function saveSetting(string $key, string $value): void {
    db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
       ->execute([$key, $value]);
}

function getMenuItems(): array {
    try {
        $stmt = db()->query("SELECT * FROM menu_items WHERE active = 1 ORDER BY order_num ASC LIMIT 3");
        return $stmt->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}

function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages  = (int)ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $currentPage,
        'total_pages' => $totalPages,
        'offset'      => $offset,
    ];
}

function formatPhone(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) === 11) {
        return '(' . substr($digits,0,2) . ') ' . substr($digits,2,5) . '-' . substr($digits,7);
    }
    if (strlen($digits) === 10) {
        return '(' . substr($digits,0,2) . ') ' . substr($digits,2,4) . '-' . substr($digits,6);
    }
    return $phone;
}

function truncate(string $text, int $len = 120): string {
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '…';
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function getCategories(): array {
    try {
        $stmt = db()->query("SELECT * FROM categories WHERE active = 1 ORDER BY name ASC");
        return $stmt->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}

function hasConsentColumn(bool $reset = false): bool {
    static $result = null;
    if ($reset) $result = null;
    if ($result === null) {
        try {
            db()->query("SELECT consent FROM resumes LIMIT 0");
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }
    }
    return $result;
}

function ensureConsentColumn(): void {
    if (!hasConsentColumn()) {
        try {
            db()->exec("ALTER TABLE resumes ADD COLUMN `consent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`");
        } catch (\Exception $e) {
            // Column added by concurrent request, or other non-critical error — ignore.
        }
        hasConsentColumn(true);
    }
}

function hasAdimplenteColumn(bool $reset = false): bool {
    static $result = null;
    if ($reset) $result = null;
    if ($result === null) {
        try {
            db()->query("SELECT adimplente FROM users LIMIT 0");
            $result = true;
        } catch (\Exception $e) {
            $result = false;
        }
    }
    return $result;
}

function ensureAdimplenteColumn(): void {
    if (!hasAdimplenteColumn()) {
        try {
            db()->exec("ALTER TABLE users ADD COLUMN `adimplente` TINYINT(1) NOT NULL DEFAULT 1 AFTER `active`");
        } catch (\Exception $e) {
            // Column added by concurrent request — ignore.
        }
        hasAdimplenteColumn(true);
    }
}

function ensureUserProfileColumns(): void {
    $cols = [
        'matricula_apejese' => "VARCHAR(50) NULL DEFAULT NULL AFTER `adimplente`",
        'cpf'               => "VARCHAR(14) NULL DEFAULT NULL AFTER `matricula_apejese`",
        'data_nascimento'   => "DATE NULL DEFAULT NULL AFTER `cpf`",
    ];
    foreach ($cols as $col => $def) {
        try {
            db()->query("SELECT `{$col}` FROM users LIMIT 0");
        } catch (\Exception $e) {
            try { db()->exec("ALTER TABLE users ADD COLUMN `{$col}` {$def}"); }
            catch (\Exception $ex) { /* concurrent */ }
        }
    }
}

function ensureUserExtendedColumns(): void {
    $cols = [
        'photo'                 => "VARCHAR(255) NULL DEFAULT NULL AFTER `data_nascimento`",
        'data_filiacao'         => "DATE NULL DEFAULT NULL AFTER `photo`",
        'registro_profissional' => "VARCHAR(100) NULL DEFAULT NULL AFTER `data_filiacao`",
        'carteira_validade'     => "DATE NULL DEFAULT NULL AFTER `registro_profissional`",
    ];
    foreach ($cols as $col => $def) {
        try {
            db()->query("SELECT `{$col}` FROM users LIMIT 0");
        } catch (\Exception $e) {
            try { db()->exec("ALTER TABLE users ADD COLUMN `{$col}` {$def}"); }
            catch (\Exception $ex) { /* concurrent */ }
        }
    }
}

function ensureLogTables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `user_logs` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NULL, `admin_id` int NULL,
        `action` varchar(100) CHARACTER SET utf8mb4 NOT NULL,
        `details` text CHARACTER SET utf8mb4 NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int NOT NULL,
        `token` varchar(64) CHARACTER SET utf8mb4 NOT NULL,
        `expires_at` timestamp NOT NULL,
        `used` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`), KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function logUserAction(int $userId, string $action, string $details = ''): void {
    try {
        $adminId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $ip      = $_SERVER['REMOTE_ADDR'] ?? null;
        db()->prepare("INSERT INTO user_logs (user_id, admin_id, action, details, ip) VALUES (?,?,?,?,?)")
            ->execute([$userId ?: null, $adminId, $action, $details, $ip]);
    } catch (\Exception $e) {
        try {
            $adminId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            db()->prepare("INSERT INTO user_logs (user_id, admin_id, action, details) VALUES (?,?,?,?)")
                ->execute([$userId ?: null, $adminId, $action, $details]);
        } catch (\Exception $ex) {}
    }
}

/* ── Rate Limiting ────────────────────────────────────────────────────── */

function ensureRateLimitTable(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `rate_limits` (
        `id` int NOT NULL AUTO_INCREMENT,
        `rate_key` varchar(150) NOT NULL,
        `attempts` int NOT NULL DEFAULT 1,
        `window_start` int NOT NULL,
        `blocked_until` int NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `rate_key` (`rate_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensureLogIpColumn(): void {
    try { db()->query("SELECT ip FROM user_logs LIMIT 0"); }
    catch (\Exception $e) {
        try { db()->exec("ALTER TABLE user_logs ADD COLUMN `ip` varchar(45) NULL DEFAULT NULL AFTER `details`"); }
        catch (\Exception $ex) {}
    }
}

function rateLimitCheck(string $action, int $maxAttempts = 5, int $windowSecs = 300): bool {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = $action . ':' . $ip;
    $now = time();
    try {
        $stmt = db()->prepare("SELECT id, attempts, window_start, blocked_until FROM rate_limits WHERE rate_key = ?");
        $stmt->execute([$key]);
        $row  = $stmt->fetch();
        if ($row) {
            if ($row['blocked_until'] && $row['blocked_until'] > $now) return false;
            if ($row['window_start'] < $now - $windowSecs) {
                db()->prepare("UPDATE rate_limits SET attempts=1, window_start=?, blocked_until=NULL WHERE id=?")
                    ->execute([$now, $row['id']]);
                return true;
            }
            $new    = (int)$row['attempts'] + 1;
            $block  = ($new >= $maxAttempts) ? ($now + $windowSecs) : null;
            db()->prepare("UPDATE rate_limits SET attempts=?, blocked_until=? WHERE id=?")
                ->execute([$new, $block, $row['id']]);
            return $new <= $maxAttempts;
        } else {
            db()->prepare("INSERT INTO rate_limits (rate_key, attempts, window_start) VALUES (?,1,?)")
                ->execute([$key, $now]);
            return true;
        }
    } catch (\Exception $e) {
        return true;
    }
}

function rateLimitClear(string $action): void {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = $action . ':' . $ip;
    try { db()->prepare("DELETE FROM rate_limits WHERE rate_key = ?")->execute([$key]); }
    catch (\Exception $e) {}
}

/* ── CPF Validation ───────────────────────────────────────────────────── */

function validateCpf(string $cpf): bool {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) $sum += (int)$cpf[$i] * ($t + 1 - $i);
        $r = (10 * $sum) % 11;
        if ((int)$cpf[$t] !== ($r < 10 ? $r : 0)) return false;
    }
    return true;
}

function sendMail(string $to, string $toName, string $subject, string $htmlBody): bool {
    if (!getSetting('mail_enabled', '0')) return false;
    $from     = getSetting('mail_from', '');
    $fromName = getSetting('mail_from_name', 'APEJESE');
    if (!$from || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: text/html; charset=UTF-8";
    return @mail($to, "=?UTF-8?B?" . base64_encode($subject) . "?=", $htmlBody, $headers);
}

function emailTemplate(string $content, string $subject = ''): string {
    $siteName = htmlspecialchars(getSetting('site_name', 'APEJESE'), ENT_QUOTES);
    $logoPath = getSetting('logo', '');
    $logoUrl  = htmlspecialchars(
        $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png',
        ENT_QUOTES
    );
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f0f4fa;font-family:'Segoe UI',Arial,sans-serif;color:#111827;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4fa;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 2px 18px rgba(0,0,0,.09);">
  <tr>
    <td style="background:#1B3A6B;padding:22px 32px;text-align:center;border-bottom:4px solid #C9A227;">
      <img src="{$logoUrl}" alt="{$siteName}" style="max-height:50px;max-width:160px;object-fit:contain;display:block;margin:0 auto;">
      <p style="color:rgba(255,255,255,.65);font-size:11px;margin:6px 0 0;letter-spacing:.08em;text-transform:uppercase;">{$siteName}</p>
    </td>
  </tr>
  <tr>
    <td style="padding:32px 36px;font-size:15px;line-height:1.75;color:#111827;">
      {$content}
    </td>
  </tr>
  <tr>
    <td style="background:#f7f9fc;border-top:1px solid #e8ecf4;padding:18px 36px;text-align:center;">
      <p style="font-size:12px;color:#8a94a8;margin:0;">&copy; {$year} <strong style="color:#1B3A6B;">{$siteName}</strong> &mdash; Todos os direitos reservados.</p>
      <p style="font-size:11px;color:#b0bcd4;margin:4px 0 0;">Este e-mail foi enviado automaticamente. Por favor, n&atilde;o responda.</p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/* ── Update / Deploy helpers ──────────────────────────────────────────── */

function copyDirectory(string $src, string $dest, array $exclude = []): void {
    $src = rtrim(str_replace('\\', '/', $src), '/');
    $it  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($src))), '/');
        foreach ($exclude as $ex) {
            if (strncmp($rel, $ex, strlen($ex)) === 0) continue 2;
        }
        $dst = $dest . '/' . $rel;
        if ($item->isDir()) {
            if (!is_dir($dst)) mkdir($dst, 0755, true);
        } else {
            @copy($item->getPathname(), $dst);
        }
    }
}

function deleteDirectory(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

// Update logic moved to includes/updater.php (upd_executeUpdate, upd_checkGithub, etc.)
