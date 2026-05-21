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
$totalPublished  = (int)db()->query("SELECT COUNT(*) FROM resumes WHERE active=1 AND consent=1")->fetchColumn();
$totalCategories = (int)db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUsers      = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalViews      = (int)(db()->query("SELECT SUM(views) FROM resumes")->fetchColumn() ?? 0);
$pendingConsent  = (int)db()->query("SELECT COUNT(*) FROM resumes WHERE consent=0")->fetchColumn();

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
?>

<div class="admin-page-header">
    <div>
        <h3><i class="bi bi-person-circle me-2"></i>Meu Painel</h3>
        <small class="text-muted">Bem-vindo, <?= e($_SESSION['full_name'] ?? '') ?>!</small>
    </div>
</div>

<?php if ($myResume): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-light"><i class="bi bi-eye"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $myResume['views'] ?></div>
                <div class="stat-label">Visualizações</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon <?= $myResume['active'] ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-circle<?= $myResume['active'] ? '-fill' : '' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1.1rem"><?= $myResume['active'] ? 'Ativo' : 'Inativo' ?></div>
                <div class="stat-label">Status admin</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon <?= ($myResume['consent'] ?? 0) ? 'bg-green-light' : 'bg-secondary-light' ?>">
                <i class="bi bi-shield-<?= ($myResume['consent'] ?? 0) ? 'check' : 'exclamation' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1.1rem"><?= ($myResume['consent'] ?? 0) ? 'Sim' : 'Não' ?></div>
                <div class="stat-label">Autorização</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon <?= ($myResume['active'] && ($myResume['consent'] ?? 0)) ? 'bg-green-light' : 'bg-accent-light' ?>">
                <i class="bi bi-globe"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value" style="font-size:1.1rem">
                    <?= ($myResume['active'] && ($myResume['consent'] ?? 0)) ? 'Visível' : 'Oculto' ?>
                </div>
                <div class="stat-label">Site público</div>
            </div>
        </div>
    </div>
</div>

<?php if (!($myResume['consent'] ?? 0)): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-shield-exclamation fs-5 flex-shrink-0"></i>
    <span>
        Você ainda não autorizou o uso da sua imagem e informações.
        <strong>Seu currículo não está visível no site público.</strong>
        Edite seu currículo, role até a seção de Autorização e marque o checkbox.
    </span>
</div>
<?php endif; ?>

<div class="card admin-card">
    <div class="card-header"><i class="bi bi-file-person me-1"></i>Meu Currículo</div>
    <div class="card-body d-flex align-items-center gap-4 flex-wrap">
        <?php if ($myResume['photo']): ?>
        <img src="<?= UPLOAD_URL . e($myResume['photo']) ?>" class="table-avatar"
             style="width:56px;border-radius:6px;">
        <?php endif; ?>
        <div class="flex-grow-1">
            <div class="fw-bold"><?= e($myResume['name']) ?></div>
            <div class="text-muted small"><?= e($myResume['profession'] ?? '') ?></div>
            <div><span class="badge bg-primary-soft"><?= e($myResume['category_name'] ?? '') ?></span></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $myResume['id'] ?>"
               class="btn btn-primary-custom btn-sm">
                <i class="bi bi-pencil me-1"></i>Editar Currículo
            </a>
            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($myResume['slug']) ?>" target="_blank"
               class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>Ver Público
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card admin-card text-center py-5">
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

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
