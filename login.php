<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) redirect(BASE_URL . '/admin/index.php');

$error    = '';
$redirect = sanitize($_GET['redirect'] ?? BASE_URL . '/admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } elseif (!rateLimitCheck('login', 5, 300)) {
        $error = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (login($username, $password)) {
            redirect($redirect);
        } else {
            $error = 'Usuário ou senha incorretos.';
        }
    }
}

$siteName = getSetting('site_name', APP_NAME);
$logoPath = getSetting('logo');
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-page">
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" class="login-logo" onerror="this.style.display='none'">
            <h4><?= e($siteName) ?></h4>
            <p>Área Administrativa</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <?= csrfField() ?>
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <div class="mb-3">
                    <label class="form-label">Usuário ou E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" required autofocus
                               value="<?= e($_POST['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" id="passwordField" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePass()">
                            <i class="bi bi-eye" id="passIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
                </button>
            </form>
        </div>
        <div class="login-footer">
            <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-arrow-left me-1"></i>Voltar ao site</a>
            &nbsp;·&nbsp;
            <a href="<?= BASE_URL ?>/esqueci-senha.php">Esqueci minha senha</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('passIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
