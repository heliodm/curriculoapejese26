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
$siteDesc = getSetting('site_description', 'Sistema de Currículos e Associados');
$logoPath = getSetting('logo');
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — <?= e($siteName) ?></title>
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

    html, body {
        height: 100%;
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: #f0f2f8;
    }

    /* ── Layout ─────────────────────────────────────────── */
    .login-root {
        min-height: 100vh;
        display: flex;
        align-items: stretch;
    }

    /* ── Left Panel ──────────────────────────────────────── */
    .login-panel-left {
        flex: 0 0 45%;
        background: linear-gradient(150deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 2.5rem;
        position: relative;
        overflow: hidden;
    }

    /* Decorative circles */
    .login-panel-left::before {
        content: '';
        position: absolute;
        width: 380px; height: 380px;
        border: 60px solid rgba(255,255,255,.06);
        border-radius: 50%;
        top: -100px; right: -120px;
    }
    .login-panel-left::after {
        content: '';
        position: absolute;
        width: 260px; height: 260px;
        border: 40px solid rgba(255,255,255,.05);
        border-radius: 50%;
        bottom: -80px; left: -80px;
    }

    .left-ring {
        position: absolute;
        width: 180px; height: 180px;
        border: 28px solid rgba(201,162,39,.15);
        border-radius: 50%;
        bottom: 30%; right: -50px;
    }

    .left-content {
        position: relative;
        z-index: 1;
        text-align: center;
        color: #fff;
    }

    .left-logo-wrap {
        width: 96px; height: 96px;
        border-radius: 24px;
        background: rgba(255,255,255,.12);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,.2);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem;
        padding: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,.2);
    }

    .left-logo-wrap img {
        width: 100%; height: 100%;
        object-fit: contain;
        filter: brightness(0) invert(1);
    }

    .left-title {
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -.3px;
        margin-bottom: .5rem;
    }

    .left-subtitle {
        font-size: .9rem;
        opacity: .72;
        max-width: 280px;
        line-height: 1.55;
        margin: 0 auto 2rem;
    }

    .left-divider {
        width: 48px; height: 3px;
        background: linear-gradient(90deg, var(--gold), var(--gold-light));
        border-radius: 2px;
        margin: 0 auto 2rem;
    }

    .left-features {
        list-style: none;
        text-align: left;
        display: inline-block;
    }

    .left-features li {
        font-size: .82rem;
        opacity: .8;
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .left-features li i {
        color: var(--gold-light);
        font-size: .95rem;
        flex-shrink: 0;
    }

    /* ── Right Panel ─────────────────────────────────────── */
    .login-panel-right {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1.5rem;
        background: #f7f8fc;
    }

    .login-form-card {
        width: 100%;
        max-width: 400px;
    }

    .login-form-head {
        margin-bottom: 2rem;
    }

    .login-form-head h2 {
        font-size: 1.55rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: .3rem;
    }

    .login-form-head p {
        color: #8891a5;
        font-size: .875rem;
    }

    /* ── Input fields ────────────────────────────────────── */
    .field-wrap {
        position: relative;
        margin-bottom: 1.1rem;
    }

    .field-wrap label {
        display: block;
        font-size: .78rem;
        font-weight: 700;
        color: #5a6278;
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: .45rem;
    }

    .field-input-group {
        position: relative;
        display: flex;
        align-items: center;
    }

    .field-icon {
        position: absolute;
        left: 14px;
        color: #9da5be;
        font-size: 1rem;
        pointer-events: none;
        transition: color .2s;
        z-index: 2;
    }

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
        -webkit-appearance: none;
    }

    .field-input-group input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(27,58,107,.1);
    }

    .field-input-group input:focus + .field-icon,
    .field-icon-left-focus { color: var(--primary); }

    .field-input-group input:focus ~ .field-icon { color: var(--primary); }

    .toggle-pass {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        color: #9da5be;
        cursor: pointer;
        padding: 4px;
        line-height: 1;
        font-size: 1rem;
        z-index: 2;
        transition: color .2s;
    }
    .toggle-pass:hover { color: var(--primary); }

    /* ── Error alert ─────────────────────────────────────── */
    .login-error {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #fff1f1;
        border: 1.5px solid #f5c6c6;
        border-radius: 10px;
        padding: .75rem 1rem;
        margin-bottom: 1.25rem;
        color: #c0392b;
        font-size: .875rem;
    }
    .login-error i { font-size: 1.05rem; flex-shrink: 0; margin-top: 1px; }

    /* ── Submit button ───────────────────────────────────── */
    .btn-login {
        width: 100%;
        padding: .8rem 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: .95rem;
        font-weight: 700;
        letter-spacing: .3px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 1.5rem;
        transition: opacity .2s, transform .1s, box-shadow .2s;
        box-shadow: 0 4px 16px rgba(27,58,107,.3);
    }
    .btn-login:hover  { opacity: .92; box-shadow: 0 6px 20px rgba(27,58,107,.4); }
    .btn-login:active { transform: scale(.98); }

    /* Gold accent line above button */
    .gold-line {
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
        border-radius: 1px;
        margin: 1.2rem 0 0;
        opacity: .6;
    }

    /* ── Footer links ────────────────────────────────────── */
    .login-links {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 1.4rem;
        font-size: .82rem;
        flex-wrap: wrap;
        gap: .5rem;
    }
    .login-links a {
        color: #7a84a0;
        text-decoration: none;
        transition: color .2s;
        display: flex; align-items: center; gap: 4px;
    }
    .login-links a:hover { color: var(--primary); }

    .login-brand-footer {
        text-align: center;
        margin-top: 2.5rem;
        font-size: .75rem;
        color: #b0b8cc;
    }

    /* ── Shake animation on error ────────────────────────── */
    @keyframes shake {
        0%,100% { transform: translateX(0); }
        20%,60%  { transform: translateX(-6px); }
        40%,80%  { transform: translateX(6px); }
    }
    .shake { animation: shake .4s ease; }

    /* ── Responsive: hide left panel on small screens ────── */
    @media (max-width: 768px) {
        .login-panel-left { display: none; }
        .login-panel-right { background: linear-gradient(150deg, var(--primary-dark) 0%, var(--primary-light) 100%); }
        .login-form-card {
            background: #fff;
            border-radius: 20px;
            padding: 2rem 1.5rem;
            box-shadow: 0 12px 48px rgba(0,0,0,.25);
        }
        .login-brand-footer { color: rgba(255,255,255,.5); }
    }
    </style>
</head>
<body>
<div class="login-root">

    <!-- ── Left decorative panel ── -->
    <div class="login-panel-left">
        <div class="left-ring"></div>
        <div class="left-content">
            <div class="left-logo-wrap">
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" onerror="this.parentElement.innerHTML='<i class=\'bi bi-shield-check\' style=\'font-size:2.5rem;color:#fff;\'></i>'">
            </div>
            <h1 class="left-title"><?= e($siteName) ?></h1>
            <p class="left-subtitle"><?= e($siteDesc) ?></p>
            <div class="left-divider"></div>
            <ul class="left-features">
                <li><i class="bi bi-file-person-fill"></i>Gestão de currículos de associados</li>
                <li><i class="bi bi-credit-card-2-front-fill"></i>Carteira e declarações digitais</li>
                <li><i class="bi bi-people-fill"></i>Controle de membros e situação financeira</li>
                <li><i class="bi bi-shield-lock-fill"></i>Acesso seguro e auditável</li>
            </ul>
        </div>
    </div>

    <!-- ── Right form panel ── -->
    <div class="login-panel-right">
        <div class="login-form-card">
            <div class="login-form-head">
                <h2>Bem-vindo de volta</h2>
                <p>Acesse sua conta para continuar</p>
            </div>

            <?php if ($error): ?>
            <div class="login-error" id="loginError">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= e($error) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="loginForm">
                <?= csrfField() ?>
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                <div class="field-wrap">
                    <label for="usernameField">Usuário ou E-mail</label>
                    <div class="field-input-group">
                        <input type="text" name="username" id="usernameField"
                               required autofocus autocomplete="username"
                               placeholder="seu.usuario ou email@exemplo.com"
                               value="<?= e($_POST['username'] ?? '') ?>">
                        <i class="bi bi-person field-icon"></i>
                    </div>
                </div>

                <div class="field-wrap">
                    <label for="passwordField">Senha</label>
                    <div class="field-input-group">
                        <input type="password" name="password" id="passwordField"
                               required autocomplete="current-password"
                               placeholder="••••••••">
                        <i class="bi bi-lock field-icon"></i>
                        <button type="button" class="toggle-pass" onclick="togglePass()" tabindex="-1" aria-label="Mostrar senha">
                            <i class="bi bi-eye" id="passIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="gold-line"></div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="bi bi-box-arrow-in-right"></i>Entrar
                </button>
            </form>

            <div class="login-links">
                <a href="<?= BASE_URL ?>/index.php">
                    <i class="bi bi-arrow-left"></i>Voltar ao site
                </a>
                <a href="<?= BASE_URL ?>/esqueci-senha.php">
                    <i class="bi bi-key"></i>Esqueci minha senha
                </a>
            </div>

            <div class="login-brand-footer">
                <?= e($siteName) ?> &mdash; Painel Administrativo
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('passIcon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                       { f.type = 'password'; i.className = 'bi bi-eye'; }
}

// Focus colour on icon
document.querySelectorAll('.field-input-group input').forEach(function (inp) {
    inp.addEventListener('focus',  function () { this.parentElement.querySelector('.field-icon').style.color = 'var(--primary)'; });
    inp.addEventListener('blur',   function () { this.parentElement.querySelector('.field-icon').style.color = ''; });
});

// Shake card on error
<?php if ($error): ?>
document.getElementById('loginForm').classList.add('shake');
<?php endif; ?>

// Loading state on submit
document.getElementById('loginForm').addEventListener('submit', function () {
    var btn = document.getElementById('loginBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Entrando…';
    btn.disabled = true;
});
</script>
</body>
</html>
