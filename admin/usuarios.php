<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$acao = sanitize($_GET['acao'] ?? 'listar');
$id   = (int)($_GET['id'] ?? 0);

// DELETE
if ($acao === 'excluir' && $id > 0) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { flash('danger', 'Token inválido.'); }
    elseif ($id === (int)$_SESSION['user_id']) { flash('danger', 'Não é possível excluir seu próprio usuário.'); }
    else {
        db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        flash('success', 'Usuário excluído.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

// TOGGLE ACTIVE
if ($acao === 'toggle' && $id > 0) {
    if (verifyCsrf($_GET['csrf'] ?? '') && $id !== (int)$_SESSION['user_id']) {
        db()->prepare("UPDATE users SET active = NOT active WHERE id = ?")->execute([$id]);
        flash('success', 'Status atualizado.');
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
    $full_name = sanitize($_POST['full_name'] ?? '');
    $username  = sanitize($_POST['username']  ?? '');
    $email     = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $role      = in_array($_POST['role'] ?? '', ['admin','editor','user']) ? $_POST['role'] : 'user';
    $active    = isset($_POST['active']) ? 1 : 0;
    $password  = $_POST['password'] ?? '';
    $editId    = (int)($_POST['edit_id'] ?? 0);

    $errors = [];
    if (empty($full_name)) $errors[] = 'Nome completo é obrigatório.';
    if (empty($username))  $errors[] = 'Nome de usuário é obrigatório.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if ($editId === 0 && empty($password)) $errors[] = 'Senha é obrigatória para novo usuário.';
    if (!empty($password) && strlen($password) < 6) $errors[] = 'Senha deve ter ao menos 6 caracteres.';

    if (empty($errors)) {
        // Check unique username/email
        $stmt = db()->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $editId]);
        if ($stmt->fetch()) $errors[] = 'Usuário ou e-mail já cadastrado.';
    }

    if (!empty($errors)) {
        foreach ($errors as $e) flash('danger', $e);
        redirect(BASE_URL . '/admin/usuarios.php' . ($editId ? "?acao=editar&id=$editId" : '?acao=novo'));
    }

    if ($editId > 0) {
        $sql    = "UPDATE users SET full_name=?, username=?, email=?, role=?, active=?, updated_at=NOW()";
        $params = [$full_name, $username, $email, $role, $active];
        if (!empty($password)) { $sql .= ", password=?"; $params[] = password_hash($password, PASSWORD_BCRYPT); }
        $sql .= " WHERE id=?"; $params[] = $editId;
        db()->prepare($sql)->execute($params);
        flash('success', 'Usuário atualizado com sucesso.');
    } else {
        db()->prepare("INSERT INTO users (full_name, username, email, password, role, active) VALUES (?,?,?,?,?,?)")
            ->execute([$full_name, $username, $email, password_hash($password, PASSWORD_BCRYPT), $role, $active]);
        flash('success', 'Usuário criado com sucesso.');
    }
    redirect(BASE_URL . '/admin/usuarios.php');
}

$users = db()->query("SELECT u.*, (SELECT COUNT(*) FROM resumes r WHERE r.user_id = u.id) AS total_resumes FROM users u ORDER BY u.full_name ASC")->fetchAll();

$roleLabels = ['admin' => 'Administrador', 'editor' => 'Editor', 'user' => 'Usuário'];
$roleBadges = ['admin' => 'bg-danger', 'editor' => 'bg-warning text-dark', 'user' => 'bg-secondary'];

$pageTitle = 'Usuários';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-people me-2"></i>Usuários</h3>
    <?php if ($acao !== 'novo' && !$editing): ?>
    <a href="?acao=novo" class="btn btn-primary-custom"><i class="bi bi-plus-lg me-1"></i>Novo Usuário</a>
    <?php endif; ?>
</div>

<?php if ($acao === 'novo' || $editing): ?>
<div class="card admin-card mb-4">
    <div class="card-header">
        <i class="bi bi-<?= $editing ? 'pencil' : 'person-plus' ?> me-2"></i>
        <?= $editing ? 'Editar Usuário' : 'Novo Usuário' ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <?php if ($editing): ?>
            <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
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
                <div class="col-md-6">
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
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="active" class="form-check-input" id="activeUser"
                               <?= (!isset($editing) || $editing['active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activeUser">Ativo</label>
                    </div>
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

<div class="card admin-card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Lista de Usuários (<?= count($users) ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Nível</th>
                        <th>Currículos</th>
                        <th>Último Login</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="fw-medium"><?= e($u['full_name']) ?></td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="badge <?= $roleBadges[$u['role']] ?? 'bg-secondary' ?>"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
                        <td><?= $u['total_resumes'] ?></td>
                        <td class="text-muted small"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></td>
                        <td>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <a href="?acao=toggle&id=<?= $u['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="badge <?= $u['active'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none">
                                <?= $u['active'] ? 'Ativo' : 'Inativo' ?>
                            </a>
                            <?php else: ?>
                            <span class="badge bg-success">Ativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?acao=editar&id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <a href="?acao=excluir&id=<?= $u['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-xs btn-outline-danger"
                               onclick="return confirm('Excluir usuário <?= e(addslashes($u['full_name'])) ?>?')"
                               title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhum usuário cadastrado</td></tr>
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
