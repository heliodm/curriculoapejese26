<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';
requireEditor();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de segurança inválido.']);
    exit;
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['image']['error'] ?? -1;
    $msg  = $code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE
          ? 'Imagem excede o tamanho máximo permitido.'
          : 'Falha no upload da imagem.';
    echo json_encode(['error' => $msg]);
    exit;
}

$path = uploadFile($_FILES['image'], 'email-imgs');
if (!$path) {
    echo json_encode(['error' => 'Tipo não permitido. Use JPEG, PNG, GIF ou WebP (máx. 5 MB).']);
    exit;
}

echo json_encode(['url' => UPLOAD_URL . $path]);
exit;
