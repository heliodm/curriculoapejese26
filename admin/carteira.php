<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
// All authenticated users may access; admin-only actions checked below.

$isAdminUser = isAdmin();
$myUserId    = (int)$_SESSION['user_id'];

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function loadUserWithResume(int $uid): array|false {
    $stmt = db()->prepare(
        "SELECT u.*, r.photo, r.profession, r.formation
         FROM users u
         LEFT JOIN resumes r ON r.user_id = u.id
         WHERE u.id = ?
         ORDER BY r.created_at DESC LIMIT 1"
    );
    $stmt->execute([$uid]);
    return $stmt->fetch();
}

/* ── Modo impressão ──────────────────────────────────────────────────────── */
if (isset($_GET['imprimir']) && isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    if (!$isAdminUser && $uid !== $myUserId) {
        redirect(BASE_URL . '/admin/carteira.php');
    }
    $u = loadUserWithResume($uid);
    if (!$u) { flash('warning', 'Usuário não encontrado.'); redirect(BASE_URL . '/admin/carteira.php'); }

    $validade  = getSetting('carteira_validade', '');
    $logoPath  = getSetting('logo', '');
    $logoUrl   = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
    $nascFormatado = !empty($u['data_nascimento']) ? date('d/m/Y', strtotime($u['data_nascimento'])) : '—';
    $photoUrl  = $u['photo'] ? UPLOAD_URL . $u['photo'] : null;
    $situacao  = ($u['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $corSit    = ($u['adimplente'] ?? 1) ? '#28a745' : '#dc3545';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Carteira — <?= e($u['full_name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { background: #e8e8e8; font-family: Arial, Helvetica, sans-serif; }
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
.page-wrap { margin-top: 55px; display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px 60px; }
.card-outer {
    width: 180mm; height: 270mm;
    display: flex; flex-direction: column;
    border-radius: 14px; overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,.28); background: #fff;
}
.card-header {
    background: linear-gradient(135deg, #1b3a6b 0%, #0f2347 100%);
    padding: 18px 20px 14px; display: flex; align-items: center; gap: 14px;
}
.card-header img.logo { height: 38px; width: auto; filter: brightness(0) invert(1); }
.card-header-text { color: #fff; line-height: 1.2; }
.card-header-text h1 { font-size: 11pt; font-weight: 800; letter-spacing: 1px; }
.card-header-text p  { font-size: 7.5pt; opacity: .8; margin-top: 2px; letter-spacing: .5px; text-transform: uppercase; }
.card-gold-bar { height: 5px; background: linear-gradient(90deg, #c9a227, #f0c845, #c9a227); }
.card-body { flex: 1; padding: 22px 22px 0; display: flex; flex-direction: column; }
.card-photo-row { display: flex; gap: 18px; margin-bottom: 18px; }
.card-photo {
    width: 90px; height: 110px; flex-shrink: 0;
    border-radius: 8px; overflow: hidden; border: 3px solid #1b3a6b;
    background: #dde3ee; display: flex; align-items: center; justify-content: center;
}
.card-photo img { width: 100%; height: 100%; object-fit: cover; }
.card-photo .no-photo { font-size: 38px; color: #b0bcd4; }
.card-info { flex: 1; display: flex; flex-direction: column; justify-content: center; }
.card-name { font-size: 11pt; font-weight: 800; color: #1b3a6b; line-height: 1.2; margin-bottom: 4px; }
.card-profession { font-size: 8.5pt; color: #555; margin-bottom: 2px; }
.card-formation  { font-size: 8pt; color: #777; }
.card-divider { height: 1px; background: linear-gradient(90deg,transparent,#c9a227,transparent); margin: 0 0 14px; }
.card-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; }
.card-field label { display: block; font-size: 6.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #888; margin-bottom: 1px; }
.card-field span  { font-size: 9pt; font-weight: 600; color: #222; }
.card-footer {
    background: linear-gradient(135deg, #1b3a6b 0%, #0f2347 100%);
    padding: 12px 22px; display: flex; align-items: center;
    justify-content: space-between; margin-top: auto;
}
.card-footer .situacao {
    font-size: 8pt; font-weight: 800; letter-spacing: 1px; color: #fff;
    background: <?= $corSit ?>; padding: 4px 12px; border-radius: 20px;
}
.card-footer .validade { font-size: 7.5pt; color: rgba(255,255,255,.7); text-align: right; }
.card-footer .validade strong { display: block; color: #f0c845; font-size: 9pt; }
@media print {
    html, body { background: #fff; }
    .print-bar { display: none !important; }
    .page-wrap { margin: 0; padding: 20mm 0; }
    .card-outer { width: 90mm; height: 135mm; border-radius: 8px; margin: 0 auto; }
    .card-header { padding: 10px 12px 8px; }
    .card-header img.logo { height: 22px; }
    .card-header-text h1 { font-size: 7pt; }
    .card-header-text p  { font-size: 5pt; }
    .card-body { padding: 12px 12px 0; }
    .card-photo-row { gap: 10px; margin-bottom: 10px; }
    .card-photo { width: 50px; height: 62px; border-radius: 5px; }
    .card-photo .no-photo { font-size: 22px; }
    .card-name { font-size: 7pt; }
    .card-profession { font-size: 6pt; }
    .card-formation  { font-size: 5.5pt; }
    .card-divider { margin: 0 0 8px; }
    .card-fields { gap: 5px 10px; }
    .card-field label { font-size: 4.5pt; }
    .card-field span  { font-size: 6pt; }
    .card-footer { padding: 7px 12px; }
    .card-footer .situacao { font-size: 5.5pt; padding: 2px 7px; }
    .card-footer .validade { font-size: 5pt; }
    .card-footer .validade strong { font-size: 6pt; }
    @page { size: A4 portrait; margin: 0; }
}
</style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">🖨 Imprimir / Salvar PDF</button>
    <a href="<?= BASE_URL ?>/admin/carteira.php<?= $isAdminUser ? '?user_id=' . $uid : '' ?>">← Voltar</a>
    <span style="margin-left:auto;font-size:.85rem;opacity:.7;"><?= e($u['full_name']) ?></span>
</div>
<div class="page-wrap">
    <div class="card-outer">
        <div class="card-header">
            <img src="<?= e($logoUrl) ?>" alt="Logo" class="logo" onerror="this.style.display='none'">
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
                    <img src="<?= e($photoUrl) ?>" alt="Foto">
                    <?php else: ?>
                    <span class="no-photo">👤</span>
                    <?php endif; ?>
                </div>
                <div class="card-info">
                    <div class="card-name"><?= e($u['full_name']) ?></div>
                    <?php if (!empty($u['profession'])): ?>
                    <div class="card-profession"><?= e($u['profession']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($u['formation'])): ?>
                    <div class="card-formation"><?= e(truncate($u['formation'], 60)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-divider"></div>
            <div class="card-fields">
                <div class="card-field">
                    <label>Matrícula APEJESE</label>
                    <span><?= $u['matricula_apejese'] ? e($u['matricula_apejese']) : '—' ?></span>
                </div>
                <div class="card-field">
                    <label>CPF</label>
                    <span><?= $u['cpf'] ? e($u['cpf']) : '—' ?></span>
                </div>
                <div class="card-field">
                    <label>Data de Nascimento</label>
                    <span><?= $nascFormatado ?></span>
                </div>
                <?php if ($validade): ?>
                <div class="card-field">
                    <label>Validade</label>
                    <span><?= e($validade) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer">
            <span class="situacao"><?= $situacao ?></span>
            <?php if ($validade): ?>
            <div class="validade">Válida até<strong><?= e($validade) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php
    exit;
}

/* ── Salvar configurações (somente admin) ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdminUser) {
        flash('danger', 'Acesso negado. Apenas administradores podem alterar as configurações.');
        redirect(BASE_URL . '/admin/carteira.php');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/carteira.php');
    }
    saveSetting('carteira_validade', sanitize($_POST['carteira_validade'] ?? ''));
    flash('success', 'Configurações salvas.');
    redirect(BASE_URL . '/admin/carteira.php');
}

/* ── Dados ──────────────────────────────────────────────────────────────── */
if ($isAdminUser) {
    $userId = (int)($_GET['user_id'] ?? 0);
} else {
    $userId = $myUserId;
}

$selectedUser = ($userId > 0) ? loadUserWithResume($userId) : null;
if (!$isAdminUser && !$selectedUser) {
    $selectedUser = loadUserWithResume($myUserId);
}

$users    = $isAdminUser
    ? db()->query("SELECT id, full_name, matricula_apejese, adimplente FROM users ORDER BY full_name ASC")->fetchAll()
    : [];
$validade = getSetting('carteira_validade', '');

$pageTitle = 'Carteira de Associado';
include __DIR__ . '/includes/header.php';

/* ── Mini-carteira helper: usada em preview na tela ─────────────────────── */
function renderMiniCard(array $u, string $validade, bool $large = false): void {
    $photoUrl  = $u['photo'] ? UPLOAD_URL . $u['photo'] : null;
    $nasc      = !empty($u['data_nascimento']) ? date('d/m/Y', strtotime($u['data_nascimento'])) : '—';
    $situacao  = ($u['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $corSit    = ($u['adimplente'] ?? 1) ? '#28a745' : '#dc3545';
    $w = $large ? '280px' : '200px';
    $scale = $large ? 1.4 : 1;
    ?>
    <div style="display:inline-block;width:<?= $w ?>;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.2);text-align:left;font-family:Arial,sans-serif;">
        <div style="background:linear-gradient(135deg,#1b3a6b,#0f2347);padding:<?= $large ? '14px 16px' : '10px 12px' ?>;display:flex;align-items:center;gap:8px;">
            <div style="color:#fff;line-height:1.2;">
                <div style="font-size:<?= $large ? '10pt' : '8pt' ?>;font-weight:800;letter-spacing:1px;">APEJESE</div>
                <div style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;opacity:.7;text-transform:uppercase;">Carteira de Associado</div>
            </div>
        </div>
        <div style="height:3px;background:linear-gradient(90deg,#c9a227,#f0c845,#c9a227);"></div>
        <div style="background:#fff;padding:<?= $large ? '14px 16px' : '10px 12px' ?>;">
            <div style="display:flex;gap:<?= $large ? '12px' : '8px' ?>;margin-bottom:<?= $large ? '12px' : '8px' ?>;">
                <div style="width:<?= $large ? '60px' : '42px' ?>;height:<?= $large ? '74px' : '52px' ?>;border-radius:5px;overflow:hidden;border:2px solid #1b3a6b;background:#dde3ee;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <?php if ($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <span style="font-size:<?= $large ? '26px' : '18px' ?>;">👤</span>
                    <?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:<?= $large ? '9pt' : '7pt' ?>;font-weight:800;color:#1b3a6b;line-height:1.2;margin-bottom:2px;"><?= e($u['full_name']) ?></div>
                    <?php if (!empty($u['profession'])): ?>
                    <div style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;color:#555;"><?= e($u['profession']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div style="height:1px;background:linear-gradient(90deg,transparent,#c9a227,transparent);margin-bottom:<?= $large ? '10px' : '7px' ?>;"></div>
            <div style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;color:#888;text-transform:uppercase;font-weight:700;margin-bottom:1px;">Matrícula</div>
            <div style="font-size:<?= $large ? '9pt' : '7pt' ?>;font-weight:600;color:#222;margin-bottom:<?= $large ? '7px' : '5px' ?>;"><?= $u['matricula_apejese'] ? e($u['matricula_apejese']) : '—' ?></div>
            <div style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;color:#888;text-transform:uppercase;font-weight:700;margin-bottom:1px;">CPF</div>
            <div style="font-size:<?= $large ? '9pt' : '7pt' ?>;font-weight:600;color:#222;margin-bottom:<?= $large ? '7px' : '5px' ?>;"><?= $u['cpf'] ? e($u['cpf']) : '—' ?></div>
            <div style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;color:#888;text-transform:uppercase;font-weight:700;margin-bottom:1px;">Nascimento</div>
            <div style="font-size:<?= $large ? '9pt' : '7pt' ?>;font-weight:600;color:#222;"><?= $nasc ?></div>
        </div>
        <div style="background:linear-gradient(135deg,#1b3a6b,#0f2347);padding:<?= $large ? '10px 16px' : '7px 12px' ?>;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:<?= $large ? '7pt' : '5.5pt' ?>;font-weight:800;letter-spacing:.5px;color:#fff;background:<?= $corSit ?>;padding:<?= $large ? '3px 10px' : '2px 7px' ?>;border-radius:10px;"><?= $situacao ?></span>
            <?php if ($validade): ?>
            <span style="font-size:<?= $large ? '6.5pt' : '5pt' ?>;color:rgba(255,255,255,.7);">Val. <strong style="color:#f0c845;"><?= e($validade) ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-credit-card-2-front me-2"></i>Carteira de Associado</h3>
    <?php if (!$isAdminUser && $selectedUser): ?>
    <a href="?user_id=<?= $myUserId ?>&imprimir=1" target="_blank" class="btn btn-primary-custom">
        <i class="bi bi-download me-1"></i>Baixar / Imprimir PDF
    </a>
    <?php endif; ?>
</div>

<?php if (!$isAdminUser): ?>
<!-- ── Visão do usuário regular ──────────────────────────────────────────── -->
<?php if ($selectedUser): ?>
<div class="row justify-content-center">
    <div class="col-lg-6 text-center">
        <div class="card admin-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-credit-card me-1"></i>Sua Carteira</span>
                <a href="?user_id=<?= $myUserId ?>&imprimir=1" target="_blank"
                   class="btn btn-sm btn-primary-custom">
                    <i class="bi bi-printer me-1"></i>Imprimir / Salvar PDF
                </a>
            </div>
            <div class="card-body py-4">
                <?php renderMiniCard($selectedUser, $validade, true); ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning">Não foi possível carregar sua carteira. Entre em contato com o administrador.</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Visão do administrador ────────────────────────────────────────────── -->
<div class="row g-4">
    <div class="col-lg-8">
        <?php if ($selectedUser): ?>
        <div class="card admin-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-credit-card me-1"></i><?= e($selectedUser['full_name']) ?></span>
                <a href="?user_id=<?= $userId ?>&imprimir=1" target="_blank"
                   class="btn btn-sm btn-primary-custom">
                    <i class="bi bi-printer me-1"></i>Imprimir / PDF
                </a>
            </div>
            <div class="card-body text-center py-4">
                <?php renderMiniCard($selectedUser, $validade, true); ?>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/admin/carteira.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar à lista
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-people me-1"></i>Selecionar Associado</div>
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="filtroCarteira"
                           placeholder="Filtrar por nome ou matrícula…">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0" id="tblCarteira">
                        <thead>
                            <tr><th>Nome</th><th>Matrícula</th><th>Situação</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="fw-medium"><?= e($u['full_name']) ?></td>
                                <td><code class="small"><?= $u['matricula_apejese'] ? e($u['matricula_apejese']) : '—' ?></code></td>
                                <td>
                                    <span class="badge <?= ($u['adimplente'] ?? 1) ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ($u['adimplente'] ?? 1) ? 'Adimplente' : 'Inadimplente' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="?user_id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary">
                                        <i class="bi bi-eye me-1"></i>Ver
                                    </a>
                                    <a href="?user_id=<?= $u['id'] ?>&imprimir=1" target="_blank"
                                       class="btn btn-xs btn-outline-secondary ms-1">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-gear-fill me-1"></i>Configurações</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label class="form-label" style="font-size:.83rem;font-weight:700;color:var(--primary);">
                            Validade da Carteira
                        </label>
                        <input type="text" name="carteira_validade" class="form-control form-control-sm"
                               value="<?= e($validade) ?>" placeholder="Ex: 31/12/2025" maxlength="20">
                        <div class="form-text" style="font-size:.73rem;">Exibida no rodapé da carteira.</div>
                    </div>
                    <div class="alert alert-info py-2 mb-3" style="font-size:.8rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Foto, profissão e formação são extraídos do currículo do associado.
                    </div>
                    <button type="submit" class="btn w-100"
                            style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:9px;font-size:.88rem;border:none;">
                        <i class="bi bi-check2 me-1"></i>Salvar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('filtroCarteira').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tblCarteira tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
