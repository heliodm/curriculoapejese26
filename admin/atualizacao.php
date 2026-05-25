<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/atualizacao.php');
    }
    if (($_POST['action'] ?? '') === 'update') {
        set_time_limit(300);
        $result = performUpdate();
        flash($result['success'] ? 'success' : 'danger', $result['message']);
    }
    redirect(BASE_URL . '/admin/atualizacao.php');
}

$repo   = getSetting('github_repo', '');
$branch = getSetting('github_branch', 'main');
$token  = getSetting('github_token', '');

$versionFile    = SITE_ROOT . '/version.txt';
$currentVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : null;

// Fetch latest commit from GitHub if repo is configured
$latestCommit = null;
$commitError  = null;
if ($repo) {
    $latestCommit = githubLatestCommit($repo, $branch, $token);
    if (isset($latestCommit['error'])) {
        $commitError  = $latestCommit['error'];
        $latestCommit = null;
    }
}

// Read update log (last 20 lines, newest first)
$logFile  = SITE_ROOT . '/logs/updates.log';
$logLines = [];
if (file_exists($logFile)) {
    $all      = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice(array_reverse($all), 0, 20);
}

$webhookUrl = BASE_URL . '/webhook.php';
$pageTitle  = 'Atualizações';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-arrow-repeat me-2"></i>Atualizações do Sistema</h3>
</div>

<?php if (!$repo): ?>
<div class="alert alert-warning d-flex gap-2 align-items-center">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <span>
        Repositório GitHub não configurado.
        <a href="<?= BASE_URL ?>/admin/configuracoes.php#tabGithub" class="alert-link">
            Configure em Configurações › GitHub
        </a>
        antes de usar o sistema de atualização.
    </span>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Coluna esquerda -->
    <div class="col-lg-7">

        <!-- Card: Estado atual -->
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Versão Instalada</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Última atualização</dt>
                    <dd class="col-sm-8">
                        <?php if ($currentVersion): ?>
                            <code><?= e($currentVersion) ?></code>
                        <?php else: ?>
                            <span class="text-muted">Nenhum registro (instalação inicial ou sem atualizações)</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Repositório</dt>
                    <dd class="col-sm-8">
                        <?php if ($repo): ?>
                            <a href="https://github.com/<?= e($repo) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-github me-1"></i><?= e($repo) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-danger">Não configurado</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-4">Branch</dt>
                    <dd class="col-sm-8"><code><?= e($branch) ?></code></dd>
                </dl>
            </div>
        </div>

        <!-- Card: Último commit no GitHub -->
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-github me-1"></i>Último Commit no GitHub</div>
            <div class="card-body">
                <?php if ($commitError): ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= e($commitError) ?>
                </div>
                <?php elseif ($latestCommit): ?>
                <dl class="row mb-3">
                    <dt class="col-sm-3">Hash</dt>
                    <dd class="col-sm-9"><code><?= e($latestCommit['sha']) ?></code></dd>
                    <dt class="col-sm-3">Autor</dt>
                    <dd class="col-sm-9"><?= e($latestCommit['author']) ?></dd>
                    <dt class="col-sm-3">Data</dt>
                    <dd class="col-sm-9">
                        <?php
                        $dt = $latestCommit['date'] ? new DateTime($latestCommit['date']) : null;
                        echo $dt ? $dt->format('d/m/Y H:i') : '—';
                        ?>
                    </dd>
                    <dt class="col-sm-3">Mensagem</dt>
                    <dd class="col-sm-9"><em><?= e($latestCommit['message']) ?></em></dd>
                </dl>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <button type="submit" class="btn btn-primary-custom"
                            onclick="return confirm('Confirma a atualização? Os arquivos serão substituídos pelos do GitHub.\nO banco de dados e os uploads NÃO serão afetados.')">
                        <i class="bi bi-download me-2"></i>Atualizar Agora
                    </button>
                    <small class="d-block text-muted mt-2">
                        <i class="bi bi-shield-check me-1"></i>
                        Protegido: <code>config/database.php</code>, <code>assets/uploads/</code>, <code>logs/</code>, <code>.htaccess</code>
                    </small>
                </form>
                <?php else: ?>
                <p class="text-muted mb-0">Configure o repositório GitHub nas configurações para ver o status.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card: Log -->
        <div class="card admin-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text me-1"></i>Histórico de Atualizações</span>
                <?php if ($logLines): ?>
                <small class="text-muted"><?= count($logLines) ?> registro(s)</small>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($logLines): ?>
                <div class="log-output" style="max-height:260px;overflow-y:auto;font-family:monospace;font-size:.8rem;padding:1rem">
                    <?php foreach ($logLines as $line): ?>
                    <div class="<?= str_contains($line, 'ERRO') ? 'text-danger' : 'text-success' ?>">
                        <?= e($line) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted p-3 mb-0">Nenhuma atualização registrada ainda.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Coluna direita: Webhook -->
    <div class="col-lg-5">
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-webhook me-1"></i>Webhook Automático</div>
            <div class="card-body">
                <p class="small text-muted">
                    Configure um webhook no GitHub para que o servidor seja atualizado automaticamente
                    a cada <code>git push</code> no branch monitorado.
                </p>

                <h6 class="fw-semibold mt-3 mb-2">1. URL do Webhook</h6>
                <div class="input-group mb-3">
                    <input type="text" class="form-control form-control-sm font-monospace"
                           id="webhookUrl" value="<?= e($webhookUrl) ?>" readonly>
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyWebhook()" title="Copiar">
                        <i class="bi bi-clipboard" id="copyIcon"></i>
                    </button>
                </div>

                <h6 class="fw-semibold mb-2">2. Configurar no GitHub</h6>
                <ol class="small text-muted ps-3">
                    <li>Acesse seu repositório no GitHub</li>
                    <li>Clique em <strong>Settings → Webhooks → Add webhook</strong></li>
                    <li>Cole a URL acima no campo <strong>Payload URL</strong></li>
                    <li>Selecione <strong>Content type: application/json</strong></li>
                    <li>Em <strong>Secret</strong>, coloque o mesmo valor configurado em
                        <a href="<?= BASE_URL ?>/admin/configuracoes.php">Configurações › GitHub</a>
                    </li>
                    <li>Escolha <strong>Just the push event</strong></li>
                    <li>Clique <strong>Add webhook</strong></li>
                </ol>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Após configurado, cada <code>git push</code> para o branch
                    <code><?= e($branch) ?></code> vai acionar a atualização automaticamente.
                </div>
            </div>
        </div>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-shield-lock me-1"></i>O que é protegido</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Estes arquivos/pastas <strong>nunca são sobrescritos</strong> durante uma atualização:</p>
                <ul class="small mb-0">
                    <li><code>config/database.php</code> — credenciais do banco</li>
                    <li><code>assets/uploads/</code> — fotos e logos enviadas</li>
                    <li><code>logs/</code> — histórico de atualizações</li>
                    <li><code>.htaccess</code> — configuração do servidor</li>
                    <li><code>install.lock</code> — marcador de instalação</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<script>
function copyWebhook() {
    const el = document.getElementById('webhookUrl');
    el.select();
    document.execCommand('copy');
    const icon = document.getElementById('copyIcon');
    icon.className = 'bi bi-clipboard-check text-success';
    setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
