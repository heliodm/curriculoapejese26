<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação — Sistema de Currículos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f0f2f8; }
        .install-wrap { max-width: 720px; margin: 2rem auto; padding: 1rem; }
        .install-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.12); overflow: hidden; }
        .install-header { background: #1B3A6B; color: #fff; padding: 1.5rem; }
        .install-header h3 { margin: 0; font-size: 1.3rem; }
        .install-body { padding: 1.5rem; }
        code { background: #f4f6fb; padding: .15rem .4rem; border-radius: 4px; font-size: .85rem; }
    </style>
</head>
<body>
<div class="install-wrap">
<div class="install-card">
<div class="install-header">
    <h3>⚙️ Instalação — Sistema de Currículos</h3>
    <small>Configure o banco de dados e clique em Instalar</small>
</div>
<div class="install-body">

<?php
// Check if already installed
$lockFile = __DIR__ . '/install.lock';
if (file_exists($lockFile)) {
    echo '<div class="alert alert-warning"><strong>Atenção:</strong> O sistema já foi instalado. Delete o arquivo <code>install.lock</code> para reinstalar (isso apagará todos os dados).</div>';
    echo '<a href="index.php" class="btn btn-primary">Ir para o site</a> <a href="admin/index.php" class="btn btn-secondary ms-2">Painel Admin</a>';
    echo '</div></div></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? 'curriculos_db');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';

    $admUser  = trim($_POST['admin_user']  ?? '');
    $admName  = trim($_POST['admin_name']  ?? '');
    $admEmail = trim($_POST['admin_email'] ?? '');
    $admPass  = $_POST['admin_pass'] ?? '';

    $errors  = [];
    $success = [];

    if ($admUser === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $admUser)) {
        $errors[] = 'Usuário admin inválido (3–50 caracteres: letras, números, ponto, hífen, underline).';
    }
    if ($admName === '') $errors[] = 'Nome do administrador é obrigatório.';
    if (!filter_var($admEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail do administrador inválido.';
    if (strlen($admPass) < 8) $errors[] = 'A senha do administrador deve ter ao menos 8 caracteres.';

    if (!empty($errors)) {
        echo '<div class="alert alert-danger"><strong>Corrija os erros abaixo:</strong><ul class="mb-0 mt-2">';
        foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul></div><a href="install.php" class="btn btn-secondary">Voltar</a>';
        echo '</div></div></div></body></html>';
        exit;
    }

    try {
        // Connect without DB first
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $success[] = "Conexão ao MySQL estabelecida.";

        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        $success[] = "Banco de dados <code>$dbName</code> criado/selecionado.";

        // Tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `id` int NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
            `id` int NOT NULL AUTO_INCREMENT,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `order_num` int NOT NULL DEFAULT '0',
            `active` tinyint(1) NOT NULL DEFAULT '1',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int NOT NULL AUTO_INCREMENT,
            `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `role` enum('admin','editor','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
            `active` tinyint(1) NOT NULL DEFAULT '1',
            `adimplente` tinyint(1) NOT NULL DEFAULT '1',
            `matricula_apejese` varchar(50) DEFAULT NULL,
            `cpf` varchar(14) DEFAULT NULL,
            `data_nascimento` date DEFAULT NULL,
            `photo` varchar(255) DEFAULT NULL,
            `data_filiacao` date DEFAULT NULL,
            `registro_profissional` varchar(100) DEFAULT NULL,
            `carteira_validade` date DEFAULT NULL,
            `last_login` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `resumes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int DEFAULT NULL,
            `category_id` int NOT NULL,
            `slug` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `profession` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `formation` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `whatsapp` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `about` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `academic_formation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `professional_experience` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
            `linkedin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `facebook` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `instagram` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `twitter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `active` tinyint(1) NOT NULL DEFAULT '1',
            `consent` tinyint(1) NOT NULL DEFAULT '0',
            `views` int NOT NULL DEFAULT '0',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`),
            KEY `category_id` (`category_id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `fk_resume_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_resume_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `menu_items` (
            `id` int NOT NULL AUTO_INCREMENT,
            `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `order_num` int NOT NULL DEFAULT '0',
            `target` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '_self',
            `active` tinyint(1) NOT NULL DEFAULT '1',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_logs` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NULL,
            `admin_id` int NULL,
            `action` varchar(100) CHARACTER SET utf8mb4 NOT NULL,
            `details` text CHARACTER SET utf8mb4 NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `token` varchar(64) CHARACTER SET utf8mb4 NOT NULL,
            `expires_at` timestamp NOT NULL,
            `used` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`), KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $success[] = "Tabelas criadas com sucesso.";

        // Admin user (credenciais definidas pelo instalador)
        $adminExists = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $adminExists->execute([$admUser, $admEmail]);
        if (!$adminExists->fetch()) {
            $pdo->prepare("INSERT INTO users (username, full_name, email, password, role) VALUES (?,?,?,?,?)")
                ->execute([$admUser, $admName, $admEmail, password_hash($admPass, PASSWORD_BCRYPT), 'admin']);
            $success[] = "Usuário administrador <strong>" . htmlspecialchars($admUser) . "</strong> criado.";
        } else {
            $success[] = "Usuário administrador já existia.";
        }

        // Default categories
        $defaultCategories = [
            'Administradores', 'Advogados', 'Assistente Social', 'Atuários', 'Contadores',
            'Documentólogos/Grafotécnicos', 'Economistas', 'Engenheiros',
            'Farmacêuticos', 'Fonoaudiólogos', 'Gestor Imobiliário',
        ];
        foreach ($defaultCategories as $i => $name) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
            $pdo->prepare("INSERT IGNORE INTO categories (name, slug, order_num) VALUES (?,?,?)")
                ->execute([$name, $slug, $i]);
        }
        $success[] = "Categorias padrão inseridas (" . count($defaultCategories) . ").";

        // Default settings
        $defaults = [
            'site_name'        => 'Sistema de Currículos',
            'site_description' => 'Busque e gerencie currículos profissionais',
            'hero_title'       => 'Encontre Profissionais',
            'hero_subtitle'    => 'Pesquise currículos por nome ou categoria profissional',
            'footer_text'      => '&copy; ' . date('Y') . ' Sistema de Currículos. Todos os direitos reservados.',
        ];
        foreach ($defaults as $k => $v) {
            $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?,?)")->execute([$k, $v]);
        }
        $success[] = "Configurações padrão inseridas.";

        // Default menu items
        $menuItems = [
            ['Início',   '/', '_self'],
            ['Contato',  '#', '_self'],
            ['Sobre',    '#', '_self'],
        ];
        $pdo->exec("DELETE FROM menu_items WHERE id > 0");
        foreach ($menuItems as $i => $m) {
            $pdo->prepare("INSERT INTO menu_items (label, url, order_num, target) VALUES (?,?,?,?)")
                ->execute([$m[0], $m[1], $i, $m[2]]);
        }
        $success[] = "Menu padrão criado.";

        // Update config file with DB credentials
        // Write config/env.php with DB credentials (never committed to git)
        $envPath    = __DIR__ . '/config/env.php';
        $dbPassEsc  = addslashes($dbPass);
        $envContent = "<?php\n"
            . "// Auto-generated by install.php — do not commit this file.\n"
            . "define('DB_HOST',    '" . addslashes($dbHost) . "');\n"
            . "define('DB_NAME',    '" . addslashes($dbName) . "');\n"
            . "define('DB_USER',    '" . addslashes($dbUser) . "');\n"
            . "define('DB_PASS',    '" . $dbPassEsc . "');\n"
            . "define('DB_CHARSET', 'utf8mb4');\n";
        file_put_contents($envPath, $envContent);
        $success[] = "Credenciais salvas em <code>config/env.php</code>.";

        // Create lock file
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        // Self-destruct: remove install.php and migrate_v1_1.php after successful install
        @unlink(__DIR__ . '/migrate_v1_1.php');
        @unlink(__FILE__);

        echo '<div class="alert alert-success"><strong>✅ Instalação concluída!</strong><ul class="mb-0 mt-2">';
        foreach ($success as $s) echo "<li>$s</li>";
        echo '</ul></div>';
        echo '<div class="alert alert-warning py-2" style="font-size:.85rem;">'
            . '<strong>Segurança:</strong> O arquivo <code>install.php</code> foi removido automaticamente.</div>';
        echo '<div class="d-flex gap-2">';
        echo '<a href="index.php" class="btn btn-success">🏠 Ir para o Site</a>';
        echo '<a href="admin/index.php" class="btn btn-primary">🔧 Painel Admin</a>';
        echo '</div>';

    } catch (Exception $ex) {
        echo '<div class="alert alert-danger"><strong>Erro:</strong> ' . htmlspecialchars($ex->getMessage()) . '</div>';
        if (!empty($success)) {
            echo '<div class="alert alert-info"><strong>Passos concluídos:</strong><ul>';
            foreach ($success as $s) echo "<li>$s</li>";
            echo '</ul></div>';
        }
    }

} else {
    // Show form
?>
<div class="alert alert-info mb-3">
    <strong>Bem-vindo!</strong> Configure a conexão com o banco de dados para instalar o sistema.
    O instalador criará o banco, as tabelas, as categorias padrão e o usuário administrador.
</div>

<form method="POST">
    <h6 class="fw-bold mb-2">Banco de Dados</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold">Host MySQL</label>
            <input type="text" name="db_host" class="form-control" value="localhost" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Nome do Banco</label>
            <input type="text" name="db_name" class="form-control" value="curriculos_db" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Usuário MySQL</label>
            <input type="text" name="db_user" class="form-control" value="root" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Senha MySQL</label>
            <input type="password" name="db_pass" class="form-control" placeholder="Deixe vazio se sem senha">
        </div>
    </div>

    <hr class="my-3">
    <h6 class="fw-bold mb-2">Conta do Administrador</h6>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold">Usuário</label>
            <input type="text" name="admin_user" class="form-control" required
                   pattern="[a-zA-Z0-9._-]{3,50}" placeholder="ex: admin">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Nome Completo</label>
            <input type="text" name="admin_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">E-mail</label>
            <input type="email" name="admin_email" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Senha <small class="text-muted fw-normal">(mín. 8 caracteres)</small></label>
            <input type="password" name="admin_pass" class="form-control" required minlength="8">
        </div>
    </div>

    <hr class="my-3">
    <h6>O que será criado:</h6>
    <ul class="small text-muted">
        <li>Tabelas: <code>settings</code>, <code>categories</code>, <code>users</code>, <code>resumes</code>, <code>menu_items</code></li>
        <li>Usuário administrador com as credenciais informadas acima</li>
        <li>11 categorias profissionais padrão</li>
        <li>Configurações e menu padrão</li>
    </ul>
    <div class="alert alert-info small mb-3">
        <strong>Segurança:</strong> <code>install.php</code> será removido automaticamente após a instalação bem-sucedida.
    </div>

    <button type="submit" class="btn btn-success btn-lg">
        <i class="me-2">🚀</i>Instalar Sistema
    </button>
</form>
<?php } ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
