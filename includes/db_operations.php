<?php
// Fix the path - use correct relative path
require_once __DIR__ . '/../config.php';

class DBOperations {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    // Department Operations
    public function createDepartment($name, $description) {
        $stmt = $this->db->prepare("INSERT INTO departments (name, description, created_by) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $description, $_SESSION['user_id']]);
    }
    
    public function getDepartments() {
        $stmt = $this->db->query("SELECT d.*, u.full_name as created_by_name FROM departments d LEFT JOIN users u ON d.created_by = u.id ORDER BY d.name");
        return $stmt->fetchAll();
    }
    
    // User Operations
    public function createUser($data) {
        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, full_name, role, department_id, position) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['full_name'],
            $data['role'],
            $data['department_id'] ?? null,
            $data['position'] ?? ''
        ]);
    }
    
    public function getUsers($role = null) {
        $sql = "SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.is_active = 1";
        
        if($role) {
            $sql .= " AND u.role = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$role]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        return $stmt->fetchAll();
    }
    
    // Task Operations
    public function createTask($data) {
        $this->db->beginTransaction();
        try {
            // Create task
            $stmt = $this->db->prepare("INSERT INTO tasks (title, description, priority, deadline, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['priority'],
                $data['deadline'],
                $_SESSION['user_id']
            ]);
            
            $task_id = $this->db->lastInsertId();
            
            // Create assignment
            $stmt = $this->db->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $task_id,
                $data['assigned_to'],
                $_SESSION['user_id'],
                'direct'
            ]);
            
            $this->db->commit();
            return $task_id;
        } catch(Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function getTasks() {
        $stmt = $this->db->query("SELECT t.*, u.full_name as created_by_name, a.full_name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id LEFT JOIN task_assignments ta ON t.id = ta.task_id LEFT JOIN users a ON ta.assigned_to = a.id ORDER BY t.created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getEmployees() {
        $stmt = $this->db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.role = 'employee' AND u.is_active = 1 ORDER BY u.full_name");
        return $stmt->fetchAll();
    }
    
    public function getHODs() {
        $stmt = $this->db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.role = 'hod' AND u.is_active = 1 ORDER BY u.full_name");
        return $stmt->fetchAll();
    }
}
?>