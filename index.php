<?php
require_once __DIR__ . '/config/config.php';

$search   = sanitize($_GET['busca']    ?? '');
$catId    = (int)($_GET['categoria']   ?? 0);
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
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($catId > 0) {
    $where[]  = 'r.category_id = ?';
    $params[] = $catId;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$totalStmt = db()->prepare("SELECT COUNT(*) FROM resumes r LEFT JOIN users u ON u.id = r.user_id $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pag = paginate($total, $perPage, $page);
$params[] = $perPage;
$params[] = $pag['offset'];

$stmt = db()->prepare("SELECT r.*, c.name AS category_name
    FROM resumes r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN users u ON u.id = r.user_id
    $whereSQL ORDER BY r.name ASC LIMIT ? OFFSET ?");
$stmt->execute($params);
$resumes = $stmt->fetchAll();

$memberCount = 0;
try { $memberCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn(); }
catch (\Exception $e) {}

$pageTitle = getSetting('site_name', APP_NAME) . ' — Encontre Profissionais';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- ── Hero ────────────────────────────────────────────────── -->
<section class="hero-section">
    <div class="container">
        <div class="hero-inner text-center">

            <?php if ($memberCount > 0): ?>
            <div class="hero-eyebrow">
                <i class="bi bi-people-fill"></i>
                <?= number_format($memberCount) ?> associado<?= $memberCount !== 1 ? 's' : '' ?> cadastrado<?= $memberCount !== 1 ? 's' : '' ?>
            </div>
            <?php endif; ?>

            <h1 class="hero-title">
                <?php
                $heroTitle = getSetting('hero_title', 'Encontre Profissionais');
                $words = explode(' ', $heroTitle);
                if (count($words) >= 2) {
                    $last = array_pop($words);
                    echo e(implode(' ', $words)) . ' <span class="hero-accent">' . e($last) . '</span>';
                } else {
                    echo '<span class="hero-accent">' . e($heroTitle) . '</span>';
                }
                ?>
            </h1>
            <p class="hero-subtitle">
                <?= e(getSetting('hero_subtitle', 'Pesquise currículos por nome, profissão ou categoria')) ?>
            </p>

            <!-- Search -->
            <form method="GET" action="<?= BASE_URL ?>/index.php" class="hero-search" autocomplete="off">
                <div class="search-box">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" name="busca"
                           placeholder="Buscar por nome, profissão…"
                           value="<?= e($search) ?>">
                    <?php if ($catId): ?>
                    <input type="hidden" name="categoria" value="<?= $catId ?>">
                    <?php endif; ?>
                    <?php if ($search || $catId): ?>
                    <a href="<?= BASE_URL ?>/index.php" class="btn-clear">
                        <i class="bi bi-x-circle me-1"></i>Limpar
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn-search">
                        <i class="bi bi-search"></i>
                        <span>Buscar</span>
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- Wave divider -->
    <svg class="hero-wave" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 60" preserveAspectRatio="none">
        <path fill="#ffffff" d="M0,32 C360,60 1080,0 1440,32 L1440,60 L0,60 Z"/>
    </svg>
</section>

<!-- ── Category bar ─────────────────────────────────────────── -->
<div class="categories-bar">
    <div class="container">
        <div class="categories-scroll">
            <a href="<?= BASE_URL ?>/index.php<?= $search ? '?busca='.urlencode($search) : '' ?>"
               class="cat-pill <?= $catId === 0 ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3-gap"></i>Todos
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= BASE_URL ?>/index.php?categoria=<?= $cat['id'] ?><?= $search ? '&busca='.urlencode($search) : '' ?>"
               class="cat-pill <?= $catId === (int)$cat['id'] ? 'active' : '' ?>">
                <?= e($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Results ──────────────────────────────────────────────── -->
<main class="main-content py-4">
    <div class="container">

        <?= renderFlash() ?>

        <!-- Results header -->
        <div class="results-header">
            <div class="results-count">
                <?php if ($search || $catId): ?>
                    <i class="bi bi-funnel-fill" style="color:var(--secondary);"></i>
                    <strong><?= number_format($total) ?></strong> resultado<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
                    <?php if ($search): ?>
                    <span>para "<em><?= e($search) ?></em>"</span>
                    <?php endif; ?>
                    <?php if ($catId): ?>
                    <?php foreach ($categories as $cat): if ((int)$cat['id'] === $catId): ?>
                    <span class="filter-active-pill">
                        <?= e($cat['name']) ?>
                        <a href="<?= BASE_URL ?>/index.php<?= $search ? '?busca='.urlencode($search) : '' ?>" title="Remover filtro"><i class="bi bi-x"></i></a>
                    </span>
                    <?php endif; endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <i class="bi bi-people" style="color:var(--primary);"></i>
                    <strong><?= number_format($total) ?></strong> currículo<?= $total !== 1 ? 's' : '' ?> publicado<?= $total !== 1 ? 's' : '' ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($resumes)): ?>
        <!-- Empty state -->
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
            <h4>Nenhum currículo encontrado</h4>
            <p>Tente buscar com outros termos ou remova os filtros ativos.</p>
            <a href="<?= BASE_URL ?>/index.php" class="btn-primary-custom">
                <i class="bi bi-arrow-counterclockwise"></i>Ver todos
            </a>
        </div>
        <?php else: ?>

        <!-- Cards grid -->
        <div class="row g-3 g-md-4">
            <?php foreach ($resumes as $r): ?>
            <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                <article class="resume-card h-100">
                    <!-- Photo + overlay -->
                    <div class="resume-card-thumb">
                        <?php if ($r['photo']): ?>
                        <img src="<?= UPLOAD_URL . e($r['photo']) ?>" alt="<?= e($r['name']) ?>" loading="lazy">
                        <?php else: ?>
                        <div class="resume-card-placeholder"><i class="bi bi-person-fill"></i></div>
                        <?php endif; ?>
                        <?php if ($r['category_name']): ?>
                        <span class="resume-card-cat-badge"><?= e($r['category_name']) ?></span>
                        <?php endif; ?>
                        <div class="resume-card-overlay">
                            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" class="btn-view">
                                <i class="bi bi-eye"></i>Ver Currículo
                            </a>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="resume-card-body">
                        <h5 class="resume-card-name"><?= e($r['name']) ?></h5>
                        <?php if ($r['profession']): ?>
                        <p class="resume-card-profession"><?= e($r['profession']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="resume-card-footer">
                        <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" class="btn-ver">
                            <i class="bi bi-arrow-right"></i>Ver Currículo
                        </a>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pag['total_pages'] > 1): ?>
        <nav class="mt-5 d-flex justify-content-center" aria-label="Paginação">
            <div class="pagination-wrap">
                <a class="page-btn <?= $pag['current'] <= 1 ? 'disabled' : '' ?>"
                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pag['current'] - 1])) ?>"
                   aria-label="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php
                $from = max(1, $pag['current'] - 2);
                $to   = min($pag['total_pages'], $pag['current'] + 2);
                if ($from > 1): ?>
                <a class="page-btn wide" href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>">1</a>
                <?php if ($from > 2): ?><span class="page-btn disabled" style="border:none;width:auto;padding:0 4px;">…</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $from; $p <= $to; $p++): ?>
                <a class="page-btn wide <?= $p === $pag['current'] ? 'active' : '' ?>"
                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($to < $pag['total_pages']): ?>
                <?php if ($to < $pag['total_pages'] - 1): ?><span class="page-btn disabled" style="border:none;width:auto;padding:0 4px;">…</span><?php endif; ?>
                <a class="page-btn wide" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pag['total_pages']])) ?>"><?= $pag['total_pages'] ?></a>
                <?php endif; ?>
                <a class="page-btn <?= $pag['current'] >= $pag['total_pages'] ? 'disabled' : '' ?>"
                   href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pag['current'] + 1])) ?>"
                   aria-label="Próxima">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </nav>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
