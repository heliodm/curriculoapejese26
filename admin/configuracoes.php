<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/configuracoes.php');
    }

    $section = sanitize($_POST['section'] ?? '');

    if ($section === 'geral') {
        saveSetting('site_name',        sanitize($_POST['site_name'] ?? ''));
        saveSetting('site_description', sanitize($_POST['site_description'] ?? ''));
        saveSetting('hero_title',       sanitize($_POST['hero_title'] ?? ''));
        saveSetting('hero_subtitle',    sanitize($_POST['hero_subtitle'] ?? ''));
        saveSetting('footer_text',      sanitize($_POST['footer_text'] ?? ''));

        // Logo upload
        if (!empty($_FILES['logo']['name'])) {
            $uploaded = uploadFile($_FILES['logo'], 'logos');
            if ($uploaded) {
                $oldLogo = getSetting('logo');
                if ($oldLogo) deleteUpload($oldLogo);
                saveSetting('logo', $uploaded);
                flash('success', 'Logo atualizada.');
            } else {
                flash('warning', 'Logo não pôde ser enviada. Verifique o formato/tamanho.');
            }
        }
        flash('success', 'Configurações gerais salvas.');
    }

    if ($section === 'github') {
        saveSetting('github_repo',           sanitize($_POST['github_repo']           ?? ''));
        saveSetting('github_branch',         sanitize($_POST['github_branch']         ?? 'main'));
        saveSetting('github_token',          sanitize($_POST['github_token']           ?? ''));
        saveSetting('github_webhook_secret', sanitize($_POST['github_webhook_secret'] ?? ''));
        flash('success', 'Configurações do GitHub salvas.');
    }

    if ($section === 'email') {
        saveSetting('mail_enabled',   isset($_POST['mail_enabled']) ? '1' : '0');
        saveSetting('mail_from',      filter_var($_POST['mail_from'] ?? '', FILTER_SANITIZE_EMAIL));
        saveSetting('mail_from_name', sanitize($_POST['mail_from_name'] ?? ''));
        flash('success', 'Configurações de e-mail salvas.');
    }

    if ($section === 'carteira') {
        saveSetting('carteira_cor_header',           sanitize($_POST['carteira_cor_header']           ?? '#1b3a6b'));
        saveSetting('carteira_cor_acento',           sanitize($_POST['carteira_cor_acento']           ?? '#c9a227'));
        saveSetting('carteira_mostrar_registro',     isset($_POST['carteira_mostrar_registro'])     ? '1' : '0');
        saveSetting('carteira_mostrar_filiacao',     isset($_POST['carteira_mostrar_filiacao'])     ? '1' : '0');
        flash('success', 'Configurações da carteira salvas.');
    }

    if ($section === 'menu') {
        $labels  = $_POST['menu_label']  ?? [];
        $urls    = $_POST['menu_url']    ?? [];
        $targets = $_POST['menu_target'] ?? [];
        $actives = $_POST['menu_active'] ?? [];

        db()->exec("DELETE FROM menu_items");
        foreach ($labels as $i => $label) {
            $label = sanitize($label);
            $url   = sanitize($urls[$i] ?? '#');
            if (empty($label)) continue;
            $target = in_array($targets[$i] ?? '_self', ['_self','_blank']) ? $targets[$i] : '_self';
            $active = in_array($i, array_keys($actives)) ? 1 : 0;
            db()->prepare("INSERT INTO menu_items (label, url, order_num, target, active) VALUES (?,?,?,?,?)")
                ->execute([$label, $url, $i, $target, $active]);
        }
        flash('success', 'Menu atualizado com sucesso.');
    }

    redirect(BASE_URL . '/admin/configuracoes.php');
}

$settings   = [];
$settingRows = db()->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($settingRows as $row) $settings[$row['setting_key']] = $row['setting_value'];

$menuItems = db()->query("SELECT * FROM menu_items ORDER BY order_num ASC")->fetchAll();
// Ensure 3 slots
while (count($menuItems) < 3) $menuItems[] = ['label'=>'','url'=>'#','target'=>'_self','active'=>1];

$logoPath = $settings['logo'] ?? null;
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';

$pageTitle = 'Configurações';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-gear me-2"></i>Configurações do Sistema</h3>
</div>

<ul class="nav nav-tabs mb-4" id="configTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGeral">
            <i class="bi bi-sliders me-1"></i>Geral
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMenu">
            <i class="bi bi-list me-1"></i>Menu
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLogo">
            <i class="bi bi-image me-1"></i>Logo
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGithub" id="tabGithubBtn">
            <i class="bi bi-github me-1"></i>GitHub
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEmail" id="tabEmailBtn">
            <i class="bi bi-envelope me-1"></i>E-mail
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCarteira" id="tabCarteiraBtn">
            <i class="bi bi-credit-card me-1"></i>Carteira
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- TAB GERAL -->
    <div class="tab-pane fade show active" id="tabGeral">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Informações do Site</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="geral">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do Site</label>
                            <input type="text" name="site_name" class="form-control" maxlength="100"
                                   value="<?= e($settings['site_name'] ?? APP_NAME) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="site_description" class="form-control" maxlength="200"
                                   value="<?= e($settings['site_description'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Título do Hero (página inicial)</label>
                            <input type="text" name="hero_title" class="form-control" maxlength="100"
                                   value="<?= e($settings['hero_title'] ?? 'Encontre Profissionais') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtítulo do Hero</label>
                            <input type="text" name="hero_subtitle" class="form-control" maxlength="200"
                                   value="<?= e($settings['hero_subtitle'] ?? '') ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Texto do Rodapé</label>
                            <input type="text" name="footer_text" class="form-control" maxlength="300"
                                   value="<?= e($settings['footer_text'] ?? '') ?>"
                                   placeholder="&copy; <?= date('Y') ?> Sistema de Currículos…">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Salvar Configurações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB MENU -->
    <div class="tab-pane fade" id="tabMenu">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-list me-2"></i>Itens do Menu (máx. 3)</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="menu">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Rótulo</th>
                                    <th>URL</th>
                                    <th>Abrir</th>
                                    <th>Ativo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <?php $m = $menuItems[$i] ?? ['label'=>'','url'=>'#','target'=>'_self','active'=>1]; ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td>
                                        <input type="text" name="menu_label[]" class="form-control form-control-sm"
                                               value="<?= e($m['label']) ?>" maxlength="50" placeholder="Ex: Contato">
                                    </td>
                                    <td>
                                        <input type="text" name="menu_url[]" class="form-control form-control-sm"
                                               value="<?= e($m['url']) ?>" maxlength="255" placeholder="https://…">
                                    </td>
                                    <td>
                                        <select name="menu_target[]" class="form-select form-select-sm">
                                            <option value="_self"  <?= ($m['target'] ?? '_self') === '_self'  ? 'selected' : '' ?>>Mesma aba</option>
                                            <option value="_blank" <?= ($m['target'] ?? '_self') === '_blank' ? 'selected' : '' ?>>Nova aba</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="checkbox" name="menu_active[<?= $i ?>]" class="form-check-input"
                                               <?= ($m['active'] ?? 1) ? 'checked' : '' ?>>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="bi bi-check-lg me-1"></i>Salvar Menu
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB LOGO -->
    <div class="tab-pane fade" id="tabLogo">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-image me-2"></i>Logo do Sistema</div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center mb-3 mb-md-0">
                        <div class="logo-preview-box">
                            <img src="<?= e($logoUrl) ?>" id="logoPreview" alt="Logo atual" class="logo-preview"
                                 onerror="this.src='<?= BASE_URL ?>/assets/img/logo-default.png'">
                        </div>
                        <small class="text-muted d-block mt-2">Logo atual</small>
                    </div>
                    <div class="col-md-9">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="section" value="geral">
                            <label class="form-label fw-medium">Enviar Nova Logo</label>
                            <div class="mb-3">
                                <input type="file" name="logo" class="form-control" accept="image/*" id="logoInput">
                                <div class="form-text">Formatos: JPG, PNG, SVG, WEBP — máx 5MB. Tamanho recomendado: 200×60px</div>
                            </div>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-upload me-1"></i>Enviar Logo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB GITHUB -->
    <div class="tab-pane fade" id="tabGithub">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-github me-2"></i>Repositório & Deploy Automático</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Configure o repositório GitHub de onde o sistema será atualizado.
                    Após salvar, acesse <a href="<?= BASE_URL ?>/admin/atualizacao.php">Atualizações</a>
                    para ver as instruções do webhook e atualizar manualmente.
                </p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="github">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-medium">Repositório <span class="text-danger">*</span></label>
                            <input type="text" name="github_repo" class="form-control"
                                   placeholder="usuario/repositorio"
                                   value="<?= e($settings['github_repo'] ?? '') ?>">
                            <div class="form-text">Ex: <code>heliodm/curriculoapejese26</code></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Branch</label>
                            <input type="text" name="github_branch" class="form-control"
                                   placeholder="main"
                                   value="<?= e($settings['github_branch'] ?? 'main') ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-medium">Token de Acesso (opcional)</label>
                            <input type="password" name="github_token" class="form-control"
                                   placeholder="ghp_xxxxxxxxxxxx"
                                   value="<?= e($settings['github_token'] ?? '') ?>"
                                   autocomplete="off">
                            <div class="form-text">Necessário para repositórios privados ou para evitar limite de taxa da API.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-medium">Segredo do Webhook</label>
                            <input type="text" name="github_webhook_secret" class="form-control"
                                   value="<?= e($settings['github_webhook_secret'] ?? '') ?>"
                                   autocomplete="off" placeholder="Ex: uma-frase-secreta-longa">
                            <div class="form-text">Use o mesmo valor ao cadastrar o webhook no GitHub (campo <em>Secret</em>).</div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Salvar
                        </button>
                        <a href="<?= BASE_URL ?>/admin/atualizacao.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-repeat me-1"></i>Ir para Atualizações
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB EMAIL -->
    <div class="tab-pane fade" id="tabEmail">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-envelope me-2"></i>Configurações de E-mail</div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    O sistema usa <code>mail()</code> do PHP. Certifique-se de que o servidor de hospedagem permite envio de e-mails.
                </p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="email">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="mail_enabled" class="form-check-input" id="mailEnabled"
                                       <?= getSetting('mail_enabled', '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium" for="mailEnabled">Habilitar envio de e-mails</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">E-mail de Envio (From)</label>
                            <input type="email" name="mail_from" class="form-control"
                                   value="<?= e(getSetting('mail_from', '')) ?>"
                                   placeholder="noreply@apejese.org.br">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Nome de Exibição</label>
                            <input type="text" name="mail_from_name" class="form-control" maxlength="100"
                                   value="<?= e(getSetting('mail_from_name', 'APEJESE')) ?>"
                                   placeholder="APEJESE">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Salvar E-mail
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB CARTEIRA -->
    <div class="tab-pane fade" id="tabCarteira">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-credit-card me-2"></i>Personalização da Carteira</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="carteira">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Cor do Cabeçalho</label>
                            <div class="input-group">
                                <input type="color" name="carteira_cor_header" class="form-control form-control-color"
                                       value="<?= e(getSetting('carteira_cor_header', '#1b3a6b')) ?>"
                                       style="max-width:60px;">
                                <input type="text" id="corHeaderText" class="form-control form-control-sm"
                                       value="<?= e(getSetting('carteira_cor_header', '#1b3a6b')) ?>"
                                       maxlength="7" pattern="#[0-9a-fA-F]{6}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Cor de Acento (Dourado)</label>
                            <div class="input-group">
                                <input type="color" name="carteira_cor_acento" class="form-control form-control-color"
                                       value="<?= e(getSetting('carteira_cor_acento', '#c9a227')) ?>"
                                       style="max-width:60px;">
                                <input type="text" id="corAcentoText" class="form-control form-control-sm"
                                       value="<?= e(getSetting('carteira_cor_acento', '#c9a227')) ?>"
                                       maxlength="7" pattern="#[0-9a-fA-F]{6}">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="carteira_mostrar_registro" class="form-check-input"
                                       id="mostrarRegistro"
                                       <?= getSetting('carteira_mostrar_registro', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mostrarRegistro">Exibir Registro Profissional na carteira</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="carteira_mostrar_filiacao" class="form-check-input"
                                       id="mostrarFiliacao"
                                       <?= getSetting('carteira_mostrar_filiacao', '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="mostrarFiliacao">Exibir Data de Filiação na carteira</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check-lg me-1"></i>Salvar Carteira
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
// Auto-open tab via URL hash
const tabMap = {
    '#tabGithub':  'tabGithubBtn',
    '#tabEmail':   'tabEmailBtn',
    '#tabCarteira': 'tabCarteiraBtn',
};
if (tabMap[window.location.hash]) {
    document.getElementById(tabMap[window.location.hash])?.click();
} else if (window.location.hash === '#tabGithub') {
    document.getElementById('tabGithubBtn')?.click();
}

// Color pickers sync
function syncColor(pickerId, textId) {
    const picker = document.querySelector('[name="' + pickerId + '"]');
    const text   = document.getElementById(textId);
    if (!picker || !text) return;
    picker.addEventListener('input', () => text.value = picker.value);
    text.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value; });
}
syncColor('carteira_cor_header', 'corHeaderText');
syncColor('carteira_cor_acento', 'corAcentoText');

document.getElementById('logoInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
    reader.readAsDataURL(file);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
