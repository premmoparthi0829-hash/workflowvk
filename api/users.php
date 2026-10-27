<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireRole(['management']);

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch($action) {
        case 'create':
            if($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if(empty($data['username']) || empty($data['email']) || empty($data['full_name']) || empty($data['role'])) {
                throw new Exception('All required fields must be filled');
            }
            
            // Check if username exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute([':username' => $data['username']]);
            if($stmt->rowCount() > 0) {
                throw new Exception('Username already exists');
            }
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $data['email']]);
            if($stmt->rowCount() > 0) {
                throw new Exception('Email already exists');
            }
            
            // Hash password
            $password = $data['password'] ?? 'password123';
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $query = "INSERT INTO users (username, email, password, full_name, role, department_id, position) 
                     VALUES (:username, :email, :password, :full_name, :role, :department_id, :position)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':username' => sanitize($data['username']),
                ':email' => sanitize($data['email']),
                ':password' => $hashed_password,
                ':full_name' => sanitize($data['full_name']),
                ':role' => $data['role'],
                ':department_id' => $data['department_id'] ?? null,
                ':position' => sanitize($data['position'] ?? '')
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            break;
            
        case 'get_departments':
            $query = "SELECT id, name FROM departments ORDER BY name";
            $stmt = $conn->query($query);
            $departments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'departments' => $departments]);
            break;
            
        case 'list':
            $role = $_GET['role'] ?? '';
            
            $query = "SELECT u.*, d.name as department_name 
                     FROM users u 
                     LEFT JOIN departments d ON u.department_id = d.id 
                     WHERE u.is_active = 1";
            
            if($role) {
                $query .= " AND u.role = :role";
            }
            
            $query .= " ORDER BY u.full_name";
            
            $stmt = $conn->prepare($query);
            
            if($role) {
                $stmt->execute([':role' => $role]);
            } else {
                $stmt->execute();
            }
            
            $users = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}
?>