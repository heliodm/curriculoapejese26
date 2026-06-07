<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$format  = sanitize($_GET['format']  ?? '');
$dataset = sanitize($_GET['dataset'] ?? 'usuarios');

if ($format === 'csv' && $dataset === 'curriculos') {
    $rows = db()->query(
        "SELECT r.name, r.profession, r.formation, r.email, r.phone, r.whatsapp,
                r.about, r.slug, r.active, r.consent, r.views, r.created_at, r.updated_at,
                c.name AS categoria, u.username, u.full_name AS usuario
         FROM resumes r
         LEFT JOIN categories c ON c.id = r.category_id
         LEFT JOIN users u ON u.id = r.user_id
         ORDER BY r.name ASC"
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="curriculos_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, [
        'Nome', 'Profissão', 'Formação', 'E-mail', 'Telefone', 'WhatsApp',
        'Sobre', 'Slug', 'Ativo', 'Consentimento', 'Visualizações',
        'Criado em', 'Atualizado em', 'Categoria', 'Usuário (login)', 'Usuário (nome)',
    ], ';');

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['name'],
            $row['profession'] ?? '',
            $row['formation'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['whatsapp'] ?? '',
            $row['about'] ?? '',
            $row['slug'],
            $row['active'] ? 'Ativo' : 'Inativo',
            ($row['consent'] ?? 0) ? 'Sim' : 'Não',
            $row['views'] ?? 0,
            $row['created_at'] ? date('d/m/Y H:i', strtotime($row['created_at'])) : '',
            $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '',
            $row['categoria'] ?? '',
            $row['username'] ?? '',
            $row['usuario'] ?? '',
        ], ';');
    }

    fclose($out);
    exit;
}

if ($format === 'csv') {
    $users = db()->query(
        "SELECT u.full_name, u.username, u.email, u.role, u.active, u.adimplente,
                u.matricula_apejese, u.cpf, u.data_nascimento, u.data_filiacao,
                u.registro_profissional, u.carteira_validade, u.last_login, u.created_at
         FROM users u
         ORDER BY u.full_name ASC"
    )->fetchAll();

    $roleLabels = ['admin' => 'Administrador', 'editor' => 'Editor', 'user' => 'Usuário'];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="usuarios_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

    fputcsv($out, [
        'Nome Completo', 'Usuário', 'E-mail', 'Nível', 'Ativo', 'Situação',
        'Matrícula APEJESE', 'CPF', 'Data de Nascimento', 'Data de Filiação',
        'Registro Profissional', 'Validade Carteira', 'Último Login', 'Cadastrado em',
    ], ';');

    foreach ($users as $u) {
        fputcsv($out, [
            $u['full_name'],
            $u['username'],
            $u['email'],
            $roleLabels[$u['role']] ?? $u['role'],
            $u['active'] ? 'Ativo' : 'Inativo',
            ($u['adimplente'] ?? 1) ? 'Adimplente' : 'Inadimplente',
            $u['matricula_apejese'] ?? '',
            $u['cpf'] ?? '',
            $u['data_nascimento'] ? date('d/m/Y', strtotime($u['data_nascimento'])) : '',
            $u['data_filiacao']   ? date('d/m/Y', strtotime($u['data_filiacao']))   : '',
            $u['registro_profissional'] ?? '',
            $u['carteira_validade'] ? date('d/m/Y', strtotime($u['carteira_validade'])) : '',
            $u['last_login']  ? date('d/m/Y H:i', strtotime($u['last_login']))  : '',
            $u['created_at']  ? date('d/m/Y H:i', strtotime($u['created_at']))  : '',
        ], ';');
    }

    fclose($out);
    exit;
}

// Default: show page with export options
$pageTitle = 'Exportar Dados';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-download me-2"></i>Exportar Dados</h3>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-people me-1"></i>Exportar Usuários</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Exporta todos os associados com seus dados cadastrais, situação financeira,
                    matrícula, CPF, datas e registro profissional.
                </p>
                <a href="?format=csv&dataset=usuarios" class="btn btn-primary-custom">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Baixar CSV (Usuários)
                </a>
                <div class="form-text mt-2">Formato compatível com Excel, Google Sheets, LibreOffice.</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-file-person me-1"></i>Exportar Currículos</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Exporta todos os currículos com dados de contato, profissão, formação,
                    categoria, visualizações e status de consentimento.
                </p>
                <a href="?format=csv&dataset=curriculos" class="btn btn-primary-custom">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>Baixar CSV (Currículos)
                </a>
                <div class="form-text mt-2">Formato compatível com Excel, Google Sheets, LibreOffice.</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
