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
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
    $destDir  = UPLOAD_DIR . trim($subdir, '/') . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) return false;
    return trim($subdir, '/') . '/' . $filename;
}

function deleteUpload(string $relativePath): void {
    if ($relativePath) {
        $full = UPLOAD_DIR . ltrim($relativePath, '/');
        if (file_exists($full)) unlink($full);
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
