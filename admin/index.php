<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<?php if (isAdmin() || isEditor()): ?>
<?php
/* ══ VISÃO ADMINISTRADOR / EDITOR ══════════════════════════════════════ */
$totalResumes    = (int)db()->query("SELECT COUNT(*) FROM resumes")->fetchColumn();
$totalPublished  = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1 AND consent=1")->fetchColumn()
    : (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1")->fetchColumn();
$totalCategories = (int)db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUsers      = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalViews      = (int)(db()->query("SELECT SUM(views) FROM resumes")->fetchColumn() ?? 0);
$pendingConsent  = hasConsentColumn()
    ? (int)db()->query("SELECT COUNT(*) FROM resumes WHERE consent=0")->fetchColumn()
    : 0;

$recentResumes = db()->query(
    "SELECT r.*, c.name AS category_name
     FROM resumes r LEFT JOIN categories c ON c.id = r.category_id
     ORDER BY r.updated_at DESC LIMIT 8"
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
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-light"><i class="bi bi-file-person"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalResumes ?></div>
                <div class="stat-label">Currículos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-secondary-light"><i class="bi bi-globe"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalPublished ?></div>
                <div class="stat-label">Publicados</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-accent-light"><i class="bi bi-people"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-label">Usuários</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-green-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($totalViews) ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
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

<!-- Recentes -->
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
                        <th>Profissão</th>
                        <th>Categoria</th>
                        <th>Público</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentResumes as $r): ?>
                    <tr>
                        <td class="fw-medium"><?= e($r['name']) ?></td>
                        <td class="text-muted small"><?= e($r['profession'] ?? '—') ?></td>
                        <td><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                        <td>
                            <span class="badge <?= ($r['consent'] ?? 0) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= ($r['consent'] ?? 0) ? 'Autorizado' : 'Pendente' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $r['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $r['active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
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
$stmt->execute([$_SESSION['user_id']]);
$myResume = $stmt->fetch();

$uStmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->execute([$_SESSION['user_id']]);
$myUser = $uStmt->fetch();

$adimplente  = (int)($myUser['adimplente'] ?? 1);
$cardValidade = !empty($myUser['carteira_validade'])
    ? date('d/m/Y', strtotime($myUser['carteira_validade']))
    : getSetting('carteira_validade', '');

$photoUrl = $myUser['photo'] ? UPLOAD_URL . $myUser['photo'] : null;

// Detect incomplete profile data
$missingFields = [];
if (empty($myUser['matricula_apejese'])) $missingFields[] = 'Matrícula APEJESE';
if (empty($myUser['cpf']))               $missingFields[] = 'CPF';
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

<!-- Alertas ──────────────────────────────────────────────────── -->
<?php if (!$adimplente): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <span>
        Sua situação junto à APEJESE está como <strong>INADIMPLENTE</strong>.
        Entre em contato com a secretaria para regularizar.
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

<!-- Stats ────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Situação financeira -->
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left:3px solid <?= $adimplente ? '#28a745' : '#dc3545' ?>;">
            <div class="stat-icon <?= $adimplente ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-<?= $adimplente ? 'check-circle' : 'x-circle' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1rem;color:<?= $adimplente ? '#28a745' : '#dc3545' ?>;">
                    <?= $adimplente ? 'Adimplente' : 'Inadimplente' ?>
                </div>
                <div class="stat-label">Situação</div>
            </div>
        </div>
    </div>
    <!-- Visualizações -->
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $myResume ? number_format($myResume['views']) : '0' ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
    </div>
    <!-- Visibilidade pública -->
    <div class="col-6 col-md-3">
        <?php $isVisible = $myResume && $myResume['active'] && ($myResume['consent'] ?? 0) && $adimplente; ?>
        <div class="stat-card">
            <div class="stat-icon <?= $isVisible ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-globe<?= $isVisible ? '2' : '' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1rem;"><?= $isVisible ? 'Visível' : 'Oculto' ?></div>
                <div class="stat-label">Site público</div>
            </div>
        </div>
    </div>
    <!-- Validade da carteira -->
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-secondary-light"><i class="bi bi-credit-card"></i></div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:<?= $cardValidade ? '.9rem' : '1rem' ?>;">
                    <?= $cardValidade ? e($cardValidade) : '—' ?>
                </div>
                <div class="stat-label">Validade Carteira</div>
            </div>
        </div>
    </div>
</div>

<!-- Meu Currículo ────────────────────────────────────────────── -->
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
            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($myResume['slug']) ?>" target="_blank"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>Ver Público
            </a>
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

<!-- Meus Dados APEJESE ───────────────────────────────────────── -->
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
                <div class="fw-semibold"><?= $myUser['cpf'] ? e($myUser['cpf']) : '<span class="text-muted">—</span>' ?></div>
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
                <div class="fw-semibold"><?= $cardValidade ? e($cardValidade) : '<span class="text-muted">—</span>' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Atalhos: Declaração, Carteira e Perfil ───────────────────── -->
<div class="row g-3">
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/declaracao.php" class="text-decoration-none">
            <div class="stat-card" style="cursor:pointer;">
                <div class="stat-icon bg-primary-light"><i class="bi bi-file-earmark-text"></i></div>
                <div class="stat-info">
                    <div class="stat-value" style="font-size:1rem;">Declaração</div>
                    <div class="stat-label">Visualizar e baixar PDF</div>
                </div>
                <i class="bi bi-arrow-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/carteira.php" class="text-decoration-none">
            <div class="stat-card" style="cursor:pointer;">
                <div class="stat-icon bg-secondary-light"><i class="bi bi-credit-card-2-front"></i></div>
                <div class="stat-info">
                    <div class="stat-value" style="font-size:1rem;">Carteira</div>
                    <div class="stat-label">Visualizar e baixar PDF</div>
                </div>
                <i class="bi bi-arrow-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="<?= BASE_URL ?>/admin/perfil.php" class="text-decoration-none">
            <div class="stat-card" style="cursor:pointer;">
                <div class="stat-icon" style="background:var(--accent-light,#f3ede0);"><i class="bi bi-person-gear" style="color:var(--secondary);"></i></div>
                <div class="stat-info">
                    <div class="stat-value" style="font-size:1rem;">Meu Perfil</div>
                    <div class="stat-label">Editar dados e foto</div>
                </div>
                <i class="bi bi-arrow-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
