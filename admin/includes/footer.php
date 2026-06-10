        </div><!-- /.admin-content -->

        <footer class="admin-footer">
            <span>
                <strong><?= e(getSetting('site_name', APP_NAME)) ?></strong>
                &mdash; Painel Administrativo
            </span>
            <span>
                <a href="<?= BASE_URL ?>/index.php" target="_blank">
                    <i class="bi bi-globe me-1"></i>Ver site público
                </a>
                <span class="mx-2 opacity-25">|</span>
                <form method="POST" action="<?= BASE_URL ?>/logout.php" class="d-inline m-0">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="btn-link-footer">
                        <i class="bi bi-box-arrow-right me-1"></i>Sair
                    </button>
                </form>
            </span>
        </footer>

    </main><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Overlay para fechar sidebar no mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
<script nonce="<?= CSP_NONCE ?>">
(function () {
    var forms = document.querySelectorAll('form[data-unsaved]');
    if (!forms.length) {
        // Auto-detect forms with inputs (exclude search/filter forms without data-no-unsaved)
        forms = document.querySelectorAll('form:not([data-no-unsaved])');
    }
    forms.forEach(function (form) {
        var dirty = false;
        form.addEventListener('change', function () { dirty = true; });
        form.addEventListener('input',  function () { dirty = true; });
        form.addEventListener('submit', function () { dirty = false; });
    });
    window.addEventListener('beforeunload', function (e) {
        var anyDirty = false;
        document.querySelectorAll('form:not([data-no-unsaved])').forEach(function (f) {
            // Only warn for forms that have visible inputs beyond hidden/submit
            var hasInputs = f.querySelector('input:not([type=hidden]):not([type=submit]),textarea,select');
            if (hasInputs && f._dirty) anyDirty = true;
        });
        document.querySelectorAll('form:not([data-no-unsaved])').forEach(function (f) {
            if (f._dirty) anyDirty = true;
        });
        if (anyDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    document.querySelectorAll('form:not([data-no-unsaved])').forEach(function (form) {
        var hasInputs = form.querySelector('input:not([type=hidden]):not([type=submit]),textarea,select');
        if (!hasInputs) return;
        form.addEventListener('change', function () { form._dirty = true; });
        form.addEventListener('input',  function () { form._dirty = true; });
        form.addEventListener('submit', function () { form._dirty = false; });
    });
})();
</script>
</body>
</html>
