<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$acao = sanitize($_GET['acao'] ?? 'listar');
$id   = (int)($_GET['id'] ?? 0);

// DELETE
if ($acao === 'excluir' && $id > 0) {
    if (!verifyCsrf($_GET['csrf'] ?? '')) { flash('danger', 'Token inválido.'); }
    else {
        $stmt2 = db()->prepare("SELECT COUNT(*) FROM resumes WHERE category_id = ?");
        $stmt2->execute([$id]);
        $countResumes = (int)$stmt2->fetchColumn();
        if ($countResumes > 0) {
            flash('danger', 'Não é possível excluir: há currículos vinculados a esta categoria.');
        } else {
            db()->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            flash('success', 'Categoria excluída com sucesso.');
        }
    }
    redirect(BASE_URL . '/admin/categorias.php');
}

// TOGGLE ACTIVE
if ($acao === 'toggle' && $id > 0) {
    if (verifyCsrf($_GET['csrf'] ?? '')) {
        db()->prepare("UPDATE categories SET active = NOT active WHERE id = ?")->execute([$id]);
        flash('success', 'Status atualizado.');
    }
    redirect(BASE_URL . '/admin/categorias.php');
}

$editing = null;
if ($acao === 'editar' && $id > 0) {
    $stmt = db()->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $editing = $stmt->fetch();
    if (!$editing) { flash('warning', 'Categoria não encontrada.'); redirect(BASE_URL . '/admin/categorias.php'); }
}

// SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/categorias.php');
    }
    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $order_num   = (int)($_POST['order_num'] ?? 0);
    $active      = isset($_POST['active']) ? 1 : 0;
    $editId      = (int)($_POST['edit_id'] ?? 0);

    if (empty($name)) {
        flash('danger', 'O nome da categoria é obrigatório.');
        redirect(BASE_URL . '/admin/categorias.php' . ($editId ? "?acao=editar&id=$editId" : '?acao=novo'));
    }

    $slug = uniqueSlug($name, 'categories', $editId);

    if ($editId > 0) {
        db()->prepare("UPDATE categories SET name=?, slug=?, description=?, order_num=?, active=?, updated_at=NOW() WHERE id=?")
            ->execute([$name, $slug, $description, $order_num, $active, $editId]);
        flash('success', 'Categoria atualizada com sucesso.');
    } else {
        db()->prepare("INSERT INTO categories (name, slug, description, order_num, active) VALUES (?,?,?,?,?)")
            ->execute([$name, $slug, $description, $order_num, $active]);
        flash('success', 'Categoria criada com sucesso.');
    }
    redirect(BASE_URL . '/admin/categorias.php');
}

$categories = db()->query("SELECT c.*, (SELECT COUNT(*) FROM resumes r WHERE r.category_id = c.id) AS total_resumes FROM categories c ORDER BY c.name ASC")->fetchAll();

$pageTitle = 'Categorias';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0"><i class="bi bi-tags me-2"></i>Categorias</h3>
    <?php if ($acao !== 'novo' && !$editing): ?>
    <a href="?acao=novo" class="btn btn-primary-custom"><i class="bi bi-plus-lg me-1"></i>Nova Categoria</a>
    <?php endif; ?>
</div>

<!-- Form -->
<?php if ($acao === 'novo' || $editing): ?>
<div class="card admin-card mb-4">
    <div class="card-header">
        <i class="bi bi-<?= $editing ? 'pencil' : 'plus-circle' ?> me-2"></i>
        <?= $editing ? 'Editar Categoria' : 'Nova Categoria' ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= csrfField() ?>
            <?php if ($editing): ?>
            <input type="hidden" name="edit_id" value="<?= $editing['id'] ?>">
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= e($editing['name'] ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ordem</label>
                    <input type="number" name="order_num" class="form-control" min="0"
                           value="<?= e((string)($editing['order_num'] ?? 0)) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input type="checkbox" name="active" class="form-check-input" id="active"
                               <?= (!isset($editing) || $editing['active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Ativa</label>
                    </div>
                </div>
                <div class="col-md-10">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="2" maxlength="500"><?= e($editing['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Salvar' : 'Criar' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/categorias.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- List -->
<div class="card admin-card">
    <div class="card-header"><i class="bi bi-list-ul me-2"></i>Lista de Categorias (<?= count($categories) ?>)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Slug</th>
                        <th>Currículos</th>
                        <th>Ordem</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="fw-medium"><?= e($cat['name']) ?></td>
                        <td><code class="small"><?= e($cat['slug']) ?></code></td>
                        <td><span class="badge bg-primary-soft"><?= $cat['total_resumes'] ?></span></td>
                        <td><?= $cat['order_num'] ?></td>
                        <td>
                            <a href="?acao=toggle&id=<?= $cat['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="badge <?= $cat['active'] ? 'bg-success' : 'bg-secondary' ?> text-decoration-none">
                                <?= $cat['active'] ? 'Ativa' : 'Inativa' ?>
                            </a>
                        </td>
                        <td>
                            <a href="?acao=editar&id=<?= $cat['id'] ?>" class="btn btn-xs btn-outline-primary me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($cat['total_resumes'] == 0): ?>
                            <a href="?acao=excluir&id=<?= $cat['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>"
                               class="btn btn-xs btn-outline-danger"
                               onclick="return confirm('Excluir categoria <?= e(addslashes($cat['name'])) ?>?')"
                               title="Excluir">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma categoria cadastrada</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
