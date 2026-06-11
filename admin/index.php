<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$myUserId = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'user';

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<?php if (isAdmin()): ?>
<?php
/* ══ VISÃO ADMINISTRADOR ══════════════════════════════════════════════ */
$totalResumes   = (int)db()->query("SELECT COUNT(*) FROM resumes")->fetchColumn();
$totalPublished = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1 AND consent=1")->fetchColumn()
    : (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1")->fetchColumn();
$totalCategories = (int)db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUsers      = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalViews      = (int)(db()->query("SELECT SUM(views) FROM resumes")->fetchColumn() ?? 0);
$pendingConsent  = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE consent=0")->fetchColumn()
    : 0;
$adimplentes = $inadimplentes = null;
if (hasAdimplenteColumn()) {
    $adimplentes   = (int)db()->query("SELECT COUNT(*) FROM users WHERE adimplente=1 AND active=1")->fetchColumn();
    $inadimplentes = (int)db()->query("SELECT COUNT(*) FROM users WHERE adimplente=0 AND active=1")->fetchColumn();
}

$recentResumes = db()->query(
    "SELECT r.*, u.full_name AS owner_name, c.name AS category_name
     FROM resumes r
     LEFT JOIN categories c ON c.id = r.category_id
     LEFT JOIN users u ON u.id = r.user_id
     ORDER BY r.updated_at DESC LIMIT 8"
)->fetchAll();

$recentUsers = db()->query(
    "SELECT id, full_name, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5"
)->fetchAll();
?>

<div class="admin-page-header">
    <div>
        <h3><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
        <small class="text-muted">Bem-vindo, <?= e($_SESSION['full_name'] ?? '') ?>!</small>
    </div>
    <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="btn btn-primary-custom">
        <i class="bi bi-plus-lg me-1"></i>Novo Currículo
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="stat-card text-decoration-none">
            <div class="stat-icon bg-secondary-light"><i class="bi bi-file-person"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalResumes) ?></div>
                <div class="stat-label">Currículos</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/curriculos.php?filtro_status=ativo" class="stat-card stat-primary text-decoration-none">
            <div class="stat-icon bg-primary-light"><i class="bi bi-globe2"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalPublished) ?></div>
                <div class="stat-label">Publicados</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/categorias.php" class="stat-card stat-purple text-decoration-none">
            <div class="stat-icon bg-purple-light"><i class="bi bi-tags"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalCategories ?></div>
                <div class="stat-label">Categorias</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/usuarios.php" class="stat-card stat-accent text-decoration-none">
            <div class="stat-icon bg-accent-light"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Usuários</div>
            </div>
        </a>
    </div>
    <?php if ($adimplentes !== null): ?>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/usuarios.php" class="stat-card stat-green text-decoration-none">
            <div class="stat-icon bg-green-light"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $adimplentes ?></div>
                <div class="stat-label">
                    Adimplentes
                    <?php if ($inadimplentes > 0): ?>
                    <span class="badge bg-danger ms-1" style="font-size:.6rem;vertical-align:middle;"
                          title="<?= $inadimplentes ?> inadimplente<?= $inadimplentes > 1 ? 's' : '' ?>">
                        <?= $inadimplentes ?> inad.
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md">
        <div class="stat-card stat-teal">
            <div class="stat-icon bg-teal-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions (admin only) -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="quick-action-card">
            <i class="bi bi-person-plus-fill"></i>
            <span>Novo Currículo</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/usuarios.php?acao=novo" class="quick-action-card">
            <i class="bi bi-person-add"></i>
            <span>Novo Usuário</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/exportar.php" class="quick-action-card">
            <i class="bi bi-download"></i>
            <span>Exportar Dados</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/email-massa.php" class="quick-action-card">
            <i class="bi bi-envelope-paper"></i>
            <span>E-mail em Massa</span>
        </a>
    </div>
</div>

<?php if ($pendingConsent > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-shield-exclamation fs-5"></i>
    <span>
        <strong><?= $pendingConsent ?> currículo<?= $pendingConsent > 1 ? 's' : '' ?></strong>
        aguardando autorização de uso de imagem — não aparece<?= $pendingConsent > 1 ? 'm' : '' ?> publicamente.
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="alert-link ms-1">Ver lista</a>
    </span>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Recentes -->
    <div class="col-lg-8">
        <div class="card admin-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i>Atualizados Recentemente</span>
                <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th class="col-hide-md">Profissão</th>
                                <th class="col-hide-md">Categoria</th>
                                <th>Status</th>
                                <th>Atualizado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentResumes as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?= e($r['name']) ?></div>
                                    <?php if ($r['owner_name']): ?>
                                    <div class="text-muted" style="font-size:.72rem;"><?= e($r['owner_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small col-hide-md"><?= e($r['profession'] ?? '—') ?></td>
                                <td class="col-hide-md"><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                                <td>
                                    <?php
                                    $isPublic = $r['active'] && ($r['consent'] ?? 0);
                                    echo $isPublic
                                        ? '<span class="badge bg-success">Público</span>'
                                        : '<span class="badge bg-secondary">' . ($r['active'] ? 'Sem consent.' : 'Inativo') . '</span>';
                                    ?>
                                </td>
                                <td class="text-muted" style="font-size:.8rem;white-space:nowrap;">
                                    <?= !empty($r['updated_at']) ? date('d/m/Y', strtotime($r['updated_at'])) : '—' ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" target="_blank"
                                       class="btn btn-xs btn-outline-secondary me-1" title="Ver público"><i class="bi bi-eye"></i></a>
                                    <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $r['id'] ?>"
                                       class="btn btn-xs btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentResumes)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Nenhum currículo cadastrado</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Coluna lateral -->
    <div class="col-lg-4">
        <!-- Novos Usuários -->
        <div class="card admin-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-plus me-1"></i>Últimos Cadastros</span>
                <a href="<?= BASE_URL ?>/admin/usuarios.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentUsers)): ?>
                <p class="text-muted text-center py-3 mb-0" style="font-size:.85rem;">Nenhum usuário</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentUsers as $u): ?>
                    <li class="list-group-item px-3 py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-medium" style="font-size:.85rem;"><?= e($u['full_name']) ?></div>
                            <div class="text-muted" style="font-size:.72rem;">@<?= e($u['username']) ?></div>
                        </div>
                        <div class="text-muted" style="font-size:.72rem;white-space:nowrap;">
                            <?= !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : '—' ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Inadimplentes -->
        <?php if ($inadimplentes > 0): ?>
        <div class="card admin-card border-danger" style="border-top-color:var(--accent)!important;">
            <div class="card-header" style="color:var(--accent);">
                <i class="bi bi-exclamation-triangle me-1"></i>Inadimplentes
                <span class="badge bg-danger ms-1"><?= $inadimplentes ?></span>
            </div>
            <div class="card-body py-2" style="font-size:.85rem;">
                <p class="mb-2 text-muted">
                    <?= $inadimplentes ?> associado<?= $inadimplentes > 1 ? 's' : '' ?> com situação financeira irregular.
                </p>
                <a href="<?= BASE_URL ?>/admin/usuarios.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-people me-1"></i>Gerenciar usuários
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card admin-card" style="border-top-color:#198754;">
            <div class="card-body py-2 d-flex align-items-center gap-2" style="font-size:.85rem;color:#198754;">
                <i class="bi bi-check-circle-fill"></i>
                <span>Todos os associados adimplentes.</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif (isEditor()): ?>
<?php
/* ══ VISÃO EDITOR ════════════════════════════════════════════════════ */
$totalResumes   = (int)db()->query("SELECT COUNT(*) FROM resumes")->fetchColumn();
$totalPublished = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1 AND consent=1")->fetchColumn()
    : (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1")->fetchColumn();
$totalViews     = (int)(db()->query("SELECT SUM(views) FROM resumes")->fetchColumn() ?? 0);
$pendingConsent = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE consent=0")->fetchColumn()
    : 0;
$totalUsers = (int)db()->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
$editorAdimplentes = $editorInadimplentes = null;
if (hasAdimplenteColumn()) {
    $editorAdimplentes   = (int)db()->query("SELECT COUNT(*) FROM users WHERE adimplente=1 AND active=1")->fetchColumn();
    $editorInadimplentes = (int)db()->query("SELECT COUNT(*) FROM users WHERE adimplente=0 AND active=1")->fetchColumn();
}

$recentResumes = db()->query(
    "SELECT r.*, c.name AS category_name
     FROM resumes r LEFT JOIN categories c ON c.id = r.category_id
     ORDER BY r.updated_at DESC LIMIT 8"
)->fetchAll();
?>

<div class="admin-page-header">
    <div>
        <h3><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
        <small class="text-muted">Bem-vindo, <?= e($_SESSION['full_name'] ?? '') ?>! <span class="badge bg-secondary ms-1">Editor</span></small>
    </div>
    <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="btn btn-primary-custom">
        <i class="bi bi-plus-lg me-1"></i>Novo Currículo
    </a>
</div>

<!-- Stats editor -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="stat-card text-decoration-none">
            <div class="stat-icon bg-secondary-light"><i class="bi bi-file-person"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalResumes) ?></div>
                <div class="stat-label">Currículos</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/curriculos.php?filtro_status=ativo" class="stat-card stat-primary text-decoration-none">
            <div class="stat-icon bg-primary-light"><i class="bi bi-globe2"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalPublished) ?></div>
                <div class="stat-label">Publicados</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/usuarios.php" class="stat-card stat-accent text-decoration-none">
            <div class="stat-icon bg-accent-light"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div class="stat-label">Associados</div>
            </div>
        </a>
    </div>
    <?php if ($editorAdimplentes !== null): ?>
    <div class="col-6 col-md">
        <a href="<?= BASE_URL ?>/admin/usuarios.php" class="stat-card stat-green text-decoration-none">
            <div class="stat-icon bg-green-light"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $editorAdimplentes ?></div>
                <div class="stat-label">
                    Adimplentes
                    <?php if ($editorInadimplentes > 0): ?>
                    <span class="badge bg-danger ms-1" style="font-size:.6rem;vertical-align:middle;">
                        <?= $editorInadimplentes ?> inad.
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md">
        <div class="stat-card stat-teal">
            <div class="stat-icon bg-teal-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions editor (only accessible pages) -->
<div class="row g-2 mb-4">
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="quick-action-card">
            <i class="bi bi-person-plus-fill"></i>
            <span>Novo Currículo</span>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/admin/usuarios.php?acao=novo" class="quick-action-card">
            <i class="bi bi-person-add"></i>
            <span>Novo Usuário</span>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/admin/email-massa.php" class="quick-action-card">
            <i class="bi bi-envelope-paper"></i>
            <span>E-mail em Massa</span>
        </a>
    </div>
</div>

<?php if ($pendingConsent > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-shield-exclamation fs-5"></i>
    <span>
        <strong><?= $pendingConsent ?> currículo<?= $pendingConsent > 1 ? 's' : '' ?></strong>
        aguardando autorização de uso de imagem.
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="alert-link ms-1">Ver lista</a>
    </span>
</div>
<?php endif; ?>

<!-- Recentes (editor) -->
<div class="card admin-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i>Atualizados Recentemente</span>
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th class="col-hide-md">Profissão</th>
                        <th class="col-hide-md">Categoria</th>
                        <th>Status</th>
                        <th>Atualizado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentResumes as $r): ?>
                    <tr>
                        <td class="fw-medium"><?= e($r['name']) ?></td>
                        <td class="text-muted small col-hide-md"><?= e($r['profession'] ?? '—') ?></td>
                        <td class="col-hide-md"><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                        <td>
                            <?php $isPublic = $r['active'] && ($r['consent'] ?? 0); ?>
                            <span class="badge <?= $isPublic ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $isPublic ? 'Público' : ($r['active'] ? 'Sem consent.' : 'Inativo') ?>
                            </span>
                        </td>
                        <td class="text-muted" style="font-size:.8rem;white-space:nowrap;">
                            <?= !empty($r['updated_at']) ? date('d/m/Y', strtotime($r['updated_at'])) : '—' ?>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" target="_blank"
                               class="btn btn-xs btn-outline-secondary me-1"><i class="bi bi-eye"></i></a>
                            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $r['id'] ?>"
                               class="btn btn-xs btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentResumes)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum currículo cadastrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<?php
/* ══ VISÃO USUÁRIO COMUM ════════════════════════════════════════════════ */
$stmt = db()->prepare(
    "SELECT r.*, c.name AS category_name
     FROM resumes r LEFT JOIN categories c ON c.id = r.category_id
     WHERE r.user_id = ? LIMIT 1"
);
$stmt->execute([$myUserId]);
$myResume = $stmt->fetch();

$uStmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$myUserId]);
$myUser = $uStmt->fetch();

$adimplente   = (int)($myUser['adimplente'] ?? 1);
$cardValidade = !empty($myUser['carteira_validade'])
    ? date('d/m/Y', strtotime($myUser['carteira_validade']))
    : getSetting('carteira_validade', '');
$carteiraVencida = !empty($myUser['carteira_validade']) && strtotime($myUser['carteira_validade']) < time();
$photoUrl = $myUser['photo'] ? UPLOAD_URL . $myUser['photo'] : null;

$totpEnabled = !empty($myUser['totp_enabled']);

// CPF mascarado: mantém apenas os 2 últimos dígitos visíveis — ***.***.***-XX
function maskCpf(string $cpf): string {
    $digits = preg_replace('/\D/', '', $cpf);
    if (strlen($digits) === 11) {
        return '***.***.***-' . substr($digits, 9, 2);
    }
    return '***' . substr($cpf, -2);
}

$missingFields = [];
if (empty($myUser['matricula_apejese'])) $missingFields[] = 'Matrícula APEJESE';
if (empty($myUser['cpf']))               $missingFields[] = 'CPF';

// Itens de completude: separa os que o usuário pode preencher dos que dependem da secretaria
$completionUser = [
    'Foto de perfil'         => !empty($myUser['photo']),
    'Currículo criado'       => !empty($myResume),
    'Profissão'              => !empty($myResume['profession']),
    'Sobre mim'              => !empty($myResume['about']),
    'Foto no currículo'      => !empty($myResume['photo']),
    'Contato (tel/WhatsApp)' => !empty($myResume['phone']) || !empty($myResume['whatsapp']),
];
$completionAdmin = [
    'Matrícula APEJESE' => !empty($myUser['matricula_apejese']),
    'CPF'               => !empty($myUser['cpf']),
];
$completionAll = array_merge($completionUser, $completionAdmin);
$completionDone  = count(array_filter($completionAll));
$completionTotal = count($completionAll);
$completionPct   = (int)round($completionDone / $completionTotal * 100);
?>

<div class="admin-page-header">
    <div class="d-flex align-items-center gap-3">
        <?php if ($photoUrl): ?>
        <img src="<?= e($photoUrl) ?>" alt="Foto"
             style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);flex-shrink:0;">
        <?php endif; ?>
        <div>
            <h3 class="mb-0"><i class="bi bi-person-circle me-2"></i>Meu Painel</h3>
            <small class="text-muted">Bem-vindo(a), <?= e($_SESSION['full_name'] ?? '') ?>!</small>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/perfil.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-gear me-1"></i>Meu Perfil
    </a>
</div>

<!-- Alertas -->
<?php if (!$adimplente): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <span>
        Sua situação junto à APEJESE está como <strong>INADIMPLENTE</strong>.
        Entre em contato com a secretaria para regularizar.
    </span>
</div>
<?php endif; ?>

<?php if ($carteiraVencida): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-credit-card fs-5 flex-shrink-0"></i>
    <span>
        Sua carteira de associado venceu em <strong><?= e($cardValidade) ?></strong>.
        Entre em contato com a secretaria para a renovação.
    </span>
</div>
<?php endif; ?>

<?php if (!($myResume['consent'] ?? 0) && $myResume): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-shield-exclamation fs-5 flex-shrink-0"></i>
    <span>
        Você ainda não autorizou o uso da sua imagem e informações.
        <strong>Seu currículo não está visível no site público.</strong>
        <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $myResume['id'] ?>" class="alert-link ms-1">Editar currículo</a>
    </span>
</div>
<?php endif; ?>

<?php if (!empty($missingFields)): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3" style="font-size:.88rem;">
    <i class="bi bi-info-circle fs-5 flex-shrink-0"></i>
    <span>
        Dados cadastrais incompletos: <strong><?= implode(', ', $missingFields) ?></strong>.
        Entre em contato com a secretaria para atualizar seu cadastro.
    </span>
</div>
<?php endif; ?>

<?php if (!$totpEnabled): ?>
<div class="alert alert-secondary d-flex align-items-center gap-2 mb-3" style="font-size:.88rem;border-left:3px solid var(--primary);">
    <i class="bi bi-shield-lock fs-5 flex-shrink-0" style="color:var(--primary);"></i>
    <span>
        Reforce a segurança da sua conta ativando a
        <strong>autenticação em duas etapas (2FA)</strong>.
        <a href="<?= BASE_URL ?>/admin/perfil.php#totp" class="alert-link ms-1">Configurar agora</a>
    </span>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-top-color:<?= $adimplente ? '#198754' : 'var(--accent)' ?>;">
            <div class="stat-icon <?= $adimplente ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-<?= $adimplente ? 'check-circle' : 'x-circle' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1rem;color:<?= $adimplente ? '#198754' : 'var(--accent)' ?>;">
                    <?= $adimplente ? 'Adimplente' : 'Inadimplente' ?>
                </div>
                <div class="stat-label">Situação</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card stat-primary">
            <div class="stat-icon bg-primary-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $myResume ? number_format($myResume['views']) : '0' ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <?php $isVisible = $myResume && $myResume['active'] && ($myResume['consent'] ?? 0) && $adimplente; ?>
        <div class="stat-card" style="border-top-color:<?= $isVisible ? '#198754' : 'var(--accent)' ?>;">
            <div class="stat-icon <?= $isVisible ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-globe<?= $isVisible ? '2' : '' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1rem;"><?= $isVisible ? 'Visível' : 'Oculto' ?></div>
                <div class="stat-label">Site público</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-top-color:<?= $carteiraVencida ? 'var(--accent)' : 'var(--secondary)' ?>;">
            <div class="stat-icon <?= $carteiraVencida ? 'bg-accent-light' : 'bg-purple-light' ?>">
                <i class="bi bi-credit-card<?= $carteiraVencida ? '' : '-2-front' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:<?= $cardValidade ? '.9rem' : '1rem' ?>;<?= $carteiraVencida ? 'color:var(--accent);' : '' ?>">
                    <?= $cardValidade ? e($cardValidade) : '—' ?>
                    <?= $carteiraVencida ? ' <span style="font-size:.65rem;">(vencida)</span>' : '' ?>
                </div>
                <div class="stat-label">Validade Carteira</div>
            </div>
        </div>
    </div>
</div>

<!-- Completude do Perfil -->
<div class="card admin-card mb-4">
    <div class="card-header">
        <i class="bi bi-patch-check me-1"></i>Completude do Perfil
        <span class="ms-auto fw-normal text-muted" style="font-size:.8rem;"><?= $completionPct ?>%</span>
    </div>
    <div class="card-body">
        <div class="completion-bar-wrap">
            <div class="completion-bar-fill <?= $completionPct >= 100 ? 'complete' : '' ?>"
                 style="width:<?= $completionPct ?>%"></div>
        </div>
        <?php if (!empty($completionUser)): ?>
        <p class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;font-weight:700;letter-spacing:.04em;">Você pode preencher</p>
        <div class="row g-1 mb-2">
            <?php foreach ($completionUser as $label => $done): ?>
            <div class="col-6 col-md-3">
                <div class="completion-item <?= $done ? 'done' : '' ?>">
                    <i class="bi bi-<?= $done ? 'check-circle-fill' : 'circle' ?>"></i>
                    <span><?= $label ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <p class="text-muted mb-1 mt-2" style="font-size:.72rem;text-transform:uppercase;font-weight:700;letter-spacing:.04em;">Preenchido pela secretaria</p>
        <div class="row g-1 mb-2">
            <?php foreach ($completionAdmin as $label => $done): ?>
            <div class="col-6 col-md-3">
                <div class="completion-item <?= $done ? 'done' : 'secretaria' ?>">
                    <i class="bi bi-<?= $done ? 'check-circle-fill' : 'building' ?>"></i>
                    <span><?= $label ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($completionPct < 100): ?>
        <div class="d-flex gap-2 flex-wrap mt-1">
            <a href="<?= BASE_URL ?>/admin/perfil.php" class="btn btn-xs btn-outline-primary">
                <i class="bi bi-person-gear me-1"></i>Editar Perfil
            </a>
            <?php if (!$myResume): ?>
            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="btn btn-xs btn-primary-custom">
                <i class="bi bi-plus me-1"></i>Criar Currículo
            </a>
            <?php elseif (!empty($myResume['id'])): ?>
            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $myResume['id'] ?>" class="btn btn-xs btn-primary-custom">
                <i class="bi bi-pencil me-1"></i>Completar Currículo
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Meu Currículo -->
<?php if ($myResume): ?>
<div class="card admin-card mb-4">
    <div class="card-header"><i class="bi bi-file-person me-1"></i>Meu Currículo</div>
    <div class="card-body d-flex align-items-center gap-4 flex-wrap">
        <?php if ($myResume['photo']): ?>
        <img src="<?= UPLOAD_URL . e($myResume['photo']) ?>" class="table-avatar"
             style="width:56px;height:56px;border-radius:8px;object-fit:cover;">
        <?php endif; ?>
        <div class="flex-grow-1">
            <div class="fw-bold"><?= e($myResume['name']) ?></div>
            <div class="text-muted small"><?= e($myResume['profession'] ?? '') ?></div>
            <div class="mt-1 d-flex gap-2 flex-wrap">
                <span class="badge bg-primary-soft"><?= e($myResume['category_name'] ?? '') ?></span>
                <span class="badge <?= $myResume['active'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $myResume['active'] ? 'Ativo' : 'Inativo' ?>
                </span>
                <span class="badge <?= ($myResume['consent'] ?? 0) ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <i class="bi bi-shield-<?= ($myResume['consent'] ?? 0) ? 'check' : 'exclamation' ?> me-1"></i>
                    <?= ($myResume['consent'] ?? 0) ? 'Autorizado' : 'Autorização pendente' ?>
                </span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $myResume['id'] ?>"
               class="btn btn-primary-custom btn-sm">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
            <?php if ($isVisible): ?>
            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($myResume['slug']) ?>" target="_blank"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>Ver Público
            </a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($myResume['slug']) ?>" target="_blank"
               class="btn btn-outline-secondary btn-sm" title="Visível apenas para você — não publicado">
                <i class="bi bi-eye-slash me-1"></i>Visualizar (privado)
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card admin-card text-center py-5 mb-4">
    <div class="card-body">
        <i class="bi bi-file-person display-1" style="color:var(--secondary)"></i>
        <h5 class="mt-3">Você ainda não tem um currículo</h5>
        <p class="text-muted">Crie seu currículo agora para aparecer no site público.</p>
        <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=novo" class="btn btn-primary-custom">
            <i class="bi bi-plus-lg me-1"></i>Criar Meu Currículo
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Meus Dados APEJESE -->
<div class="card admin-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-card-list me-1"></i>Meus Dados APEJESE</span>
        <a href="<?= BASE_URL ?>/admin/perfil.php" class="btn btn-xs btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Editar Perfil
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Matrícula APEJESE</div>
                <div class="fw-semibold"><?= $myUser['matricula_apejese'] ? e($myUser['matricula_apejese']) : '<span class="text-muted">—</span>' ?></div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">CPF</div>
                <div class="fw-semibold">
                    <?= $myUser['cpf'] ? maskCpf($myUser['cpf']) : '<span class="text-muted">—</span>' ?>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Data de Nascimento</div>
                <div class="fw-semibold">
                    <?= !empty($myUser['data_nascimento']) ? date('d/m/Y', strtotime($myUser['data_nascimento'])) : '<span class="text-muted">—</span>' ?>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Data de Filiação</div>
                <div class="fw-semibold">
                    <?= !empty($myUser['data_filiacao']) ? date('d/m/Y', strtotime($myUser['data_filiacao'])) : '<span class="text-muted">—</span>' ?>
                </div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Registro Profissional</div>
                <div class="fw-semibold"><?= $myUser['registro_profissional'] ? e($myUser['registro_profissional']) : '<span class="text-muted">—</span>' ?></div>
            </div>
            <div class="col-sm-6 col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700;">Validade da Carteira</div>
                <div class="fw-semibold <?= $carteiraVencida ? 'text-danger' : '' ?>">
                    <?= $cardValidade ? e($cardValidade) : '<span class="text-muted">—</span>' ?>
                    <?= $carteiraVencida ? ' <span class="badge bg-danger" style="font-size:.6rem;">Vencida</span>' : '' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Atalhos -->
<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/declaracao.php" class="quick-action-card">
            <i class="bi bi-file-earmark-text" style="color:var(--primary);"></i>
            <span>Minha Declaração</span>
            <small class="text-muted" style="font-size:.7rem;">Visualizar e baixar PDF</small>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/carteira.php" class="quick-action-card">
            <i class="bi bi-credit-card-2-front" style="color:#9a7a18;"></i>
            <span>Minha Carteira</span>
            <small class="text-muted" style="font-size:.7rem;">Visualizar e baixar PDF</small>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/perfil.php" class="quick-action-card">
            <i class="bi bi-person-gear" style="color:var(--primary);"></i>
            <span>Meu Perfil</span>
            <small class="text-muted" style="font-size:.7rem;">Editar dados e foto</small>
        </a>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
