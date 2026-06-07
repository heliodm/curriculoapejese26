<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$myUserId = (int)$_SESSION['user_id'];
$error    = '';

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/perfil.php');
    }

    $full_name = sanitize($_POST['full_name'] ?? '');
    $email     = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    $errors = [];
    if (empty($full_name)) $errors[] = 'Nome completo é obrigatório.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if (!empty($password) && strlen($password) < 6) $errors[] = 'Senha deve ter ao menos 6 caracteres.';
    if (!empty($password) && $password !== $password2) $errors[] = 'As senhas não coincidem.';

    // Check email uniqueness
    if (empty($errors)) {
        $chk = db()->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $myUserId]);
        if ($chk->fetch()) $errors[] = 'Este e-mail já está em uso.';
    }

    if (!empty($errors)) {
        foreach ($errors as $e) flash('danger', $e);
        redirect(BASE_URL . '/admin/perfil.php');
    }

    // Handle photo upload / removal
    $photoPath    = null;
    $removePhoto  = !empty($_POST['remove_photo']);

    if (!empty($_FILES['photo']['name'])) {
        $uploaded = uploadFile($_FILES['photo'], 'usuarios');
        if ($uploaded) {
            $photoPath = $uploaded;
        } else {
            flash('warning', 'Foto não pôde ser enviada. Use JPG, PNG ou WebP com até 5MB.');
        }
    }

    if ($photoPath || $removePhoto) {
        $oldStmt = db()->prepare("SELECT photo FROM users WHERE id = ?");
        $oldStmt->execute([$myUserId]);
        $oldRow = $oldStmt->fetch();
        if ($oldRow && $oldRow['photo']) deleteUpload($oldRow['photo']);
    }

    $sql    = "UPDATE users SET full_name=?, email=?, updated_at=NOW()";
    $params = [$full_name, $email];
    if ($photoPath)        { $sql .= ", photo=?";    $params[] = $photoPath; }
    elseif ($removePhoto)  { $sql .= ", photo=NULL"; }
    if (!empty($password)) { $sql .= ", password=?"; $params[] = password_hash($password, PASSWORD_BCRYPT); }
    $sql .= " WHERE id=?"; $params[] = $myUserId;
    db()->prepare($sql)->execute($params);

    // Update session name
    $_SESSION['full_name'] = $full_name;
    logUserAction($myUserId, 'editar_perfil', 'Usuário atualizou seu próprio perfil.');
    flash('success', 'Perfil atualizado com sucesso.');
    redirect(BASE_URL . '/admin/perfil.php');
}

// Load user
$stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$myUserId]);
$myUser = $stmt->fetch();
if (!$myUser) { redirect(BASE_URL . '/logout.php'); }

// Carteira validity
$cardValidade = !empty($myUser['carteira_validade'])
    ? date('d/m/Y', strtotime($myUser['carteira_validade']))
    : getSetting('carteira_validade', '');

$photoUrl = $myUser['photo'] ? UPLOAD_URL . $myUser['photo'] : null;

$pageTitle = 'Meu Perfil';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-person-circle me-2"></i>Meu Perfil</h3>
</div>

<div class="row g-4">
    <!-- Formulário de edição -->
    <div class="col-lg-7">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-pencil me-1"></i>Editar Dados</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <!-- Foto -->
                        <div class="col-12 d-flex align-items-center gap-4 mb-1">
                            <?php if ($photoUrl): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto"
                                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);">
                            <?php else: ?>
                            <div style="width:72px;height:72px;border-radius:50%;background:#dde3ee;display:flex;align-items:center;justify-content:center;border:2px dashed #b0bcd4;">
                                <i class="bi bi-person-fill" style="font-size:2rem;color:#b0bcd4;"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <label class="form-label fw-semibold mb-1">Foto de Perfil</label>
                                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*"
                                       style="max-width:280px;">
                                <div class="form-text">JPG, PNG ou WebP — máx. 5MB</div>
                                <?php if ($photoUrl): ?>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="remove_photo" id="removePhoto" value="1">
                                    <label class="form-check-label text-danger small" for="removePhoto">Remover foto atual</label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required maxlength="100"
                                   value="<?= e($myUser['full_name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usuário</label>
                            <input type="text" class="form-control" value="<?= e($myUser['username']) ?>" disabled>
                            <div class="form-text">Não pode ser alterado.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required maxlength="100"
                                   value="<?= e($myUser['email']) ?>">
                        </div>
                        <div class="col-12"><hr class="my-0"><small class="text-muted fw-semibold">Alterar Senha (opcional)</small></div>
                        <div class="col-md-6">
                            <label class="form-label">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" id="pw1"
                                       minlength="6" placeholder="Deixe em branco para manter">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleF('pw1','ic1')">
                                    <i class="bi bi-eye" id="ic1"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar Nova Senha</label>
                            <div class="input-group">
                                <input type="password" name="password2" class="form-control" id="pw2"
                                       minlength="6" placeholder="Repita a nova senha">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleF('pw2','ic2')">
                                    <i class="bi bi-eye" id="ic2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Dados cadastrais (somente leitura) -->
    <div class="col-lg-5">
        <div class="card admin-card mb-3">
            <div class="card-header"><i class="bi bi-card-list me-1"></i>Dados Cadastrais</div>
            <div class="card-body">
                <div class="alert alert-info py-2 mb-3" style="font-size:.8rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Para atualizar estes dados, entre em contato com a secretaria da APEJESE.
                </div>

                <?php
                $fields = [
                    ['Situação Financeira', ($myUser['adimplente'] ?? 1)
                        ? '<span class="badge bg-success">ADIMPLENTE</span>'
                        : '<span class="badge bg-danger">INADIMPLENTE</span>'],
                    ['Matrícula APEJESE', $myUser['matricula_apejese'] ? e($myUser['matricula_apejese']) : '<span class="text-muted">—</span>'],
                    ['CPF', $myUser['cpf'] ? e($myUser['cpf']) : '<span class="text-muted">—</span>'],
                    ['Data de Nascimento', !empty($myUser['data_nascimento']) ? date('d/m/Y', strtotime($myUser['data_nascimento'])) : '<span class="text-muted">—</span>'],
                    ['Data de Filiação', !empty($myUser['data_filiacao']) ? date('d/m/Y', strtotime($myUser['data_filiacao'])) : '<span class="text-muted">—</span>'],
                    ['Registro Profissional', $myUser['registro_profissional'] ? e($myUser['registro_profissional']) : '<span class="text-muted">—</span>'],
                    ['Validade da Carteira', $cardValidade ?: '<span class="text-muted">—</span>'],
                ];
                ?>
                <table class="table table-sm mb-0" style="font-size:.88rem;">
                    <?php foreach ($fields as [$label, $val]): ?>
                    <tr>
                        <td class="text-muted" style="width:55%;"><?= $label ?></td>
                        <td class="fw-semibold"><?= $val ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-link-45deg me-1"></i>Atalhos</div>
            <div class="card-body p-2">
                <?php
                $links = [
                    [BASE_URL . '/admin/curriculos.php', 'bi-file-person', 'Meu Currículo', 'Editar ou criar'],
                    [BASE_URL . '/admin/declaracao.php', 'bi-file-earmark-text', 'Declaração', 'Baixar PDF'],
                    [BASE_URL . '/admin/carteira.php',   'bi-credit-card-2-front', 'Carteira', 'Baixar PDF'],
                ];
                ?>
                <?php foreach ($links as [$href, $icon, $title, $sub]): ?>
                <a href="<?= $href ?>" class="d-flex align-items-center gap-3 p-2 rounded text-decoration-none text-dark mb-1"
                   style="transition:.15s;border:1px solid transparent;"
                   onmouseover="this.style.background='#f0f4fb';this.style.borderColor='#d0d8ee';"
                   onmouseout="this.style.background='';this.style.borderColor='transparent';">
                    <i class="bi <?= $icon ?>" style="font-size:1.3rem;color:var(--primary);"></i>
                    <div>
                        <div class="fw-semibold" style="font-size:.9rem;"><?= $title ?></div>
                        <div class="text-muted" style="font-size:.76rem;"><?= $sub ?></div>
                    </div>
                    <i class="bi bi-chevron-right ms-auto text-muted small"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleF(fId, iId) {
    const f = document.getElementById(fId);
    const i = document.getElementById(iId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
