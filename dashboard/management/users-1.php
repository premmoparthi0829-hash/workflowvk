<?php
// Fix paths
require_once __DIR__ . '/../../config.php';

// Simple authentication
//session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'management') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['full_name'];

// Database connection
try {
    $db = getDB();
} catch(Exception $e) {
    die("Database connection failed. Please check your database setup.");
}

// Handle form submissions
$message = '';
$message_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $role = $_POST['role'];
    $department_id = $_POST['department_id'] ?: null;
    $position = htmlspecialchars(trim($_POST['position']));
    
    if(!empty($username) && !empty($email) && !empty($password) && !empty($full_name) && !empty($role)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, department_id, position) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if($stmt->execute([$username, $email, $hashed_password, $full_name, $role, $department_id, $position])) {
                $message = "User created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create user.";
                $message_type = "danger";
            }
        } catch(Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "All required fields must be filled!";
        $message_type = "warning";
    }
}

// Get users
$users = [];
$departments = [];
try {
    $stmt = $db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.full_name");
    $users = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT * FROM departments ORDER BY name");
    $departments = $stmt->fetchAll();
} catch(Exception $e) {
    $message = "Error loading data: " . $e->getMessage();
    $message_type = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Workflow :: VK Logistics Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
 <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body style="background-color: #f8f9fa;">
    <nav class="navbar navbar-light bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="index.php">
                <i class="fas fa-tasks"></i> Workflow :: VK Logistics Hub
            </a>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user-circle me-2"></i>
                <?php echo $user_name; ?> (Management)
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0 sidebar-custom" style="background: #2c3e50; min-height: calc(100vh - 56px); color: white; padding-top: 20px;">
                <div class="text-center mb-4">
                    <h5><i class="fas fa-user-shield"></i> Management</h5>
                    <small><?php echo $user_name; ?></small>
                </div>
                <ul class="nav flex-column d-none d-md-block">
                    <li class="nav-item"><a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                   <li class="nav-item"><a class="nav-link text-white" href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
                    <li class="nav-item"><a class="nav-link text-white  active bg-primary rounded" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    
                </ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">User Management</h1>
                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>

                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php 
                                            $role_colors = [
                                                'management' => 'danger',
                                                'hod' => 'success',
                                                'employee' => 'primary'
                                            ];
                                            $color = $role_colors[$user['role']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['department_name'] ?? 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($user['position'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-control" required>
                                    <option value="">Select Role</option>
                                    <option value="management">Management</option>
                                    <option value="hod">Head of Department (HOD)</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., Software Developer">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>