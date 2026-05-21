<?php
$siteName = getSetting('site_name', APP_NAME);
$footerText = getSetting('footer_text', '&copy; ' . date('Y') . ' ' . $siteName . '. Todos os direitos reservados.');
?>
<footer class="site-footer">
    <div class="container">
        <div class="row align-items-center py-3">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <span class="footer-brand"><?= e($siteName) ?></span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <small><?= $footerText ?></small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
