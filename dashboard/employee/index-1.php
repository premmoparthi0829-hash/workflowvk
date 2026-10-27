<?php
require_once __DIR__ . '/../../config.php';

// Authentication check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];

// Database connection
try {
    $db = getDB();
    
    // Get tasks assigned to this employee
    $stmt = $db->prepare("
        SELECT t.*, 
               u.full_name as created_by_name,
               ta.id as assignment_id,
               ta.accepted_at,
               ta.rejected_at,
               ta.rejection_reason
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
        LEFT JOIN users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll();
    
} catch(Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle task actions
$message = '';
$message_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['accept_task'])) {
        $assignment_id = $_POST['assignment_id'];
        
        try {
            $stmt = $db->prepare("UPDATE task_assignments SET accepted_at = NOW() WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$assignment_id, $user_id]);
            
            // Update task status
            $stmt = $db->prepare("
                UPDATE tasks t 
                JOIN task_assignments ta ON t.id = ta.task_id 
                SET t.status = 'not_started' 
                WHERE ta.id = ?
            ");
            $stmt->execute([$assignment_id]);
            
            $message = "Task accepted successfully!";
            $message_type = "success";
            header("Location: index.php?msg=accepted");
            exit;
            
        } catch(Exception $e) {
            $message = "Error accepting task: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if(isset($_POST['reject_task'])) {
        $assignment_id = $_POST['assignment_id'];
        $reason = sanitize($_POST['rejection_reason']);
        
        if(empty($reason)) {
            $message = "Please provide a reason for rejection";
            $message_type = "warning";
        } else {
            try {
                $stmt = $db->prepare("UPDATE task_assignments SET rejected_at = NOW(), rejection_reason = ? WHERE id = ? AND assigned_to = ?");
                $stmt->execute([$reason, $assignment_id, $user_id]);
                
                // Update task status
                $stmt = $db->prepare("
                    UPDATE tasks t 
                    JOIN task_assignments ta ON t.id = ta.task_id 
                    SET t.status = 'rejected' 
                    WHERE ta.id = ?
                ");
                $stmt->execute([$assignment_id]);
                
                $message = "Task rejected successfully!";
                $message_type = "success";
                header("Location: index.php?msg=rejected");
                exit;
                
            } catch(Exception $e) {
                $message = "Error rejecting task: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
    
    if(isset($_POST['update_status'])) {
        $task_id = $_POST['task_id'];
        $new_status = $_POST['status'];
        
        try {
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);
            
            $message = "Task status updated successfully!";
            $message_type = "success";
            header("Location: index.php?msg=updated");
            exit;
            
        } catch(Exception $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Check for success message from redirect
if(isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'accepted': 
            $message = "Task accepted successfully!";
            $message_type = "success"; 
            break;
        case 'rejected': 
            $message = "Task rejected successfully!"; 
            $message_type = "success"; 
            break;
        case 'updated': 
            $message = "Task status updated successfully!"; 
            $message_type = "success"; 
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
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
    <nav class="navbar navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tasks"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user me-2"></i>
                <?php echo $user_name; ?> (Employee)
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0" >
                <div class="card">
                    <div class="card-header bg-red-vk text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Employee Panel</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        
                        <a href="index.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tasks"></i> My Tasks
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-history"></i> Task History
                        </a>
                    </div>
                </div>
                <!-- style="background: #3498db; min-height: calc(100vh - 56px); color: white; padding-top: 20px;" -->
                 <!-- <li class="nav-item"><a class="nav-link text-white" href="index.php"><i class="fas fa-home"></i> Dashboard</a></li> -->
                <!-- <div class="text-center mb-4 mt-5">
                    <h5><i class="fas fa-user"></i> Employee Panel</h5>
                   
                </div>
                <ul class="nav flex-column">
                    
                    <li class="nav-item"><a class="nav-link text-white active bg-dark rounded" href="index.php"><i class="fas fa-tasks"></i> My Tasks</a></li>
                </ul> -->
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h1 class="h2 mb-4">My Tasks</h1>
                
                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible show">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Pending Acceptance Tasks -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Tasks Pending Acceptance (<?php echo count(array_filter($tasks, function($t) { return $t['accepted_at'] === null && $t['rejected_at'] === null; })); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $pending_tasks = array_filter($tasks, function($task) {
                            return $task['accepted_at'] === null && $task['rejected_at'] === null;
                        });
                        
                        if(empty($pending_tasks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No tasks pending acceptance.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($pending_tasks as $task): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card task-card priority-<?php echo $task['priority']; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars(substr($task['description'], 0, 150)) . (strlen($task['description']) > 150 ? '...' : ''); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
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
                                            
                                            <small class="text-muted d-block mb-3">
                                                <i class="fas fa-user"></i> Assigned by: <?php echo $task['created_by_name']; ?>
                                            </small>
                                            
                                            <div class="btn-group w-100">
                                                <form method="POST" class="w-50">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $task['assignment_id']; ?>">
                                                    <button type="submit" name="accept_task" class="btn btn-success w-100">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-danger w-50" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $task['assignment_id']; ?>">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                            
                                            <!-- Reject Modal -->
                                            <div class="modal" id="rejectModal<?php echo $task['assignment_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Reject Task: <?php echo htmlspecialchars($task['title']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="assignment_id" value="<?php echo $task['assignment_id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Reason for Rejection *</label>
                                                                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Please provide reason for rejecting this task"></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="reject_task" class="btn btn-danger">Submit Rejection</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Active Tasks -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-running"></i> Active Tasks (<?php echo count(array_filter($tasks, function($t) { return $t['accepted_at'] !== null && $t['rejected_at'] === null && $t['status'] !== 'completed'; })); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $active_tasks = array_filter($tasks, function($task) {
                            return $task['accepted_at'] !== null && $task['rejected_at'] === null && $task['status'] !== 'completed';
                        });
                        
                        if(empty($active_tasks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No active tasks.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach($active_tasks as $task): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card task-card priority-<?php echo $task['priority']; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars(substr($task['description'], 0, 150)) . (strlen($task['description']) > 150 ? '...' : ''); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                                </small>
                                                <span class="badge bg-<?php 
                                                    $status_colors = [
                                                        'not_started' => 'secondary',
                                                        'in_progress' => 'primary',
                                                        'waiting_review' => 'info',
                                                        'completed' => 'success'
                                                    ];
                                                    echo $status_colors[$task['status']] ?? 'warning';
                                                ?>">
                                                    <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                                </span>
                                            </div>
                                            
                                            <small class="text-muted d-block mb-3">
                                                Accepted on: <?php echo date('M d, Y', strtotime($task['accepted_at'])); ?>
                                            </small>
                                            
                                            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $task['id']; ?>">
                                                <i class="fas fa-edit"></i> Update Status
                                            </button>
                                            
                                            <!-- Status Update Modal -->
                                            <div class="modal fade" id="statusModal<?php echo $task['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Task Status</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Select New Status</label>
                                                                    <select name="status" class="form-control" required>
                                                                        <option value="not_started" <?php echo $task['status'] == 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                                                                        <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                        <option value="waiting_review" <?php echo $task['status'] == 'waiting_review' ? 'selected' : ''; ?>>Waiting for Review</option>
                                                                        <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>