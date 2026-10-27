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

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_department'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $description = htmlspecialchars(trim($_POST['description']));
    
    if(!empty($name)) {
        try {
            $stmt = $db->prepare("INSERT INTO departments (name, description, created_by) VALUES (?, ?, ?)");
            if($stmt->execute([$name, $description, $_SESSION['user_id']])) {
                $message = "Department created successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to create department.";
                $message_type = "danger";
            }
        } catch(Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Department name is required!";
        $message_type = "warning";
    }
}

// Get departments
$departments = [];
try {
    $stmt = $db->query("SELECT d.*, u.full_name as created_by_name FROM departments d LEFT JOIN users u ON d.created_by = u.id ORDER BY d.name");
    $departments = $stmt->fetchAll();
} catch(Exception $e) {
    $message = "Error loading departments: " . $e->getMessage();
    $message_type = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Workflow :: VK Logistics Hub</title>
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
                    <li class="nav-item"><a class="nav-link text-white  active bg-primary rounded" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    
                </ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Department Management</h1>
                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createDeptModal">
                        <i class="fas fa-plus"></i> Add Department
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
                        <h5 class="mb-0">All Departments</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($departments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No departments found. Create your first department.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Department Name</th>
                                            <th>Description</th>
                                            <th>Created By</th>
                                            <th>Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($departments as $dept): ?>
                                        <tr>
                                            <td><?php echo $dept['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($dept['description']); ?></td>
                                            <td><?php echo $dept['created_by_name'] ?? 'System'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($dept['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Department Modal -->
    <div class="modal fade" id="createDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., IT Department">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the department"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_department" class="btn btn-primary">Create Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>