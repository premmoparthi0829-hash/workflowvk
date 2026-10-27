<?php
require_once '../config.php';
require_once '../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        $query = "SELECT * FROM users WHERE username = :username AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['department_id'] = $user['department_id'];
                
                // Update last login
                $this->updateLastLogin($user['id']);
                
                return true;
            }
        }
        return false;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function logout() {
        session_destroy();
        header("Location: ../auth/login.php");
        exit;
    }
    
    public function requireLogin() {
        if(!$this->isLoggedIn()) {
            header("Location: ../auth/login.php");
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        if(!in_array($_SESSION['role'], (array)$roles)) {
            header("Location: ../dashboard/" . $_SESSION['role'] . "/");
            exit;
        }
    }
    
    private function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
    }
}
?>