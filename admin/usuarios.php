<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$acao = sanitize($_GET['acao'] ?? ($_POST['acao'] ?? 'listar'));
$id   = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

// DELETE — POST only
if ($acao === 'excluir' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { flash('danger', 'Token inválido.'); }
    elseif ($id === (int)$_SESSION['user_id']) { flash('danger', 'Não é possível excluir seu próprio usuário.'); }
    else {
        $u = db()->prepare("SELECT photo FROM users WHERE id = ?");
        $u->execute([$id]);
        $row = $u->fetch();
        if ($row && $row['photo']) deleteUpload($row['photo']);
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        logUserAction($id, 'excluir_usuario', 'Usuário excluído pelo admin.');
        flash('success', 'Usuário excluído.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

// TOGGLE ACTIVE — POST only
if ($acao === 'toggle' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '') && $id !== (int)$_SESSION['user_id']) {
        db()->prepare("UPDATE users SET active = NOT active WHERE id = ?")->execute([$id]);
        logUserAction($id, 'toggle_ativo', 'Status ativo alternado.');
        flash('success', 'Status atualizado.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

// TOGGLE ADIMPLENTE — POST only
if ($acao === 'toggle_adimplente' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $stmt = db()->prepare("SELECT full_name, email, adimplente FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $uRow = $stmt->fetch();
        db()->prepare("UPDATE users SET adimplente = NOT adimplente WHERE id = ?")->execute([$id]);
        $newStatus = ($uRow['adimplente'] ?? 1) ? 0 : 1;
        $statusLabel = $newStatus ? 'adimplente' : 'inadimplente';
        logUserAction($id, 'toggle_adimplente', "Situação alterada para {$statusLabel}.");
        if ($uRow && $uRow['email']) {
            $sitMsg = $newStatus ? 'ADIMPLENTE' : 'INADIMPLENTE';
            $sitTxt = $newStatus ? 'Sua situação foi regularizada.' : 'Sua anuidade pode estar em aberto. Entre em contato com a associação.';
            sendMail(
                $uRow['email'],
                $uRow['full_name'],
                'Atualização de Situação — APEJESE',
                "<p>Prezado(a) <strong>" . htmlspecialchars($uRow['full_name'], ENT_QUOTES) . "</strong>,</p>
                 <p>Sua situação junto à APEJESE foi atualizada para: <strong>{$sitMsg}</strong>.</p>
                 <p>{$sitTxt}</p>
                 <p>Em caso de dúvidas, entre em contato com a secretaria.</p>"
            );
        }
        flash('success', 'Situação financeira atualizada.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

// RESET SENHA — POST only
if ($acao === 'reset_senha' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token inválido.');
    } else {
        $tempPass = bin2hex(random_bytes(6));
        $stmt = db()->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $uRow = $stmt->fetch();
        db()->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($tempPass, PASSWORD_BCRYPT), $id]);
        logUserAction($id, 'reset_senha', 'Senha redefinida pelo admin.');
        if ($uRow && $uRow['email']) {
            sendMail(
                $uRow['email'],
                $uRow['full_name'],
                'Redefinição de Senha — APEJESE',
                "<p>Prezado(a) <strong>" . htmlspecialchars($uRow['full_name'], ENT_QUOTES) . "</strong>,</p>
                 <p>Sua senha foi redefinida pelo administrador. Sua nova senha temporária é:</p>
                 <p style='font-size:1.4em;font-weight:bold;letter-spacing:2px;'>{$tempPass}</p>
                 <p>Acesse o sistema e altere sua senha assim que possível.</p>"
            );
            flash('success', "Senha redefinida e enviada por e-mail para {$uRow['email']}.");
        } else {
            flash('success', "Senha redefinida com sucesso. Nova senha temporária: <strong>{$tempPass}</strong>");
        }
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

$editing = null;
if ($acao === 'editar' && $id > 0) {
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
    if (!$editing) { flash('warning', 'Usuário não encontrado.'); redirect(BASE_URL . '/admin/usuarios.php'); }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/usuarios.php');
    }
    $full_name             = sanitize($_POST['full_name']             ?? '');
    $username              = sanitize($_POST['username']              ?? '');
    $email                 = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $role                  = in_array($_POST['role'] ?? '', ['admin','editor','user']) ? $_POST['role'] : 'user';
    $active                = isset($_POST['active'])      ? 1 : 0;
    $adimplente            = isset($_POST['adimplente'])  ? 1 : 0;
    $matricula_apejese     = sanitize($_POST['matricula_apejese']     ?? '');
    $cpf                   = preg_replace('/[^0-9.\-]/', '', sanitize($_POST['cpf'] ?? ''));
    $data_nascimento       = sanitize($_POST['data_nascimento']       ?? '') ?: null;
    $data_filiacao         = sanitize($_POST['data_filiacao']         ?? '') ?: null;
    $registro_profissional = sanitize($_POST['registro_profissional'] ?? '');
    $carteira_validade     = sanitize($_POST['carteira_validade']     ?? '') ?: null;
    $password              = $_POST['password'] ?? '';
    $editId                = (int)($_POST['edit_id'] ?? 0);

    $errors = [];
    if (empty($full_name)) $errors[] = 'Nome completo é obrigatório.';
    if (empty($username))  $errors[] = 'Nome de usuário é obrigatório.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if ($editId === 0 && empty($password)) $errors[] = 'Senha é obrigatória para novo usuário.';
    if (!empty($password) && strlen($password) < 6) $errors[] = 'Senha deve ter ao menos 6 caracteres.';
    if (!empty($cpf) && !validateCpf($cpf)) $errors[] = 'CPF inválido. Verifique os dígitos.';

    if (empty($errors)) {
        $stmt = db()->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $editId]);
        if ($stmt->fetch()) $errors[] = 'Usuário ou e-mail já cadastrado.';
    }

    if (!empty($errors)) {
        foreach ($errors as $err) flash('danger', $err);
        redirect(BASE_URL . '/admin/usuarios.php' . ($editId ? "?acao=editar&id=$editId" : '?acao=novo'));
    }

    // Handle photo upload
    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        $uploaded = uploadFile($_FILES['photo'], 'usuarios');
        if ($uploaded) {
            $photoPath = $uploaded;
        } else {
            flash('warning', 'Foto não pôde ser enviada. Verifique o formato/tamanho.');
        }
    }

    if ($editId > 0) {
        // Delete old photo if new one uploaded
        if ($photoPath) {
            $oldStmt = db()->prepare("SELECT photo FROM users WHERE id = ?");
            $oldStmt->execute([$editId]);
            $oldRow = $oldStmt->fetch();
            if ($oldRow && $oldRow['photo']) deleteUpload($oldRow['photo']);
        }

        $sql    = "UPDATE users SET full_name=?, username=?, email=?, role=?, active=?, adimplente=?, matricula_apejese=?, cpf=?, data_nascimento=?, data_filiacao=?, registro_profissional=?, carteira_validade=?, updated_at=NOW()";
        $params = [$full_name, $username, $email, $role, $active, $adimplente, $matricula_apejese, $cpf, $data_nascimento, $data_filiacao, $registro_profissional, $carteira_validade];
        if ($photoPath) { $sql .= ", photo=?"; $params[] = $photoPath; }
        if (!empty($password)) { $sql .= ", password=?"; $params[] = password_hash($password, PASSWORD_BCRYPT); }
        $sql .= " WHERE id=?"; $params[] = $editId;
        db()->prepare($sql)->execute($params);
        logUserAction($editId, 'editar_usuario', 'Dados do usuário atualizados.');
        flash('success', 'Usuário atualizado com sucesso.');
    } else {
        db()->prepare(
            "INSERT INTO users (full_name, username, email, password, role, active, adimplente, matricula_apejese, cpf, data_nascimento, data_filiacao, registro_profissional, carteira_validade, photo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $full_name, $username, $email,
            password_hash($password, PASSWORD_BCRYPT),
            $role, $active, $adimplente,
            $matricula_apejese, $cpf, $data_nascimento,
            $data_filiacao, $registro_profissional, $carteira_validade,
            $photoPath
        ]);
        logUserAction(0, 'criar_usuario', "Novo usuário criado: {$username}.");
        flash('success', 'Usuário criado com sucesso.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

// Filters
$busca     = sanitize($_GET['busca']   ?? '');
$filRole   = sanitize($_GET['role']    ?? '');
$filSit    = sanitize($_GET['sit']     ?? '');

$where  = ['1=1'];
$params = [];
if ($busca !== '') {
    $where[]  = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.matricula_apejese LIKE ?)";
    $like     = '%' . $busca . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filRole !== '') { $where[] = "u.role = ?"; $params[] = $filRole; }
if ($filSit === '1') { $where[] = "u.adimplente = 1"; }
if ($filSit === '0') { $where[] = "u.adimplente = 0"; }

$whereClause = implode(' AND ', $where);
$stmt = db()->prepare(
    "SELECT u.*, (SELECT COUNT(*) FROM resumes r WHERE r.user_id = u.id) AS total_resumes
     FROM users u
     WHERE {$whereClause}
     ORDER BY u.full_name ASC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roleLabels = ['admin' => 'Administrador', 'editor' => 'Editor', 'user' => 'Usuário'];
$roleBadges = ['admin' => 'bg-danger', 'editor' => 'bg-warning text-dark', 'user' => 'bg-secondary'];

$pageTitle = 'Usuários';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-people me-2"></i>Usuários</h3>
    <div class="d-flex gap-2">
        <?php if ($acao !== 'novo' && !$editing): ?>
        <a href="<?= BASE_URL ?>/admin/exportar.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Exportar CSV
        </a>
        <a href="?acao=novo" class="btn btn-primary-custom">
            <i class="bi bi-plus-lg me-1"></i>Novo Usuário
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($acao === 'novo' || $editing): ?>
<div class="card admin-card mb-4">
    <div class="card-header">
        <i class="bi bi-<?= $editing ? 'pencil' : 'person-plus' ?> me-2"></i>
        <?= $editing ? 'Editar Usuário' : 'Novo Usuário' ?>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <?php if ($editing): ?>
            <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <!-- Foto -->
                <div class="col-md-2 text-center">
                    <?php
                    $editPhoto = $editing['photo'] ?? null;
                    $editPhotoUrl = $editPhoto ? UPLOAD_URL . $editPhoto : null;
                    ?>
                    <?php if ($editPhotoUrl): ?>
                    <img src="<?= e($editPhotoUrl) ?>" class="rounded mb-2"
                         style="width:80px;height:80px;object-fit:cover;border:2px solid var(--primary);">
                    <?php else: ?>
                    <div class="rounded mb-2 d-flex align-items-center justify-content-center"
                         style="width:80px;height:80px;background:#dde3ee;margin:0 auto;">
                        <i class="bi bi-person-fill" style="font-size:2rem;color:#b0bcd4;"></i>
                    </div>
                    <?php endif; ?>
                    <label class="form-label" style="font-size:.78rem;">Foto do Perfil</label>
                    <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
                </div>
                <div class="col-md-10">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required maxlength="100"
                                   value="<?= e($editing['full_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Usuário <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required maxlength="50"
                                   value="<?= e($editing['username'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nível de Acesso</label>
                            <select name="role" class="form-select">
                                <?php foreach ($roleLabels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($editing['role'] ?? 'user') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">E-mail <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required maxlength="100"
                                   value="<?= e($editing['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Senha <?= $editing ? '' : '<span class="text-danger">*</span>' ?></label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" id="pwdField"
                                       placeholder="<?= $editing ? 'Deixe em branco para manter' : '' ?>"
                                       <?= $editing ? '' : 'required' ?> minlength="6">
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleField('pwdField','pwdIcon')">
                                    <i class="bi bi-eye" id="pwdIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-3 pb-1">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="active" class="form-check-input" id="activeUser"
                                       <?= (!isset($editing) || $editing['active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activeUser">Ativo</label>
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="adimplente" class="form-check-input" id="adimplenteUser"
                                       <?= (!isset($editing) || ($editing['adimplente'] ?? 1)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="adimplenteUser">Adimplente</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Dados APEJESE</small></div>
                <div class="col-md-3">
                    <label class="form-label">Matrícula APEJESE</label>
                    <input type="text" name="matricula_apejese" class="form-control" maxlength="50"
                           value="<?= e($editing['matricula_apejese'] ?? '') ?>" placeholder="Ex: 00123">
                </div>
                <div class="col-md-3">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf" class="form-control" maxlength="14"
                           value="<?= e($editing['cpf'] ?? '') ?>" placeholder="000.000.000-00" id="cpfField">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" class="form-control"
                           value="<?= e($editing['data_nascimento'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data de Filiação</label>
                    <input type="date" name="data_filiacao" class="form-control"
                           value="<?= e($editing['data_filiacao'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Registro Profissional</label>
                    <input type="text" name="registro_profissional" class="form-control" maxlength="100"
                           value="<?= e($editing['registro_profissional'] ?? '') ?>" placeholder="Ex: CRC/SE 000000">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Validade da Carteira</label>
                    <input type="date" name="carteira_validade" class="form-control"
                           value="<?= e($editing['carteira_validade'] ?? '') ?>">
                    <div class="form-text" style="font-size:.72rem;">Deixe vazio para usar a validade global.</div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Salvar' : 'Criar' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card admin-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="busca" class="form-control form-control-sm"
                       placeholder="Buscar por nome, usuário, e-mail ou matrícula…"
                       value="<?= e($busca) ?>">
            </div>
            <div class="col-md-2">
                <select name="role" class="form-select form-select-sm">
                    <option value="">Todos os níveis</option>
                    <?php foreach ($roleLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filRole === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sit" class="form-select form-select-sm">
                    <option value="">Toda situação</option>
                    <option value="1" <?= $filSit === '1' ? 'selected' : '' ?>>Adimplente</option>
                    <option value="0" <?= $filSit === '0' ? 'selected' : '' ?>>Inadimplente</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary-custom"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= BASE_URL ?>/admin/usuarios.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card admin-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Lista de Usuários (<?= count($users) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Nome</th>
                        <th>Matrícula</th>
                        <th>Nível</th>
                        <th>Currículos</th>
                        <th>Último Login</th>
                        <th>Status</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <?php if ($u['photo']): ?>
                            <img src="<?= UPLOAD_URL . e($u['photo']) ?>" class="table-avatar"
                                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <?php else: ?>
                            <div style="width:36px;height:36px;border-radius:50%;background:#dde3ee;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-person-fill" style="color:#b0bcd4;"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="fw-medium">
                            <?= e($u['full_name']) ?>
                            <?php if ($u['cpf']): ?>
                            <br><small class="text-muted"><?= e($u['cpf']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code class="small"><?= $u['matricula_apejese'] ? e($u['matricula_apejese']) : '<span class="text-muted">—</span>' ?></code></td>
                        <td><span class="badge <?= $roleBadges[$u['role']] ?? 'bg-secondary' ?>"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
                        <td><?= $u['total_resumes'] ?></td>
                        <td class="text-muted small"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></td>
                        <td>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline" data-no-unsaved>
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="toggle">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="badge border-0 <?= $u['active'] ? 'bg-success' : 'bg-secondary' ?>"
                                        style="cursor:pointer;" title="Clique para alternar">
                                    <?= $u['active'] ? 'Ativo' : 'Inativo' ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <span class="badge bg-success">Ativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="d-inline" data-no-unsaved>
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="toggle_adimplente">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="badge border-0 <?= ($u['adimplente'] ?? 1) ? 'bg-success' : 'bg-danger' ?>"
                                        style="cursor:pointer;" title="Clique para alternar situação">
                                    <?= ($u['adimplente'] ?? 1) ? 'Adimplente' : 'Inadimplente' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-nowrap">
                            <a href="?acao=editar&id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" data-no-unsaved
                                  onsubmit="return confirm('Redefinir senha de <?= e(addslashes($u['full_name'])) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="reset_senha">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-warning me-1" title="Redefinir senha">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline" data-no-unsaved
                                  onsubmit="return confirm('Excluir usuário <?= e(addslashes($u['full_name'])) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Excluir">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Nenhum usuário encontrado</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleField(fieldId, iconId) {
    const f = document.getElementById(fieldId);
    const i = document.getElementById(iconId);
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
const cpfField = document.getElementById('cpfField');
if (cpfField) {
    cpfField.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 9) v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6,9)+'-'+v.slice(9);
        else if (v.length > 6) v = v.slice(0,3)+'.'+v.slice(3,6)+'.'+v.slice(6);
        else if (v.length > 3) v = v.slice(0,3)+'.'+v.slice(3);
        this.value = v;
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
