<?php
require_once __DIR__ . '/../config.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $this->port = defined('DB_PORT') ? DB_PORT : '8889';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'taskmanagementsystem';
        $this->username = defined('DB_USER') ? DB_USER : 'root';
        $this->password = defined('DB_PASS') ? DB_PASS : 'root';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host;
            if (!empty($this->port)) {
                $dsn .= ";port=" . $this->port;
            }
            $dsn .= ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
            // Set MySQL timezone to Asia/Kolkata
            $this->conn->exec("SET time_zone = '+05:30'");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>