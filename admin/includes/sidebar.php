<?php
$currentFile = basename($_SERVER['PHP_SELF']);

$menu = [
    ['file' => 'index.php',         'icon' => 'bi-speedometer2',   'label' => 'Dashboard'],
    ['file' => 'curriculos.php',    'icon' => 'bi-file-person',    'label' => 'Currículos'],
    ['file' => 'categorias.php',    'icon' => 'bi-tags',           'label' => 'Categorias'],
    ['file' => 'usuarios.php',      'icon' => 'bi-people',         'label' => 'Usuários'],
    ['file' => 'configuracoes.php', 'icon' => 'bi-gear',           'label' => 'Configurações'],
];
?>
<aside class="admin-sidebar" id="adminSidebar">
    <ul class="sidebar-menu list-unstyled mb-0">
        <?php foreach ($menu as $item): ?>
        <li>
            <a href="<?= BASE_URL ?>/admin/<?= $item['file'] ?>"
               class="sidebar-link <?= $currentFile === $item['file'] ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
        </li>
        <?php endforeach; ?>

        <li><div class="sidebar-divider"></div></li>

        <li>
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="sidebar-link">
                <i class="bi bi-globe"></i>
                <span>Ver Site</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link sidebar-exit">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sair</span>
            </a>
        </li>
    </ul>
</aside>
