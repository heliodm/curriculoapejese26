<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$texto      = getSetting('declaracao_texto', '');
$fundo      = getSetting('declaracao_fundo', '');
$assinatura = getSetting('declaracao_assinatura', '');
$assinante  = getSetting('declaracao_assinante', '');
$cargo      = getSetting('declaracao_cargo', '');
$local      = getSetting('declaracao_local', 'Aracaju/SE');

$filtroAdimplente = sanitize($_GET['adimplente'] ?? 'todos');
$whereAdimplente  = '';
if ($filtroAdimplente === 'adimplentes')   $whereAdimplente = ' AND (adimplente = 1 OR adimplente IS NULL)';
if ($filtroAdimplente === 'inadimplentes') $whereAdimplente = ' AND adimplente = 0';

$users = db()->query(
    "SELECT id, full_name, cpf, matricula_apejese, adimplente, data_nascimento
     FROM users WHERE active = 1{$whereAdimplente} ORDER BY full_name ASC"
)->fetchAll();

// Use IntlDateFormatter for month name if available, else fallback
function mesExt(): string {
    $meses = ['janeiro','fevereiro','março','abril','maio','junho',
              'julho','agosto','setembro','outubro','novembro','dezembro'];
    return $meses[(int)date('n') - 1];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Declarações em Lote — APEJESE</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #f0f0f0; font-family: 'Times New Roman', Times, serif; }
.print-bar {
    background: #1b3a6b; color: #fff; padding: 10px 20px;
    display: flex; align-items: center; gap: 12px;
    position: fixed; top: 0; left: 0; right: 0; z-index: 99;
}
.print-bar button {
    background: #c9a227; color: #fff; border: none; padding: 7px 20px;
    border-radius: 6px; font-size: .9rem; font-weight: 700; cursor: pointer;
}
.print-bar a { color: #ccc; text-decoration: none; font-size: .85rem; }
.page-wrap { margin-top: 55px; padding: 20px 20px 60px; }
.a4 {
    width: 210mm; min-height: 297mm; background: #fff; position: relative;
    overflow: hidden; padding: 25mm 20mm 20mm;
    box-shadow: 0 4px 30px rgba(0,0,0,.18);
    margin: 0 auto 20px;
}
.a4-bg {
    position: absolute; inset: 0; background-size: cover;
    background-position: center; opacity: .08; pointer-events: none;
}
.decl-title {
    text-align: center; font-size: 16pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: 4px; margin-bottom: 30px;
    color: #1b3a6b; border-bottom: 2px solid #1b3a6b; padding-bottom: 10px;
}
.decl-body { font-size: 12pt; line-height: 1.9; text-align: justify; color: #222; white-space: pre-wrap; }
.decl-local { text-align: center; margin-top: 40px; font-size: 11pt; color: #444; }
.decl-sign { text-align: center; margin-top: 55px; }
.decl-sign img { max-width: 200px; max-height: 80px; display: block; margin: 0 auto 6px; }
.decl-sign-line { display: inline-block; min-width: 220px; border-top: 1px solid #333; padding-top: 6px; font-size: 11pt; }
.decl-sign-cargo { font-size: 10pt; color: #555; margin-top: 3px; }
@media print {
    html, body { background: #fff; }
    .print-bar { display: none !important; }
    .page-wrap { margin: 0; padding: 0; }
    .a4 { box-shadow: none; width: 100%; min-height: auto; padding: 15mm 15mm 12mm; margin: 0; }
    .a4 + .a4 { page-break-before: always; }
    @page { size: A4 portrait; margin: 0; }
}
</style>
</head>
<body>
<div class="print-bar">
    <button id="btnPrint">🖨 Imprimir / Salvar PDF</button>
    <a href="<?= BASE_URL ?>/admin/declaracao.php">← Voltar</a>
    <span style="margin-left:8px;">
        <select id="filtroAdimplente"
                style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);padding:4px 8px;border-radius:4px;font-size:.8rem;">
            <option value="todos"         <?= $filtroAdimplente==='todos'         ? 'selected':'' ?>>Todos</option>
            <option value="adimplentes"   <?= $filtroAdimplente==='adimplentes'   ? 'selected':'' ?>>Adimplentes</option>
            <option value="inadimplentes" <?= $filtroAdimplente==='inadimplentes' ? 'selected':'' ?>>Inadimplentes</option>
        </select>
    </span>
    <span style="margin-left:auto;opacity:.7;font-size:.85rem;"><?= count($users) ?> declaração(ões)</span>
</div>
<div class="page-wrap">
<?php foreach ($users as $user):
    $situacao  = ($user['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $nascFmt   = !empty($user['data_nascimento']) ? date('d/m/Y', strtotime($user['data_nascimento'])) : '';
    $vars = [
        '{nome}'      => $user['full_name'],
        '{cpf}'       => $user['cpf'] ?? '',
        '{matricula}' => $user['matricula_apejese'] ?? '',
        '{situacao}'  => $situacao,
        '{nasc}'      => $nascFmt,
        '{data}'      => date('d \d\e') . ' ' . mesExt() . ' de ' . date('Y'),
        '{local}'     => $local,
    ];
    $textoRender = strtr($texto, $vars);
?>
    <div class="a4">
        <?php if ($fundo): ?>
        <div class="a4-bg" style="background-image:url('<?= UPLOAD_URL . e($fundo) ?>')"></div>
        <?php endif; ?>
        <div class="decl-title">DECLARAÇÃO</div>
        <div class="decl-body"><?= e($textoRender) ?></div>
        <div class="decl-local">
            <?= e($local) ?>, <?= date('d') ?> de <?= mesExt() ?> de <?= date('Y') ?>
        </div>
        <div class="decl-sign">
            <?php if ($assinatura): ?>
            <img src="<?= UPLOAD_URL . e($assinatura) ?>" alt="Assinatura">
            <?php endif; ?>
            <div class="decl-sign-line"><?= e($assinante) ?></div>
            <?php if ($cargo): ?>
            <div class="decl-sign-cargo"><?= e($cargo) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<script nonce="<?= CSP_NONCE ?>">
document.getElementById('btnPrint').addEventListener('click', function () { window.print(); });
var filtro = document.getElementById('filtroAdimplente');
if (filtro) filtro.addEventListener('change', function () { location.href = '?adimplente=' + this.value; });
</script>
</body>
</html>
