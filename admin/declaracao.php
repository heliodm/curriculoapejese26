<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
// All authenticated users may access; admin-only actions checked below.

$isAdminUser = isAdmin();
$myUserId    = (int)$_SESSION['user_id'];

/* ── Portuguese month names (strftime + utf8_encode both deprecated in PHP 8.x) */
function ptMonth(int $month = 0): string {
    static $months = ['janeiro','fevereiro','março','abril','maio','junho',
                      'julho','agosto','setembro','outubro','novembro','dezembro'];
    $m = $month > 0 ? $month : (int)date('n');
    return $months[$m - 1] ?? '';
}

/* ── Modo impressão: output limpo sem admin chrome ─────────────────────── */
if (isset($_GET['imprimir']) && isset($_GET['user_id'])) {
    $uid = (int)$_GET['user_id'];
    if (!$isAdminUser && $uid !== $myUserId) {
        redirect(BASE_URL . '/admin/declaracao.php');
    }
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) { flash('warning', 'Usuário não encontrado.'); redirect(BASE_URL . '/admin/declaracao.php'); }

    $texto      = getSetting('declaracao_texto', '');
    $fundo      = getSetting('declaracao_fundo', '');
    $assinatura = getSetting('declaracao_assinatura', '');
    $assinante  = getSetting('declaracao_assinante', '');
    $cargo      = getSetting('declaracao_cargo', '');
    $local      = getSetting('declaracao_local', 'Aracaju/SE');
    $situacao   = ($user['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $nascFormatado = !empty($user['data_nascimento']) ? date('d/m/Y', strtotime($user['data_nascimento'])) : '';
    $dataExtenso   = date('d') . ' de ' . ptMonth() . ' de ' . date('Y');

    $vars = [
        '{nome}'      => $user['full_name'],
        '{cpf}'       => $user['cpf'] ?? '',
        '{matricula}' => $user['matricula_apejese'] ?? '',
        '{situacao}'  => $situacao,
        '{nasc}'      => $nascFormatado,
        '{data}'      => $dataExtenso,
        '{local}'     => $local,
    ];
    $textoRender = strtr($texto, $vars);
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Declaração — <?= e($user['full_name']) ?></title>
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
.page-wrap { margin-top: 55px; display: flex; justify-content: center; padding: 30px 20px 60px; }
.a4 {
    width: 210mm; min-height: 297mm; background: #fff; position: relative;
    overflow: hidden; padding: 25mm 20mm 20mm;
    box-shadow: 0 4px 30px rgba(0,0,0,.18);
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
    .a4 { box-shadow: none; width: 100%; min-height: auto; padding: 15mm 15mm 12mm; }
    @page { size: A4 portrait; margin: 0; }
}
</style>
</head>
<body>
<div class="print-bar">
    <button id="btnPrint">🖨 Imprimir / Salvar PDF</button>
    <a href="<?= BASE_URL ?>/admin/declaracao.php<?= $isAdminUser ? '?user_id=' . $uid : '' ?>">← Voltar</a>
    <span style="margin-left:auto;font-size:.85rem;opacity:.7;"><?= e($user['full_name']) ?></span>
</div>
<div class="page-wrap">
    <div class="a4">
        <?php if ($fundo): ?>
        <div class="a4-bg" style="background-image:url('<?= UPLOAD_URL . e($fundo) ?>')"></div>
        <?php endif; ?>
        <div class="decl-title">DECLARAÇÃO</div>
        <div class="decl-body"><?= e($textoRender) ?></div>
        <div class="decl-local"><?= e($local) ?>, <?= $dataExtenso ?></div>
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
</div>
<script nonce="<?= CSP_NONCE ?>">
document.getElementById('btnPrint').addEventListener('click', () => window.print());
</script>
</body>
</html>
<?php
    exit;
}

/* ── Salvar configurações (somente admin) ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdminUser) {
        flash('danger', 'Acesso negado. Apenas administradores podem alterar as configurações.');
        redirect(BASE_URL . '/admin/declaracao.php');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/declaracao.php');
    }
    saveSetting('declaracao_texto',     $_POST['declaracao_texto']    ?? '');
    saveSetting('declaracao_assinante', sanitize($_POST['declaracao_assinante'] ?? ''));
    saveSetting('declaracao_cargo',     sanitize($_POST['declaracao_cargo']     ?? ''));
    saveSetting('declaracao_local',     sanitize($_POST['declaracao_local']     ?? 'Aracaju/SE'));

    // Remove image files if checkboxes ticked
    if (!empty($_POST['remove_fundo'])) {
        $old = getSetting('declaracao_fundo');
        if ($old) deleteUpload($old);
        saveSetting('declaracao_fundo', '');
    }
    if (!empty($_POST['remove_assinatura'])) {
        $old = getSetting('declaracao_assinatura');
        if ($old) deleteUpload($old);
        saveSetting('declaracao_assinatura', '');
    }

    foreach (['declaracao_fundo' => 'decl_bg', 'declaracao_assinatura' => 'decl_sign'] as $key => $field) {
        if (!empty($_FILES[$field]['name'])) {
            $up = uploadFile($_FILES[$field], 'declaracao');
            if ($up) {
                $old = getSetting($key);
                if ($old) deleteUpload($old);
                saveSetting($key, $up);
            } else {
                flash('warning', 'Falha no upload. Verifique formato/tamanho.');
            }
        }
    }
    flash('success', 'Configurações salvas.');
    redirect(BASE_URL . '/admin/declaracao.php#config');
}

/* ── Dados ──────────────────────────────────────────────────────────────── */
$defaultTexto = "Declaramos, para os devidos fins, que {nome}, portador(a) do CPF nº {cpf}, inscrito(a) com a Matrícula APEJESE nº {matricula}, encontra-se na situação de ASSOCIADO {situacao} junto à Associação dos Peritos Judiciais do Estado de Sergipe – APEJESE.\n\nPor ser expressão da verdade, firmamos a presente declaração.";

$texto      = getSetting('declaracao_texto', $defaultTexto);
$assinante  = getSetting('declaracao_assinante', '');
$cargo      = getSetting('declaracao_cargo', '');
$local      = getSetting('declaracao_local', 'Aracaju/SE');
$fundo      = getSetting('declaracao_fundo', '');
$assinatura = getSetting('declaracao_assinatura', '');
$dataExtenso = date('d') . ' de ' . ptMonth() . ' de ' . date('Y');

if ($isAdminUser) {
    $userId = (int)($_GET['user_id'] ?? 0);
} else {
    $userId = $myUserId;
}

$selectedUser = null;
if ($userId > 0) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $selectedUser = $stmt->fetch();
}

if (!$isAdminUser && !$selectedUser) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$myUserId]);
    $selectedUser = $stmt->fetch();
}

$users = $isAdminUser
    ? db()->query("SELECT id, full_name, matricula_apejese, adimplente FROM users ORDER BY full_name ASC")->fetchAll()
    : [];

$pageTitle = 'Declaração de Associado';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-file-earmark-text me-2"></i>Declaração de Associado</h3>
    <div class="d-flex gap-2">
        <?php if (!$isAdminUser && $selectedUser): ?>
        <a href="?user_id=<?= $myUserId ?>&imprimir=1" target="_blank" class="btn btn-primary-custom">
            <i class="bi bi-download me-1"></i>Baixar / Imprimir PDF
        </a>
        <?php endif; ?>
        <?php if ($isAdminUser): ?>
        <a href="<?= BASE_URL ?>/admin/declaracao-lote.php" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimir Lote
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$isAdminUser): ?>
<!-- ── Visão do usuário regular ─────────────────────────────────────────── -->
<?php if ($selectedUser):
    $situacao = ($selectedUser['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
    $vars = [
        '{nome}'      => $selectedUser['full_name'],
        '{cpf}'       => $selectedUser['cpf'] ?? '',
        '{matricula}' => $selectedUser['matricula_apejese'] ?? '',
        '{situacao}'  => $situacao,
        '{nasc}'      => !empty($selectedUser['data_nascimento']) ? date('d/m/Y', strtotime($selectedUser['data_nascimento'])) : '',
        '{data}'      => $dataExtenso,
        '{local}'     => $local,
    ];
    $prev = strtr($texto, $vars);
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card admin-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-file-text me-1"></i>Sua Declaração</span>
                <a href="?user_id=<?= $myUserId ?>&imprimir=1" target="_blank"
                   class="btn btn-sm btn-primary-custom">
                    <i class="bi bi-printer me-1"></i>Imprimir / Salvar PDF
                </a>
            </div>
            <div class="card-body">
                <div class="decl-preview">
                    <div class="decl-preview-title">DECLARAÇÃO</div>
                    <div class="decl-preview-body"><?= e($prev) ?></div>
                    <div class="decl-preview-local"><?= e($local) ?>, <?= $dataExtenso ?></div>
                    <?php if ($assinante): ?>
                    <div class="decl-preview-sign">
                        <?php if ($assinatura): ?>
                        <img src="<?= UPLOAD_URL . e($assinatura) ?>" class="decl-preview-sign-img" alt="Assinatura">
                        <?php endif; ?>
                        <div class="decl-preview-sign-line"><?= e($assinante) ?></div>
                        <?php if ($cargo): ?><div class="decl-preview-sign-cargo"><?= e($cargo) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning">Não foi possível carregar sua declaração. Entre em contato com o administrador.</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Visão do administrador: lista + configurações ────────────────────── -->
<div class="row g-4">

    <div class="col-lg-8">

        <?php if ($selectedUser): ?>
        <div class="card admin-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-eye me-1"></i>Pré-visualização — <?= e($selectedUser['full_name']) ?></span>
                <a href="?user_id=<?= $userId ?>&imprimir=1" target="_blank"
                   class="btn btn-sm btn-primary-custom">
                    <i class="bi bi-printer me-1"></i>Imprimir / PDF
                </a>
            </div>
            <div class="card-body">
                <?php
                $situacao = ($selectedUser['adimplente'] ?? 1) ? 'ADIMPLENTE' : 'INADIMPLENTE';
                $vars = [
                    '{nome}'      => $selectedUser['full_name'],
                    '{cpf}'       => $selectedUser['cpf'] ?? '',
                    '{matricula}' => $selectedUser['matricula_apejese'] ?? '',
                    '{situacao}'  => $situacao,
                    '{nasc}'      => !empty($selectedUser['data_nascimento']) ? date('d/m/Y', strtotime($selectedUser['data_nascimento'])) : '',
                    '{data}'      => $dataExtenso,
                    '{local}'     => $local,
                ];
                $prev = strtr($texto, $vars);
                ?>
                <div class="decl-preview" id="declPreview">
                    <div class="decl-preview-title">DECLARAÇÃO</div>
                    <div class="decl-preview-body" id="declPreviewBody"><?= e($prev) ?></div>
                    <div class="decl-preview-local"><?= e($local) ?>, <?= $dataExtenso ?></div>
                    <?php if ($assinante): ?>
                    <div class="decl-preview-sign">
                        <?php if ($assinatura): ?>
                        <img src="<?= UPLOAD_URL . e($assinatura) ?>" class="decl-preview-sign-img" alt="Assinatura">
                        <?php endif; ?>
                        <div class="decl-preview-sign-line"><?= e($assinante) ?></div>
                        <?php if ($cargo): ?><div class="decl-preview-sign-cargo"><?= e($cargo) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <a href="<?= BASE_URL ?>/admin/declaracao.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar à lista
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card admin-card mb-4">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-arrow-down-circle fs-3 d-block mb-2 opacity-50"></i>
                Selecione um associado na lista abaixo para pré-visualizar a declaração.
            </div>
        </div>
        <?php endif; ?>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-people me-1"></i>Selecionar Associado</div>
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input type="text" class="form-control form-control-sm" id="filtroUsuario"
                           placeholder="Filtrar por nome ou matrícula…">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0" id="tblUsuarios">
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
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-braces me-1"></i>Variáveis disponíveis</div>
            <div class="card-body p-0">
                <?php foreach ([
                    '{nome}'      => 'Nome completo',
                    '{cpf}'       => 'CPF',
                    '{matricula}' => 'Matrícula APEJESE',
                    '{situacao}'  => 'ADIMPLENTE / INADIMPLENTE',
                    '{nasc}'      => 'Data de nascimento',
                    '{local}'     => 'Local configurado',
                    '{data}'      => 'Data de hoje por extenso',
                ] as $var => $desc): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <code class="small" style="color:var(--primary);"><?= $var ?></code>
                    <span class="text-muted small"><?= $desc ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card admin-card" id="config">
            <div class="card-header"><i class="bi bi-gear-fill me-1"></i>Configurações</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small" style="color:var(--primary);">Texto da declaração</label>
                        <textarea name="declaracao_texto" id="textoDecl" class="form-control"
                                  rows="8" style="font-size:.82rem;font-family:monospace;"><?= e($texto) ?></textarea>
                        <div class="form-text">Edite e veja o resultado na pré-visualização ao lado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small" style="color:var(--primary);">Local</label>
                        <input type="text" name="declaracao_local" class="form-control form-control-sm"
                               value="<?= e($local) ?>" placeholder="Aracaju/SE">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small" style="color:var(--primary);">Nome do Assinante</label>
                        <input type="text" name="declaracao_assinante" class="form-control form-control-sm"
                               value="<?= e($assinante) ?>" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small" style="color:var(--primary);">Cargo / Título</label>
                        <input type="text" name="declaracao_cargo" class="form-control form-control-sm"
                               value="<?= e($cargo) ?>" maxlength="100" placeholder="Presidente — APEJESE">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small" style="color:var(--primary);">
                            Imagem de Fundo <span class="fw-normal text-muted">(marca d'água)</span>
                        </label>
                        <?php if ($fundo): ?>
                        <div class="mb-2 d-flex align-items-center gap-3">
                            <img src="<?= UPLOAD_URL . e($fundo) ?>"
                                 style="max-width:80px;max-height:50px;border-radius:4px;border:1px solid #ddd;" alt="Fundo atual">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="remove_fundo" value="1"
                                       class="form-check-input" id="chkRemoveFundo">
                                <label class="form-check-label small text-danger fw-semibold" for="chkRemoveFundo">
                                    <i class="bi bi-trash3 me-1"></i>Remover
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="decl_bg" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small" style="color:var(--primary);">Assinatura</label>
                        <?php if ($assinatura): ?>
                        <div class="mb-2 d-flex align-items-center gap-3">
                            <img src="<?= UPLOAD_URL . e($assinatura) ?>"
                                 style="max-width:120px;max-height:50px;border-radius:4px;border:1px solid #ddd;" alt="Assinatura atual">
                            <div class="form-check mb-0">
                                <input type="checkbox" name="remove_assinatura" value="1"
                                       class="form-check-input" id="chkRemoveAssinatura">
                                <label class="form-check-label small text-danger fw-semibold" for="chkRemoveAssinatura">
                                    <i class="bi bi-trash3 me-1"></i>Remover
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="decl_sign" class="form-control form-control-sm" accept="image/*">
                        <div class="form-text">PNG com fundo transparente recomendado.</div>
                    </div>
                    <button type="submit" class="btn btn-primary-custom w-100 fw-bold">
                        <i class="bi bi-check2 me-1"></i>Salvar Configurações
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
(function () {
    // Table filter
    const filtro = document.getElementById('filtroUsuario');
    if (filtro) {
        filtro.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#tblUsuarios tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Live preview: textarea → preview body (raw template without var substitution)
    const textareaEl = document.getElementById('textoDecl');
    const previewEl  = document.getElementById('declPreviewBody');
    if (textareaEl && previewEl) {
        textareaEl.addEventListener('input', function () {
            previewEl.textContent = this.value;
        });
    }
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
