<?php
require_once __DIR__ . '/config/config.php';

$slug = sanitize($_GET['s'] ?? '');
if (!$slug) redirect(BASE_URL . '/index.php');

$consentSQL    = hasConsentColumn()    ? ' AND r.consent = 1' : '';
$adimpSQL      = hasAdimplenteColumn() ? ' AND (u.adimplente = 1 OR r.user_id IS NULL)' : '';
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

// Increment views
db()->prepare("UPDATE resumes SET views = views + 1 WHERE id = ?")->execute([$r['id']]);

$curriculoUrl   = BASE_URL . '/curriculo.php?s=' . urlencode($r['slug']);
$pageTitle      = $r['name'] . ' — ' . getSetting('site_name', APP_NAME);
$ogType         = 'profile';
$ogTitle        = $r['name'] . ($r['profession'] ? ' — ' . $r['profession'] : '');
$ogDescription  = $r['about'] ? mb_substr(strip_tags($r['about']), 0, 160) : ($r['profession'] ?? getSetting('site_description', ''));
$ogUrl          = $curriculoUrl;
$ogImage        = $r['photo'] ? UPLOAD_URL . $r['photo'] : null;

$socialNetworks = [
    'linkedin'  => ['icon' => 'bi-linkedin',   'label' => 'LinkedIn',   'prefix' => ''],
    'instagram' => ['icon' => 'bi-instagram',  'label' => 'Instagram',  'prefix' => ''],
    'facebook'  => ['icon' => 'bi-facebook',   'label' => 'Facebook',   'prefix' => ''],
    'twitter'   => ['icon' => 'bi-twitter-x',  'label' => 'Twitter/X',  'prefix' => ''],
    'website'   => ['icon' => 'bi-globe',      'label' => 'Website',    'prefix' => ''],
];
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Início</a></li>
            <?php if ($r['category_name']): ?>
            <li class="breadcrumb-item">
                <a href="<?= BASE_URL ?>/index.php?categoria=<?= $r['category_id'] ?>">
                    <?= e($r['category_name']) ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= e($r['name']) ?></li>
        </ol>
    </nav>

    <div class="resume-page" id="resumeContent">
        <div class="row g-0">
            <!-- Left Column -->
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
                    <?php if ($r['formation']): ?>
                    <span class="resume-formation-badge"><i class="bi bi-mortarboard me-1"></i><?= e($r['formation']) ?></span>
                    <?php endif; ?>
                    <?php if ($r['category_name']): ?>
                    <span class="resume-cat-badge mt-2"><i class="bi bi-bookmark me-1"></i><?= e($r['category_name']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="resume-contacts">
                    <h6 class="resume-section-title"><i class="bi bi-telephone me-2"></i>Contatos</h6>
                    <?php if ($r['phone']): ?>
                    <a href="tel:<?= e(preg_replace('/\D/', '', $r['phone'])) ?>" class="resume-contact-item">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?= e(formatPhone($r['phone'])) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($r['whatsapp']): ?>
                    <a href="https://wa.me/55<?= e(preg_replace('/\D/', '', $r['whatsapp'])) ?>" target="_blank" class="resume-contact-item whatsapp">
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

            <!-- Right Column -->
            <div class="col-md-8 resume-right">
                <?php if ($r['about']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading"><i class="bi bi-person-lines-fill me-2"></i>Sobre</h2>
                    <div class="resume-text"><?= nl2br(e($r['about'])) ?></div>
                </section>
                <?php endif; ?>

                <?php if ($r['academic_formation']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading"><i class="bi bi-mortarboard-fill me-2"></i>Formação Acadêmica</h2>
                    <div class="resume-text"><?= nl2br(e($r['academic_formation'])) ?></div>
                </section>
                <?php endif; ?>

                <?php if ($r['professional_experience']): ?>
                <section class="resume-section">
                    <h2 class="resume-section-heading"><i class="bi bi-briefcase-fill me-2"></i>Experiência Profissional</h2>
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
                    <h2 class="resume-section-heading"><i class="bi bi-share-fill me-2"></i>Redes Sociais</h2>
                    <div class="resume-social-links">
                        <?php foreach ($socialNetworks as $field => $info): ?>
                        <?php if (!empty($r[$field])): ?>
                        <a href="<?= e($r[$field]) ?>" target="_blank" rel="noopener noreferrer" class="social-link">
                            <i class="bi <?= $info['icon'] ?>"></i>
                            <span><?= $info['label'] ?></span>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="resume-actions d-flex flex-wrap gap-3 justify-content-center mt-4 no-print">
        <button class="btn btn-action-qr" onclick="showQRCode()">
            <i class="bi bi-qr-code me-2"></i>Gerar QR Code
        </button>
        <button class="btn btn-action-pdf" onclick="printResume()">
            <i class="bi bi-file-pdf me-2"></i>Gerar PDF
        </button>
        <button class="btn btn-outline-secondary" onclick="copyLink()" id="copyLinkBtn">
            <i class="bi bi-link-45deg me-2"></i>Copiar Link
        </button>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-action-back">
            <i class="bi bi-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<?php
// Related professionals — same category, excluding current
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
<div class="container pb-4 no-print">
    <h5 class="fw-semibold mb-3" style="color:var(--primary);">
        <i class="bi bi-people me-2"></i>Outros Profissionais em <?= e($r['category_name']) ?>
    </h5>
    <div class="row g-3">
        <?php foreach ($related as $rel): ?>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= urlencode($rel['slug']) ?>"
               class="card text-decoration-none resume-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <?php if ($rel['photo']): ?>
                    <img src="<?= UPLOAD_URL . e($rel['photo']) ?>" alt=""
                         style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                    <?php else: ?>
                    <div style="width:44px;height:44px;border-radius:50%;background:#dde3ee;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-person-fill" style="color:#b0bcd4;font-size:1.2rem;"></i>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold" style="font-size:.9rem;"><?= e($rel['name']) ?></div>
                        <?php if ($rel['profession']): ?>
                        <div class="text-muted" style="font-size:.78rem;"><?= e($rel['profession']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; endif; ?>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2"></i>QR Code — <?= e($r['name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=<?= urlencode($curriculoUrl) ?>&color=1B3A6B&bgcolor=FFFFFF&margin=10"
                     alt="QR Code" class="qr-image img-fluid rounded shadow-sm">
                <p class="mt-3 small text-muted">Escaneie para abrir este currículo</p>
                <p class="small text-break"><code><?= e($curriculoUrl) ?></code></p>
            </div>
            <div class="modal-footer">
                <a href="https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=<?= urlencode($curriculoUrl) ?>&color=1B3A6B&bgcolor=FFFFFF&margin=10"
                   download="qrcode-<?= e($r['slug']) ?>.png" class="btn btn-primary-custom">
                    <i class="bi bi-download me-1"></i>Baixar QR Code
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
function showQRCode() {
    new bootstrap.Modal(document.getElementById('qrModal')).show();
}
function printResume() {
    window.print();
}
function copyLink() {
    var url = '<?= addslashes($curriculoUrl) ?>';
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            var btn = document.getElementById('copyLinkBtn');
            btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Link Copiado!';
            setTimeout(function () {
                btn.innerHTML = '<i class="bi bi-link-45deg me-2"></i>Copiar Link';
            }, 2000);
        });
    } else {
        prompt('Copie o link:', url);
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
