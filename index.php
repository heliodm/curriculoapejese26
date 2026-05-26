<?php
require_once __DIR__ . '/config/config.php';

$search   = sanitize($_GET['busca']   ?? '');
$catId    = (int)($_GET['categoria']  ?? 0);
$page     = max(1, (int)($_GET['pagina'] ?? 1));
$perPage  = 12;

$categories = getCategories();

$where  = ['r.active = 1'];
if (hasConsentColumn())    $where[] = 'r.consent = 1';
if (hasAdimplenteColumn()) $where[] = '(u.adimplente = 1 OR r.user_id IS NULL)';
$params = [];

if ($search !== '') {
    $where[]  = '(r.name LIKE ? OR r.profession LIKE ? OR r.about LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($catId > 0) {
    $where[]  = 'r.category_id = ?';
    $params[] = $catId;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$totalStmt = db()->prepare("SELECT COUNT(*) FROM resumes r LEFT JOIN users u ON u.id = r.user_id $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pag    = paginate($total, $perPage, $page);
$params[] = $perPage;
$params[] = $pag['offset'];

$stmt = db()->prepare("SELECT r.*, c.name AS category_name
    FROM resumes r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN users u ON u.id = r.user_id
    $whereSQL
    ORDER BY r.name ASC
    LIMIT ? OFFSET ?");
$stmt->execute($params);
$resumes = $stmt->fetchAll();

$pageTitle = getSetting('site_name', APP_NAME) . ' — Busca de Currículos';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Hero / Search -->
<section class="hero-section">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title"><?= e(getSetting('hero_title', 'Encontre Profissionais')) ?></h1>
            <p class="hero-subtitle"><?= e(getSetting('hero_subtitle', 'Pesquise currículos por nome ou categoria profissional')) ?></p>
            <form method="GET" action="<?= BASE_URL ?>/index.php" class="search-form">
                <div class="input-group input-group-lg search-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="busca" placeholder="Buscar por nome ou profissão…"
                           value="<?= e($search) ?>" autocomplete="off">
                    <?php if ($catId): ?>
                    <input type="hidden" name="categoria" value="<?= $catId ?>">
                    <?php endif; ?>
                    <button class="btn btn-secondary-custom" type="submit">Buscar</button>
                    <?php if ($search || $catId): ?>
                    <a class="btn btn-outline-light" href="<?= BASE_URL ?>/index.php">Limpar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Category Filters -->
<section class="categories-section py-3">
    <div class="container">
        <div class="category-pills d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?= BASE_URL ?>/index.php<?= $search ? '?busca=' . urlencode($search) : '' ?>"
               class="category-pill <?= $catId === 0 ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i>Todas
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= BASE_URL ?>/index.php?categoria=<?= $cat['id'] ?><?= $search ? '&busca=' . urlencode($search) : '' ?>"
               class="category-pill <?= $catId === $cat['id'] ? 'active' : '' ?>">
                <?= e($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Results -->
<main class="main-content py-4">
    <div class="container">

        <?= renderFlash() ?>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="text-muted mb-0">
                <?php if ($search || $catId): ?>
                    <i class="bi bi-funnel me-1"></i>
                    <?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                <?php else: ?>
                    <i class="bi bi-people me-1"></i>
                    <?= $total ?> currículo<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?>
                <?php endif; ?>
            </h6>
        </div>

        <?php if (empty($resumes)): ?>
        <div class="empty-state text-center py-5">
            <i class="bi bi-person-x display-1 text-muted"></i>
            <h4 class="mt-3 text-muted">Nenhum currículo encontrado</h4>
            <p class="text-muted">Tente buscar com outros termos ou limpe os filtros.</p>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-custom">Ver todos</a>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($resumes as $r): ?>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="resume-card h-100">
                    <div class="resume-card-photo">
                        <?php if ($r['photo']): ?>
                        <img src="<?= UPLOAD_URL . e($r['photo']) ?>" alt="<?= e($r['name']) ?>">
                        <?php else: ?>
                        <div class="resume-card-placeholder">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="resume-card-body">
                        <span class="resume-card-category"><?= e($r['category_name'] ?? '') ?></span>
                        <h5 class="resume-card-name"><?= e($r['name']) ?></h5>
                        <?php if ($r['profession']): ?>
                        <p class="resume-card-profession"><?= e($r['profession']) ?></p>
                        <?php endif; ?>
                        <?php if ($r['about']): ?>
                        <p class="resume-card-about"><?= e(truncate(strip_tags($r['about']), 90)) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="resume-card-footer">
                        <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" class="btn btn-primary-custom btn-sm w-100">
                            <i class="bi bi-eye me-1"></i>Ver Currículo
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pag['total_pages'] > 1): ?>
        <nav class="mt-5 d-flex justify-content-center" aria-label="Paginação">
            <ul class="pagination pagination-custom">
                <li class="page-item <?= $pag['current'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pag['current'] - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $pag['current'] - 2); $p <= min($pag['total_pages'], $pag['current'] + 2); $p++): ?>
                <li class="page-item <?= $p === $pag['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $pag['current'] >= $pag['total_pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pag['current'] + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
