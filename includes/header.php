<?php
$siteName  = getSetting('site_name', APP_NAME);
$logoPath  = getSetting('logo');
$logoUrl   = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
$menuItems = getMenuItems();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? $siteName) ?></title>
    <meta name="description" content="<?= e($ogDescription ?? getSetting('site_description', 'Sistema de Currículos')) ?>">
    <!-- Open Graph -->
    <meta property="og:type"        content="<?= e($ogType ?? 'website') ?>">
    <meta property="og:title"       content="<?= e($ogTitle ?? $pageTitle ?? $siteName) ?>">
    <meta property="og:description" content="<?= e($ogDescription ?? getSetting('site_description', 'Sistema de Currículos')) ?>">
    <meta property="og:url"         content="<?= e($ogUrl ?? BASE_URL . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image"       content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header" id="siteHeader">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" class="header-logo" onerror="this.style.display='none'">
                <span class="brand-name"><?= e($siteName) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <?php foreach ($menuItems as $item): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e($item['url']) ?>" target="<?= e($item['target']) ?>">
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                            <span class="d-flex align-items-center justify-content-center rounded-circle"
                                  style="width:28px;height:28px;background:rgba(255,255,255,.18);font-size:.85rem;">
                                <i class="bi bi-person-fill"></i>
                            </span>
                            <span><?= e($_SESSION['full_name'] ?? '') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">
                                <i class="bi bi-speedometer2 me-2 text-primary"></i>Painel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="<?= BASE_URL ?>/logout.php" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-secondary-custom" href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-lock me-1"></i>Área do Associado
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="header-gold-line"></div>
</header>

<script nonce="<?= CSP_NONCE ?>">
(function () {
    var header = document.getElementById('siteHeader');
    function onScroll() {
        if (window.scrollY > 20) header.classList.add('scrolled');
        else header.classList.remove('scrolled');
    }
    window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
