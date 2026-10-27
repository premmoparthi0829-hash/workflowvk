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

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));
    $priority = $_POST['priority'];
    $deadline = $_POST['deadline'];
    $assigned_to = $_POST['assigned_to'];
    
    if(!empty($title) && !empty($description) && !empty($deadline) && !empty($assigned_to)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Create task
            $stmt = $db->prepare("INSERT INTO tasks (title, description, priority, deadline, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $priority, $deadline, $_SESSION['user_id']]);
            
            $task_id = $db->lastInsertId();
            
            // Create assignment
            $stmt = $db->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$task_id, $assigned_to, $_SESSION['user_id'], 'direct']);
            
            $db->commit();
            
            $message = "Task created successfully! Task ID: " . $task_id;
            $message_type = "success";
        } catch(Exception $e) {
            $db->rollBack();
            $message = "Error creating task: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "All required fields must be filled!";
        $message_type = "warning";
    }
}

// Get data
$tasks = [];
$employees = [];
try {
    // Get tasks
    $stmt = $db->query("SELECT t.*, u.full_name as created_by_name, a.full_name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id LEFT JOIN task_assignments ta ON t.id = ta.task_id LEFT JOIN users a ON ta.assigned_to = a.id ORDER BY t.created_at DESC");
    $tasks = $stmt->fetchAll();
    
    // Get employees for assignment
    $stmt = $db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.is_active = 1 AND u.role IN ('employee', 'hod') ORDER BY u.role, u.full_name");
    $employees = $stmt->fetchAll();
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
    <title>Tasks - Workflow :: VK Logistics Hub</title>
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
                   <li class="nav-item"><a class="nav-link text-white  active bg-primary rounded" href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    
                </ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Task Management</h1>
                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="fas fa-plus"></i> Create Task
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
                        <h5 class="mb-0">All Tasks</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($tasks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tasks found. Create your first task.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Task Title</th>
                                            <th>Assigned To</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Deadline</th>
                                            <th>Created By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($tasks as $task): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td><?php echo $task['assigned_to_name'] ?? 'Unassigned'; ?></td>
                                            <td>
                                                <?php 
                                                $priority_colors = [
                                                    'low' => 'success',
                                                    'medium' => 'warning',
                                                    'high' => 'danger',
                                                    'critical' => 'dark'
                                                ];
                                                $color = $priority_colors[$task['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'not_started' => 'secondary',
                                                    'in_progress' => 'primary',
                                                    'waiting_review' => 'info',
                                                    'completed' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $color = $status_colors[$task['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($task['deadline'])); ?></td>
                                            <td><?php echo $task['created_by_name']; ?></td>
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

    <!-- Create Task Modal -->
    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Task Title *</label>
                                <input type="text" name="title" class="form-control" required placeholder="Enter task title">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority *</label>
                                <select name="priority" class="form-control" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="Describe the task in detail"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Deadline *</label>
                                <input type="date" name="deadline" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assign To *</label>
                                <select name="assigned_to" class="form-control" required>
                                    <option value="">Select User</option>
                                    <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['full_name']); ?> 
                                        (<?php echo ucfirst($emp['role']); ?> - <?php echo $emp['department_name'] ?? 'No Dept'; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_task" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date for deadline
        document.querySelector('input[name="deadline"]').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>