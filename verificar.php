<?php
require_once __DIR__ . '/config/config.php';

$matricula = sanitize($_GET['m'] ?? '');

// Limita consultas por IP para dificultar enumeração de matrículas
$rateLimited = $matricula !== '' && !rateLimitCheck('verificar', 30, 300);
if ($rateLimited) $matricula = '';

$user = null;
if ($matricula !== '') {
    $stmt = db()->prepare(
        "SELECT u.id, u.full_name, u.matricula_apejese, u.adimplente, u.carteira_validade,
                u.registro_profissional, u.data_filiacao,
                r.profession
         FROM users u
         LEFT JOIN resumes r ON r.user_id = u.id
         WHERE u.matricula_apejese = ? AND u.active = 1
         ORDER BY r.created_at DESC LIMIT 1"
    );
    $stmt->execute([$matricula]);
    $user = $stmt->fetch();
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
<title>Verificação de Associado — <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
body {
    background: #f0f2f8;
    font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    -webkit-font-smoothing: antialiased;
}
.verify-card {
    max-width: 480px; margin: 48px auto 80px; border-radius: 16px;
    overflow: hidden; box-shadow: 0 8px 36px rgba(0,0,0,.14);
}
.verify-header {
    background: linear-gradient(135deg, #0f2347, #1b3a6b);
    padding: 24px; text-align: center; color: #fff;
}
.verify-header img { height: 50px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
.verify-header h4 { font-size: 1rem; font-weight: 700; margin: 0; }
.gold-bar { height: 3px; background: linear-gradient(90deg, #c9a227, #f0c845, #c9a227); }
.verify-body { background: #fff; padding: 28px 24px; }
.verify-status {
    text-align: center; padding: 12px 16px; border-radius: 10px;
    font-size: 1rem; font-weight: 800; margin-bottom: 20px; letter-spacing: .5px;
}
.adimplente   { background: #d4edda; color: #155724; border: 2px solid #28a745; }
.inadimplente { background: #f8d7da; color: #721c24; border: 2px solid #dc3545; }
.field-row { display: flex; gap: 16px; margin-bottom: 14px; flex-wrap: wrap; }
.field-label { font-size: .7rem; color: #9da5be; text-transform: uppercase; font-weight: 700; letter-spacing: .06em; margin-bottom: 2px; }
.field-val   { font-size: .92rem; font-weight: 600; color: #1a2035; }
.verify-footer { text-align: center; padding: 14px; font-size: .78rem; color: #9da5be; background: #f8f9fc; border-top: 1px solid #eef0f6; }
.verify-footer a { color: #1b3a6b; font-weight: 600; text-decoration: none; }
.verify-footer a:hover { opacity: .75; }
</style>
</head>
<body>
<div class="verify-card">
    <div class="verify-header">
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" id="verifyLogo">
        <h4>Verificação de Associado</h4>
    </div>
    <div class="gold-bar"></div>
    <div class="verify-body">
        <!-- Search form always visible -->
        <form method="GET" action="<?= BASE_URL ?>/verificar.php" class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Buscar por Matrícula</label>
            <div class="input-group input-group-sm">
                <input type="text" name="m" class="form-control"
                       placeholder="Nº de matrícula APEJESE"
                       value="<?= e($matricula) ?>" required>
                <button class="btn btn-primary" type="submit" style="background:#1b3a6b;border-color:#1b3a6b;">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
        <?php if ($rateLimited): ?>
        <div class="alert alert-warning text-center" style="font-size:.85rem;">
            <i class="bi bi-hourglass-split me-1"></i>
            Muitas consultas em pouco tempo. Aguarde alguns minutos e tente novamente.
        </div>
        <?php elseif (!$matricula): ?>
        <div class="alert alert-info text-center" style="font-size:.85rem;">
            <i class="bi bi-qr-code-scan me-1"></i>
            Informe a matrícula acima ou escaneie o QR Code da carteira de associado.
        </div>
        <?php elseif (!$user): ?>
        <div class="alert alert-danger text-center">
            <i class="bi bi-x-circle fs-2 d-block mb-2"></i>
            <strong>Matrícula não encontrada.</strong><br>
            <small>A matrícula <code><?= e($matricula) ?></code> não está cadastrada ou o associado está inativo.</small>
        </div>
        <?php else:
            $adimplente = ($user['adimplente'] ?? 1);
            $situacao   = $adimplente ? 'ADIMPLENTE' : 'INADIMPLENTE';
            $sitCls     = $adimplente ? 'adimplente' : 'inadimplente';
            $sitIcon    = $adimplente ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            $validade   = !empty($user['carteira_validade']) ? date('d/m/Y', strtotime($user['carteira_validade'])) : getSetting('carteira_validade', '—');
            $filFmt     = !empty($user['data_filiacao']) ? date('d/m/Y', strtotime($user['data_filiacao'])) : '—';
        ?>
        <div class="verify-status <?= $sitCls ?>">
            <i class="bi <?= $sitIcon ?> me-2"></i><?= $situacao ?>
        </div>
        <div class="field-row">
            <div>
                <div class="field-label">Nome</div>
                <div class="field-val"><?= e($user['full_name']) ?></div>
            </div>
        </div>
        <div class="field-row">
            <div>
                <div class="field-label">Matrícula</div>
                <div class="field-val"><?= e($user['matricula_apejese']) ?></div>
            </div>
            <?php if (!empty($user['profession'])): ?>
            <div>
                <div class="field-label">Profissão</div>
                <div class="field-val"><?= e($user['profession']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="field-row">
            <?php if (!empty($user['registro_profissional'])): ?>
            <div>
                <div class="field-label">Registro Profissional</div>
                <div class="field-val"><?= e($user['registro_profissional']) ?></div>
            </div>
            <?php endif; ?>
            <div>
                <div class="field-label">Filiado desde</div>
                <div class="field-val"><?= $filFmt ?></div>
            </div>
        </div>
        <div class="field-row">
            <div>
                <div class="field-label">Validade da Carteira</div>
                <div class="field-val"><?= e($validade) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="verify-footer">
        <?= e($siteName) ?> · Verificação em <?= date('d/m/Y H:i') ?>
        <br><a href="<?= BASE_URL ?>/index.php">← Voltar ao site</a>
    </div>
</div>
<script nonce="<?= CSP_NONCE ?>">
(function () {
    var l = document.getElementById('verifyLogo');
    if (l) l.addEventListener('error', function () { this.style.display = 'none'; });
})();
</script>
</body>
</html>
