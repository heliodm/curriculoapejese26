<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$role        = $_SESSION['role'] ?? 'user';
$isAdmin     = in_array($role, ['admin', 'editor']);
?>
<aside class="admin-sidebar" id="adminSidebar">
    <ul class="sidebar-menu list-unstyled mb-0">

        <li class="sidebar-section-label">Principal</li>

        <li>
            <a href="<?= BASE_URL ?>/admin/index.php"
               class="sidebar-link <?= $currentFile === 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/curriculos.php"
               class="sidebar-link <?= $currentFile === 'curriculos.php' ? 'active' : '' ?>">
                <i class="bi bi-file-person"></i>
                <span><?= $isAdmin ? 'Currículos' : 'Meu Currículo' ?></span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/declaracao.php"
               class="sidebar-link <?= $currentFile === 'declaracao.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span><?= $isAdmin ? 'Declarações' : 'Minha Declaração' ?></span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/carteira.php"
               class="sidebar-link <?= $currentFile === 'carteira.php' ? 'active' : '' ?>">
                <i class="bi bi-credit-card-2-front"></i>
                <span><?= $isAdmin ? 'Carteira' : 'Minha Carteira' ?></span>
            </a>
        </li>

        <?php if ($isAdmin): ?>
        <li class="sidebar-section-label">Administração</li>

        <li>
            <a href="<?= BASE_URL ?>/admin/categorias.php"
               class="sidebar-link <?= $currentFile === 'categorias.php' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Categorias</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/usuarios.php"
               class="sidebar-link <?= $currentFile === 'usuarios.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Usuários</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/configuracoes.php"
               class="sidebar-link <?= $currentFile === 'configuracoes.php' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Configurações</span>
            </a>
        </li>

        <li class="sidebar-section-label">Sistema</li>

        <li>
            <?php $updInfo = upd_readVersion(); ?>
            <a href="<?= BASE_URL ?>/admin/atualizacao.php"
               class="sidebar-link <?= $currentFile === 'atualizacao.php' ? 'active' : '' ?>">
                <i class="bi bi-arrow-repeat"></i>
                <span>Atualizações</span>
                <?php if (!empty($updInfo['update_available'])): ?>
                <span class="badge rounded-pill ms-auto"
                      style="background:var(--secondary);color:#fff;font-size:.65rem;padding:2px 6px;">Novo</span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/exportar.php"
               class="sidebar-link <?= $currentFile === 'exportar.php' ? 'active' : '' ?>">
                <i class="bi bi-download"></i>
                <span>Exportar</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/email-massa.php"
               class="sidebar-link <?= $currentFile === 'email-massa.php' ? 'active' : '' ?>">
                <i class="bi bi-envelope-paper"></i>
                <span>E-mail em Massa</span>
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>/admin/logs.php"
               class="sidebar-link <?= $currentFile === 'logs.php' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Logs</span>
            </a>
        </li>
        <?php endif; ?>

        <li><div class="sidebar-divider mt-2"></div></li>

        <li>
            <a href="<?= BASE_URL ?>/admin/perfil.php"
               class="sidebar-link <?= $currentFile === 'perfil.php' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>Meu Perfil</span>
            </a>
        </li>

        <li><div class="sidebar-divider"></div></li>

        <li>
            <a href="<?= BASE_URL ?>/index.php" target="_blank" class="sidebar-link">
                <i class="bi bi-globe"></i>
                <span>Ver Site</span>
            </a>
        </li>
        <li>
            <form method="POST" action="<?= BASE_URL ?>/logout.php" class="m-0">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button type="submit" class="sidebar-link sidebar-exit w-100">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Sair</span>
                </button>
            </form>
        </li>

    </ul>
</aside>
