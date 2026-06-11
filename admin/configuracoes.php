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
    // Volta para a aba que o usuário estava após salvar
    $tabHash = [
        'geral'      => '#tabGeral',   'menu'    => '#tabMenu',
        'logo'       => '#tabLogo',    'github'  => '#tabGithub',
        'email'      => '#tabEmail',   'email_test' => '#tabEmail',
        'carteira'   => '#tabCarteira',
    ][$section] ?? '';

    if ($section === 'geral') {
        saveSetting('site_name',        sanitize($_POST['site_name'] ?? ''));
        saveSetting('site_description', sanitize($_POST['site_description'] ?? ''));
        saveSetting('hero_title',       sanitize($_POST['hero_title'] ?? ''));
        saveSetting('hero_subtitle',    sanitize($_POST['hero_subtitle'] ?? ''));
        saveSetting('footer_text',      sanitize($_POST['footer_text'] ?? ''));
        flash('success', 'Configurações gerais salvas.');
    }

    if ($section === 'logo') {
        if (!empty($_POST['remove_logo'])) {
            $oldLogo = getSetting('logo');
            if ($oldLogo) deleteUpload($oldLogo);
            saveSetting('logo', '');
            flash('success', 'Logo removida. O sistema usará a logo padrão.');
        } elseif (!empty($_FILES['logo']['name'])) {
            $uploaded = uploadFile($_FILES['logo'], 'logos');
            if ($uploaded) {
                $oldLogo = getSetting('logo');
                if ($oldLogo) deleteUpload($oldLogo);
                saveSetting('logo', $uploaded);
                flash('success', 'Logo atualizada.');
            } else {
                flash('warning', 'Logo não pôde ser enviada. Use JPG, PNG, GIF ou WebP com até 5MB.');
            }
        } else {
            flash('info', 'Nenhuma alteração: selecione um arquivo ou marque "remover logo".');
        }
    }

    if ($section === 'github') {
        saveSetting('github_repo',   sanitize($_POST['github_repo']   ?? ''));
        saveSetting('github_branch', sanitize($_POST['github_branch'] ?? 'main'));

        // Token e segredo: campo em branco mantém o valor atual (nunca são ecoados no HTML)
        if (!empty($_POST['github_token_clear'])) {
            saveSetting('github_token', '');
        } elseif (trim($_POST['github_token'] ?? '') !== '') {
            saveSetting('github_token', sanitize($_POST['github_token']));
        }
        if (!empty($_POST['github_webhook_clear'])) {
            saveSetting('github_webhook_secret', '');
        } elseif (trim($_POST['github_webhook_secret'] ?? '') !== '') {
            saveSetting('github_webhook_secret', sanitize($_POST['github_webhook_secret']));
        }
        flash('success', 'Configurações do GitHub salvas.');
    }

    if ($section === 'email') {
        saveSetting('mail_enabled',   isset($_POST['mail_enabled']) ? '1' : '0');
        saveSetting('mail_from',      filter_var($_POST['mail_from'] ?? '', FILTER_SANITIZE_EMAIL));
        saveSetting('mail_from_name', sanitize($_POST['mail_from_name'] ?? ''));
        saveSetting('mail_reply_to',  filter_var($_POST['mail_reply_to'] ?? '', FILTER_SANITIZE_EMAIL));
        saveSetting('mail_smtp_host', sanitize($_POST['mail_smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['mail_smtp_port'] ?? 587);
        saveSetting('mail_smtp_port', (string)($smtpPort > 0 ? $smtpPort : 587));
        $smtpEnc  = $_POST['mail_smtp_encryption'] ?? 'tls';
        saveSetting('mail_smtp_encryption', in_array($smtpEnc, ['tls','ssl','none']) ? $smtpEnc : 'tls');
        saveSetting('mail_smtp_user', sanitize($_POST['mail_smtp_user'] ?? ''));
        if (!empty($_POST['mail_smtp_pass_clear'])) {
            saveSetting('mail_smtp_pass', '');
        } elseif (trim($_POST['mail_smtp_pass'] ?? '') !== '') {
            saveSetting('mail_smtp_pass', trim($_POST['mail_smtp_pass']));
        }
        flash('success', 'Configurações de e-mail salvas.');
    }

    if ($section === 'email_test') {
        $me = currentUser();
        $to = $me['email'] ?? '';
        if (getSetting('mail_enabled', '0') !== '1') {
            flash('warning', 'Habilite e salve o envio de e-mails antes de testar.');
        } elseif (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Seu usuário não possui um e-mail válido cadastrado.');
        } elseif (!rateLimitCheck('email_test', 3, 300)) {
            flash('warning', 'Muitos testes seguidos. Aguarde alguns minutos.');
        } else {
            $ok = sendMail($to, $me['full_name'] ?? '', 'Teste de e-mail — ' . getSetting('site_name', 'APEJESE'),
                emailTemplate(
                    '<p>Este é um <strong>e-mail de teste</strong> enviado pelo painel de configurações.</p>'
                    . '<p>Se você recebeu esta mensagem, o envio de e-mails do sistema está funcionando corretamente.</p>'
                ));
            flash($ok ? 'success' : 'danger', $ok
                ? "E-mail de teste enviado para {$to}. Verifique a caixa de entrada (e a pasta de spam)."
                : 'Falha no envio. Verifique se o servidor permite a função mail() e se o e-mail de envio é válido.');
        }
    }

    if ($section === 'carteira') {
        $corHeader = $_POST['carteira_cor_header'] ?? '#1b3a6b';
        $corAcento = $_POST['carteira_cor_acento'] ?? '#c9a227';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corHeader)) $corHeader = '#1b3a6b';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corAcento)) $corAcento = '#c9a227';
        saveSetting('carteira_cor_header',       $corHeader);
        saveSetting('carteira_cor_acento',       $corAcento);
        saveSetting('carteira_mostrar_registro', isset($_POST['carteira_mostrar_registro']) ? '1' : '0');
        saveSetting('carteira_mostrar_filiacao', isset($_POST['carteira_mostrar_filiacao']) ? '1' : '0');
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
            $url   = sanitize($urls[$i] ?? '#') ?: '#';
            if (empty($label)) continue;
            $target = in_array($targets[$i] ?? '_self', ['_self','_blank']) ? $targets[$i] : '_self';
            $active = in_array($i, array_keys($actives)) ? 1 : 0;
            db()->prepare("INSERT INTO menu_items (label, url, order_num, target, active) VALUES (?,?,?,?,?)")
                ->execute([$label, $url, $i, $target, $active]);
        }
        flash('success', 'Menu atualizado com sucesso.');
    }

    redirect(BASE_URL . '/admin/configuracoes.php' . $tabHash);
}

$settings   = [];
$settingRows = db()->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($settingRows as $row) $settings[$row['setting_key']] = $row['setting_value'];

$menuItems = db()->query("SELECT * FROM menu_items ORDER BY order_num ASC")->fetchAll();
// Ensure 3 slots
while (count($menuItems) < 3) $menuItems[] = ['label'=>'','url'=>'#','target'=>'_self','active'=>1];

$logoPath = $settings['logo'] ?? null;
$logoUrl  = $logoPath ? UPLOAD_URL . $logoPath : BASE_URL . '/assets/img/logo-default.png';

$hasToken       = !empty($settings['github_token']);
$hasWebhook     = !empty($settings['github_webhook_secret']);
$mailEnabled    = ($settings['mail_enabled'] ?? '0') === '1';
$mailFromOk     = !empty($settings['mail_from']) && filter_var($settings['mail_from'], FILTER_VALIDATE_EMAIL);
$smtpHost       = $settings['mail_smtp_host'] ?? '';
$smtpPort       = $settings['mail_smtp_port'] ?? '587';
$smtpEnc        = $settings['mail_smtp_encryption'] ?? 'tls';
$smtpUser       = $settings['mail_smtp_user'] ?? '';
$smtpHasPass    = !empty($settings['mail_smtp_pass']);
$mailReplyTo    = $settings['mail_reply_to'] ?? '';
$smtpConfigured = $smtpHost !== '' && $smtpUser !== '' && $smtpHasPass;
$corHeader    = $settings['carteira_cor_header'] ?? '#1b3a6b';
$corAcento    = $settings['carteira_cor_acento'] ?? '#c9a227';

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
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGithub">
            <i class="bi bi-github me-1"></i>GitHub
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEmail">
            <i class="bi bi-envelope me-1"></i>E-mail
            <span class="badge ms-1 <?= $mailEnabled ? 'bg-success' : 'bg-secondary' ?>" style="font-size:.6rem;">
                <?= $mailEnabled ? 'ON' : 'OFF' ?>
            </span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCarteira">
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
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="geral">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do Site</label>
                            <input type="text" name="site_name" class="form-control" maxlength="100"
                                   value="<?= e($settings['site_name'] ?? APP_NAME) ?>">
                            <div class="form-text">Aparece no cabeçalho, e-mails e título das páginas.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descrição</label>
                            <input type="text" name="site_description" class="form-control" maxlength="200"
                                   value="<?= e($settings['site_description'] ?? '') ?>">
                            <div class="form-text">Usada em mecanismos de busca e na tela de login.</div>
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
                    <div class="form-text mb-2">Deixe o rótulo em branco para remover um item do menu.</div>
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
                                 data-fallback="<?= BASE_URL ?>/assets/img/logo-default.png">
                        </div>
                        <small class="text-muted d-block mt-2">
                            <?= $logoPath ? 'Logo personalizada' : 'Logo padrão' ?>
                        </small>
                    </div>
                    <div class="col-md-9">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="section" value="logo">
                            <label class="form-label fw-medium">Enviar Nova Logo</label>
                            <div class="mb-2">
                                <input type="file" name="logo" class="form-control" accept="image/*" id="logoInput">
                                <div class="form-text">Formatos: JPG, PNG, GIF ou WebP — máx. 5MB. Tamanho recomendado: 200×60px.</div>
                            </div>
                            <?php if ($logoPath): ?>
                            <div class="form-check mb-3">
                                <input type="checkbox" name="remove_logo" value="1" class="form-check-input" id="removeLogo">
                                <label class="form-check-label text-danger small" for="removeLogo">
                                    Remover logo personalizada (volta à logo padrão)
                                </label>
                            </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary-custom">
                                <i class="bi bi-upload me-1"></i>Salvar Logo
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
                            <label class="form-label fw-medium">
                                Token de Acesso
                                <?php if ($hasToken): ?>
                                <span class="badge bg-success ms-1" style="font-size:.62rem;">CONFIGURADO</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" name="github_token" class="form-control"
                                   placeholder="<?= $hasToken ? '••••••••  (deixe em branco para manter o atual)' : 'ghp_xxxxxxxxxxxx' ?>"
                                   autocomplete="new-password">
                            <div class="form-text">Necessário para repositórios privados. Por segurança, o token salvo nunca é exibido.</div>
                            <?php if ($hasToken): ?>
                            <div class="form-check mt-1">
                                <input type="checkbox" name="github_token_clear" value="1" class="form-check-input" id="tokenClear">
                                <label class="form-check-label text-danger small" for="tokenClear">Remover token salvo</label>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-medium">
                                Segredo do Webhook
                                <?php if ($hasWebhook): ?>
                                <span class="badge bg-success ms-1" style="font-size:.62rem;">CONFIGURADO</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.62rem;">PENDENTE</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <input type="text" name="github_webhook_secret" class="form-control" id="webhookSecretInput"
                                       placeholder="<?= $hasWebhook ? '••••••••  (deixe em branco para manter o atual)' : 'Clique em Gerar ou digite uma frase longa' ?>"
                                       autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" id="genSecretBtn">
                                    <i class="bi bi-shuffle me-1"></i>Gerar
                                </button>
                            </div>
                            <div class="form-text">
                                Use o mesmo valor ao cadastrar o webhook no GitHub (campo <em>Secret</em>).
                                Copie o valor gerado <strong>antes de salvar</strong> — ele não será exibido depois.
                            </div>
                            <?php if ($hasWebhook): ?>
                            <div class="form-check mt-1">
                                <input type="checkbox" name="github_webhook_clear" value="1" class="form-check-input" id="webhookClear">
                                <label class="form-check-label text-danger small" for="webhookClear">Remover segredo salvo (desativa o webhook)</label>
                            </div>
                            <?php endif; ?>
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
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="email">

            <!-- Básico -->
            <div class="card admin-card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-envelope me-1"></i>Configurações Básicas
                    <?php if ($mailEnabled && $smtpConfigured): ?>
                        <span class="badge bg-success ms-auto" style="font-size:.65rem;">SMTP ATIVO</span>
                    <?php elseif ($mailEnabled): ?>
                        <span class="badge bg-warning text-dark ms-auto" style="font-size:.65rem;">mail() — sem SMTP</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="mail_enabled" class="form-check-input" id="mailEnabled"
                                       <?= $mailEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label fw-medium" for="mailEnabled">Habilitar envio de e-mails</label>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium">E-mail de Envio (From) <span class="text-danger">*</span></label>
                            <input type="email" name="mail_from" class="form-control"
                                   value="<?= e($settings['mail_from'] ?? '') ?>"
                                   placeholder="noreply@apejese.org.br">
                            <?php if ($mailEnabled && !$mailFromOk): ?>
                            <div class="text-danger small mt-1">
                                <i class="bi bi-exclamation-circle me-1"></i>E-mail de envio inválido — nenhuma mensagem será enviada.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Nome de Exibição</label>
                            <input type="text" name="mail_from_name" class="form-control" maxlength="100"
                                   value="<?= e($settings['mail_from_name'] ?? 'APEJESE') ?>"
                                   placeholder="APEJESE">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Reply-To <span class="text-muted fw-normal small">(opcional)</span></label>
                            <input type="email" name="mail_reply_to" class="form-control"
                                   value="<?= e($mailReplyTo) ?>"
                                   placeholder="contato@apejese.org.br">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMTP -->
            <div class="card admin-card mb-3">
                <div class="card-header">
                    <i class="bi bi-shield-lock me-1"></i>Servidor SMTP
                    <span class="text-muted fw-normal small ms-2">— recomendado para evitar spam</span>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Configure um servidor SMTP autenticado para que os e-mails cheguem na caixa de entrada.
                        Sem SMTP, o PHP usa <code>sendmail</code> local, que costuma ser bloqueado como spam.<br>
                        <strong>Provedores recomendados:</strong>
                        Gmail (porta 587 · TLS), Brevo (porta 587 · TLS), SendGrid (porta 587 · TLS), Outlook/Office 365 (porta 587 · TLS).
                    </p>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-medium">Servidor SMTP (Host)</label>
                            <input type="text" name="mail_smtp_host" class="form-control"
                                   value="<?= e($smtpHost) ?>"
                                   placeholder="smtp.gmail.com">
                            <div class="form-text">Ex: smtp.gmail.com · smtp-relay.brevo.com · smtp.sendgrid.net</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Porta</label>
                            <input type="number" name="mail_smtp_port" class="form-control"
                                   value="<?= e($smtpPort) ?>" min="1" max="65535" placeholder="587">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Criptografia</label>
                            <select name="mail_smtp_encryption" class="form-select">
                                <option value="tls"  <?= $smtpEnc === 'tls'  ? 'selected' : '' ?>>TLS / STARTTLS (porta 587)</option>
                                <option value="ssl"  <?= $smtpEnc === 'ssl'  ? 'selected' : '' ?>>SSL (porta 465)</option>
                                <option value="none" <?= $smtpEnc === 'none' ? 'selected' : '' ?>>Nenhuma (não recomendado)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <?php /* spacer */ ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium">Usuário SMTP</label>
                            <input type="text" name="mail_smtp_user" class="form-control"
                                   value="<?= e($smtpUser) ?>"
                                   placeholder="seu@email.com" autocomplete="off">
                            <div class="form-text">Normalmente o mesmo e-mail do "From".</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium">
                                Senha SMTP
                                <?php if ($smtpHasPass): ?>
                                <span class="badge bg-success ms-1" style="font-size:.62rem;">CONFIGURADA</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" name="mail_smtp_pass" class="form-control"
                                   placeholder="<?= $smtpHasPass ? 'Deixe em branco para manter' : 'Senha ou token de aplicativo' ?>"
                                   autocomplete="new-password">
                            <div class="form-text">
                                Para Gmail use uma <strong>Senha de App</strong> (não a senha da conta).
                                <?php if ($smtpHasPass): ?>
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="mail_smtp_pass_clear" value="1" class="form-check-input" id="smtpPassClear">
                                    <label class="form-check-label text-danger small" for="smtpPassClear">Remover senha salva</label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($smtpHost && (!$smtpUser || !$smtpHasPass)): ?>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Host SMTP configurado mas usuário ou senha estão em falta — a autenticação falhará.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="bi bi-check-lg me-1"></i>Salvar Configurações de E-mail
                </button>
            </div>
        </form>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-send-check me-2"></i>Testar Envio</div>
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <p class="text-muted small mb-0">
                    Envia um e-mail de teste para o seu endereço cadastrado
                    (<strong><?= e(currentUser()['email'] ?? '—') ?></strong>)
                    <?= $smtpConfigured ? 'via SMTP configurado' : 'via mail() do PHP' ?>.
                </p>
                <form method="POST" data-no-unsaved>
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="email_test">
                    <button type="submit" class="btn btn-outline-primary" <?= $mailEnabled ? '' : 'disabled title="Habilite o envio de e-mails primeiro"' ?>>
                        <i class="bi bi-send me-1"></i>Enviar E-mail de Teste
                    </button>
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
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Cor do Cabeçalho</label>
                                    <div class="input-group">
                                        <input type="color" name="carteira_cor_header" class="form-control form-control-color"
                                               value="<?= e($corHeader) ?>" style="max-width:60px;">
                                        <input type="text" id="corHeaderText" class="form-control form-control-sm"
                                               value="<?= e($corHeader) ?>"
                                               maxlength="7" pattern="#[0-9a-fA-F]{6}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Cor de Acento (Dourado)</label>
                                    <div class="input-group">
                                        <input type="color" name="carteira_cor_acento" class="form-control form-control-color"
                                               value="<?= e($corAcento) ?>" style="max-width:60px;">
                                        <input type="text" id="corAcentoText" class="form-control form-control-sm"
                                               value="<?= e($corAcento) ?>"
                                               maxlength="7" pattern="#[0-9a-fA-F]{6}">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="carteira_mostrar_registro" class="form-check-input"
                                               id="mostrarRegistro"
                                               <?= ($settings['carteira_mostrar_registro'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mostrarRegistro">Exibir Registro Profissional na carteira</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="carteira_mostrar_filiacao" class="form-check-input"
                                               id="mostrarFiliacao"
                                               <?= ($settings['carteira_mostrar_filiacao'] ?? '1') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mostrarFiliacao">Exibir Data de Filiação na carteira</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-check-lg me-1"></i>Salvar Carteira
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label fw-medium text-muted" style="font-size:.78rem;">PRÉ-VISUALIZAÇÃO</label>
                            <div id="carteiraPreview" style="max-width:330px;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.18);font-size:.7rem;">
                                <div id="cpHeader" style="background:<?= e($corHeader) ?>;color:#fff;padding:.7rem 1rem;border-bottom:3px solid <?= e($corAcento) ?>;">
                                    <div style="font-weight:800;letter-spacing:.04em;">APEJESE</div>
                                    <div style="opacity:.7;font-size:.6rem;">CARTEIRA DE ASSOCIADO</div>
                                </div>
                                <div style="background:#fff;padding:.8rem 1rem;display:flex;gap:.8rem;align-items:center;">
                                    <div style="width:44px;height:54px;background:#dde3ee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#b0bcd4;">
                                        <i class="bi bi-person-fill" style="font-size:1.4rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;color:#1a2035;">Nome do Associado</div>
                                        <div style="color:#8a94a8;">Matrícula: 0000</div>
                                        <div id="cpAccent" style="color:<?= e($corAcento) ?>;font-weight:700;">Validade: 12/2026</div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text mt-2">As cores se aplicam à carteira em PDF gerada para cada associado.</div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
document.addEventListener('DOMContentLoaded', function () {
    // Abre a aba indicada pelo hash da URL (ex.: após salvar uma seção)
    if (window.location.hash) {
        var btn = document.querySelector('#configTabs button[data-bs-target="' + window.location.hash + '"]');
        if (btn) btn.click();
    }
    // Atualiza o hash ao trocar de aba (mantém a aba após F5)
    document.querySelectorAll('#configTabs button[data-bs-target]').forEach(function (b) {
        b.addEventListener('shown.bs.tab', function () {
            history.replaceState(null, '', b.getAttribute('data-bs-target'));
        });
    });

    // Sincroniza color picker <-> campo texto e pré-visualização da carteira
    function syncColor(pickerName, textId, apply) {
        var picker = document.querySelector('[name="' + pickerName + '"]');
        var text   = document.getElementById(textId);
        if (!picker || !text) return;
        picker.addEventListener('input', function () { text.value = picker.value; apply(picker.value); });
        text.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) { picker.value = text.value; apply(text.value); }
        });
    }
    var cpHeader = document.getElementById('cpHeader');
    var cpAccent = document.getElementById('cpAccent');
    syncColor('carteira_cor_header', 'corHeaderText', function (v) {
        if (cpHeader) cpHeader.style.background = v;
    });
    syncColor('carteira_cor_acento', 'corAcentoText', function (v) {
        if (cpHeader) cpHeader.style.borderBottomColor = v;
        if (cpAccent) cpAccent.style.color = v;
    });

    // Pré-visualização da logo ao selecionar arquivo
    var logoInput = document.getElementById('logoInput');
    if (logoInput) {
        logoInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (ev) { document.getElementById('logoPreview').src = ev.target.result; };
            reader.readAsDataURL(file);
        });
    }
    var logoPreview = document.getElementById('logoPreview');
    if (logoPreview) {
        logoPreview.addEventListener('error', function () {
            if (this.src !== this.dataset.fallback) this.src = this.dataset.fallback;
        });
    }

    // Gerar segredo aleatório do webhook
    var genBtn = document.getElementById('genSecretBtn');
    if (genBtn) {
        genBtn.addEventListener('click', function () {
            var arr = new Uint8Array(24);
            crypto.getRandomValues(arr);
            var hex = Array.from(arr, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
            var input = document.getElementById('webhookSecretInput');
            input.value = hex;
            input.focus();
            input.select();
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
