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
                <a href="<?= BASE_URL ?>/logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>Sair
                </a>
            </span>
        </footer>

    </main><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<!-- Overlay para fechar sidebar no mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
