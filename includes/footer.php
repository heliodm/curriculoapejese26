<?php
$siteName   = getSetting('site_name', APP_NAME);
$footerText = getSetting('footer_text', '&copy; ' . date('Y') . ' ' . $siteName . '. Todos os direitos reservados.');
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">
            <small><?= $footerText ?></small>
            <nav class="footer-nav">
                <a href="<?= BASE_URL ?>/index.php">Início</a>
                <a href="<?= BASE_URL ?>/verificar.php">Verificar Associado</a>
                <a href="<?= BASE_URL ?>/login.php">Área do Associado</a>
            </nav>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
