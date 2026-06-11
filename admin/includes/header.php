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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    <?php if (!empty($pageExtraHead)) echo $pageExtraHead; ?>
</head>
<body class="admin-body">

<nav class="admin-topbar navbar navbar-expand-lg">
    <div class="container-fluid px-3">
        <button class="btn btn-link sidebar-toggle me-2" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/index.php">
            <img src="<?= e($logoUrl) ?>" alt="" class="admin-logo" data-hide-on-error>
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
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/perfil.php"><i class="bi bi-person-gear me-2"></i>Meu Perfil</a></li>
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
            </div>
        </div>
    </div>
</nav>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-main" id="adminMain">
        <div class="admin-content p-3 p-md-4">
<?php
$_breadcrumbMap = [
    'index.php'         => 'Dashboard',
    'curriculos.php'    => 'Currículos',
    'declaracao.php'    => 'Declaração',
    'carteira.php'      => 'Carteira',
    'categorias.php'    => 'Categorias',
    'usuarios.php'      => 'Usuários',
    'configuracoes.php' => 'Configurações',
    'atualizacao.php'   => 'Atualizações',
    'exportar.php'      => 'Exportar',
    'logs.php'          => 'Logs',
    'perfil.php'        => 'Meu Perfil',
    'email-massa.php'   => 'E-mail em Massa',
    'carteira-lote.php' => 'Carteiras em Lote',
    'declaracao-lote.php' => 'Declarações em Lote',
];
$_cf  = basename($_SERVER['PHP_SELF']);
$_crumb = $_breadcrumbMap[$_cf] ?? null;
if ($_crumb && $_cf !== 'index.php'):
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="font-size:.82rem;">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
        <li class="breadcrumb-item active"><?= e($_crumb) ?></li>
    </ol>
</nav>
<?php endif; ?>
