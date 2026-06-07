<?php
$siteName   = getSetting('site_name', APP_NAME);
$siteDesc   = getSetting('site_description', 'Associação dos Peritos Judiciais do Estado de Sergipe');
$footerText = getSetting('footer_text', '&copy; ' . date('Y') . ' ' . $siteName . '. Todos os direitos reservados.');
$logoPath   = getSetting('logo');
$logoUrl    = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';
?>
<footer class="site-footer">
    <div class="footer-top">
        <div class="container">
            <div class="row g-4">

                <!-- Brand column -->
                <div class="col-lg-4 col-md-6 footer-brand-col">
                    <img src="<?= e($logoUrl) ?>" alt="<?= e($siteName) ?>" class="footer-logo"
                         onerror="this.style.display='none'">
                    <div class="footer-name"><?= e($siteName) ?></div>
                    <p><?= e($siteDesc) ?></p>
                </div>

                <!-- Quick links -->
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="footer-heading">Navegação</div>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/index.php">
                            <i class="bi bi-house"></i>Página Inicial</a></li>
                        <li><a href="<?= BASE_URL ?>/index.php">
                            <i class="bi bi-people"></i>Profissionais</a></li>
                        <li><a href="<?= BASE_URL ?>/verificar.php">
                            <i class="bi bi-patch-check"></i>Verificar Associado</a></li>
                        <li><a href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-lock"></i>Área do Associado</a></li>
                    </ul>
                </div>

                <!-- Associate -->
                <div class="col-lg-2 col-sm-6">
                    <div class="footer-heading">Associado</div>
                    <ul class="footer-links">
                        <li><a href="<?= BASE_URL ?>/login.php">
                            <i class="bi bi-box-arrow-in-right"></i>Entrar</a></li>
                        <li><a href="<?= BASE_URL ?>/esqueci-senha.php">
                            <i class="bi bi-key"></i>Recuperar Senha</a></li>
                        <?php if (isLoggedIn()): ?>
                        <li><a href="<?= BASE_URL ?>/admin/index.php">
                            <i class="bi bi-speedometer2"></i>Painel</a></li>
                        <li><a href="<?= BASE_URL ?>/logout.php">
                            <i class="bi bi-box-arrow-right"></i>Sair</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Verify / QR -->
                <div class="col-lg-3 col-md-6">
                    <div class="footer-heading">Verificação</div>
                    <p style="font-size:.8rem;line-height:1.6;margin-bottom:.75rem;">
                        Verifique a autenticidade de uma carteira de associado escaneando o QR Code ou buscando pela matrícula.
                    </p>
                    <a href="<?= BASE_URL ?>/verificar.php"
                       class="btn btn-secondary-custom btn-sm"
                       style="border-radius:50px;font-size:.78rem;">
                        <i class="bi bi-patch-check me-1"></i>Verificar Agora
                    </a>
                </div>

            </div>
        </div>
    </div>

    <div class="footer-gold-line"></div>

    <div class="container">
        <div class="footer-bottom">
            <small><?= $footerText ?></small>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= BASE_URL ?>/index.php">Início</a>
                <a href="<?= BASE_URL ?>/verificar.php">Verificar</a>
                <a href="<?= BASE_URL ?>/login.php">Login</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
