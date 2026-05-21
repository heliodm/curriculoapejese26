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
    <meta name="description" content="<?= e(getSetting('site_description', 'Sistema de Currículos')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/index.php">
                <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" class="header-logo" onerror="this.style.display='none'">
                <span class="brand-name"><?= e($siteName) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['full_name'] ?? '') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-speedometer2 me-2"></i>Painel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-secondary-custom ms-2" href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-lock me-1"></i>Área Admin
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
