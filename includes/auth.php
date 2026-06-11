<?php
function login(string $username, string $password): bool {
    // Username-based lockout (10 attempts per 10 minutes)
    $userKey = 'login_user:' . strtolower($username);
    if (!rateLimitCheck($userKey, 10, 600)) return false;

    $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Clear per-username lockout counter on success
    rateLimitClearKey($userKey);
    rateLimitClear('login');

    if (!empty($user['totp_enabled']) && !empty($user['totp_secret'])) {
        // 2FA required — store partial auth in session, do NOT fully log in
        session_regenerate_id(true);
        $_SESSION['2fa_uid']      = (int)$user['id'];
        $_SESSION['2fa_username'] = $user['username'];
        return true;
    }

    // Full login
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logUserAction($user['id'], 'login', 'Login realizado. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    return true;
}

function completeTotpLogin(int $userId): void {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return;

    unset($_SESSION['2fa_uid'], $_SESSION['2fa_username']);
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logUserAction($user['id'], 'login', 'Login 2FA realizado. IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect(BASE_URL . '/login.php');
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isEditor(): bool {
    return isLoggedIn() && in_array($_SESSION['role'] ?? '', ['admin', 'editor']);
}

function requireAuth(string $redirect = ''): void {
    if (!empty($_SESSION['2fa_uid'])) {
        redirect(BASE_URL . '/admin/2fa.php');
    }
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php?redirect=' . urlencode($redirect ?: $_SERVER['REQUEST_URI']));
    }
}

function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        flash('danger', 'Acesso negado. Apenas administradores.');
        redirect(BASE_URL . '/admin/index.php');
    }
}

function requireEditor(): void {
    requireAuth();
    if (!isEditor()) {
        flash('danger', 'Acesso negado.');
        redirect(BASE_URL . '/admin/index.php');
    }
}

function currentUser(): array {
    if (!isLoggedIn()) return [];
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: [];
    }
    return $user;
}
