<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) redirect(BASE_URL . '/admin/index.php');

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um endereço de e-mail válido.';
        } else {
            $stmt = db()->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Invalidate old tokens
                db()->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);

                // Create new token
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)")
                    ->execute([$user['id'], $token, $expires]);

                $resetUrl = BASE_URL . '/redefinir-senha.php?token=' . $token;
                $htmlBody = "
                    <p>Olá, <strong>" . htmlspecialchars($user['full_name'], ENT_QUOTES) . "</strong>!</p>
                    <p>Você solicitou a redefinição de senha. Clique no link abaixo para criar uma nova senha:</p>
                    <p><a href='{$resetUrl}'>{$resetUrl}</a></p>
                    <p><small>Este link expira em 1 hora. Se você não solicitou esta redefinição, ignore este e-mail.</small></p>
                ";
                sendMail($user['email'], $user['full_name'], 'Redefinição de Senha — APEJESE', $htmlBody);
            }

            // Always show success (don't reveal if email exists)
            $sent = true;
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
    <title>Esqueci a Senha — <?= e($siteName) ?></title>
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
            <p>Recuperar Senha</p>
        </div>
        <div class="login-body">
            <?php if ($sent): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                Se o e-mail informado estiver cadastrado, você receberá as instruções para redefinir sua senha em breve.
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$sent): ?>
            <p class="text-muted small mb-3">
                Informe seu e-mail cadastrado e enviaremos um link para redefinir sua senha.
            </p>
            <form method="POST">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required autofocus
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary-custom w-100">
                    <i class="bi bi-send me-2"></i>Enviar Link de Redefinição
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="login-footer">
            <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left me-1"></i>Voltar ao login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
