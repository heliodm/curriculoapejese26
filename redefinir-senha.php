<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) redirect(BASE_URL . '/admin/index.php');

$token = sanitize($_GET['token'] ?? '');
$error = '';
$done  = false;

// Validate token
$tokenRow = null;
if ($token !== '') {
    $stmt = db()->prepare(
        "SELECT pr.*, u.full_name, u.email
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()"
    );
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch();
}

if (!$tokenRow && $token !== '') {
    $error = 'Este link de redefinição é inválido ou expirou. Solicite um novo link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';
        if (strlen($pass1) < 6) {
            $error = 'A senha deve ter ao menos 6 caracteres.';
        } elseif ($pass1 !== $pass2) {
            $error = 'As senhas não coincidem.';
        } else {
            db()->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
                ->execute([password_hash($pass1, PASSWORD_BCRYPT), $tokenRow['user_id']]);
            db()->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
                ->execute([$tokenRow['id']]);
            logUserAction($tokenRow['user_id'], 'reset_senha_self', 'Senha redefinida via e-mail.');
            $done = true;
        }
    }
}

$siteName = getSetting('site_name', 'APEJESE');
$logoPath = getSetting('logo', '');
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha — <?= e($siteName) ?></title>
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
            <p>Redefinir Senha</p>
        </div>
        <div class="login-body">
            <?php if ($done): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Senha alterada com sucesso!
            </div>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary-custom w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Ir para o Login
            </a>

            <?php elseif ($error && !$tokenRow): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
            <a href="<?= BASE_URL ?>/esqueci-senha.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i>Solicitar novo link
            </a>

            <?php elseif ($tokenRow): ?>
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
            <?php endif; ?>
            <p class="text-muted small mb-3">
                Olá, <strong><?= e($tokenRow['full_name']) ?></strong>. Crie sua nova senha abaixo.
            </p>
            <form method="POST" action="<?= BASE_URL ?>/redefinir-senha.php?token=<?= urlencode($token) ?>">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Nova Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" id="pw1"
                               required minlength="6" autofocus>
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleP('pw1','ic1')">
                            <i class="bi bi-eye" id="ic1"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password2" class="form-control" id="pw2"
                               required minlength="6">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleP('pw2','ic2')">
                            <i class="bi bi-eye" id="ic2"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="bi bi-check-lg me-2"></i>Salvar Nova Senha
                </button>
            </form>

            <?php else: ?>
            <div class="alert alert-warning">
                Token não fornecido. Acesse o link enviado por e-mail.
            </div>
            <?php endif; ?>
        </div>
        <div class="login-footer">
            <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left me-1"></i>Voltar ao login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleP(fId, iId) {
    const f = document.getElementById(fId);
    const i = document.getElementById(iId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
