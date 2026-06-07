<?php
require_once __DIR__ . '/config/config.php';

$slug = sanitize($_GET['s'] ?? '');
if (!$slug) redirect(BASE_URL . '/index.php');

$consentSQL = hasConsentColumn()    ? ' AND r.consent = 1' : '';
$adimpSQL   = hasAdimplenteColumn() ? ' AND (u.adimplente = 1 OR r.user_id IS NULL)' : '';
$stmt = db()->prepare("SELECT r.*, c.name AS category_name
    FROM resumes r
    LEFT JOIN categories c ON c.id = r.category_id
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.slug = ? AND r.active = 1{$consentSQL}{$adimpSQL}");
$stmt->execute([$slug]);
$r = $stmt->fetch();

if (!$r) {
    flash('warning', 'Currículo não encontrado.');
    redirect(BASE_URL . '/index.php');
}

db()->prepare("UPDATE resumes SET views = views + 1 WHERE id = ?")->execute([$r['id']]);

$curriculoUrl  = BASE_URL . '/curriculo.php?s=' . urlencode($r['slug']);
$pageTitle     = $r['name'] . ' — ' . getSetting('site_name', APP_NAME);
$ogType        = 'profile';
$ogTitle       = $r['name'] . ($r['profession'] ? ' — ' . $r['profession'] : '');
$ogDescription = $r['about'] ? mb_substr(strip_tags($r['about']), 0, 160) : ($r['profession'] ?? getSetting('site_description', ''));
$ogUrl         = $curriculoUrl;
$ogImage       = $r['photo'] ? UPLOAD_URL . $r['photo'] : null;

$socialNetworks = [
    'linkedin'  => ['icon' => 'bi-linkedin',  'label' => 'LinkedIn'],
    'instagram' => ['icon' => 'bi-instagram', 'label' => 'Instagram'],
    'facebook'  => ['icon' => 'bi-facebook',  'label' => 'Facebook'],
    'twitter'   => ['icon' => 'bi-twitter-x', 'label' => 'Twitter/X'],
    'website'   => ['icon' => 'bi-globe',     'label' => 'Website'],
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main-content">
<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house me-1"></i>Início</a></li>
            <?php if ($r['category_name']): ?>
            <li class="breadcrumb-item">
                <a href="<?= BASE_URL ?>/index.php?categoria=<?= $r['category_id'] ?>"><?= e($r['category_name']) ?></a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($r['name']) ?></li>
        </ol>
    </nav>

    <!-- Resume card -->
    <div class="resume-page" id="resumeContent">
        <div class="row g-0">

            <!-- ── Left column ──────────────────────────────── -->
            <div class="col-md-4 resume-left">

                <div class="resume-photo-wrap">
                    <?php if ($r['photo']): ?>
                    <img src="<?= UPLOAD_URL . e($r['photo']) ?>" alt="<?= e($r['name']) ?>" class="resume-photo">
                    <?php else: ?>
                    <div class="resume-photo-placeholder"><i class="bi bi-person-fill"></i></div>
                    <?php endif; ?>
                </div>

                <div class="resume-identity">
                    <h1 class="resume-name"><?= e($r['name']) ?></h1>
                    <?php if ($r['profession']): ?>
                    <p class="resume-profession"><?= e($r['profession']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-1 justify-content-center mt-2">
                        <?php if ($r['formation']): ?>
                        <span class="resume-badge resume-badge-formation">
                            <i class="bi bi-mortarboard"></i><?= e($r['formation']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($r['category_name']): ?>
                        <span class="resume-badge resume-badge-category">
                            <i class="bi bi-bookmark-fill"></i><?= e($r['category_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="resume-left-divider"></div>

                <div class="resume-contacts">
                    <p class="resume-section-title"><i class="bi bi-telephone me-1"></i>Contatos</p>
                    <?php if ($r['phone']): ?>
                    <a href="tel:<?= e(preg_replace('/\D/', '', $r['phone'])) ?>" class="resume-contact-item">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?= e(formatPhone($r['phone'])) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($r['whatsapp']): ?>
                    <a href="https://wa.me/55<?= e(preg_replace('/\D/', '', $r['whatsapp'])) ?>"
                       target="_blank" rel="noopener" class="resume-contact-item whatsapp">
                        <i class="bi bi-whatsapp"></i>
                        <span><?= e(formatPhone($r['whatsapp'])) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($r['email']): ?>
                    <a href="mailto:<?= e($r['email']) ?>" class="resume-contact-item">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?= e($r['email']) ?></span>
                    </a>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ── Right column ─────────────────────────────── -->
            <div class="col-md-8 resume-right">

                <?php if ($r['about']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading">
                        <i class="bi bi-person-lines-fill"></i>Sobre
                    </h2>
                    <div class="resume-text"><?= nl2br(e($r['about'])) ?></div>
                </section>
                <?php endif; ?>

                <?php if ($r['academic_formation']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading">
                        <i class="bi bi-mortarboard-fill"></i>Formação Acadêmica
                    </h2>
                    <div class="resume-text"><?= nl2br(e($r['academic_formation'])) ?></div>
                </section>
                <?php endif; ?>

                <?php if ($r['professional_experience']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading">
                        <i class="bi bi-briefcase-fill"></i>Experiência Profissional
                    </h2>
                    <div class="resume-text"><?= nl2br(e($r['professional_experience'])) ?></div>
                </section>
                <?php endif; ?>

                <?php
                $hasSocial = false;
                foreach ($socialNetworks as $field => $info) {
                    if (!empty($r[$field])) { $hasSocial = true; break; }
                }
                if ($hasSocial):
                ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading">
                        <i class="bi bi-share-fill"></i>Redes Sociais
                    </h2>
                    <div class="resume-social-links">
                        <?php foreach ($socialNetworks as $field => $info): ?>
                        <?php if (!empty($r[$field])): ?>
                        <a href="<?= e($r[$field]) ?>" target="_blank" rel="noopener noreferrer" class="social-link">
                            <i class="bi <?= $info['icon'] ?>"></i><?= $info['label'] ?>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Action buttons -->
    <div class="resume-actions no-print mt-4">
        <button class="btn-action btn-action-primary" onclick="showQRCode()">
            <i class="bi bi-qr-code"></i>QR Code
        </button>
        <button class="btn-action btn-action-danger" onclick="window.print()">
            <i class="bi bi-file-pdf"></i>Gerar PDF
        </button>
        <button class="btn-action btn-action-ghost" onclick="copyLink()" id="copyLinkBtn">
            <i class="bi bi-link-45deg"></i>Copiar Link
        </button>
        <a href="<?= BASE_URL ?>/index.php" class="btn-action btn-action-ghost">
            <i class="bi bi-arrow-left"></i>Voltar
        </a>
    </div>

</div>

<?php
// Related professionals
if ($r['category_id']):
    $consentSQL2 = hasConsentColumn()    ? ' AND r2.consent = 1' : '';
    $adimpSQL2   = hasAdimplenteColumn() ? ' AND (u2.adimplente = 1 OR r2.user_id IS NULL)' : '';
    $relStmt = db()->prepare(
        "SELECT r2.name, r2.slug, r2.profession, r2.photo
         FROM resumes r2
         LEFT JOIN users u2 ON u2.id = r2.user_id
         WHERE r2.category_id = ? AND r2.id != ? AND r2.active = 1{$consentSQL2}{$adimpSQL2}
         ORDER BY RAND() LIMIT 3"
    );
    $relStmt->execute([$r['category_id'], $r['id']]);
    $related = $relStmt->fetchAll();
    if ($related):
?>
<div class="container pb-5 no-print">
    <div class="related-section">
        <h5><i class="bi bi-people-fill" style="color:var(--secondary);"></i>Outros Profissionais em <?= e($r['category_name']) ?></h5>
        <div class="row g-3">
            <?php foreach ($related as $rel): ?>
            <div class="col-md-4">
                <a href="<?= BASE_URL ?>/curriculo.php?s=<?= urlencode($rel['slug']) ?>" class="related-card">
                    <?php if ($rel['photo']): ?>
                    <img src="<?= UPLOAD_URL . e($rel['photo']) ?>" alt="" class="related-card-avatar">
                    <?php else: ?>
                    <div class="related-card-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                    <?php endif; ?>
                    <div>
                        <div class="related-card-name"><?= e($rel['name']) ?></div>
                        <?php if ($rel['profession']): ?>
                        <div class="related-card-prof"><?= e($rel['profession']) ?></div>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-right ms-auto" style="color:var(--border);font-size:.85rem;flex-shrink:0;"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; endif; ?>

</main>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">
            <div class="modal-header" style="background:var(--primary);color:#fff;border:none;">
                <h5 class="modal-title" style="font-size:.95rem;font-weight:700;">
                    <i class="bi bi-qr-code me-2"></i>QR Code — <?= e($r['name']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=<?= urlencode($curriculoUrl) ?>&color=1B3A6B&bgcolor=FFFFFF&margin=12"
                     alt="QR Code" class="qr-image img-fluid">
                <p class="mt-3 small text-muted mb-1">Escaneie para abrir este currículo</p>
                <p class="small text-muted text-break"><code style="font-size:.75rem;"><?= e($curriculoUrl) ?></code></p>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f2f8;">
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode($curriculoUrl) ?>&color=1B3A6B&bgcolor=FFFFFF&margin=12"
                   download="qrcode-<?= e($r['slug']) ?>.png" class="btn-primary-custom">
                    <i class="bi bi-download"></i>Baixar PNG
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
function showQRCode() {
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}
function copyLink() {
    var url = '<?= addslashes($curriculoUrl) ?>';
    var btn = document.getElementById('copyLinkBtn');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            btn.innerHTML = '<i class="bi bi-check-lg"></i>Copiado!';
            btn.style.borderColor = '#28a745';
            btn.style.color = '#28a745';
            setTimeout(function () {
                btn.innerHTML = '<i class="bi bi-link-45deg"></i>Copiar Link';
                btn.style.borderColor = '';
                btn.style.color = '';
            }, 2200);
        });
    } else {
        prompt('Copie o link:', url);
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
