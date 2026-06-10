<?php
require_once dirname(__DIR__) . '/config/config.php';

// Must have a pending 2FA session
if (empty($_SESSION['2fa_uid'])) {
    redirect(BASE_URL . '/login.php');
}

$error  = '';
$uid    = (int)$_SESSION['2fa_uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido.';
    } elseif (!rateLimitCheck('2fa:' . $uid, 5, 300)) {
        $error = 'Muitas tentativas. Aguarde alguns minutos.';
    } else {
        $stmt = db()->prepare("SELECT totp_secret FROM users WHERE id = ? AND active = 1");
        $stmt->execute([$uid]);
        $row  = $stmt->fetch();
        $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

        if ($row && (new Totp($row['totp_secret']))->verify($code)) {
            completeTotpLogin($uid);
            redirect(BASE_URL . '/admin/index.php');
        } else {
            $error = 'Código inválido ou expirado.';
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
    <title>Verificação em duas etapas — <?= e($siteName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--primary:#1b3a6b;--gold:#c9a227}
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f4fa;font-family:'Inter','Segoe UI',system-ui,sans-serif;}
    .card-2fa{width:100%;max-width:380px;background:#fff;border-radius:16px;box-shadow:0 4px 32px rgba(0,0,0,.1);padding:2.5rem 2rem;}
    .logo-wrap{width:64px;height:64px;border-radius:16px;background:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;}
    .logo-wrap img{max-width:80%;max-height:80%;object-fit:contain;filter:brightness(0) invert(1);}
    h1{font-size:1.25rem;font-weight:800;color:var(--primary);text-align:center;margin-bottom:.35rem}
    .sub{font-size:.82rem;color:#8891a5;text-align:center;margin-bottom:1.75rem}
    .code-input{font-size:2rem;font-weight:700;letter-spacing:.4em;text-align:center;border:2px solid #dde2ef;border-radius:10px;padding:.6rem;width:100%;outline:none;transition:border-color .2s;}
    .code-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(27,58,107,.1)}
    .btn-verify{width:100%;padding:.75rem;background:var(--primary);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:.95rem;cursor:pointer;margin-top:1.25rem;transition:opacity .2s}
    .btn-verify:hover{opacity:.9}
    .err{background:#fff1f1;border:1.5px solid #f5c6c6;border-radius:8px;padding:.65rem .9rem;font-size:.85rem;color:#c0392b;margin-bottom:1rem;display:flex;align-items:center;gap:8px}
    .back{display:block;text-align:center;margin-top:1.25rem;font-size:.8rem;color:#8891a5;text-decoration:none}
    .back:hover{color:var(--primary)}
    </style>
</head>
<body>
<div class="card-2fa">
    <div class="logo-wrap">
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>">
    </div>
    <h1>Verificação em duas etapas</h1>
    <p class="sub">Abra seu aplicativo autenticador e insira o código de 6 dígitos.</p>

    <?php if ($error): ?>
    <div class="err"><i class="bi bi-exclamation-circle-fill"></i><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="form2fa">
        <?= csrfField() ?>
        <input type="text" name="code" class="code-input" id="codeInput"
               inputmode="numeric" pattern="\d{6}" maxlength="6"
               autocomplete="one-time-code" placeholder="000000" autofocus required>
        <button type="submit" class="btn-verify">
            <i class="bi bi-shield-check me-1"></i>Verificar
        </button>
    </form>
    <a href="<?= BASE_URL ?>/logout.php" class="back">
        <i class="bi bi-arrow-left me-1"></i>Usar outra conta
    </a>
</div>
<script nonce="<?= CSP_NONCE ?>">
document.getElementById('codeInput').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) document.getElementById('form2fa').submit();
});
</script>
</body>
</html>
