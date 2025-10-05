<?php
/**
 * Enhanced Database Connection with Security Features
 * Banking Grade Database Connection Handler
 */

class DatabaseConnection {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->connection = new PDO(
                "mysql:host=localhost;dbname=gcc_bank;charset=utf8mb4",
                "root",
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($query) {
        try {
            $stmt = $this->connection->prepare($query);
            if (!$stmt) {
                throw new Exception("Database query preparation failed");
            }
            return $stmt;
        } catch (Exception $e) {
            error_log("Prepare failed: " . $e->getMessage());
            throw new Exception("Database query preparation failed");
        }
    }
    
    public function escape($string) {
        return $this->connection->quote($string);
    }
    
    public function getLastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

// Legacy compatibility - Create mysqli-like wrapper for PDO
class MySQLiCompatibility {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }
    
    public function query($query) {
        return $this->pdo->query($query);
    }
    
    public function exec($query) {
        return $this->pdo->exec($query);
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    public function __get($property) {
        if ($property === 'insert_id') {
            return $this->pdo->lastInsertId();
        }
        return null;
    }
}

$con = new MySQLiCompatibility(DatabaseConnection::getInstance()->getConnection());
?>