<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$isUser  = ($_SESSION['role'] === 'user');
$isAdmin = isAdmin() || isEditor();

// Para role=user: localiza o único currículo dele
$myResume = null;
if ($isUser) {
    $s = db()->prepare("SELECT * FROM resumes WHERE user_id = ? LIMIT 1");
    $s->execute([$_SESSION['user_id']]);
    $myResume = $s->fetch() ?: null;
}

$acao = sanitize($_GET['acao'] ?? ($_POST['acao'] ?? 'listar'));
$id   = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

/* ── Operações protegidas (somente admin/editor) ─────────────────────── */
if ($isUser && in_array($acao, ['excluir', 'toggle'])) {
    flash('danger', 'Sem permissão para esta operação.');
    redirect(BASE_URL . '/admin/curriculos.php');
}

// DELETE (POST only)
if ($acao === 'excluir' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('danger', 'Token inválido.'); }
    else {
        $stmt = db()->prepare("SELECT photo FROM resumes WHERE id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        if ($r && $r['photo']) deleteUpload($r['photo']);
        db()->prepare("DELETE FROM resumes WHERE id = ?")->execute([$id]);
        flash('success', 'Currículo excluído.');
    }
    redirect(BASE_URL . '/admin/curriculos.php');
}

// TOGGLE ACTIVE — POST only
if ($acao === 'toggle' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        db()->prepare("UPDATE resumes SET active = NOT active WHERE id = ?")->execute([$id]);
        flash('success', 'Status atualizado.');
    }
    redirect(BASE_URL . '/admin/curriculos.php');
}

/* ── Roteamento para role=user ───────────────────────────────────────── */
if ($isUser) {
    if ($acao === 'listar') {
        redirect($myResume
            ? BASE_URL . '/admin/curriculos.php?acao=editar&id=' . $myResume['id']
            : BASE_URL . '/admin/curriculos.php?acao=novo');
    }
    if ($acao === 'novo' && $myResume) {
        flash('info', 'Você já possui um currículo. Edite-o abaixo.');
        redirect(BASE_URL . '/admin/curriculos.php?acao=editar&id=' . $myResume['id']);
    }
    if ($acao === 'editar' && $id > 0 && (!$myResume || $myResume['id'] !== $id)) {
        flash('danger', 'Você só pode editar o seu próprio currículo.');
        redirect(BASE_URL . '/admin/curriculos.php');
    }
}

/* ── FORMULÁRIO (novo / editar) ──────────────────────────────────────── */
if (in_array($acao, ['novo', 'editar'])) {

    $editing = null;
    if ($acao === 'editar' && $id > 0) {
        $stmt = db()->prepare("SELECT * FROM resumes WHERE id = ?");
        $stmt->execute([$id]);
        $editing = $stmt->fetch();
        if (!$editing) { flash('warning', 'Currículo não encontrado.'); redirect(BASE_URL . '/admin/curriculos.php'); }
    }

    /* ── POST SAVE ───────────────────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            flash('danger', 'Token de segurança inválido.');
            redirect(BASE_URL . '/admin/curriculos.php');
        }

        $fields = [
            'name'                    => sanitize($_POST['name']                    ?? ''),
            'profession'              => sanitize($_POST['profession']              ?? ''),
            'formation'               => sanitize($_POST['formation']               ?? ''),
            'phone'                   => sanitize($_POST['phone']                   ?? ''),
            'whatsapp'                => sanitize($_POST['whatsapp']                ?? ''),
            'email'                   => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'about'                   => sanitize($_POST['about']                   ?? ''),
            'academic_formation'      => sanitize($_POST['academic_formation']      ?? ''),
            'professional_experience' => sanitize($_POST['professional_experience'] ?? ''),
            'linkedin'                => sanitize($_POST['linkedin']                ?? ''),
            'facebook'                => sanitize($_POST['facebook']                ?? ''),
            'instagram'               => sanitize($_POST['instagram']               ?? ''),
            'twitter'                 => sanitize($_POST['twitter']                 ?? ''),
            'website'                 => sanitize($_POST['website']                 ?? ''),
        ];

        // role=user: user_id fixo; admin/editor: pelo seletor
        $category_id = (int)($_POST['category_id'] ?? 0);
        $user_id     = $isUser ? (int)$_SESSION['user_id'] : ((int)($_POST['user_id'] ?? 0) ?: null);
        $active      = $isUser ? ($editing['active'] ?? 0) : (isset($_POST['active']) ? 1 : 0);
        $consent     = isset($_POST['consent']) ? 1 : 0;
        $editId      = (int)($_POST['edit_id'] ?? 0);

        // Validação dos campos obrigatórios
        $errors = [];
        if (empty($fields['name']))                    $errors[] = 'Nome completo é obrigatório.';
        if ($category_id === 0)                        $errors[] = 'Categoria é obrigatória.';
        if (empty($fields['profession']))              $errors[] = 'Profissão é obrigatória.';
        if (empty($fields['formation']))               $errors[] = 'Formação é obrigatória.';
        if (empty($fields['phone']))                   $errors[] = 'Telefone é obrigatório.';
        if (empty($fields['whatsapp']))                $errors[] = 'WhatsApp é obrigatório.';
        if (empty($fields['email']) || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL))
                                                       $errors[] = 'E-mail válido é obrigatório.';
        if (empty($fields['about']))                   $errors[] = 'A seção "Sobre" é obrigatória.';
        if (empty($fields['academic_formation']))      $errors[] = 'Formação acadêmica é obrigatória.';
        if (empty($fields['professional_experience'])) $errors[] = 'Experiência profissional é obrigatória.';

        if (!empty($errors)) {
            foreach ($errors as $err) flash('danger', $err);
            redirect(BASE_URL . '/admin/curriculos.php?acao=' . ($editId ? "editar&id=$editId" : 'novo'));
        }

        // Upload de foto
        $photo = $editing['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadFile($_FILES['photo'], 'fotos');
            if ($uploaded) {
                if ($photo) deleteUpload($photo);
                $photo = $uploaded;
            } else {
                flash('warning', 'Foto não pôde ser enviada (formato/tamanho inválido — máx 5 MB).');
            }
        }
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            if ($photo) deleteUpload($photo);
            $photo = null;
        }

        $slug = uniqueSlug($fields['name'], 'resumes', $editId);

        if ($editId > 0) {
            db()->prepare(
                "UPDATE resumes SET category_id=?, user_id=?, slug=?, photo=?,
                 name=?, profession=?, formation=?,
                 phone=?, whatsapp=?, email=?,
                 about=?, academic_formation=?, professional_experience=?,
                 linkedin=?, facebook=?, instagram=?, twitter=?, website=?,
                 active=?, consent=?, updated_at=NOW()
                 WHERE id=?"
            )->execute([
                $category_id, $user_id, $slug, $photo,
                $fields['name'], $fields['profession'], $fields['formation'],
                $fields['phone'], $fields['whatsapp'], $fields['email'],
                $fields['about'], $fields['academic_formation'], $fields['professional_experience'],
                $fields['linkedin'], $fields['facebook'], $fields['instagram'], $fields['twitter'], $fields['website'],
                $active, $consent, $editId,
            ]);
            flash('success', 'Currículo atualizado com sucesso.');
        } else {
            db()->prepare(
                "INSERT INTO resumes
                 (category_id, user_id, slug, photo, name, profession, formation,
                  phone, whatsapp, email, about, academic_formation, professional_experience,
                  linkedin, facebook, instagram, twitter, website, active, consent)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $category_id, $user_id, $slug, $photo,
                $fields['name'], $fields['profession'], $fields['formation'],
                $fields['phone'], $fields['whatsapp'], $fields['email'],
                $fields['about'], $fields['academic_formation'], $fields['professional_experience'],
                $fields['linkedin'], $fields['facebook'], $fields['instagram'], $fields['twitter'], $fields['website'],
                $active, $consent,
            ]);
            flash('success', 'Currículo criado com sucesso.');
        }

        redirect(BASE_URL . '/admin/curriculos.php');
    }

    /* ── RENDERIZAÇÃO DO FORMULÁRIO ──────────────────────────────────── */
    $categories = getCategories();
    $allUsers   = $isAdmin ? db()->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll() : [];
    $pageTitle  = ($editing ? 'Editar' : ($isUser ? 'Meu' : 'Novo')) . ' Currículo';

    include __DIR__ . '/includes/header.php';
    ?>
    <?= renderFlash() ?>

    <div class="admin-page-header">
        <h3>
            <i class="bi bi-<?= $editing ? 'pencil' : 'file-person' ?> me-2"></i><?= $pageTitle ?>
        </h3>
        <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
        <?php endif; ?>
    </div>

    <div class="card admin-card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="resumeForm" novalidate>
                <?= csrfField() ?>
                <?php if ($editing): ?>
                <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
                <?php endif; ?>

                <!-- ── Foto + dados básicos ───────────────────────── -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3 text-center">
                        <label class="form-label d-block">
                            Foto <span class="text-danger">*</span>
                            <span class="text-muted small d-block fw-normal">354×472 px recomendado</span>
                        </label>
                        <div class="photo-upload-wrap" id="photoPreviewWrap">
                            <?php if (!empty($editing['photo'])): ?>
                            <img src="<?= UPLOAD_URL . e($editing['photo']) ?>" id="photoPreview" class="photo-preview">
                            <div class="mt-2">
                                <label class="form-check-label small text-danger">
                                    <input type="checkbox" name="remove_photo" value="1" class="form-check-input me-1">
                                    Remover foto
                                </label>
                            </div>
                            <?php else: ?>
                            <div class="photo-placeholder" id="photoPlaceholder"
                                 onclick="document.getElementById('photoInput').click()">
                                <i class="bi bi-person-bounding-box"></i>
                                <span>Clique para selecionar</span>
                            </div>
                            <img src="" id="photoPreview" class="photo-preview d-none">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="photo" id="photoInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                                onclick="document.getElementById('photoInput').click()">
                            <i class="bi bi-upload me-1"></i><?= ($editing && $editing['photo']) ? 'Trocar' : 'Enviar' ?> Foto
                        </button>
                        <div class="small text-muted mt-1">JPG, PNG, WEBP — máx 5 MB</div>
                    </div>

                    <div class="col-md-9">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required maxlength="100"
                                       value="<?= e($editing['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Categoria <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= ($editing['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Profissão <span class="text-danger">*</span></label>
                                <input type="text" name="profession" class="form-control" required maxlength="100"
                                       value="<?= e($editing['profession'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Formação (título) <span class="text-danger">*</span></label>
                                <input type="text" name="formation" class="form-control" required maxlength="200"
                                       value="<?= e($editing['formation'] ?? '') ?>"
                                       placeholder="Ex: Bacharelado em Direito">
                            </div>
                            <?php if ($isAdmin): ?>
                            <div class="col-md-3">
                                <label class="form-label">Usuário vinculado</label>
                                <select name="user_id" class="form-select">
                                    <option value="">Nenhum</option>
                                    <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>"
                                        <?= ($editing['user_id'] ?? null) == $u['id'] ? 'selected' : '' ?>>
                                        <?= e($u['full_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ── Contatos ──────────────────────────────────── -->
                <h6 class="section-subtitle"><i class="bi bi-telephone me-2"></i>Contatos</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Telefone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required maxlength="30"
                               value="<?= e($editing['phone'] ?? '') ?>" placeholder="(00) 0000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">WhatsApp <span class="text-danger">*</span></label>
                        <input type="text" name="whatsapp" class="form-control" required maxlength="30"
                               value="<?= e($editing['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="100"
                               value="<?= e($editing['email'] ?? '') ?>">
                    </div>
                </div>

                <!-- ── Conteúdo ──────────────────────────────────── -->
                <h6 class="section-subtitle"><i class="bi bi-card-text me-2"></i>Conteúdo do Currículo</h6>
                <div class="mb-3">
                    <label class="form-label">Sobre <span class="text-danger">*</span></label>
                    <textarea name="about" class="form-control" rows="4" required
                              maxlength="3000"><?= e($editing['about'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Formação Acadêmica <span class="text-danger">*</span></label>
                    <textarea name="academic_formation" class="form-control" rows="5" required
                              maxlength="5000"><?= e($editing['academic_formation'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Experiência Profissional <span class="text-danger">*</span></label>
                    <textarea name="professional_experience" class="form-control" rows="5" required
                              maxlength="5000"><?= e($editing['professional_experience'] ?? '') ?></textarea>
                </div>

                <!-- ── Redes Sociais ─────────────────────────────── -->
                <h6 class="section-subtitle"><i class="bi bi-share me-2"></i>Redes Sociais</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-linkedin me-1"></i>LinkedIn</label>
                        <input type="url" name="linkedin" class="form-control" maxlength="255"
                               value="<?= e($editing['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-instagram me-1"></i>Instagram</label>
                        <input type="url" name="instagram" class="form-control" maxlength="255"
                               value="<?= e($editing['instagram'] ?? '') ?>" placeholder="https://instagram.com/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-facebook me-1"></i>Facebook</label>
                        <input type="url" name="facebook" class="form-control" maxlength="255"
                               value="<?= e($editing['facebook'] ?? '') ?>" placeholder="https://facebook.com/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-twitter-x me-1"></i>Twitter/X</label>
                        <input type="url" name="twitter" class="form-control" maxlength="255"
                               value="<?= e($editing['twitter'] ?? '') ?>" placeholder="https://x.com/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-globe me-1"></i>Website</label>
                        <input type="url" name="website" class="form-control" maxlength="255"
                               value="<?= e($editing['website'] ?? '') ?>" placeholder="https://…">
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="active" class="form-check-input" id="activeResume"
                                   <?= (!isset($editing) || $editing['active']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="activeResume">
                                Publicar currículo (admin)
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── Autorização de Uso ────────────────────────── -->
                <?php $consented = (int)($editing['consent'] ?? 0); ?>
                <div class="consent-block <?= $consented ? 'consent-granted' : '' ?>" id="consentBlock">
                    <div class="consent-header">
                        <i class="bi bi-shield-check me-2"></i>
                        Autorização de Uso de Imagem e Informações
                    </div>
                    <div class="consent-body">
                        <p class="mb-2">Ao marcar a opção abaixo, você declara que:</p>
                        <ul class="consent-list mb-3">
                            <li>Autoriza o uso da sua <strong>foto e imagem</strong> neste currículo;</li>
                            <li>Autoriza a <strong>publicação de todas as informações</strong> no site público;</li>
                            <li>Confirma que os dados informados são <strong>verdadeiros e corretos</strong>;</li>
                            <li>Está ciente de que a autorização pode ser revogada a qualquer momento pelo administrador do sistema.</li>
                        </ul>
                        <div class="form-check consent-check-wrap">
                            <input type="checkbox" name="consent" class="form-check-input" id="consentCheck"
                                   <?= $consented ? 'checked' : '' ?>>
                            <label class="form-check-label" for="consentCheck">
                                <strong>Concordo e autorizo</strong> o uso da minha foto e informações pessoais
                                para exibição pública neste currículo.
                            </label>
                        </div>
                        <div class="consent-warning mt-2 <?= $consented ? 'd-none' : '' ?>" id="consentWarning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Atenção:</strong> Sem esta autorização, seu currículo
                            <strong>não será exibido publicamente</strong>, independentemente de outros ajustes.
                        </div>
                        <div class="consent-ok mt-2 <?= $consented ? '' : 'd-none' ?>" id="consentOk">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Autorização concedida — o currículo será exibido publicamente (quando também estiver ativo).
                        </div>
                    </div>
                </div>

                <!-- ── Botões ─────────────────────────────────────── -->
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Salvar Alterações' : 'Criar Currículo' ?>
                    </button>
                    <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($editing['slug']) ?>" target="_blank"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-eye me-1"></i>Ver Currículo
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="copyLinkFormBtn"
                            onclick="copyResFormLink()">
                        <i class="bi bi-link-45deg me-1"></i>Copiar Link
                    </button>
                    <?php endif; ?>
                    <?php if ($isAdmin): ?>
                    <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-outline-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mt-2"><span class="text-danger">*</span> Campos obrigatórios</p>
            </form>
        </div>
    </div>

    <script>
    // Foto preview
    document.getElementById('photoInput').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const preview     = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPlaceholder');
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    // Copy link
    function copyResFormLink() {
        <?php if ($editing): ?>
        var url = '<?= addslashes(BASE_URL . '/curriculo.php?s=' . urlencode($editing['slug'])) ?>';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                var btn = document.getElementById('copyLinkFormBtn');
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copiado!';
                setTimeout(function () { btn.innerHTML = '<i class="bi bi-link-45deg me-1"></i>Copiar Link'; }, 2000);
            });
        } else { prompt('Copie o link:', url); }
        <?php endif; ?>
    }

    // Consent toggle
    document.getElementById('consentCheck').addEventListener('change', function () {
        const warn  = document.getElementById('consentWarning');
        const ok    = document.getElementById('consentOk');
        const block = document.getElementById('consentBlock');
        warn.classList.toggle('d-none', this.checked);
        ok.classList.toggle('d-none', !this.checked);
        block.classList.toggle('consent-granted', this.checked);
    });
    </script>

    <?php include __DIR__ . '/includes/footer.php';
    exit;
}

/* ══════════════════════════════════════════════════════════════════════
   LISTA (somente admin/editor)
══════════════════════════════════════════════════════════════════════ */
$search  = sanitize($_GET['busca']     ?? '');
$catId   = (int)($_GET['categoria']    ?? 0);
$page    = max(1, (int)($_GET['pagina'] ?? 1));
$perPage = 15;

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[]  = '(r.name LIKE ? OR r.profession LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($catId > 0) { $where[] = 'r.category_id = ?'; $params[] = $catId; }
$whereSQL = implode(' AND ', $where);

$totalStmt = db()->prepare("SELECT COUNT(*) FROM resumes r WHERE $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pag   = paginate($total, $perPage, $page);

$params[] = $perPage;
$params[] = $pag['offset'];

$stmt = db()->prepare(
    "SELECT r.*, c.name AS category_name
     FROM resumes r
     LEFT JOIN categories c ON c.id = r.category_id
     WHERE $whereSQL
     ORDER BY r.name ASC LIMIT ? OFFSET ?"
);
// updated_at column may not exist on very old installs — handled in query above
$stmt->execute($params);
$resumes = $stmt->fetchAll();

$categories = getCategories();
$pageTitle  = 'Currículos';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-file-person me-2"></i>Currículos</h3>
    <a href="?acao=novo" class="btn btn-primary-custom">
        <i class="bi bi-plus-lg me-1"></i>Novo Currículo
    </a>
</div>

<div class="card admin-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="busca" class="form-control form-control-sm"
                       placeholder="Buscar por nome ou profissão…" value="<?= e($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="categoria" class="form-select form-select-sm">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catId === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary-custom">Filtrar</button>
                <?php if ($search || $catId): ?>
                <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card admin-card">
    <div class="card-header">
        <i class="bi bi-list-ul me-1"></i>Lista (<?= $total ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nome</th>
                        <th>Profissão</th>
                        <th>Categoria</th>
                        <th>Views</th>
                        <th>Público</th>
                        <th>Editado</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumes as $r): ?>
                    <tr>
                        <td>
                            <?php if ($r['photo']): ?>
                            <img src="<?= UPLOAD_URL . e($r['photo']) ?>" class="table-avatar">
                            <?php else: ?>
                            <div class="table-avatar-placeholder"><i class="bi bi-person"></i></div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-medium"><?= e($r['name']) ?></td>
                        <td class="text-muted small"><?= e($r['profession'] ?? '—') ?></td>
                        <td><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                        <td><?= $r['views'] ?></td>
                        <td>
                            <span class="badge <?= ($r['consent'] ?? 0) ? 'bg-success' : 'bg-warning text-dark' ?>"
                                  title="<?= ($r['consent'] ?? 0) ? 'Autorizado pelo usuário' : 'Aguardando autorização' ?>">
                                <?= ($r['consent'] ?? 0) ? 'Autorizado' : 'Pendente' ?>
                            </span>
                        </td>
                        <td class="text-muted small text-nowrap">
                            <?= !empty($r['updated_at']) ? date('d/m/Y', strtotime($r['updated_at'])) : '—' ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline" data-no-unsaved>
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="toggle">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit"
                                        class="badge border-0 <?= $r['active'] ? 'bg-success' : 'bg-secondary' ?>"
                                        style="cursor:pointer;">
                                    <?= $r['active'] ? 'Ativo' : 'Inativo' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-nowrap">
                            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" target="_blank"
                               class="btn btn-xs btn-outline-secondary me-1" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button type="button" class="btn btn-xs btn-outline-secondary me-1"
                                    title="Copiar link"
                                    onclick="copyResLink('<?= addslashes(BASE_URL . '/curriculo.php?s=' . urlencode($r['slug'])) ?>', this)">
                                <i class="bi bi-link-45deg"></i>
                            </button>
                            <a href="?acao=editar&id=<?= $r['id'] ?>"
                               class="btn btn-xs btn-outline-primary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" data-no-unsaved
                                  onsubmit="return confirm('Excluir currículo de <?= e(addslashes($r['name'])) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($resumes)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Nenhum currículo encontrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-center">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $pag['total_pages']; $p++): ?>
            <li class="page-item <?= $p === $pag['current'] ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>">
                    <?= $p ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<script>
function copyResLink(url, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { btn.innerHTML = orig; }, 1800);
        });
    } else {
        prompt('Copie o link:', url);
    }
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
