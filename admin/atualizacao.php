<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireAdmin();

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido. Tente novamente.');
        redirect(BASE_URL . '/admin/atualizacao.php');
    }

    $acao = sanitize($_POST['acao'] ?? '');

    if ($acao === 'verificar') {
        $prev = upd_readVersion();
        $info = upd_checkGithub(true);
        if (!empty($info['api_error'])) {
            flash('danger', 'Erro ao verificar: ' . $info['api_error']);
            redirect(BASE_URL . '/admin/atualizacao.php');
        }
        $msg = 'Verificação concluída.';
        if (($prev['commit'] ?? '') === 'desconhecido' && !empty($info['commit'])) {
            $msg = 'Versão atual inicializada como <code>' . e($info['commit']) . '</code>. Futuras atualizações serão detectadas automaticamente.';
        }
        flash('success', $msg);
        redirect(BASE_URL . '/admin/atualizacao.php');
    }

    if ($acao === 'atualizar') {
        set_time_limit(300);
        $resultado = upd_executeUpdate();
        if ($resultado['sucesso']) {
            flash('success', 'Sistema atualizado! Versão: <code>' . e($resultado['commit']) . '</code>');
            redirect(BASE_URL . '/admin/atualizacao.php');
        }
        // mantém $resultado para exibir o erro inline
    }

    if ($acao === 'salvar_config') {
        $token  = trim($_POST['github_token'] ?? '');
        $branch = preg_replace('/[^a-zA-Z0-9\/_\-\.]/', '', trim($_POST['branch'] ?? ''));
        $repo   = sanitize($_POST['github_repo'] ?? '');

        saveSetting('github_repo', $repo);
        // Never overwrite the stored token with an empty field. A blank field
        // means "keep current"; tick the clear checkbox to actually remove it.
        if (!empty($_POST['github_token_clear'])) {
            saveSetting('github_token', '');
        } elseif ($token !== '') {
            saveSetting('github_token', $token);
        }
        if ($branch) {
            saveSetting('github_branch', $branch);
            $v = upd_readVersion();
            $v['branch']     = $branch;
            $v['api_error']  = '';
            $v['checked_at'] = '';
            upd_writeVersion($v);
        }
        flash('success', 'Configurações salvas.');
        redirect(BASE_URL . '/admin/atualizacao.php');
    }
}

// Auto-check on GET (respects 1h cache)
$info = upd_checkGithub(false);

$canDownload = upd_canDownload();
$canExtract  = upd_canExtract();
$canWrite    = upd_canWrite();
$canUpdate   = $canDownload && $canExtract && $canWrite;

$token      = getSetting('github_token', '');
$hasToken   = $token !== '';
$repo       = getSetting('github_repo', '');
$webhookUrl = BASE_URL . '/webhook.php';

$pageTitle = 'Atualizações';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-cloud-arrow-up-fill me-2"></i>Atualizações do Sistema</h3>
    <form method="POST" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="acao" value="verificar">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Verificar Agora
        </button>
    </form>
</div>

<div class="row g-4">

    <!-- ── Coluna principal ────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Status da versão -->
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-cloud-arrow-up-fill me-1"></i>Status da Versão</div>
            <div class="card-body">

                <?php if ($resultado && !$resultado['sucesso']): ?>
                <div class="alert alert-danger mb-4">
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Falha na atualização</strong>
                    <pre class="mb-0 mt-2" style="font-size:.8rem;white-space:pre-wrap;background:rgba(0,0,0,.05);padding:10px;border-radius:6px;"><?= e($resultado['output']) ?></pre>
                </div>
                <?php endif; ?>

                <!-- Versão instalada vs GitHub -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div style="background:rgba(27,58,107,.05);border-radius:10px;padding:18px;">
                            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                                <i class="bi bi-hdd me-1"></i>Versão Instalada
                            </div>
                            <code style="font-size:1.1rem;font-weight:700;color:var(--primary);"><?= e($info['commit']) ?></code>
                            <?php if (!empty($info['updated_at'])): ?>
                            <div style="font-size:.75rem;color:#888;margin-top:6px;">
                                <i class="bi bi-clock me-1"></i>Atualizado em <?= upd_formatDate($info['updated_at']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php
                        $hasNew   = !empty($info['update_available']);
                        $bgColor  = $hasNew ? 'rgba(201,162,39,.08)' : 'rgba(40,167,69,.06)';
                        $border   = $hasNew ? 'rgba(201,162,39,.3)'  : 'rgba(40,167,69,.2)';
                        $txtColor = $hasNew ? 'var(--secondary)'     : '#28a745';
                        ?>
                        <div style="background:<?= $bgColor ?>;border:1px solid <?= $border ?>;border-radius:10px;padding:18px;">
                            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                                <i class="bi bi-github me-1"></i>Versão no GitHub
                            </div>
                            <?php if (!empty($info['latest_commit'])): ?>
                                <code style="font-size:1.1rem;font-weight:700;color:<?= $txtColor ?>;"><?= e($info['latest_commit']) ?></code>
                                <?php if (!empty($info['latest_date'])): ?>
                                <div style="font-size:.75rem;color:#888;margin-top:6px;">
                                    <i class="bi bi-clock me-1"></i><?= upd_formatDate($info['latest_date']) ?>
                                </div>
                                <?php endif; ?>
                            <?php elseif (!empty($info['api_error'])): ?>
                                <span style="font-size:.82rem;color:var(--accent);"><i class="bi bi-exclamation-circle me-1"></i><?= e($info['api_error']) ?></span>
                            <?php else: ?>
                                <span style="font-size:.85rem;color:#aaa;">Nunca verificado — clique em "Verificar Agora"</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Atualização disponível -->
                <?php if ($hasNew): ?>
                <div style="background:linear-gradient(135deg,rgba(201,162,39,.1),rgba(201,162,39,.04));border:1px solid rgba(201,162,39,.3);border-radius:12px;padding:20px;" class="mb-3">
                    <div class="d-flex align-items-start gap-3 flex-wrap">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:var(--secondary);font-size:.95rem;margin-bottom:6px;">
                                <i class="bi bi-arrow-up-circle-fill me-1"></i>Nova versão disponível!
                            </div>
                            <?php if (!empty($info['latest_message'])): ?>
                            <div style="font-size:.84rem;color:#555;margin-bottom:5px;word-break:break-word;">
                                <i class="bi bi-chat-left-text me-1 text-muted"></i><?= e(explode("\n", $info['latest_message'])[0]) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($info['latest_author'])): ?>
                            <div style="font-size:.76rem;color:#999;">
                                <i class="bi bi-person me-1"></i><?= e($info['latest_author']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($canUpdate): ?>
                        <form method="POST" id="form-atualizar">
                            <?= csrfField() ?>
                            <input type="hidden" name="acao" value="atualizar">
                            <button type="submit" id="btn-atualizar"
                                    style="background:var(--secondary);color:#fff;font-weight:700;border-radius:10px;padding:11px 22px;font-size:.9rem;border:none;white-space:nowrap;cursor:pointer;">
                                <i class="bi bi-cloud-download-fill me-1"></i>Atualizar Agora
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning mb-0 py-2 px-3" style="font-size:.82rem;">
                            <i class="bi bi-exclamation-triangle me-1"></i>Atualização automática indisponível
                            <a href="#prereqs" style="color:var(--primary);font-weight:700;margin-left:4px;">Ver requisitos ↓</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif (!empty($info['latest_commit'])): ?>
                <div style="background:rgba(40,167,69,.07);border:1px solid rgba(40,167,69,.2);border-radius:10px;padding:14px;font-size:.88rem;color:#166534;">
                    <i class="bi bi-check-circle-fill me-2" style="color:#28a745;"></i>
                    Sistema atualizado! Você já possui a versão mais recente.
                </div>
                <?php endif; ?>

                <?php if (!empty($info['checked_at'])): ?>
                <div style="font-size:.74rem;color:#bbb;margin-top:10px;text-align:right;">
                    Última verificação: <?= upd_formatDate($info['checked_at']) ?> (cache de 1h)
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Como funciona -->
        <div class="card admin-card mb-4">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Como Funciona</div>
            <div class="card-body">
                <ol style="font-size:.85rem;color:#555;line-height:2;padding-left:18px;margin:0;">
                    <li>O sistema consulta a API do GitHub a cada 1 hora e armazena o resultado em cache.</li>
                    <li>Quando há nova versão, o aviso aparece automaticamente nesta página.</li>
                    <li>Ao clicar em <strong>"Atualizar Agora"</strong>, o sistema baixa o <code>.zip</code> do GitHub e extrai os arquivos diretamente no servidor — sem precisar de SSH ou FTP.</li>
                    <li>Você também pode configurar um <strong>webhook</strong> para que o servidor atualize automaticamente a cada <code>git push</code>.</li>
                    <li>Os arquivos de configuração são <strong>sempre preservados</strong>: <code>config/database.php</code>, <code>assets/uploads/</code>, <code>logs/</code>, <code>.htaccess</code> e <code>install.lock</code>.</li>
                </ol>
                <?php if (!$token): ?>
                <div class="alert alert-info mt-3 mb-0 py-2" style="font-size:.82rem;">
                    <i class="bi bi-github me-1"></i>
                    Se o repositório for <strong>privado</strong>, configure um <strong>GitHub Token</strong> nas configurações ao lado.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Webhook -->
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-webhook me-1"></i>Webhook Automático</div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Configure um webhook no GitHub para que o servidor seja atualizado automaticamente a cada <code>git push</code>.
                </p>
                <label class="form-label small fw-semibold">URL do Webhook</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control form-control-sm font-monospace" id="webhookUrl"
                           value="<?= e($webhookUrl) ?>" readonly>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyWebhook" title="Copiar">
                        <i class="bi bi-clipboard" id="copyIcon"></i>
                    </button>
                </div>
                <ol class="small text-muted ps-3 mb-0">
                    <li>No GitHub: <strong>Settings → Webhooks → Add webhook</strong></li>
                    <li>Cole a URL acima no campo <strong>Payload URL</strong></li>
                    <li>Selecione <strong>Content type: application/json</strong></li>
                    <li>Coloque o <strong>Segredo do Webhook</strong> (configurado ao lado) no campo <strong>Secret</strong></li>
                    <li>Escolha <strong>Just the push event</strong> e salve</li>
                </ol>
            </div>
        </div>

    </div>

    <!-- ── Coluna lateral ───────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Requisitos do servidor -->
        <div class="card admin-card mb-4" id="prereqs">
            <div class="card-header"><i class="bi bi-server me-1"></i>Requisitos do Servidor</div>
            <div class="card-body" style="padding-top:12px;">
                <?php
                $checks = [
                    [
                        'label' => 'Download via cURL',
                        'ok'    => function_exists('curl_init'),
                        'valor' => 'disponível (recomendado)',
                        'fix'   => 'cPanel → Selecionar Versão PHP → Extensões → curl',
                    ],
                    [
                        'label' => 'Download via allow_url_fopen',
                        'ok'    => (bool)ini_get('allow_url_fopen'),
                        'valor' => 'ativo (fallback)',
                        'fix'   => 'cPanel → PHP → allow_url_fopen = On',
                    ],
                    [
                        'label' => 'Extração ZIP (ZipArchive)',
                        'ok'    => class_exists('ZipArchive'),
                        'valor' => 'disponível',
                        'fix'   => 'cPanel → Selecionar Versão PHP → zip',
                    ],
                    [
                        'label' => 'Permissão de escrita',
                        'ok'    => $canWrite,
                        'valor' => 'ok',
                        'fix'   => 'cPanel → Gerenciador de Arquivos → Permissões 755',
                    ],
                ];
                foreach ($checks as $c):
                    $ok = $c['ok'];
                ?>
                <div style="display:flex;align-items:flex-start;gap:8px;padding:9px 0;border-bottom:1px solid #f0f0f0;">
                    <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> mt-1"
                       style="color:<?= $ok ? '#28a745' : 'var(--accent)' ?>;flex-shrink:0;font-size:.9rem;"></i>
                    <div style="flex:1;">
                        <div style="font-size:.83rem;font-weight:600;color:#333;"><?= e($c['label']) ?></div>
                        <?php if ($ok): ?>
                        <div style="font-size:.74rem;color:#28a745;"><?= e($c['valor']) ?></div>
                        <?php else: ?>
                        <div style="font-size:.74rem;color:var(--accent);"><?= e($c['fix']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:12px;padding:10px;background:<?= $canUpdate ? 'rgba(40,167,69,.07)' : 'rgba(230,57,70,.06)' ?>;border-radius:8px;font-size:.82rem;text-align:center;font-weight:700;color:<?= $canUpdate ? '#166534' : 'var(--accent)' ?>;">
                    <?php if ($canUpdate): ?>
                    <i class="bi bi-check-circle-fill me-1"></i>Servidor pronto para atualização automática
                    <?php else: ?>
                    <i class="bi bi-x-circle-fill me-1"></i>Corrija os requisitos acima para habilitar
                    <?php endif; ?>
                </div>
                <div style="font-size:.74rem;color:#aaa;margin-top:8px;text-align:center;">PHP <?= PHP_VERSION ?></div>
            </div>
        </div>

        <!-- Configurações -->
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-gear-fill me-1"></i>Configurações</div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="acao" value="salvar_config">

                    <div class="mb-3">
                        <label class="form-label" style="font-size:.83rem;font-weight:700;color:var(--primary);">Repositório GitHub</label>
                        <input type="text" class="form-control form-control-sm" name="github_repo"
                               value="<?= e($repo) ?>" placeholder="usuario/repositorio" maxlength="100">
                        <div class="form-text" style="font-size:.73rem;">Ex: <code>heliodm/curriculoapejese26</code></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:.83rem;font-weight:700;color:var(--primary);">Branch do GitHub</label>
                        <input type="text" class="form-control form-control-sm" name="branch"
                               value="<?= e($info['branch'] ?: getSetting('github_branch', 'claude/resume-management-system-Aam95')) ?>"
                               placeholder="claude/resume-management-system-Aam95" maxlength="100">
                        <div class="form-text" style="font-size:.73rem;">Branch a monitorar para atualizações</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:.83rem;font-weight:700;color:var(--primary);">
                            GitHub Token
                            <span style="font-weight:400;color:#999;font-size:.75rem;">(repos privados)</span>
                            <?php if ($hasToken): ?>
                            <span class="badge bg-success ms-1" style="font-size:.62rem;">CONFIGURADO</span>
                            <?php endif; ?>
                        </label>
                        <input type="password" class="form-control form-control-sm" name="github_token" id="inp-token"
                               value="" autocomplete="new-password"
                               placeholder="<?= $hasToken ? '•••••••• (deixe em branco para manter)' : 'ghp_xxxxxxxxxxxx' ?>"
                               maxlength="100">
                        <?php if ($hasToken): ?>
                        <div class="form-check mt-1">
                            <input type="checkbox" class="form-check-input" name="github_token_clear" value="1" id="chkTokenClear">
                            <label class="form-check-label text-danger small" for="chkTokenClear">
                                <i class="bi bi-trash3 me-1"></i>Remover token salvo
                            </label>
                        </div>
                        <?php endif; ?>
                        <div class="form-text" style="font-size:.73rem;">
                            Necessário para repos privados. Por segurança, o token nunca é exibido.
                            <a href="https://github.com/settings/tokens/new?scopes=repo&description=SistemaCurriculos" target="_blank" rel="noopener" style="color:var(--primary);">Gerar token →</a>
                        </div>
                    </div>

                    <button type="submit" class="btn w-100"
                            style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:9px;font-size:.88rem;border:none;">
                        <i class="bi bi-check2 me-1"></i>Salvar Configurações
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
var formUpd = document.getElementById('form-atualizar');
if (formUpd) {
    formUpd.addEventListener('submit', function (e) {
        if (!confirm('Confirmar atualização do sistema?\n\nOs arquivos de configuração e uploads serão preservados.\nOs arquivos de código serão substituídos pela versão mais recente do GitHub.')) {
            e.preventDefault();
            return;
        }
        var btn = document.getElementById('btn-atualizar');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Baixando e instalando…';
        }
    });
}

var btnCopyWebhook = document.getElementById('btnCopyWebhook');
if (btnCopyWebhook) {
    btnCopyWebhook.addEventListener('click', function () {
        var el = document.getElementById('webhookUrl');
        var icon = document.getElementById('copyIcon');
        var done = function () {
            icon.className = 'bi bi-clipboard-check text-success';
            setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 2000);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).then(done);
        } else {
            el.select();
            document.execCommand('copy');
            done();
        }
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
