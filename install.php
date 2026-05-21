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

    $errors  = [];
    $success = [];

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

        $success[] = "Tabelas criadas com sucesso.";

        // Default admin user
        $adminExists = $pdo->prepare("SELECT id FROM users WHERE username = 'heliodm'");
        $adminExists->execute();
        if (!$adminExists->fetch()) {
            $pdo->prepare("INSERT INTO users (username, full_name, email, password, role) VALUES (?,?,?,?,?)")
                ->execute(['heliodm', 'Hélio DM', 'heliodm@outlook.com', password_hash('Helio74*', PASSWORD_BCRYPT), 'admin']);
            $success[] = "Usuário administrador <strong>heliodm</strong> criado.";
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
        $configPath = __DIR__ . '/config/database.php';
        $configContent = file_get_contents($configPath);
        $configContent = preg_replace("/private string \\\$host\s*=\s*'[^']*'/",     "private string \$host     = '$dbHost'", $configContent);
        $configContent = preg_replace("/private string \\\$dbname\s*=\s*'[^']*'/",   "private string \$dbname   = '$dbName'", $configContent);
        $configContent = preg_replace("/private string \\\$username\s*=\s*'[^']*'/", "private string \$username = '$dbUser'", $configContent);
        $configContent = preg_replace("/private string \\\$password\s*=\s*'[^']*'/", "private string \$password = '$dbPass'", $configContent);
        file_put_contents($configPath, $configContent);
        $success[] = "Arquivo de configuração atualizado.";

        // Create lock file
        file_put_contents($lockFile, date('Y-m-d H:i:s'));

        echo '<div class="alert alert-success"><strong>✅ Instalação concluída!</strong><ul class="mb-0 mt-2">';
        foreach ($success as $s) echo "<li>$s</li>";
        echo '</ul></div>';
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
    <h6>O que será criado:</h6>
    <ul class="small text-muted">
        <li>Tabelas: <code>settings</code>, <code>categories</code>, <code>users</code>, <code>resumes</code>, <code>menu_items</code></li>
        <li>Usuário admin: <strong>heliodm</strong> / senha: <strong>Helio74*</strong></li>
        <li>11 categorias profissionais padrão</li>
        <li>Configurações e menu padrão</li>
    </ul>

    <button type="submit" class="btn btn-success btn-lg">
        <i class="me-2">🚀</i>Instalar Sistema
    </button>
</form>
<?php } ?>

</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
