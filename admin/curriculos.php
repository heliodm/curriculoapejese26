<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$acao = sanitize($_GET['acao'] ?? 'listar');
$id   = (int)($_GET['id'] ?? 0);

// DELETE
if ($acao === 'excluir' && $id > 0) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { flash('danger', 'Token inválido.'); }
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

// TOGGLE ACTIVE
if ($acao === 'toggle' && $id > 0) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        db()->prepare("UPDATE resumes SET active = NOT active WHERE id = ?")->execute([$id]);
        flash('success', 'Status atualizado.');
    }
    redirect(BASE_URL . '/admin/curriculos.php');
}

// FORM (add/edit)
if (in_array($acao, ['novo', 'editar'])) {
    $editing = null;
    if ($acao === 'editar' && $id > 0) {
        $stmt = db()->prepare("SELECT * FROM resumes WHERE id = ?");
        $stmt->execute([$id]);
        $editing = $stmt->fetch();
        if (!$editing) { flash('warning', 'Currículo não encontrado.'); redirect(BASE_URL . '/admin/curriculos.php'); }
    }

    // POST SAVE
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            flash('danger', 'Token de segurança inválido.');
            redirect(BASE_URL . '/admin/curriculos.php');
        }
        $fields = [
            'name'                   => sanitize($_POST['name'] ?? ''),
            'profession'             => sanitize($_POST['profession'] ?? ''),
            'formation'              => sanitize($_POST['formation'] ?? ''),
            'phone'                  => sanitize($_POST['phone'] ?? ''),
            'whatsapp'               => sanitize($_POST['whatsapp'] ?? ''),
            'email'                  => filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'about'                  => sanitize($_POST['about'] ?? ''),
            'academic_formation'     => sanitize($_POST['academic_formation'] ?? ''),
            'professional_experience'=> sanitize($_POST['professional_experience'] ?? ''),
            'linkedin'               => sanitize($_POST['linkedin'] ?? ''),
            'facebook'               => sanitize($_POST['facebook'] ?? ''),
            'instagram'              => sanitize($_POST['instagram'] ?? ''),
            'twitter'                => sanitize($_POST['twitter'] ?? ''),
            'website'                => sanitize($_POST['website'] ?? ''),
        ];
        $category_id = (int)($_POST['category_id'] ?? 0);
        $user_id     = (int)($_POST['user_id'] ?? 0) ?: null;
        $active      = isset($_POST['active']) ? 1 : 0;
        $editId      = (int)($_POST['edit_id'] ?? 0);

        if (empty($fields['name']) || $category_id === 0) {
            flash('danger', 'Nome e categoria são obrigatórios.');
            redirect(BASE_URL . '/admin/curriculos.php?acao=' . ($editId ? "editar&id=$editId" : 'novo'));
        }

        // Handle photo upload
        $photo = $editing['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadFile($_FILES['photo'], 'fotos');
            if ($uploaded) {
                if ($photo) deleteUpload($photo);
                $photo = $uploaded;
            } else {
                flash('warning', 'Foto não pôde ser enviada. Verifique o formato/tamanho (máx 5MB).');
            }
        }
        if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
            if ($photo) deleteUpload($photo);
            $photo = null;
        }

        $slug = uniqueSlug($fields['name'], 'resumes', $editId);

        if ($editId > 0) {
            $sql = "UPDATE resumes SET category_id=?, user_id=?, slug=?, photo=?, name=?, profession=?, formation=?,
                    phone=?, whatsapp=?, email=?, about=?, academic_formation=?, professional_experience=?,
                    linkedin=?, facebook=?, instagram=?, twitter=?, website=?, active=?, updated_at=NOW()
                    WHERE id=?";
            db()->prepare($sql)->execute([
                $category_id, $user_id, $slug, $photo,
                $fields['name'], $fields['profession'], $fields['formation'],
                $fields['phone'], $fields['whatsapp'], $fields['email'],
                $fields['about'], $fields['academic_formation'], $fields['professional_experience'],
                $fields['linkedin'], $fields['facebook'], $fields['instagram'], $fields['twitter'], $fields['website'],
                $active, $editId
            ]);
            flash('success', 'Currículo atualizado com sucesso.');
        } else {
            $sql = "INSERT INTO resumes (category_id, user_id, slug, photo, name, profession, formation,
                    phone, whatsapp, email, about, academic_formation, professional_experience,
                    linkedin, facebook, instagram, twitter, website, active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            db()->prepare($sql)->execute([
                $category_id, $user_id, $slug, $photo,
                $fields['name'], $fields['profession'], $fields['formation'],
                $fields['phone'], $fields['whatsapp'], $fields['email'],
                $fields['about'], $fields['academic_formation'], $fields['professional_experience'],
                $fields['linkedin'], $fields['facebook'], $fields['instagram'], $fields['twitter'], $fields['website'],
                $active
            ]);
            flash('success', 'Currículo criado com sucesso.');
        }
        redirect(BASE_URL . '/admin/curriculos.php');
    }

    $categories = getCategories();
    $allUsers   = db()->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll();

    $pageTitle = ($editing ? 'Editar' : 'Novo') . ' Currículo';
    include __DIR__ . '/includes/header.php';
    ?>
    <?= renderFlash() ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-<?= $editing ? 'pencil' : 'file-person' ?> me-2"></i><?= $pageTitle ?></h3>
        <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <div class="card admin-card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <?php if ($editing): ?>
                <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
                <?php endif; ?>

                <!-- Foto e dados básicos -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3 text-center">
                        <label class="form-label d-block">Foto</label>
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
                            <div class="photo-placeholder" id="photoPlaceholder">
                                <i class="bi bi-person-bounding-box"></i>
                                <span>Clique para selecionar</span>
                            </div>
                            <img src="" id="photoPreview" class="photo-preview d-none">
                            <?php endif; ?>
                        </div>
                        <input type="file" name="photo" id="photoInput" accept="image/*" class="d-none">
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="document.getElementById('photoInput').click()">
                            <i class="bi bi-upload me-1"></i><?= $editing && $editing['photo'] ? 'Trocar' : 'Enviar' ?> Foto
                        </button>
                        <div class="small text-muted mt-1">JPG, PNG, WEBP — máx 5MB</div>
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
                                    <option value="<?= $cat['id'] ?>" <?= ($editing['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Profissão</label>
                                <input type="text" name="profession" class="form-control" maxlength="100"
                                       value="<?= e($editing['profession'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Formação (título)</label>
                                <input type="text" name="formation" class="form-control" maxlength="200"
                                       value="<?= e($editing['formation'] ?? '') ?>"
                                       placeholder="Ex: Bacharelado em Direito">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Usuário vinculado</label>
                                <select name="user_id" class="form-select">
                                    <option value="">Nenhum</option>
                                    <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($editing['user_id'] ?? null) == $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contatos -->
                <h6 class="section-subtitle"><i class="bi bi-telephone me-2"></i>Contatos</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="phone" class="form-control" maxlength="30"
                               value="<?= e($editing['phone'] ?? '') ?>" placeholder="(00) 0000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-control" maxlength="30"
                               value="<?= e($editing['whatsapp'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control" maxlength="100"
                               value="<?= e($editing['email'] ?? '') ?>">
                    </div>
                </div>

                <!-- Conteúdo -->
                <h6 class="section-subtitle"><i class="bi bi-card-text me-2"></i>Conteúdo do Currículo</h6>
                <div class="mb-3">
                    <label class="form-label">Sobre</label>
                    <textarea name="about" class="form-control" rows="4" maxlength="3000"><?= e($editing['about'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Formação Acadêmica</label>
                    <textarea name="academic_formation" class="form-control" rows="5" maxlength="5000"><?= e($editing['academic_formation'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Experiência Profissional</label>
                    <textarea name="professional_experience" class="form-control" rows="5" maxlength="5000"><?= e($editing['professional_experience'] ?? '') ?></textarea>
                </div>

                <!-- Redes Sociais -->
                <h6 class="section-subtitle"><i class="bi bi-share me-2"></i>Redes Sociais</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-linkedin me-1 text-primary"></i>LinkedIn</label>
                        <input type="url" name="linkedin" class="form-control" maxlength="255"
                               value="<?= e($editing['linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-instagram me-1 text-danger"></i>Instagram</label>
                        <input type="url" name="instagram" class="form-control" maxlength="255"
                               value="<?= e($editing['instagram'] ?? '') ?>" placeholder="https://instagram.com/…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-facebook me-1 text-primary"></i>Facebook</label>
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
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox" name="active" class="form-check-input" id="activeResume"
                                   <?= (!isset($editing) || $editing['active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activeResume">Publicar currículo</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Salvar Alterações' : 'Criar Currículo' ?>
                    </button>
                    <?php if ($editing): ?>
                    <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($editing['slug']) ?>" target="_blank" class="btn btn-outline-secondary">
                        <i class="bi bi-eye me-1"></i>Ver Currículo
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/admin/curriculos.php" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('photoInput').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPlaceholder');
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });
    </script>

    <?php include __DIR__ . '/includes/footer.php';
    exit;
}

// LIST VIEW
$search  = sanitize($_GET['busca']    ?? '');
$catId   = (int)($_GET['categoria']   ?? 0);
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

$stmt = db()->prepare("SELECT r.*, c.name AS category_name FROM resumes r LEFT JOIN categories c ON c.id = r.category_id WHERE $whereSQL ORDER BY r.name ASC LIMIT ? OFFSET ?");
$stmt->execute($params);
$resumes = $stmt->fetchAll();

$categories = getCategories();
$pageTitle = 'Currículos';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-file-person me-2"></i>Currículos</h3>
    <a href="?acao=novo" class="btn btn-primary-custom"><i class="bi bi-plus-lg me-1"></i>Novo Currículo</a>
</div>

<div class="card admin-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="busca" class="form-control form-control-sm" placeholder="Buscar por nome ou profissão…" value="<?= e($search) ?>">
            </div>
            <div class="col-md-4">
                <select name="categoria" class="form-select form-select-sm">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $catId === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
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
    <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Lista (<?= $total ?>)</span>
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
                        <td class="text-muted"><?= e($r['profession'] ?? '—') ?></td>
                        <td><span class="badge bg-primary-soft"><?= e($r['category_name'] ?? '—') ?></span></td>
                        <td><?= $r['views'] ?></td>
                        <td>
                            <a href="?acao=toggle&id=<?= $r['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="badge <?= $r['active'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none">
                                <?= $r['active'] ? 'Ativo' : 'Inativo' ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/curriculo.php?s=<?= e($r['slug']) ?>" target="_blank"
                               class="btn btn-xs btn-outline-secondary me-1" title="Ver">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?acao=editar&id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="?acao=excluir&id=<?= $r['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-xs btn-outline-danger"
                               onclick="return confirm('Excluir currículo de <?= e(addslashes($r['name'])) ?>?')" title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($resumes)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Nenhum currículo encontrado</td></tr>
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
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
