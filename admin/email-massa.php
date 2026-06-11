<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/auth_check.php';
requireEditor();

$maxAttachSize  = 10 * 1024 * 1024; // 10 MB per file
$maxAttachTotal = 20 * 1024 * 1024; // 20 MB total
$maxAttachCount = 5;
$attachMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/vnd.oasis.opendocument.presentation',
];
$attachExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','png','jpg','jpeg','gif','webp','odt','ods','odp'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(BASE_URL . '/admin/email-massa.php');
    }

    $subject      = sanitize($_POST['subject']      ?? '');
    $bodyHtml     = trim($_POST['body']              ?? '');
    $destinatarios = sanitize($_POST['destinatarios'] ?? 'todos');

    if (empty($subject))  $errors[] = 'Assunto é obrigatório.';
    if (empty($bodyHtml) || $bodyHtml === '<p><br></p>') $errors[] = 'Mensagem é obrigatória.';

    // Validate and pre-load attachments
    $processedAttachments = [];
    $totalAttachSize = 0;

    if (!empty($_FILES['attachments']['name'][0])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $count = count($_FILES['attachments']['name']);

        if ($count > $maxAttachCount) {
            $errors[] = "Máximo de {$maxAttachCount} anexos permitidos.";
        } else {
            for ($i = 0; $i < $count; $i++) {
                $err  = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                $name = $_FILES['attachments']['name'][$i]  ?? '';
                $tmp  = $_FILES['attachments']['tmp_name'][$i] ?? '';
                $size = $_FILES['attachments']['size'][$i]  ?? 0;

                if ($err === UPLOAD_ERR_NO_FILE || !$name) continue;

                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = "Erro no arquivo \"{$name}\".";
                    continue;
                }
                if ($size > $maxAttachSize) {
                    $errors[] = "\"{$name}\" excede 10 MB.";
                    continue;
                }

                $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mimeReal = $finfo->file($tmp);

                if (!in_array($ext, $attachExts) || !in_array($mimeReal, $attachMimes)) {
                    $errors[] = "Tipo não permitido: \"{$name}\".";
                    continue;
                }

                $data = file_get_contents($tmp);
                if ($data === false) {
                    $errors[] = "Não foi possível ler \"{$name}\".";
                    continue;
                }

                $totalAttachSize += $size;
                if ($totalAttachSize > $maxAttachTotal) {
                    $errors[] = 'Tamanho total dos anexos excede 20 MB.';
                    break;
                }

                $processedAttachments[] = [
                    'name' => $name,
                    'data' => $data,
                    'type' => $mimeReal,
                ];
            }
        }
    }

    if (empty($errors)) {
        if (!getSetting('mail_enabled', '0')) {
            flash('warning', 'Envio de e-mail não está habilitado. Configure em Configurações › E-mail.');
            redirect(BASE_URL . '/admin/email-massa.php');
        }

        $where = 'active = 1';
        if ($destinatarios === 'adimplentes')   $where .= ' AND (adimplente = 1 OR adimplente IS NULL)';
        if ($destinatarios === 'inadimplentes') $where .= ' AND adimplente = 0';

        $recipients = db()->query(
            "SELECT full_name, email FROM users WHERE {$where} AND email != '' ORDER BY full_name ASC"
        )->fetchAll();

        $sent      = 0;
        $sendErrors = [];
        $siteName  = getSetting('site_name', 'APEJESE');

        foreach ($recipients as $rec) {
            $nameHtml     = htmlspecialchars($rec['full_name'], ENT_QUOTES);
            $personalBody = emailTemplate(
                "<p style=\"margin:0 0 16px;\">Olá, <strong>{$nameHtml}</strong>!</p>" . $bodyHtml,
                $subject
            );
            if (sendMailWithAttachments($rec['email'], $rec['full_name'], $subject, $personalBody, $processedAttachments)) {
                $sent++;
            } else {
                $sendErrors[] = $rec['email'];
            }
        }

        logUserAction(
            (int)$_SESSION['user_id'],
            'email_massa',
            "Enviou e-mail em massa '{$subject}' para {$sent} destinatário(s). Filtro: {$destinatarios}."
            . (!empty($processedAttachments) ? ' Anexos: ' . count($processedAttachments) . '.' : '')
        );

        if ($sent > 0) {
            flash('success', "<i class=\"bi bi-check-circle-fill me-1\"></i>E-mail enviado para <strong>{$sent}</strong> destinatário(s)." . (!empty($processedAttachments) ? ' Com ' . count($processedAttachments) . ' anexo(s).' : ''));
        }
        foreach (array_slice($sendErrors, 0, 5) as $failEmail) {
            flash('warning', "Falha ao enviar para: {$failEmail}");
        }
        redirect(BASE_URL . '/admin/email-massa.php');
    }

    // On validation error — flash and redirect (Quill state is JS-only, can't repopulate)
    foreach ($errors as $err) flash('danger', $err);
    redirect(BASE_URL . '/admin/email-massa.php');
}

// Recipient counts
$counts = [];
try {
    $counts['todos']         = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!=''")->fetchColumn();
    $counts['adimplentes']   = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!='' AND (adimplente=1 OR adimplente IS NULL)")->fetchColumn();
    $counts['inadimplentes'] = (int)db()->query("SELECT COUNT(*) FROM users WHERE active=1 AND email!='' AND adimplente=0")->fetchColumn();
} catch (\Exception $e) {}

$mailEnabled = getSetting('mail_enabled', '0');
$csrfToken   = csrfToken();

$pageExtraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">';
$pageTitle = 'E-mail em Massa';
include __DIR__ . '/includes/header.php';
?>

<?= renderFlash() ?>

<div class="admin-page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h3 class="mb-0"><i class="bi bi-envelope-paper me-2"></i>E-mail em Massa</h3>
        <small class="text-muted">Envie mensagens personalizadas para os associados</small>
    </div>
    <?php if (!$mailEnabled): ?>
    <a href="<?= BASE_URL ?>/admin/configuracoes.php" class="btn btn-warning btn-sm">
        <i class="bi bi-gear me-1"></i>Configurar E-mail
    </a>
    <?php endif; ?>
</div>

<?php if (!$mailEnabled): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div>
        O envio de e-mail não está habilitado.
        <a href="<?= BASE_URL ?>/admin/configuracoes.php" class="alert-link fw-semibold">Configure as credenciais SMTP</a>
        antes de enviar mensagens.
    </div>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="emailForm">
<?= csrfField() ?>
<input type="hidden" name="body" id="bodyField">

<div class="row g-4">
    <!-- ── Main column ─────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- 1. Destinatários -->
        <div class="card admin-card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary" style="width:22px;height:22px;font-size:.7rem;line-height:22px;text-align:center;padding:0;">1</span>
                <span class="fw-semibold">Destinatários</span>
            </div>
            <div class="card-body">
                <div class="row g-2" id="destCards">
                    <?php
                    $destOptions = [
                        'todos'         => ['label' => 'Todos os associados', 'icon' => 'bi-people-fill',       'color' => 'primary', 'count' => $counts['todos']         ?? 0],
                        'adimplentes'   => ['label' => 'Adimplentes',         'icon' => 'bi-check-circle-fill', 'color' => 'success', 'count' => $counts['adimplentes']   ?? 0],
                        'inadimplentes' => ['label' => 'Inadimplentes',       'icon' => 'bi-x-circle-fill',     'color' => 'danger',  'count' => $counts['inadimplentes'] ?? 0],
                    ];
                    foreach ($destOptions as $val => $opt):
                    ?>
                    <div class="col-sm-4">
                        <label class="dest-card w-100 <?= $val === 'todos' ? 'selected' : '' ?>" for="dest_<?= $val ?>">
                            <input type="radio" name="destinatarios" id="dest_<?= $val ?>"
                                   value="<?= $val ?>" <?= $val === 'todos' ? 'checked' : '' ?> class="dest-radio">
                            <div class="dest-card-inner">
                                <i class="bi <?= $opt['icon'] ?> text-<?= $opt['color'] ?> fs-4 mb-1"></i>
                                <div class="dest-card-label"><?= $opt['label'] ?></div>
                                <div class="dest-card-count" data-dest="<?= $val ?>"><?= $opt['count'] ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2 text-muted small" id="destHint">
                    <i class="bi bi-info-circle me-1"></i>
                    <span id="destHintText"><?= $counts['todos'] ?? 0 ?></span> e-mail(s) serão enviados.
                </div>
            </div>
        </div>

        <!-- 2. Assunto -->
        <div class="card admin-card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary" style="width:22px;height:22px;font-size:.7rem;line-height:22px;text-align:center;padding:0;">2</span>
                <span class="fw-semibold">Assunto</span>
            </div>
            <div class="card-body">
                <input type="text" name="subject" id="subjectField" class="form-control form-control-lg"
                       required maxlength="150" placeholder="Escreva o assunto do e-mail…"
                       value="<?= e($_POST['subject'] ?? '') ?>">
                <div class="form-text mt-1"><span id="subjectCount">0</span>/150 caracteres</div>
            </div>
        </div>

        <!-- 3. Mensagem -->
        <div class="card admin-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle bg-primary" style="width:22px;height:22px;font-size:.7rem;line-height:22px;text-align:center;padding:0;">3</span>
                    <span class="fw-semibold">Mensagem</span>
                </div>
                <span class="badge bg-secondary" style="font-size:.7rem;">
                    <i class="bi bi-person-fill me-1"></i>Saudação personalizada será adicionada automaticamente
                </span>
            </div>
            <div class="card-body p-0">
                <div id="editor" style="min-height:320px;font-size:15px;border:none;"></div>
            </div>
        </div>

        <!-- 4. Anexos -->
        <div class="card admin-card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="badge rounded-circle bg-primary" style="width:22px;height:22px;font-size:.7rem;line-height:22px;text-align:center;padding:0;">4</span>
                <span class="fw-semibold">Anexos</span>
                <span class="text-muted small fw-normal">— opcional</span>
            </div>
            <div class="card-body">
                <div id="dropzone" class="attach-dropzone" tabindex="0" role="button"
                     aria-label="Clique ou arraste arquivos para anexar">
                    <i class="bi bi-cloud-upload fs-2 text-muted mb-2 d-block"></i>
                    <div class="fw-semibold text-muted">Arraste arquivos aqui ou clique para selecionar</div>
                    <div class="text-muted small mt-1">
                        PDF, Word, Excel, PowerPoint, TXT, PNG, JPG
                        &middot; Máx. 10 MB por arquivo &middot; Máx. 5 arquivos
                    </div>
                    <input type="file" name="attachments[]" id="attachInput" multiple class="d-none"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.webp,.odt,.ods,.odp">
                </div>
                <div id="attachList" class="mt-2"></div>
            </div>
        </div>

        <!-- Send button -->
        <div class="d-flex gap-2 align-items-center pb-2">
            <button type="submit" class="btn btn-primary-custom btn-lg px-4" id="sendBtn"
                    <?= !$mailEnabled ? 'disabled' : '' ?>>
                <i class="bi bi-send-fill me-2"></i>
                <span id="sendBtnText">Enviar para <?= $counts['todos'] ?? 0 ?> associados</span>
            </button>
            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
        </div>

    </div>

    <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Stats card -->
        <div class="card admin-card mb-3">
            <div class="card-header"><i class="bi bi-people me-1"></i>Destinatários</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:.87rem;">
                    <tr>
                        <td class="px-3 py-2"><i class="bi bi-people-fill text-primary me-2"></i>Todos os ativos</td>
                        <td class="px-3 py-2 fw-semibold text-end"><?= $counts['todos'] ?? 0 ?></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Adimplentes</td>
                        <td class="px-3 py-2 fw-semibold text-success text-end"><?= $counts['adimplentes'] ?? 0 ?></td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2"><i class="bi bi-x-circle-fill text-danger me-2"></i>Inadimplentes</td>
                        <td class="px-3 py-2 fw-semibold text-danger text-end"><?= $counts['inadimplentes'] ?? 0 ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Tips -->
        <div class="card admin-card mb-3">
            <div class="card-header"><i class="bi bi-lightbulb me-1"></i>Dicas</div>
            <div class="card-body" style="font-size:.85rem;">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2 d-flex gap-2">
                        <i class="bi bi-person-heart text-primary mt-1 flex-shrink-0"></i>
                        <span>Cada e-mail é enviado individualmente com o nome do destinatário.</span>
                    </li>
                    <li class="mb-2 d-flex gap-2">
                        <i class="bi bi-palette text-info mt-1 flex-shrink-0"></i>
                        <span>Use o editor para formatar texto, inserir imagens e links.</span>
                    </li>
                    <li class="mb-2 d-flex gap-2">
                        <i class="bi bi-hourglass-split text-warning mt-1 flex-shrink-0"></i>
                        <span>O envio pode demorar dependendo da quantidade de destinatários.</span>
                    </li>
                    <li class="mb-2 d-flex gap-2">
                        <i class="bi bi-paperclip text-secondary mt-1 flex-shrink-0"></i>
                        <span>Anexos são enviados para todos os destinatários selecionados.</span>
                    </li>
                    <li class="d-flex gap-2">
                        <i class="bi bi-journal-text text-success mt-1 flex-shrink-0"></i>
                        <span>O envio fica registrado no log de atividades.</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Email config reminder -->
        <?php if ($mailEnabled): ?>
        <div class="card admin-card" style="border-top-color:#198754;">
            <div class="card-body py-2 d-flex align-items-center gap-2" style="font-size:.84rem;color:#198754;">
                <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                <span>Serviço de e-mail ativo e configurado.</span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</form>

<!-- Quill JS from CDN (already in CSP) -->
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script nonce="<?= CSP_NONCE ?>">
(function () {

/* ── Quill init ──────────────────────────────────────────────────────── */
var quill = new Quill('#editor', {
    theme: 'snow',
    placeholder: 'Escreva a mensagem aqui…',
    modules: {
        toolbar: {
            container: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link', 'image'],
                ['blockquote'],
                ['clean']
            ],
            handlers: { image: handleImageUpload }
        }
    }
});

/* ── Image upload handler ────────────────────────────────────────────── */
function handleImageUpload() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp';
    input.click();
    input.onchange = function () {
        var file = input.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 5 MB.');
            return;
        }
        var fd = new FormData();
        fd.append('image', file);
        fd.append('csrf_token', <?= json_encode($csrfToken) ?>);
        fetch('<?= BASE_URL ?>/admin/ajax/email-img-upload.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.url) {
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range ? range.index : 0, 'image', data.url, 'user');
                } else {
                    alert('Erro ao enviar imagem: ' + (data.error || 'desconhecido'));
                }
            })
            .catch(function () { alert('Falha na conexão ao enviar imagem.'); });
    };
}

/* ── Mark form dirty on Quill changes (for unsaved-changes guard) ────── */
var form = document.getElementById('emailForm');
quill.on('text-change', function () { form._dirty = true; });

/* ── Form submit: copy Quill HTML to hidden field ────────────────────── */
form.addEventListener('submit', function (e) {
    var html = quill.root.innerHTML;
    if (!html || html === '<p><br></p>') {
        e.preventDefault();
        alert('A mensagem não pode estar vazia.');
        quill.focus();
        return;
    }
    document.getElementById('bodyField').value = html;
    var btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando…';
});

/* ── Recipient card selection ────────────────────────────────────────── */
var counts = {
    todos:         <?= (int)($counts['todos']         ?? 0) ?>,
    adimplentes:   <?= (int)($counts['adimplentes']   ?? 0) ?>,
    inadimplentes: <?= (int)($counts['inadimplentes'] ?? 0) ?>,
};
var destLabels = {
    todos:         'todos os associados',
    adimplentes:   'associados adimplentes',
    inadimplentes: 'associados inadimplentes',
};

document.querySelectorAll('.dest-radio').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.dest-card').forEach(function (c) { c.classList.remove('selected'); });
        radio.closest('.dest-card').classList.add('selected');
        var val = radio.value;
        document.getElementById('destHintText').textContent = counts[val] || 0;
        document.getElementById('sendBtnText').textContent  =
            'Enviar para ' + (counts[val] || 0) + ' ' + (destLabels[val] || 'destinatários');
    });
});

/* ── Subject char count ──────────────────────────────────────────────── */
var subjectField = document.getElementById('subjectField');
var subjectCount = document.getElementById('subjectCount');
subjectCount.textContent = subjectField.value.length;
subjectField.addEventListener('input', function () {
    subjectCount.textContent = this.value.length;
});

/* ── Attachment drag-and-drop + file list ────────────────────────────── */
var dropzone   = document.getElementById('dropzone');
var attachInput = document.getElementById('attachInput');
var attachList = document.getElementById('attachList');
var selectedFiles = new DataTransfer();
var MAX_FILES = <?= $maxAttachCount ?>;
var MAX_SIZE  = <?= $maxAttachSize ?>;
var MAX_TOTAL = <?= $maxAttachTotal ?>;

var typeIcons = {
    pdf:  'bi-file-earmark-pdf-fill text-danger',
    doc:  'bi-file-earmark-word-fill text-primary',
    docx: 'bi-file-earmark-word-fill text-primary',
    xls:  'bi-file-earmark-excel-fill text-success',
    xlsx: 'bi-file-earmark-excel-fill text-success',
    ppt:  'bi-file-earmark-ppt-fill text-warning',
    pptx: 'bi-file-earmark-ppt-fill text-warning',
    txt:  'bi-file-earmark-text-fill text-secondary',
    png:  'bi-file-earmark-image-fill text-info',
    jpg:  'bi-file-earmark-image-fill text-info',
    jpeg: 'bi-file-earmark-image-fill text-info',
    gif:  'bi-file-earmark-image-fill text-info',
    webp: 'bi-file-earmark-image-fill text-info',
};

function formatBytes(b) {
    if (b < 1024)       return b + ' B';
    if (b < 1048576)    return (b / 1024).toFixed(1) + ' KB';
    return (b / 1048576).toFixed(1) + ' MB';
}

function addFiles(fileList) {
    var errors = [];
    for (var i = 0; i < fileList.length; i++) {
        var f = fileList[i];
        if (selectedFiles.files.length >= MAX_FILES) {
            errors.push('Máximo de ' + MAX_FILES + ' arquivos atingido.');
            break;
        }
        if (f.size > MAX_SIZE) {
            errors.push('"' + f.name + '" excede 10 MB.');
            continue;
        }
        selectedFiles.items.add(f);
    }
    attachInput.files = selectedFiles.files;
    renderAttachList();
    if (errors.length) alert(errors.join('\n'));
}

function removeFile(index) {
    var dt = new DataTransfer();
    var files = selectedFiles.files;
    for (var i = 0; i < files.length; i++) {
        if (i !== index) dt.items.add(files[i]);
    }
    selectedFiles = dt;
    attachInput.files = selectedFiles.files;
    renderAttachList();
}

function renderAttachList() {
    var files = selectedFiles.files;
    if (!files.length) { attachList.innerHTML = ''; return; }

    var totalSize = 0;
    for (var i = 0; i < files.length; i++) totalSize += files[i].size;

    var html = '<ul class="list-group">';
    for (var i = 0; i < files.length; i++) {
        var f   = files[i];
        var ext = f.name.split('.').pop().toLowerCase();
        var ico = typeIcons[ext] || 'bi-file-earmark-fill text-secondary';
        html += '<li class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2 px-3">'
            + '<i class="bi ' + ico + ' fs-5 flex-shrink-0"></i>'
            + '<span class="flex-grow-1 text-truncate small fw-medium" title="' + f.name + '">' + f.name + '</span>'
            + '<span class="text-muted small text-nowrap me-2">' + formatBytes(f.size) + '</span>'
            + '<button type="button" class="btn btn-xs btn-outline-danger flex-shrink-0 attach-remove" data-idx="' + i + '" title="Remover">'
            + '<i class="bi bi-x-lg"></i></button>'
            + '</li>';
    }
    html += '</ul>';
    if (files.length > 1) {
        html += '<div class="text-muted small mt-1 text-end">'
              + files.length + ' arquivo(s) &middot; Total: ' + formatBytes(totalSize)
              + (totalSize > MAX_TOTAL ? ' <span class="text-danger fw-bold">⚠ excede 20 MB</span>' : '')
              + '</div>';
    }
    attachList.innerHTML = html;

    attachList.querySelectorAll('.attach-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            removeFile(parseInt(this.getAttribute('data-idx'), 10));
        });
    });
}

/* Dropzone events */
dropzone.addEventListener('click', function () { attachInput.click(); });
dropzone.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') attachInput.click(); });
attachInput.addEventListener('change', function () { addFiles(this.files); });

dropzone.addEventListener('dragover', function (e) {
    e.preventDefault();
    this.classList.add('dragover');
});
dropzone.addEventListener('dragleave', function (e) {
    if (!this.contains(e.relatedTarget)) this.classList.remove('dragover');
});
dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    this.classList.remove('dragover');
    addFiles(e.dataTransfer.files);
});

})();
</script>

<style nonce="<?= CSP_NONCE ?>">
/* ── Quill editor overrides ──────────────────────────────────────────── */
.ql-toolbar.ql-snow {
    border: none;
    border-bottom: 1px solid var(--border-color, #dee2e6);
    background: #f8f9fc;
    border-radius: 0;
    padding: 8px 12px;
}
.ql-container.ql-snow {
    border: none;
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
}
.ql-editor {
    min-height: 280px;
    padding: 16px 20px;
    font-size: 15px;
    line-height: 1.75;
    color: #1a2035;
}
.ql-editor.ql-blank::before {
    color: #adb5bd;
    font-style: normal;
    font-size: 14px;
}
.ql-snow .ql-picker.ql-header .ql-picker-label::before,
.ql-snow .ql-picker.ql-header .ql-picker-item::before { content: 'Normal'; }
.ql-snow .ql-picker.ql-header .ql-picker-label[data-value="1"]::before,
.ql-snow .ql-picker.ql-header .ql-picker-item[data-value="1"]::before { content: 'Título 1'; }
.ql-snow .ql-picker.ql-header .ql-picker-label[data-value="2"]::before,
.ql-snow .ql-picker.ql-header .ql-picker-item[data-value="2"]::before { content: 'Título 2'; }
.ql-snow .ql-picker.ql-header .ql-picker-label[data-value="3"]::before,
.ql-snow .ql-picker.ql-header .ql-picker-item[data-value="3"]::before { content: 'Título 3'; }

/* ── Recipient cards ─────────────────────────────────────────────────── */
.dest-card {
    cursor: pointer;
    margin: 0;
}
.dest-card .dest-card-inner {
    border: 2px solid var(--border-color, #dee2e6);
    border-radius: 10px;
    padding: 14px 12px;
    text-align: center;
    transition: border-color .15s, background .15s, box-shadow .15s;
    background: #fff;
}
.dest-card:hover .dest-card-inner {
    border-color: var(--primary, #1b3a6b);
    background: #f0f4fb;
}
.dest-card.selected .dest-card-inner {
    border-color: var(--primary, #1b3a6b);
    background: #eef2fa;
    box-shadow: 0 0 0 3px rgba(27,58,107,.12);
}
.dest-card-label {
    font-size: .8rem;
    font-weight: 600;
    color: #495057;
    margin-top: 4px;
}
.dest-card-count {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--primary, #1b3a6b);
    line-height: 1.1;
    margin-top: 2px;
}
.dest-radio { position: absolute; opacity: 0; pointer-events: none; }

/* ── Attachment dropzone ─────────────────────────────────────────────── */
.attach-dropzone {
    border: 2px dashed #c4cdd8;
    border-radius: 10px;
    padding: 28px 16px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    background: #fafbfc;
    outline: none;
}
.attach-dropzone:hover,
.attach-dropzone:focus {
    border-color: var(--primary, #1b3a6b);
    background: #f0f4fb;
}
.attach-dropzone.dragover {
    border-color: var(--secondary, #c9a227);
    background: #fdf8ec;
}
.attach-remove { line-height: 1; }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
