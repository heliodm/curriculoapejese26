<?php
function login(string $username, string $password): bool {
    $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    return true;
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
