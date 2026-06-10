<?php
class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct() {
        // Credentials come from config/env.php (created during install).
        // Fall back to legacy define() constants for backward compatibility,
        // then to hardcoded defaults so a fresh clone still works locally.
        $host    = defined('DB_HOST')    ? DB_HOST    : 'localhost';
        $dbname  = defined('DB_NAME')    ? DB_NAME    : 'curriculos_db';
        $user    = defined('DB_USER')    ? DB_USER    : 'root';
        $pass    = defined('DB_PASS')    ? DB_PASS    : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $this->connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->connection;
    }
}

function db(): PDO {
    return Database::getInstance()->getConnection();
}
