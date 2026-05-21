<?php
$siteName = getSetting('site_name', APP_NAME);
$logoPath = getSetting('logo');
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Painel') ?> — <?= e($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<nav class="admin-topbar navbar navbar-expand-lg">
    <div class="container-fluid px-3">
        <button class="btn btn-link sidebar-toggle me-2" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/index.php">
            <img src="<?= e($logoUrl) ?>" alt="" class="admin-logo" onerror="this.style.display='none'">
            <span><?= e($siteName) ?></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="btn btn-sm btn-outline-light" title="Ver site">
                <i class="bi bi-box-arrow-up-right me-1"></i><span class="d-none d-sm-inline">Ver Site</span>
            </a>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['full_name'] ?? '') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small"><?= e($_SESSION['username'] ?? '') ?> (<?= e($_SESSION['role'] ?? '') ?>)</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-main" id="adminMain">
        <div class="admin-content p-3 p-md-4">
