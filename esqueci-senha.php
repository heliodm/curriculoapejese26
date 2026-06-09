<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) redirect(BASE_URL . '/admin/index.php');

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } elseif (!rateLimitCheck('reset', 3, 600)) {
        $error = 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.';
    } else {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Informe um endereço de e-mail válido.';
        } else {
            $stmt = db()->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                db()->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")->execute([$user['id']]);

                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                db()->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)")
                    ->execute([$user['id'], $token, $expires]);

                $resetUrl    = BASE_URL . '/redefinir-senha.php?token=' . $token;
                $resetUrlEsc = htmlspecialchars($resetUrl, ENT_QUOTES);
                $htmlBody = emailTemplate(
                    "<p>Olá, <strong>" . htmlspecialchars($user['full_name'], ENT_QUOTES) . "</strong>!</p>
                     <p>Você solicitou a redefinição de senha. Clique no botão abaixo para criar uma nova senha:</p>
                     <p style=\"text-align:center;margin:28px 0;\">
                         <a href=\"{$resetUrlEsc}\" style=\"display:inline-block;background:#1B3A6B;color:#ffffff;padding:12px 30px;border-radius:7px;text-decoration:none;font-weight:600;font-size:15px;\">Redefinir Senha</a>
                     </p>
                     <p style=\"font-size:13px;color:#6c757d;\">Ou copie e cole este link no navegador:<br>
                         <a href=\"{$resetUrlEsc}\" style=\"color:#1B3A6B;word-break:break-all;\">{$resetUrl}</a>
                     </p>
                     <p style=\"font-size:12px;color:#b0bcd4;margin-top:16px;\">Este link expira em 1 hora. Se você não solicitou esta redefinição, pode ignorar este e-mail.</p>",
                    'Redefinição de Senha — APEJESE'
                );
                sendMail($user['email'], $user['full_name'], 'Redefinição de Senha — APEJESE', $htmlBody);
            }

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
    <title>Recuperar Senha — <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
        --primary: #1b3a6b;
        --primary-dark: #0f2347;
        --primary-light: #2a5298;
        --gold: #c9a227;
        --gold-light: #e8c347;
    }
    html, body { height: 100%; font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; }
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(150deg, var(--primary-dark) 0%, var(--primary-light) 100%);
        padding: 1.5rem;
    }
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background:
            radial-gradient(circle at 20% 80%, rgba(201,162,39,.12) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255,255,255,.05) 0%, transparent 40%);
        pointer-events: none;
    }
    .recover-card {
        width: 100%;
        max-width: 420px;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .recover-top {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        padding: 2rem 2rem 1.5rem;
        text-align: center;
        color: #fff;
    }
    .recover-icon-wrap {
        width: 68px; height: 68px;
        border-radius: 18px;
        background: rgba(255,255,255,.12);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.8rem;
    }
    .recover-top h2 { font-size: 1.25rem; font-weight: 800; margin-bottom: .3rem; }
    .recover-top p  { font-size: .82rem; opacity: .72; }
    .gold-bar { height: 3px; background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold)); }
    .recover-body { padding: 1.75rem 2rem 2rem; }
    .field-wrap { margin-bottom: 1.1rem; }
    .field-wrap label {
        display: block;
        font-size: .78rem;
        font-weight: 700;
        color: #5a6278;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: .45rem;
    }
    .field-input-group { position: relative; display: flex; align-items: center; }
    .field-icon { position: absolute; left: 14px; color: #9da5be; font-size: 1rem; pointer-events: none; z-index: 2; transition: color .2s; }
    .field-input-group input {
        width: 100%;
        padding: .72rem 1rem .72rem 2.65rem;
        border: 1.5px solid #dde2ef;
        border-radius: 10px;
        font-size: .95rem;
        background: #fff;
        color: #1a2035;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }
    .field-input-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27,58,107,.1); }
    .field-input-group input:focus ~ .field-icon { color: var(--primary); }
    .btn-send {
        width: 100%;
        padding: .8rem 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: #fff; border: none; border-radius: 10px;
        font-size: .95rem; font-weight: 700; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        margin-top: 1.2rem;
        transition: opacity .2s, box-shadow .2s;
        box-shadow: 0 4px 16px rgba(27,58,107,.3);
    }
    .btn-send:hover { opacity: .92; box-shadow: 0 6px 20px rgba(27,58,107,.4); }
    .success-state {
        text-align: center; padding: 1rem 0;
    }
    .success-icon {
        width: 64px; height: 64px;
        border-radius: 50%;
        background: #e8f5e9;
        border: 2px solid #4caf50;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; color: #2e7d32;
        margin: 0 auto 1rem;
    }
    .success-state h5 { font-weight: 700; color: var(--primary); margin-bottom: .4rem; }
    .success-state p  { font-size: .875rem; color: #6b7a99; line-height: 1.55; }
    .login-error {
        display: flex; align-items: flex-start; gap: 10px;
        background: #fff1f1; border: 1.5px solid #f5c6c6;
        border-radius: 10px; padding: .75rem 1rem;
        margin-bottom: 1.25rem; color: #c0392b; font-size: .875rem;
    }
    .login-error i { font-size: 1.05rem; flex-shrink: 0; margin-top: 1px; }
    .recover-footer {
        border-top: 1px solid #f0f2f8;
        padding: .9rem 2rem;
        text-align: center;
        font-size: .82rem;
    }
    .recover-footer a { color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
    .recover-footer a:hover { opacity: .8; }
    </style>
</head>
<body>
<div class="recover-card">
    <div class="recover-top">
        <div class="recover-icon-wrap">
            <i class="bi bi-key-fill" style="color:#fff;"></i>
        </div>
        <h2>Recuperar Senha</h2>
        <p>Enviaremos um link de redefinição para o seu e-mail</p>
    </div>
    <div class="gold-bar"></div>
    <div class="recover-body">

        <?php if ($sent): ?>
        <div class="success-state">
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <h5>E-mail enviado!</h5>
            <p>Se o endereço informado estiver cadastrado, você receberá as instruções para redefinir sua senha em breve.<br><br>Verifique também a pasta de spam.</p>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="login-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="recoverForm">
            <?= csrfField() ?>
            <div class="field-wrap">
                <label for="emailField">E-mail cadastrado</label>
                <div class="field-input-group">
                    <input type="email" name="email" id="emailField"
                           required autofocus autocomplete="email"
                           placeholder="seu@email.com"
                           value="<?= e($_POST['email'] ?? '') ?>">
                    <i class="bi bi-envelope field-icon"></i>
                </div>
            </div>
            <button type="submit" class="btn-send" id="sendBtn">
                <i class="bi bi-send"></i>Enviar Link de Redefinição
            </button>
        </form>

        <?php endif; ?>
    </div>
    <div class="recover-footer">
        <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i>Voltar ao login</a>
    </div>
</div>

<script>
document.querySelectorAll('.field-input-group input').forEach(function (inp) {
    inp.addEventListener('focus', function () { this.parentElement.querySelector('.field-icon').style.color = 'var(--primary)'; });
    inp.addEventListener('blur',  function () { this.parentElement.querySelector('.field-icon').style.color = ''; });
});

var form = document.getElementById('recoverForm');
if (form) {
    form.addEventListener('submit', function () {
        var btn = document.getElementById('sendBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enviando…';
        btn.disabled = true;
    });
}
</script>
</body>
</html>
