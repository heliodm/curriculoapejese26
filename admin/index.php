<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$totalResumes    = (int)db()->query("SELECT COUNT(*) FROM resumes")->fetchColumn();
$totalCategories = (int)db()->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalUsers      = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalViews      = (int)db()->query("SELECT SUM(views) FROM resumes")->fetchColumn();

$recentResumes = db()->query("SELECT r.*, c.name AS category_name FROM resumes r LEFT JOIN categories c ON c.id = r.category_id ORDER BY r.created_at DESC LIMIT 8")->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

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
            <div class="stat-icon bg-secondary-light"><i class="bi bi-tags"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $totalCategories ?></div>
                <div class="stat-label">Categorias</div>
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

<!-- Recent Resumes -->
<div class="card admin-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2"></i>Currículos Recentes</span>
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
                        <th>Views</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentResumes as $r): ?>
                    <tr>
                        <td class="fw-medium"><?= e($r['name']) ?></td>
                        <td class="text-muted"><?= e($r['profession'] ?? '—') ?></td>
                        <td><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                        <td><?= $r['views'] ?></td>
                        <td>
                            <span class="badge <?= $r['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $r['active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" target="_blank"
                               class="btn btn-xs btn-outline-secondary me-1" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/admin/curriculos.php?acao=editar&id=<?= $r['id'] ?>"
                               class="btn btn-xs btn-outline-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
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

<?php include __DIR__ . '/includes/footer.php'; ?>
