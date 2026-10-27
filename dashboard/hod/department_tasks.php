<?php
require_once __DIR__ . '/../../config.php';

// Authentication check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$department_id = $_SESSION['department_id'];

// Database connection
try {
    $db = getDB();
    
    // Get tasks for HOD's department
    $stmt = $db->prepare("
        SELECT t.*, 
               u.full_name as created_by_name,
               a.full_name as assigned_to_name,
               ta.assigned_at,
               ta.accepted_at,
               ta.rejected_at
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id
        JOIN users a ON ta.assigned_to = a.id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE a.department_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$department_id]);
    $department_tasks = $stmt->fetchAll();
    
    // Get employees in HOD's department
    $stmt = $db->prepare("
        SELECT u.* 
        FROM users u 
        WHERE u.department_id = ? AND u.role = 'employee' AND u.is_active = 1
        ORDER BY u.full_name
    ");
    $stmt->execute([$department_id]);
    $department_employees = $stmt->fetchAll();
    
} catch(Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle create task
$message = '';
$message_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $priority = $_POST['priority'];
    $deadline = $_POST['deadline'];
    $assigned_to = $_POST['assigned_to'];
    
    if(!empty($title) && !empty($description) && !empty($deadline) && !empty($assigned_to)) {
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Create task
            $stmt = $db->prepare("INSERT INTO tasks (title, description, priority, deadline, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $priority, $deadline, $user_id]);
            
            $task_id = $db->lastInsertId();
            
            // Create assignment
            $stmt = $db->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$task_id, $assigned_to, $user_id, 'direct']);
            
            $db->commit();
            
            $message = "Task created successfully for department employee!";
            $message_type = "success";
            header("Location: department_tasks.php?msg=created");
            exit;
            
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

// Check for success message
if(isset($_GET['msg']) && $_GET['msg'] == 'created') {
    $message = "Task created successfully!";
    $message_type = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .task-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .priority-low { border-left-color: #28a745; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-high { border-left-color: #dc3545; }
        .priority-critical { border-left-color: #343a40; }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <nav class="navbar navbar-dark bg-success sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tasks"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user-tie me-2"></i>
                <?php echo $user_name; ?> (HOD)
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0" style="background: #27ae60; min-height: calc(100vh - 56px); color: white; padding-top: 20px;">
                <div class="text-center mb-4">
                    <h5><i class="fas fa-user-tie"></i> HOD Panel</h5>
                    <small><?php echo $user_name; ?></small>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link text-white" href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white active bg-dark rounded" href="department_tasks.php"><i class="fas fa-tasks"></i> Department Tasks</a></li>
                </ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Department Task Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="fas fa-plus"></i> Create Task for Employee
                    </button>
                </div>
                
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Department Tasks Count -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h6>Total Tasks</h6>
                                <h2><?php echo count($department_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h6>Active Employees</h6>
                                <h2><?php echo count($department_employees); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h6>Pending</h6>
                                <h2><?php echo count(array_filter($department_tasks, function($t) { return $t['status'] === 'pending'; })); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h6>In Progress</h6>
                                <h2><?php echo count(array_filter($department_tasks, function($t) { return $t['status'] === 'in_progress'; })); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Department Tasks -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Department Tasks (<?php echo count($department_tasks); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($department_tasks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tasks assigned in your department.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($department_tasks as $task): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card task-card priority-<?php echo $task['priority']; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars(substr($task['description'], 0, 150)) . (strlen($task['description']) > 150 ? '...' : ''); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> Assigned to: <?php echo $task['assigned_to_name']; ?>
                                                </small>
                                                <span class="badge bg-<?php 
                                                    $priority_colors = [
                                                        'low' => 'success',
                                                        'medium' => 'warning',
                                                        'high' => 'danger',
                                                        'critical' => 'dark'
                                                    ];
                                                    echo $priority_colors[$task['priority']] ?? 'secondary';
                                                ?>">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                                </small>
                                                <span class="badge bg-<?php 
                                                    $status_colors = [
                                                        'pending' => 'warning',
                                                        'not_started' => 'secondary',
                                                        'in_progress' => 'primary',
                                                        'waiting_review' => 'info',
                                                        'completed' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    echo $status_colors[$task['status']] ?? 'secondary';
                                                ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                                </span>
                                            </div>
                                            
                                            <small class="text-muted d-block">
                                                <i class="fas fa-clock"></i> Created: <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
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
                    <h5 class="modal-title">Create Task for Department Employee</h5>
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
                                <label class="form-label">Assign To Employee *</label>
                                <select name="assigned_to" class="form-control" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach($department_employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['full_name']); ?> 
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