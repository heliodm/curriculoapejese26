<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $allLogs = db()->query(
        "SELECT l.created_at, u.full_name AS user_name, l.action, l.details, l.ip, a.full_name AS admin_name
         FROM user_logs l
         LEFT JOIN users u ON u.id = l.user_id
         LEFT JOIN users a ON a.id = l.admin_id
         ORDER BY l.created_at DESC"
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Data/Hora', 'Usuário Afetado', 'Ação', 'Detalhes', 'IP', 'Executado por'], ';');
    foreach ($allLogs as $row) {
        fputcsv($out, [
            $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '',
            $row['user_name'] ?? '',
            $row['action'],
            $row['details'] ?? '',
            $row['ip'] ?? '',
            $row['admin_name'] ?? 'Sistema',
        ], ';');
    }
    fclose($out);
    exit;
}

$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$busca   = sanitize($_GET['busca'] ?? '');
$filAcao = sanitize($_GET['acao_filtro'] ?? '');

$where  = ['1=1'];
$params = [];
if ($busca !== '') {
    $like = '%' . $busca . '%';
    $where[]  = "(u.full_name LIKE ? OR l.action LIKE ? OR l.details LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filAcao !== '') {
    $where[]  = "l.action = ?";
    $params[] = $filAcao;
}
$whereClause = implode(' AND ', $where);

$total = (int)db()->prepare(
    "SELECT COUNT(*) FROM user_logs l LEFT JOIN users u ON u.id = l.user_id WHERE {$whereClause}"
)->execute($params) ? (int)db()->prepare(
    "SELECT COUNT(*) FROM user_logs l LEFT JOIN users u ON u.id = l.user_id WHERE {$whereClause}"
)->execute($params) : 0;

// Re-query with proper params for count
$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM user_logs l LEFT JOIN users u ON u.id = l.user_id WHERE {$whereClause}"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pag    = paginate($total, $perPage, $page);
$params2 = array_merge($params, [$pag['offset'], $perPage]);
$logStmt = db()->prepare(
    "SELECT l.*, u.full_name AS user_name, a.full_name AS admin_name,
            l.ip
     FROM user_logs l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN users a ON a.id = l.admin_id
     WHERE {$whereClause}
     ORDER BY l.created_at DESC
     LIMIT ?, ?"
);
$logStmt->execute($params2);
$logs = $logStmt->fetchAll();

// Get distinct actions for filter
$acoes = db()->query("SELECT DISTINCT action FROM user_logs ORDER BY action ASC")->fetchAll(\PDO::FETCH_COLUMN);

$actionIcons = [
    'excluir_usuario'    => ['bi-trash',          'text-danger'],
    'toggle_ativo'       => ['bi-toggle-on',       'text-secondary'],
    'toggle_adimplente'  => ['bi-currency-dollar', 'text-warning'],
    'reset_senha'        => ['bi-key',             'text-info'],
    'editar_usuario'     => ['bi-pencil',          'text-primary'],
    'criar_usuario'      => ['bi-person-plus',     'text-success'],
];

$pageTitle = 'Logs de Atividade';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h3><i class="bi bi-journal-text me-2"></i>Logs de Atividade</h3>
        <small class="text-muted"><?= number_format($total) ?> registro<?= $total !== 1 ? 's' : '' ?></small>
    </div>
    <a href="?export=csv" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar CSV
    </a>
</div>

<!-- Filtros -->
<div class="card admin-card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="busca" class="form-control form-control-sm"
                       placeholder="Buscar por usuário, ação ou detalhes…"
                       value="<?= e($busca) ?>">
            </div>
            <div class="col-md-3">
                <select name="acao_filtro" class="form-select form-select-sm">
                    <option value="">Todas as ações</option>
                    <?php foreach ($acoes as $a): ?>
                    <option value="<?= e($a) ?>" <?= $filAcao === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary-custom"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= BASE_URL ?>/admin/logs.php" class="btn btn-sm btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="card admin-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário Afetado</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                        <th>Executado por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php
                    $icon  = $actionIcons[$log['action']] ?? ['bi-circle', 'text-muted'];
                    ?>
                    <tr>
                        <td class="text-muted small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td class="fw-medium"><?= $log['user_name'] ? e($log['user_name']) : '<span class="text-muted">—</span>' ?></td>
                        <td>
                            <span class="d-flex align-items-center gap-1">
                                <i class="bi <?= $icon[0] ?> <?= $icon[1] ?>"></i>
                                <code class="small"><?= e($log['action']) ?></code>
                            </span>
                        </td>
                        <td class="text-muted small"><?= $log['details'] ? e($log['details']) : '—' ?></td>
                        <td class="text-muted small text-nowrap"><?= !empty($log['ip']) ? e($log['ip']) : '—' ?></td>
                        <td class="small"><?= $log['admin_name'] ? e($log['admin_name']) : '<span class="text-muted">Sistema</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($pag['total_pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $pag['total_pages']; $i++): ?>
        <li class="page-item <?= $i === $pag['current'] ? 'active' : '' ?>">
            <a class="page-link" href="?p=<?= $i ?>&busca=<?= urlencode($busca) ?>&acao_filtro=<?= urlencode($filAcao) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
