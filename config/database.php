<?php
class Database {
    private static ?Database $instance = null;
    private PDO $connection;

    private string $host     = 'localhost';
    private string $dbname   = 'curriculos_db';
    private string $username = 'root';
    private string $password = '';
    private string $charset  = 'utf8mb4';

    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $this->connection = new PDO($dsn, $this->username, $this->password, [
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
