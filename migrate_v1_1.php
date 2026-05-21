<?php
/**
 * Migração v1.1 — Adiciona coluna `consent` na tabela resumes
 * Execute este arquivo UMA VEZ após a instalação inicial.
 */
require_once __DIR__ . '/config/config.php';

if (!isAdmin()) {
    http_response_code(403);
    echo 'Acesso negado. Faça login como administrador primeiro.';
    exit;
}

$migrated = false;
$message  = '';

try {
    db()->exec("ALTER TABLE resumes ADD COLUMN consent TINYINT(1) NOT NULL DEFAULT 0 AFTER active");
    $migrated = true;
    $message  = 'Coluna <code>consent</code> adicionada com sucesso à tabela <code>resumes</code>.';
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        $migrated = true;
        $message  = 'Coluna <code>consent</code> já existia. Nenhuma alteração necessária.';
    } else {
        $message = 'Erro: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração v1.1</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>body { font-family: Tahoma, sans-serif; background: #f0f2f8; } .wrap { max-width: 540px; margin: 3rem auto; }</style>
</head>
<body>
<div class="wrap">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">Migração v1.1 — Campo de Consentimento</div>
        <div class="card-body">
            <div class="alert <?= $migrated ? 'alert-success' : 'alert-danger' ?>"><?= $message ?></div>
            <?php if ($migrated): ?>
            <p class="small text-muted">Após esta migração, currículos só aparecem publicamente quando <code>consent = 1</code> (usuário autorizou) e <code>active = 1</code> (admin publicou).</p>
            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-primary btn-sm">Voltar ao painel</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
