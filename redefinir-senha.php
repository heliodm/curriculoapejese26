<?php
require_once __DIR__ . '/config/config.php';

if (isLoggedIn()) redirect(BASE_URL . '/admin/index.php');

$token = sanitize($_GET['token'] ?? '');
$error = '';
$done  = false;

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
        if (strlen($pass1) < 8) {
            $error = 'A senha deve ter ao menos 8 caracteres.';
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
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--primary-dark) 0%, var(--primary-light) 100%);
        padding: 1.5rem;
    }
    body::before {
        content: '';
        position: fixed; inset: 0;
        background:
            radial-gradient(circle at 20% 80%, rgba(201,162,39,.12) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255,255,255,.05) 0%, transparent 40%);
        pointer-events: none;
    }
    .reset-card {
        width: 100%; max-width: 420px;
        background: #fff; border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
        overflow: hidden; position: relative; z-index: 1;
    }
    .reset-top {
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        padding: 2rem 2rem 1.5rem; text-align: center; color: #fff;
    }
    .reset-icon-wrap {
        width: 68px; height: 68px; border-radius: 18px;
        background: rgba(255,255,255,.12); backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1rem; font-size: 1.8rem;
    }
    .reset-top h2 { font-size: 1.25rem; font-weight: 800; margin-bottom: .3rem; }
    .reset-top p  { font-size: .82rem; opacity: .72; }
    .gold-bar { height: 3px; background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold)); }
    .reset-body { padding: 1.75rem 2rem 2rem; }
    .field-wrap { margin-bottom: 1.1rem; }
    .field-wrap label {
        display: block; font-size: .78rem; font-weight: 700;
        color: #5a6278; text-transform: uppercase; letter-spacing: .5px; margin-bottom: .45rem;
    }
    .field-input-group { position: relative; display: flex; align-items: center; }
    .field-icon { position: absolute; left: 14px; color: #9da5be; font-size: 1rem; pointer-events: none; z-index: 2; transition: color .2s; }
    .field-input-group input {
        width: 100%; padding: .72rem 2.8rem .72rem 2.65rem;
        border: 1.5px solid #dde2ef; border-radius: 10px;
        font-size: .95rem; font-family: inherit;
        background: #fff; color: #1a2035;
        transition: border-color .2s, box-shadow .2s; outline: none;
    }
    .field-input-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(27,58,107,.1); }
    .field-input-group input:focus ~ .field-icon { color: var(--primary); }
    .toggle-pass {
        position: absolute; right: 12px;
        background: none; border: none; color: #9da5be;
        cursor: pointer; padding: 4px; line-height: 1;
        font-size: 1rem; z-index: 2; transition: color .2s;
    }
    .toggle-pass:hover { color: var(--primary); }
    .btn-submit {
        width: 100%; padding: .8rem 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: #fff; border: none; border-radius: 10px;
        font-size: .95rem; font-weight: 700; font-family: inherit; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        margin-top: 1.2rem;
        transition: opacity .2s, box-shadow .2s;
        box-shadow: 0 4px 16px rgba(27,58,107,.3);
    }
    .btn-submit:hover { opacity: .92; box-shadow: 0 6px 20px rgba(27,58,107,.4); }
    .success-state { text-align: center; padding: 1rem 0; }
    .success-icon {
        width: 64px; height: 64px; border-radius: 50%;
        background: #e8f5e9; border: 2px solid #4caf50;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; color: #2e7d32; margin: 0 auto 1rem;
    }
    .success-state h5 { font-weight: 700; color: var(--primary); margin-bottom: .4rem; }
    .success-state p  { font-size: .875rem; color: #6b7a99; line-height: 1.55; }
    .field-error {
        display: flex; align-items: flex-start; gap: 10px;
        background: #fff1f1; border: 1.5px solid #f5c6c6;
        border-radius: 10px; padding: .75rem 1rem;
        margin-bottom: 1.25rem; color: #c0392b; font-size: .875rem;
    }
    .field-error i { font-size: 1.05rem; flex-shrink: 0; margin-top: 1px; }
    .reset-footer {
        border-top: 1px solid #f0f2f8; padding: .9rem 2rem;
        text-align: center; font-size: .82rem;
    }
    .reset-footer a { color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
    .reset-footer a:hover { opacity: .8; }
    .btn-back {
        width: 100%; padding: .75rem 1rem;
        background: none; border: 1.5px solid #dde2ef; border-radius: 10px;
        font-size: .9rem; font-weight: 600; font-family: inherit; color: var(--primary);
        cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
        text-decoration: none; margin-top: .75rem; transition: border-color .2s, background .2s;
    }
    .btn-back:hover { border-color: var(--primary); background: rgba(27,58,107,.04); color: var(--primary); }
    </style>
</head>
<body>
<div class="reset-card">
    <div class="reset-top">
        <div class="reset-icon-wrap">
            <i class="bi bi-shield-lock-fill" style="color:#fff;"></i>
        </div>
        <h2>Redefinir Senha</h2>
        <p>Crie uma nova senha para sua conta</p>
    </div>
    <div class="gold-bar"></div>
    <div class="reset-body">

        <?php if ($done): ?>
        <div class="success-state">
            <div class="success-icon"><i class="bi bi-check-lg"></i></div>
            <h5>Senha redefinida!</h5>
            <p>Sua senha foi alterada com sucesso. Acesse o painel com suas novas credenciais.</p>
        </div>
        <a href="<?= BASE_URL ?>/login.php" class="btn-submit" style="margin-top:1.5rem;text-decoration:none;">
            <i class="bi bi-box-arrow-in-right"></i>Ir para o Login
        </a>

        <?php elseif ($error && !$tokenRow): ?>
        <div class="field-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <a href="<?= BASE_URL ?>/esqueci-senha.php" class="btn-submit" style="text-decoration:none;">
            <i class="bi bi-arrow-left"></i>Solicitar novo link
        </a>

        <?php elseif ($tokenRow): ?>
        <?php if ($error): ?>
        <div class="field-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>
        <p style="font-size:.875rem;color:#6b7a99;margin-bottom:1.25rem;">
            Olá, <strong style="color:#1a2035;"><?= e($tokenRow['full_name']) ?></strong>. Crie sua nova senha abaixo.
        </p>
        <form method="POST" action="<?= BASE_URL ?>/redefinir-senha.php?token=<?= urlencode($token) ?>" id="resetForm">
            <?= csrfField() ?>
            <div class="field-wrap">
                <label for="pw1">Nova Senha</label>
                <div class="field-input-group">
                    <input type="password" name="password" id="pw1" required minlength="8" autofocus placeholder="Mínimo 8 caracteres">
                    <i class="bi bi-lock field-icon"></i>
                    <button type="button" class="toggle-pass" onclick="toggleP('pw1','ic1')" tabindex="-1">
                        <i class="bi bi-eye" id="ic1"></i>
                    </button>
                </div>
            </div>
            <div class="field-wrap">
                <label for="pw2">Confirmar Nova Senha</label>
                <div class="field-input-group">
                    <input type="password" name="password2" id="pw2" required minlength="8" placeholder="Repita a senha">
                    <i class="bi bi-lock-fill field-icon"></i>
                    <button type="button" class="toggle-pass" onclick="toggleP('pw2','ic2')" tabindex="-1">
                        <i class="bi bi-eye" id="ic2"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="bi bi-check-lg"></i>Salvar Nova Senha
            </button>
        </form>

        <?php else: ?>
        <div class="field-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span>Token não fornecido. Acesse o link enviado por e-mail.</span>
        </div>
        <?php endif; ?>

    </div>
    <div class="reset-footer">
        <a href="<?= BASE_URL ?>/login.php"><i class="bi bi-arrow-left"></i>Voltar ao login</a>
    </div>
</div>

<script>
function toggleP(fId, iId) {
    const f = document.getElementById(fId);
    const i = document.getElementById(iId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
document.querySelectorAll('.field-input-group input').forEach(function (inp) {
    inp.addEventListener('focus', function () { this.parentElement.querySelector('.field-icon').style.color = 'var(--primary)'; });
    inp.addEventListener('blur',  function () { this.parentElement.querySelector('.field-icon').style.color = ''; });
});
var form = document.getElementById('resetForm');
if (form) {
    form.addEventListener('submit', function () {
        var btn = document.getElementById('submitBtn');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Salvando…';
        btn.disabled = true;
    });
}
</script>
</body>
</html>
