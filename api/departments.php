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
            
            if(empty($data['name'])) {
                throw new Exception('Department name is required');
            }
            
            // Check if department exists
            $stmt = $conn->prepare("SELECT id FROM departments WHERE name = :name");
            $stmt->execute([':name' => $data['name']]);
            if($stmt->rowCount() > 0) {
                throw new Exception('Department already exists');
            }
            
            // Create department
            $query = "INSERT INTO departments (name, description, created_by) 
                     VALUES (:name, :description, :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':name' => sanitize($data['name']),
                ':description' => sanitize($data['description'] ?? ''),
                ':created_by' => $_SESSION['user_id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Department created successfully']);
            break;
            
        case 'list':
            $query = "SELECT d.*, 
                     (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'hod') as hod_count,
                     (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'employee') as employee_count
                     FROM departments d 
                     ORDER BY d.name";
            $stmt = $conn->query($query);
            $departments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'departments' => $departments]);
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