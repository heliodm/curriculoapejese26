<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireEditor();

$sent = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/email-massa.php');
    }

    $subject   = sanitize($_POST['subject'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $destinatarios = sanitize($_POST['destinatarios'] ?? 'todos');

    if (empty($subject)) $errors[] = 'Assunto é obrigatório.';
    if (empty($body))    $errors[] = 'Mensagem é obrigatória.';

    if (empty($errors)) {
        $where = 'active = 1';
        if ($destinatarios === 'adimplentes')   $where .= ' AND (adimplente = 1 OR adimplente IS NULL)';
        if ($destinatarios === 'inadimplentes') $where .= ' AND adimplente = 0';

        $recipients = db()->query("SELECT full_name, email FROM users WHERE {$where} AND email != '' ORDER BY full_name ASC")->fetchAll();

        if (!getSetting('mail_enabled', '0')) {
            flash('warning', 'Envio de e-mail não está habilitado. Configure em Configurações > E-mail.');
            redirect(BASE_URL . '/admin/email-massa.php');
        }

        $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $siteName = getSetting('site_name', 'APEJESE');

        foreach ($recipients as $rec) {
            $personalBody = "
                <p>Olá, <strong>" . htmlspecialchars($rec['full_name'], ENT_QUOTES) . "</strong>!</p>
                <br>
                {$htmlBody}
                <br><br>
                <hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
                <p style='font-size:.8rem;color:#888;'>{$siteName}</p>
            ";
            if (sendMail($rec['email'], $rec['full_name'], $subject, $personalBody)) {
                $sent++;
            } else {
                $errors[] = 'Falha ao enviar para: ' . $rec['email'];
            }
        }

        $adminId = (int)$_SESSION['user_id'];
        logUserAction($adminId, 'email_massa', "Enviou e-mail em massa '{$subject}' para {$sent} destinatário(s). Filtro: {$destinatarios}.");

        if ($sent > 0) {
            flash('success', "E-mail enviado com sucesso para {$sent} destinatário(s).");
        }
        foreach (array_slice($errors, 0, 5) as $err) {
            flash('warning', $err);
        }
        redirect(BASE_URL . '/admin/email-massa.php');
    }
}

// Preview recipient count
$counts = [];
try {
    $counts['todos']         = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!='' ")->fetchColumn();
    $counts['adimplentes']   = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!='' AND (adimplente=1 OR adimplente IS NULL)")->fetchColumn();
    $counts['inadimplentes'] = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!='' AND adimplente=0")->fetchColumn();
} catch (\Exception $e) {}

$mailEnabled = getSetting('mail_enabled', '0');

$pageTitle = 'E-mail em Massa';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header">
    <h3><i class="bi bi-envelope-paper me-2"></i>E-mail em Massa</h3>
</div>

<?php if (!$mailEnabled): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    O envio de e-mail não está habilitado. Configure as credenciais em
    <a href="<?= BASE_URL ?>/admin/configuracoes.php">Configurações &rsaquo; E-mail</a>.
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-pencil me-1"></i>Compor Mensagem</div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger py-2"><?= e($err) ?></div>
                <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" data-no-unsaved
                      data-confirm="Confirma o envio do e-mail para os destinatários selecionados?">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Destinatários <span class="text-danger">*</span></label>
                        <select name="destinatarios" class="form-select" id="destSelect">
                            <option value="todos">Todos os associados ativos (<?= $counts['todos'] ?? 0 ?>)</option>
                            <option value="adimplentes">Somente adimplentes (<?= $counts['adimplentes'] ?? 0 ?>)</option>
                            <option value="inadimplentes">Somente inadimplentes (<?= $counts['inadimplentes'] ?? 0 ?>)</option>
                        </select>
                        <div class="form-text" id="destInfo">
                            <i class="bi bi-people me-1"></i>
                            <span id="destCount"><?= $counts['todos'] ?? 0 ?></span> e-mail(s) serão enviados.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assunto <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" required maxlength="150"
                               placeholder="Assunto do e-mail"
                               value="<?= e($_POST['subject'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mensagem <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="10" required
                                  placeholder="Escreva o texto do e-mail aqui. Cada destinatário receberá uma cópia personalizada com seu nome."><?= e($_POST['body'] ?? '') ?></textarea>
                        <div class="form-text">O saudação "Olá, [Nome]!" será adicionada automaticamente no início.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary-custom"
                                <?= !$mailEnabled ? 'disabled' : '' ?>>
                            <i class="bi bi-send me-1"></i>Enviar E-mail
                        </button>
                        <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card admin-card mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Informações</div>
            <div class="card-body" style="font-size:.87rem;">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Cada e-mail é enviado individualmente com o nome do destinatário.</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Apenas associados com e-mail cadastrado recebem.</li>
                    <li class="mb-2"><i class="bi bi-exclamation-triangle text-warning me-2"></i>O envio pode demorar dependendo da quantidade de destinatários.</li>
                    <li class="mb-0"><i class="bi bi-journal-text text-info me-2"></i>A ação fica registrada no log de atividades.</li>
                </ul>
            </div>
        </div>

        <div class="card admin-card">
            <div class="card-header"><i class="bi bi-people me-1"></i>Totais</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:.85rem;">
                    <tr>
                        <td class="px-3 py-2">Todos os ativos</td>
                        <td class="px-3 py-2 fw-semibold text-end"><?= $counts['todos'] ?? 0 ?></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2">Adimplentes</td>
                        <td class="px-3 py-2 fw-semibold text-success text-end"><?= $counts['adimplentes'] ?? 0 ?></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2">Inadimplentes</td>
                        <td class="px-3 py-2 fw-semibold text-danger text-end"><?= $counts['inadimplentes'] ?? 0 ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= CSP_NONCE ?>">
var counts = {
    todos:         <?= (int)($counts['todos']         ?? 0) ?>,
    adimplentes:   <?= (int)($counts['adimplentes']   ?? 0) ?>,
    inadimplentes: <?= (int)($counts['inadimplentes'] ?? 0) ?>,
};
function updateCount() {
    var val = document.getElementById('destSelect').value;
    document.getElementById('destCount').textContent = counts[val] || 0;
}
document.getElementById('destSelect').addEventListener('change', updateCount);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
