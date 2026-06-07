<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$corHeader = getSetting('carteira_cor_header', '#1b3a6b');
$corAcento = getSetting('carteira_cor_acento', '#c9a227');
$mostrarRegistro = getSetting('carteira_mostrar_registro', '1') === '1';
$mostrarFiliacao = getSetting('carteira_mostrar_filiacao', '1') === '1';
$globalVal = getSetting('carteira_validade', '');
$logoPath  = getSetting('logo', '');
$logoUrl   = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';

$filtroAdimplente = sanitize($_GET['adimplente'] ?? 'todos');
$whereAdimplente  = '';
if ($filtroAdimplente === 'adimplentes')   $whereAdimplente = ' AND (u.adimplente = 1 OR u.adimplente IS NULL)';
if ($filtroAdimplente === 'inadimplentes') $whereAdimplente = ' AND u.adimplente = 0';

$users = db()->query(
    "SELECT u.id, u.full_name, u.adimplente, u.matricula_apejese, u.cpf,
            u.data_nascimento, u.photo AS user_photo, u.data_filiacao,
            u.registro_profissional, u.carteira_validade,
            r.photo AS resume_photo, r.profession, r.formation
     FROM users u
     LEFT JOIN resumes r ON r.user_id = u.id
     WHERE u.active = 1{$whereAdimplente}
     GROUP BY u.id
     ORDER BY u.full_name ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Carteiras em Lote — APEJESE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #e8e8e8; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; }
.print-bar {
    background: <?= $corHeader ?>; color: #fff; padding: 10px 20px;
    display: flex; align-items: center; gap: 12px;
    position: fixed; top: 0; left: 0; right: 0; z-index: 99;
}
.print-bar button {
    background: <?= $corAcento ?>; color: #fff; border: none; padding: 7px 20px;
    border-radius: 6px; font-size: .9rem; font-weight: 700; cursor: pointer;
}
.print-bar a { color: #ccc; text-decoration: none; font-size: .85rem; }
.page-wrap { margin-top: 60px; padding: 20px; }
.card-page {
    display: flex; gap: 12mm; justify-content: center;
    flex-wrap: wrap; margin-bottom: 10mm;
    page-break-inside: avoid;
}
.card-outer {
    width: 86mm; flex-shrink: 0;
    border-radius: 8px; overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.2); background: #fff;
    display: flex; flex-direction: column;
}
.card-header-bar {
    background: linear-gradient(135deg, <?= $corHeader ?>, <?= $corHeader ?>cc);
    padding: 8px 10px; display: flex; align-items: center; gap: 8px;
}
.card-header-bar img { height: 20px; filter: brightness(0) invert(1); }
.card-header-text { color: #fff; line-height: 1.2; }
.card-header-text h1 { font-size: 7pt; font-weight: 800; }
.card-header-text p  { font-size: 5pt; opacity: .75; text-transform: uppercase; }
.card-gold-bar { height: 3px; background: linear-gradient(90deg,<?= $corAcento ?>,<?= $corAcento ?>88,<?= $corAcento ?>); }
.card-body { flex: 1; padding: 8px 10px 0; display: flex; flex-direction: column; }
.card-photo-row { display: flex; gap: 8px; margin-bottom: 8px; }
.card-photo {
    width: 44px; height: 54px; flex-shrink: 0;
    border-radius: 4px; overflow: hidden; border: 2px solid <?= $corHeader ?>;
    background: #dde3ee; display: flex; align-items: center; justify-content: center;
}
.card-photo img { width: 100%; height: 100%; object-fit: cover; }
.card-photo .np { font-size: 18px; color: #b0bcd4; }
.card-name { font-size: 7pt; font-weight: 800; color: <?= $corHeader ?>; line-height: 1.2; }
.card-prof { font-size: 5.5pt; color: #555; }
.card-divider { height: 1px; background: linear-gradient(90deg,transparent,<?= $corAcento ?>,transparent); margin: 4px 0 6px; }
.card-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; }
.card-field label { display: block; font-size: 4.5pt; font-weight: 700; text-transform: uppercase; color: #888; }
.card-field span  { font-size: 6pt; font-weight: 600; color: #222; }
.card-footer-bar {
    background: linear-gradient(135deg, <?= $corHeader ?>, <?= $corHeader ?>cc);
    padding: 6px 10px; display: flex; align-items: center;
    justify-content: space-between; margin-top: auto; gap: 6px;
}
.situacao { font-size: 5.5pt; font-weight: 800; color: #fff; padding: 2px 7px; border-radius: 10px; }
.validade { font-size: 5pt; color: rgba(255,255,255,.7); }
.validade strong { display: block; color: <?= $corAcento ?>; font-size: 6pt; }
.qr img { width: 28px; height: 28px; border-radius: 3px; }
@media print {
    html, body { background: #fff; }
    .print-bar { display: none !important; }
    .page-wrap { margin: 0; padding: 10mm; }
    .card-page { gap: 8mm; margin-bottom: 8mm; }
    @page { size: A4 landscape; margin: 15mm; }
}
</style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a href="<?= BASE_URL ?>/admin/carteira.php">← Voltar</a>
    <span style="margin-left:8px;">
        <select onchange="location.href='?adimplente='+this.value"
                style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);padding:4px 8px;border-radius:4px;font-size:.8rem;">
            <option value="todos"         <?= $filtroAdimplente==='todos'         ? 'selected':'' ?>>Todos</option>
            <option value="adimplentes"   <?= $filtroAdimplente==='adimplentes'   ? 'selected':'' ?>>Adimplentes</option>
            <option value="inadimplentes" <?= $filtroAdimplente==='inadimplentes' ? 'selected':'' ?>>Inadimplentes</option>
        </select>
    </span>
    <span style="margin-left:auto;opacity:.7;font-size:.85rem;"><?= count($users) ?> carteira(s)</span>
</div>
<div class="page-wrap">
<?php
$chunks = array_chunk($users, 2);
foreach ($chunks as $chunk):
?>
<div class="card-page">
<?php foreach ($chunk as $u):
    $photoUrl  = !empty($u['user_photo']) ? UPLOAD_URL . $u['user_photo'] : (!empty($u['resume_photo']) ? UPLOAD_URL . $u['resume_photo'] : null);
    $nasc      = !empty($u['data_nascimento']) ? date('d/m/Y', strtotime($u['data_nascimento'])) : '—';
    $fil       = !empty($u['data_filiacao'])   ? date('d/m/Y', strtotime($u['data_filiacao']))   : '—';
    $situacao  = ($u['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $corSit    = ($u['adimplente'] ?? 1) ? '#28a745' : '#dc3545';
    $validade  = !empty($u['carteira_validade']) ? date('d/m/Y', strtotime($u['carteira_validade'])) : $globalVal;
    $qrData    = $u['matricula_apejese'] ? BASE_URL . '/verificar.php?m=' . urlencode($u['matricula_apejese']) : '';
    $qrUrl     = $qrData ? 'https://api.qrserver.com/v1/create-qr-code/?size=56x56&margin=3&data=' . urlencode($qrData) : '';
?>
    <div class="card-outer">
        <div class="card-header-bar">
            <img src="<?= e($logoUrl) ?>" alt="" onerror="this.style.display='none'">
            <div class="card-header-text">
                <h1>APEJESE</h1>
                <p>Carteira de Associado</p>
            </div>
        </div>
        <div class="card-gold-bar"></div>
        <div class="card-body">
            <div class="card-photo-row">
                <div class="card-photo">
                    <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" alt="">
                    <?php else: ?>
                    <span class="np">👤</span>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="card-name"><?= e($u['full_name']) ?></div>
                    <?php if (!empty($u['profession'])): ?>
                    <div class="card-prof"><?= e($u['profession']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-divider"></div>
            <div class="card-fields">
                <div class="card-field">
                    <label>Matrícula</label>
                    <span><?= $u['matricula_apejese'] ? e($u['matricula_apejese']) : '—' ?></span>
                </div>
                <div class="card-field">
                    <label>CPF</label>
                    <span><?= $u['cpf'] ? e($u['cpf']) : '—' ?></span>
                </div>
                <div class="card-field">
                    <label>Nascimento</label>
                    <span><?= $nasc ?></span>
                </div>
                <?php if ($mostrarFiliacao && !empty($u['data_filiacao'])): ?>
                <div class="card-field">
                    <label>Filiação</label>
                    <span><?= $fil ?></span>
                </div>
                <?php endif; ?>
                <?php if ($mostrarRegistro && !empty($u['registro_profissional'])): ?>
                <div class="card-field" style="grid-column:span 2;">
                    <label>Registro Prof.</label>
                    <span><?= e($u['registro_profissional']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer-bar">
            <span class="situacao" style="background:<?= $corSit ?>;"><?= $situacao ?></span>
            <?php if ($validade): ?>
            <div class="validade">Val.<strong><?= e($validade) ?></strong></div>
            <?php endif; ?>
            <?php if ($qrUrl): ?>
            <div class="qr"><img src="<?= e($qrUrl) ?>" alt="QR" loading="lazy"></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
