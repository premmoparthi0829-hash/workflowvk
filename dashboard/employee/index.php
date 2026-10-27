<?php
require_once __DIR__ . '/../../config.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'];
$employees = [];
// Database connection
try {
    $db = getDB();
    $sql = "
    SELECT 
        u.*, 
        d.name AS department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
      AND u.is_active = 1
      AND u.role IN ('employee', 'hod')
    ORDER BY u.role, u.full_name
";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Function to log task actions
    function logTaskAction($db, $task_id, $user_id, $action, $details = '', $old_status = null, $new_status = null)
    {
        try {
            $query = "INSERT INTO task_logs (task_id, user_id, action, details, old_status, new_status) 
                     VALUES (:task_id, :user_id, :action, :details, :old_status, :new_status)";
            $stmt = $db->prepare($query);
            return $stmt->execute([
                ':task_id' => $task_id,
                ':user_id' => $user_id,
                ':action' => $action,
                ':details' => $details,
                ':old_status' => $old_status,
                ':new_status' => $new_status
            ]);
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Logging error: " . $e->getMessage());
            return false;
        }
    }

    // Function to get next allowed statuses
    function getAllowedNextStatuses($current_status)
    {
        $status_flow = [
            'not_started' => ['in_progress'],
            'in_progress' => ['dependency_block', 'waiting_review'],
            'dependency_block' => ['in_progress'],
            'waiting_review' => ['completed', 'in_progress']
        ];

        return $status_flow[$current_status] ?? [];
    }

    // Calculate date threshold: 1 month ago
    $one_month_ago = date('Y-m-d H:i:s', strtotime('-1 month'));

    // Get tasks assigned to this employee with filters:
    // 1. Show all active tasks (not completed, not rejected)
    // 2. Show completed/rejected tasks only from last month
    $stmt = $db->prepare("
        SELECT t.*, 
               u.full_name as created_by_name,
               ta.id as assignment_id,
               ta.accepted_at,
               ta.rejected_at,
               ta.rejection_reason,
               ta.assignment_type,
               t.submission_details
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
        LEFT JOIN users u ON t.created_by = u.id
        WHERE (
            -- Show all active tasks (not completed, not rejected)
            (t.status NOT IN ('completed', 'rejected'))
            OR 
            -- Show completed/rejected tasks only if they're from last month
            (t.status IN ('completed', 'rejected') AND t.created_at >= ?)
        )
        ORDER BY 
            CASE 
                WHEN t.status NOT IN ('completed', 'rejected') THEN 1
                ELSE 2
            END,
            t.created_at DESC
    ");
    $stmt->execute([$user_id, $one_month_ago]);
    $tasks = $stmt->fetchAll();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle task actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority    = $_POST['priority'] ?? 'medium';
    $deadline    = $_POST['deadline'] ?? '';

    // FORCE self assignment (do NOT trust POST)
    $assigned_to = $_SESSION['user_id'];
    $created_by  = $_SESSION['user_id'];

    if ($title && $description && $deadline) {
        try {
            $db->beginTransaction();

            // Insert task
            $stmt = $db->prepare("
                INSERT INTO tasks (title, description, priority, deadline, created_by, status)
                VALUES (?, ?, ?, ?, ?, 'not_started')
            ");
            $stmt->execute([
                $title,
                $description,
                $priority,
                $deadline,
                $created_by
            ]);

            $task_id = $db->lastInsertId();

            // Insert assignment (self) - auto-accepted since it's self-created
            $stmt = $db->prepare("
                INSERT INTO task_assignments
                (task_id, assigned_to, assigned_by, assignment_type, current_assignee, accepted_at)
                VALUES (?, ?, ?, 'self', 1, NOW())
            ");
            $stmt->execute([
                $task_id,
                $assigned_to,
                $created_by
            ]);

            $db->commit();

            // Redirect to avoid form resubmission on refresh
            header("Location: index.php?msg=created&task_id=" . $task_id);
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error creating task: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "All required fields must be filled!";
        $message_type = "warning";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['create_task'])) {
    if (isset($_POST['accept_task'])) {
        $assignment_id = $_POST['assignment_id'];

        try {
            // Start transaction
            $db->beginTransaction();

            // Get task details before update
            $stmt = $db->prepare("
                SELECT t.id as task_id, t.title, t.status, ta.assigned_to 
                FROM tasks t 
                JOIN task_assignments ta ON t.id = ta.task_id 
                WHERE ta.id = ?
            ");
            $stmt->execute([$assignment_id]);
            $task_data = $stmt->fetch();

            if (!$task_data) {
                throw new Exception("Task not found");
            }

            $task_id = $task_data['task_id'];
            $task_title = $task_data['title'];
            $old_status = $task_data['status'];
            $new_status = 'not_started';

            // Update task assignment
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

            // Log the acceptance with old and new status
            logTaskAction(
                $db,
                $task_id,
                $user_id,
                'accepted',
                "Task '{$task_title}' accepted by {$user_name}",
                $old_status,
                $new_status
            );

            $db->commit();

            // Redirect to avoid form resubmission on refresh
            header("Location: index.php?msg=accepted&assignment_id=" . $assignment_id);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error accepting task: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if (isset($_POST['reject_task'])) {
        $assignment_id = $_POST['assignment_id'];
        $reason = sanitize($_POST['rejection_reason'] ?? '');

        if (empty($reason)) {
            $message = "Please provide a reason for rejection";
            $message_type = "warning";
        } else {
            try {
                // Start transaction
                $db->beginTransaction();

                // Get task details before update
                $stmt = $db->prepare("
                    SELECT t.id as task_id, t.title, t.status, ta.assigned_to 
                    FROM tasks t 
                    JOIN task_assignments ta ON t.id = ta.task_id 
                    WHERE ta.id = ?
                ");
                $stmt->execute([$assignment_id]);
                $task_data = $stmt->fetch();

                if (!$task_data) {
                    throw new Exception("Task not found");
                }

                $task_id = $task_data['task_id'];
                $task_title = $task_data['title'];
                $old_status = $task_data['status'];
                $new_status = 'rejected';

                // Update task assignment
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

                // Log the rejection with old and new status
                logTaskAction(
                    $db,
                    $task_id,
                    $user_id,
                    'rejected',
                    "Task '{$task_title}' rejected by {$user_name}. Reason: {$reason}",
                    $old_status,
                    $new_status
                );

                $db->commit();

                // Redirect to avoid form resubmission on refresh
                header("Location: index.php?msg=rejected&assignment_id=" . $assignment_id);
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $message = "Error rejecting task: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }

    if (isset($_POST['update_status'])) {
        $task_id = $_POST['task_id'];
        $new_status = $_POST['status'];
        $submission_details = trim($_POST['submission_details'] ?? '');

        try {
            // Start transaction
            $db->beginTransaction();

            // Get current task details
            $stmt = $db->prepare("SELECT status, title, created_by FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception("Task not found");
            }

            // BACKEND VALIDATION: Check if user can change to this status
            $current_status = $task['status'];
            $allowed_next_statuses = getAllowedNextStatuses($current_status);

            // If task is completed, no further changes allowed
            if ($current_status === 'completed') {
                throw new Exception("Cannot change status of a completed task");
            }

            // Check if the requested status change is allowed
            if (!in_array($new_status, $allowed_next_statuses)) {
                $allowed_list = implode(", ", array_map(function ($s) {
                    return str_replace('_', ' ', ucfirst($s));
                }, $allowed_next_statuses));

                throw new Exception("Invalid status transition. From '{$current_status}', you can only change to: {$allowed_list}");
            }

            // If changing to "waiting_review", submission details are required
            if ($new_status === 'waiting_review' && empty($submission_details)) {
                throw new Exception("Submission details are required when marking task as 'Waiting for Review'. Please provide where you submitted the work (email, drive link, etc.).");
            }

            $old_status = $task['status'];
            $task_title = $task['title'];

            // Prepare update parameters
            $update_params = [$new_status, $task_id];
            $update_query = "UPDATE tasks SET status = ?";
            
            // If changing to waiting_review, add submission details
            if ($new_status === 'waiting_review') {
                $update_query .= ", submission_details = ?";
                $update_params = [$new_status, $submission_details, $task_id];
            }
            
            $update_query .= " WHERE id = ?";
            
            // Update task status (and submission details if applicable)
            $stmt = $db->prepare($update_query);
            $stmt->execute($update_params);

            // Log the status change with old and new status
            $log_details = "Task '{$task_title}' status changed from {$old_status} to {$new_status}";
            if ($new_status === 'waiting_review' && !empty($submission_details)) {
                $log_details .= ". Submission details: " . (strlen($submission_details) > 100 ? substr($submission_details, 0, 100) . "..." : $submission_details);
            }
            
            logTaskAction(
                $db,
                $task_id,
                $user_id,
                'status_update',
                $log_details,
                $old_status,
                $new_status
            );

            // If task is completed, set completion time
            if ($new_status === 'completed') {
                $stmt = $db->prepare("UPDATE tasks SET completed_at = NOW() WHERE id = ?");
                $stmt->execute([$task_id]);

                // Log completion
                logTaskAction(
                    $db,
                    $task_id,
                    $user_id,
                    'completed',
                    "Task '{$task_title}' marked as completed",
                    $old_status,
                    $new_status
                );
            }

            $db->commit();

            // Redirect to avoid form resubmission on refresh
            header("Location: index.php?msg=updated&task_id=" . $task_id);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Check for success message from redirect
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $message = "Task created successfully!";
            $message_type = "success";
            break;
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

// Filter tasks for display
$pending_tasks = array_filter($tasks, function ($task) {
    // Show tasks that need acceptance (not self-assigned AND not accepted/rejected)
    return $task['accepted_at'] === null && 
           $task['rejected_at'] === null && 
           $task['assignment_type'] !== 'self';
});

$active_tasks = array_filter($tasks, function ($task) {
    // Show tasks that are accepted AND not completed/rejected
    // OR self-assigned tasks that are not completed/rejected
    return (($task['accepted_at'] !== null || $task['assignment_type'] === 'self') && 
            $task['rejected_at'] === null && 
            $task['status'] !== 'completed' &&
            $task['status'] !== 'rejected');
});

$completed_tasks = array_filter($tasks, function ($task) {
    return $task['status'] === 'completed' && 
           ($task['accepted_at'] !== null || $task['assignment_type'] === 'self');
});

$rejected_tasks = array_filter($tasks, function ($task) {
    return $task['status'] === 'rejected' && $task['rejected_at'] !== null;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
    .task-card {
        border-left: 4px solid;
        margin-bottom: 15px;
    }

    .task-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .priority-low {
        border-left-color: #28a745;
    }

    .priority-medium {
        border-left-color: #ffc107;
    }

    .priority-high {
        border-left-color: #dc3545;
    }

    .priority-critical {
        border-left-color: #343a40;
    }

    .accordion-button:not(.collapsed) {
        background-color: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
    }

    .badge-count {
        font-size: 0.8rem;
        margin-left: 8px;
    }

    .deadline-soon {
        background-color: #fff3cd !important;
        border-left-color: #ffc107 !important;
    }

    .deadline-overdue {
        background-color: #f8d7da !important;
        border-left-color: #dc3545 !important;
    }

    .task-description {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    select option:disabled {
        color: #ccc;
        background-color: #f8f9fa;
    }

    .submission-details {
        background-color: #f8f9fa;
        border-left: 3px solid #0d6efd;
        padding: 10px;
        margin-top: 10px;
        border-radius: 3px;
    }
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
            <div class="col-md-3 col-lg-2 px-0">
                <div class="card">
                    <div class="card-header bg-red-vk text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Employee Panel</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tasks"></i> My Tasks
                        </a>
                        <a href="task-history.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history"></i> Task History
                        </a>
                       
                            <a class="list-group-item list-group-item-action" href="attendance.php">
                                <i class="fas fa-clock"></i> Attendance
                            </a>
                       
                       
                            <a class="list-group-item list-group-item-action" href="view_attendance.php">
                                <i class="fas fa-history"></i> Attendance History
                            </a>
                            <a class="list-group-item list-group-item-action" href="apply_leave.php">
                                <i class="fas fa-umbrella-beach"></i> Apply Leave
                            </a>
                       
                    </div>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <h1 class="h2 mb-0">My Tasks</h1>
                    </div>

                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                        <i class="fas fa-plus"></i> Create Task
                    </button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible show">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tasks Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Pending Acceptance</h5>
                                <h2 class="mb-0"><?php echo count($pending_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Active Tasks</h5>
                                <h2 class="mb-0"><?php echo count($active_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Completed</h5>
                                <h2 class="mb-0"><?php echo count($completed_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Rejected</h5>
                                <h2 class="mb-0"><?php echo count($rejected_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All Tasks Accordion -->
                <div class="accordion" id="tasksAccordion">

                    <!-- Pending Acceptance Tasks -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo empty($pending_tasks) ? 'collapsed' : ''; ?>"
                                type="button" data-bs-toggle="collapse" data-bs-target="#pendingTasks">
                                <i class="fas fa-clock me-2"></i> Tasks Pending Acceptance
                                <span class="badge bg-warning badge-count"><?php echo count($pending_tasks); ?></span>
                            </button>
                        </h2>
                        <div id="pendingTasks"
                            class="accordion-collapse collapse <?php echo empty($pending_tasks) ? '' : 'show'; ?>"
                            data-bs-parent="#tasksAccordion">
                            <div class="accordion-body">
                                <?php if (empty($pending_tasks)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No tasks pending acceptance.
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($pending_tasks as $task):
                                            // Check deadline status
                                            $deadline_class = '';
                                            $deadline_date = strtotime($task['deadline']);
                                            $today = time();
                                            $days_diff = floor(($deadline_date - $today) / (60 * 60 * 24));

                                            if ($days_diff < 0) {
                                                $deadline_class = 'deadline-overdue';
                                            } elseif ($days_diff < 3) {
                                                $deadline_class = 'deadline-soon';
                                            }
                                        ?>
                                    <div class="col-md-6 mb-3">
                                        <div
                                            class="card task-card priority-<?php echo $task['priority']; ?> <?php echo $deadline_class; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?>
                                                </h5>
                                                <p class="card-text task-description">
                                                    <?php echo htmlspecialchars($task['description']); ?></p>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php
                                                                if ($days_diff < 0) {
                                                                    echo '<span class="text-danger">Overdue: ';
                                                                } elseif ($days_diff < 3) {
                                                                    echo '<span class="text-warning">Due soon: ';
                                                                } else {
                                                                    echo 'Deadline: ';
                                                                }
                                                                echo date('M d, Y', $deadline_date);
                                                                if ($days_diff < 0 || $days_diff < 3) echo '</span>';
                                                                ?>
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
                                                    <i class="fas fa-user"></i> Assigned by:
                                                    <?php echo $task['created_by_name']; ?>
                                                </small>

                                                <div class="btn-group w-100">
                                                    <form method="POST" class="w-50">
                                                        <input type="hidden" name="assignment_id"
                                                            value="<?php echo $task['assignment_id']; ?>">
                                                        <button type="submit" name="accept_task"
                                                            class="btn btn-success w-100">
                                                            <i class="fas fa-check"></i> Accept
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger w-50"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#rejectModal<?php echo $task['assignment_id']; ?>">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>

                                                <!-- Reject Modal -->
                                                <div class="modal fade"
                                                    id="rejectModal<?php echo $task['assignment_id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Reject Task:
                                                                    <?php echo htmlspecialchars($task['title']); ?></h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="assignment_id"
                                                                        value="<?php echo $task['assignment_id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Reason for Rejection
                                                                            *</label>
                                                                        <textarea name="rejection_reason"
                                                                            class="form-control" rows="3" required
                                                                            placeholder="Please provide reason for rejecting this task"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="reject_task"
                                                                        class="btn btn-danger">Submit Rejection</button>
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
                    </div>

                    <!-- Active Tasks -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#activeTasks">
                                <i class="fas fa-running me-2"></i> Active Tasks
                                <span class="badge bg-primary badge-count"><?php echo count($active_tasks); ?></span>
                            </button>
                        </h2>
                        <div id="activeTasks" class="accordion-collapse collapse" data-bs-parent="#tasksAccordion">
                            <div class="accordion-body">
                                <?php if (empty($active_tasks)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No active tasks.
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($active_tasks as $task):
                                            // Check deadline status
                                            $deadline_class = '';
                                            $deadline_date = strtotime($task['deadline']);
                                            $today = time();
                                            $days_diff = floor(($deadline_date - $today) / (60 * 60 * 24));

                                            if ($days_diff < 0) {
                                                $deadline_class = 'deadline-overdue';
                                            } elseif ($days_diff < 3) {
                                                $deadline_class = 'deadline-soon';
                                            }

                                            // Get allowed next statuses for this task
                                            $allowed_next_statuses = getAllowedNextStatuses($task['status']);
                                        ?>
                                    <div class="col-md-6 mb-3">
                                        <div
                                            class="card task-card priority-<?php echo $task['priority']; ?> <?php echo $deadline_class; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?>
                                                    <?php if ($task['assignment_type'] === 'self'): ?>
                                                    <span class="badge bg-info ms-2">Self-Created</span>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text task-description">
                                                    <?php echo htmlspecialchars($task['description']); ?></p>

                                                <?php if ($task['status'] === 'waiting_review' && !empty($task['submission_details'])): ?>
                                                <div class="submission-details mb-3">
                                                    <small class="text-muted d-block mb-1">
                                                        <i class="fas fa-paper-plane"></i> <strong>Submission
                                                            Details:</strong>
                                                    </small>
                                                    <small><?php echo nl2br(htmlspecialchars($task['submission_details'])); ?></small>
                                                </div>
                                                <?php endif; ?>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php
                                                                if ($days_diff < 0) {
                                                                    echo '<span class="text-danger">Overdue: ';
                                                                } elseif ($days_diff < 3) {
                                                                    echo '<span class="text-warning">Due soon: ';
                                                                } else {
                                                                    echo 'Deadline: ';
                                                                }
                                                                echo date('M d, Y', $deadline_date);
                                                                if ($days_diff < 0 || $days_diff < 3) echo '</span>';
                                                                ?>
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

                                                <?php if ($task['assignment_type'] !== 'self' && $task['accepted_at']): ?>
                                                <small class="text-muted d-block mb-3">
                                                    <i class="fas fa-check-circle"></i> Accepted on:
                                                    <?php echo date('M d, Y', strtotime($task['accepted_at'])); ?>
                                                </small>
                                                <?php elseif ($task['assignment_type'] === 'self'): ?>
                                                <small class="text-muted d-block mb-3">
                                                    <i class="fas fa-user-plus"></i> Self-created task
                                                </small>
                                                <?php endif; ?>

                                                <button type="button" class="btn btn-primary w-100"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#statusModal<?php echo $task['id']; ?>">
                                                    <i class="fas fa-edit"></i> Update Status
                                                </button>

                                                <!-- Status Update Modal -->
                                                <div class="modal fade" id="statusModal<?php echo $task['id']; ?>"
                                                    tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Update Task Status</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="task_id"
                                                                        value="<?php echo $task['id']; ?>">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Select New
                                                                            Status</label>
                                                                        <select name="status" class="form-control"
                                                                            required
                                                                            id="statusSelect<?php echo $task['id']; ?>"
                                                                            onchange="toggleSubmissionDetails(<?php echo $task['id']; ?>)">
                                                                            <option value="" disabled selected>Select
                                                                                status...</option>
                                                                            <option value="not_started"
                                                                                <?php echo $task['status'] == 'not_started' ? 'selected' : (in_array('not_started', $allowed_next_statuses) ? '' : 'disabled'); ?>>
                                                                                Not Started
                                                                            </option>
                                                                            <option value="in_progress"
                                                                                <?php echo $task['status'] == 'in_progress' ? 'selected' : (in_array('in_progress', $allowed_next_statuses) ? '' : 'disabled'); ?>>
                                                                                In Progress
                                                                            </option>
                                                                            <option value="dependency_block"
                                                                                <?php echo $task['status'] == 'dependency_block' ? 'selected' : (in_array('dependency_block', $allowed_next_statuses) ? '' : 'disabled'); ?>>
                                                                                Dependency Block
                                                                            </option>
                                                                            <option value="waiting_review"
                                                                                <?php echo $task['status'] == 'waiting_review' ? 'selected' : (in_array('waiting_review', $allowed_next_statuses) ? '' : 'disabled'); ?>>
                                                                                Waiting for Review
                                                                            </option>
                                                                            <option value="completed"
                                                                                <?php echo $task['status'] == 'completed' ? 'selected' : (in_array('completed', $allowed_next_statuses) ? '' : 'disabled'); ?>>
                                                                                Completed
                                                                            </option>
                                                                        </select>
                                                                        <small class="text-muted">
                                                                            <?php if (!empty($allowed_next_statuses)): ?>
                                                                            Allowed next statuses:
                                                                            <?php echo implode(", ", array_map(function ($s) {
                                                                                            return str_replace('_', ' ', ucfirst($s));
                                                                                        }, $allowed_next_statuses)); ?>
                                                                            <?php endif; ?>
                                                                        </small>
                                                                    </div>

                                                                    <div class="mb-3"
                                                                        id="submissionDetailsContainer<?php echo $task['id']; ?>"
                                                                        style="display: none;">
                                                                        <label class="form-label">Submission Details
                                                                            *</label>
                                                                        <textarea name="submission_details"
                                                                            class="form-control" rows="3"
                                                                            placeholder="Provide details of where you submitted the work (e.g., Google Drive link, email address with subject, file location, etc.)"></textarea>
                                                                        <small class="text-muted">
                                                                            Please provide details of where you
                                                                            submitted the completed work for review.
                                                                            This could be:
                                                                            <ul class="mb-0 mt-1">
                                                                                <li>Google Drive/OneDrive link</li>
                                                                                <li>Email address and subject line</li>
                                                                                <li>File path on shared server</li>
                                                                                <li>Project management tool link (Jira,
                                                                                    Trello, etc.)</li>
                                                                            </ul>
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="update_status"
                                                                        class="btn btn-primary">Update Status</button>
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
                    </div>

                    <!-- Completed Tasks (Last Month Only) -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#completedTasks">
                                <i class="fas fa-check-circle me-2"></i> Recently Completed
                                <span class="badge bg-success badge-count"><?php echo count($completed_tasks); ?></span>
                            </button>
                        </h2>
                        <div id="completedTasks" class="accordion-collapse collapse" data-bs-parent="#tasksAccordion">
                            <div class="accordion-body">
                                <?php if (empty($completed_tasks)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No completed tasks from the last month.
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($completed_tasks as $task): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card task-card priority-<?php echo $task['priority']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?>
                                                    <?php if ($task['assignment_type'] === 'self'): ?>
                                                    <span class="badge bg-info ms-2">Self-Created</span>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text task-description">
                                                    <?php echo htmlspecialchars($task['description']); ?></p>

                                                <?php if (!empty($task['submission_details'])): ?>
                                                <div class="submission-details mb-3">
                                                    <small class="text-muted d-block mb-1">
                                                        <i class="fas fa-paper-plane"></i> <strong>Submission
                                                            Details:</strong>
                                                    </small>
                                                    <small><?php echo nl2br(htmlspecialchars($task['submission_details'])); ?></small>
                                                </div>
                                                <?php endif; ?>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> Completed:
                                                        <?php echo date('M d, Y', strtotime($task['updated_at'] ?? $task['created_at'])); ?>
                                                    </small>
                                                    <span class="badge bg-success">
                                                        Completed
                                                    </span>
                                                </div>

                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user"></i> Created by:
                                                    <?php echo $task['created_by_name']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rejected Tasks (Last Month Only) -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#rejectedTasks">
                                <i class="fas fa-times-circle me-2"></i> Recently Rejected
                                <span class="badge bg-danger badge-count"><?php echo count($rejected_tasks); ?></span>
                            </button>
                        </h2>
                        <div id="rejectedTasks" class="accordion-collapse collapse" data-bs-parent="#tasksAccordion">
                            <div class="accordion-body">
                                <?php if (empty($rejected_tasks)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No rejected tasks from the last month.
                                </div>
                                <?php else: ?>
                                <div class="row">
                                    <?php foreach ($rejected_tasks as $task): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card task-card priority-<?php echo $task['priority']; ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?>
                                                </h5>
                                                <p class="card-text task-description">
                                                    <?php echo htmlspecialchars($task['description']); ?></p>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> Rejected:
                                                        <?php echo date('M d, Y', strtotime($task['rejected_at'])); ?>
                                                    </small>
                                                    <span class="badge bg-danger">
                                                        Rejected
                                                    </span>
                                                </div>

                                                <div class="alert alert-warning mt-2">
                                                    <small><strong>Reason:</strong>
                                                        <?php echo htmlspecialchars($task['rejection_reason']); ?></small>
                                                </div>

                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user"></i> Assigned by:
                                                    <?php echo $task['created_by_name']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="createTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskModalTitle">Create New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="taskForm" method="POST" action="">
                    <input type="hidden" id="task_id" name="task_id" value="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Task Title *</label>
                                <input type="text" id="task_title" name="title" class="form-control" required
                                    placeholder="Enter task title">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Priority *</label>
                                <select id="task_priority" name="priority" class="form-control" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea id="task_description" name="description" class="form-control" rows="4" required
                                placeholder="Describe the task in detail"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Deadline *</label>
                                <input type="date" id="task_deadline" name="deadline" class="form-control" required
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assign To *</label>
                                <select id="task_assigned_to" name="assigned_to" class="form-control" disabled required>

                                    <?php if (!empty($employees)):
                                        $emp = $employees[0]; // only one result
                                    ?>
                                    <option value="<?php echo $emp['id']; ?>" selected>
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                        (<?php echo ucfirst($emp['role']); ?> -
                                        <?php echo htmlspecialchars($emp['department_name'] ?? 'No Dept'); ?>)
                                    </option>
                                    <?php else: ?>
                                    <option value="">No User Found</option>
                                    <?php endif; ?>

                                </select>
                                <?php if (!empty($employees)): ?>
                                <input type="hidden" name="assigned_to" value="<?php echo $emp['id']; ?>">
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="task_submit_btn" name="create_task" class="btn btn-primary">Create
                            Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleSubmissionDetails(taskId) {
        const statusSelect = document.getElementById('statusSelect' + taskId);
        const container = document.getElementById('submissionDetailsContainer' + taskId);

        if (statusSelect.value === 'waiting_review') {
            container.style.display = 'block';
            container.querySelector('textarea').required = true;
        } else {
            container.style.display = 'none';
            container.querySelector('textarea').required = false;
        }
    }

    // Initialize submission details visibility for each modal when opened
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('[id^="statusModal"]');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function(event) {
                const modalId = this.id.replace('statusModal', '');
                const statusSelect = document.getElementById('statusSelect' + modalId);
                if (statusSelect) {
                    setTimeout(() => {
                        toggleSubmissionDetails(modalId);
                    }, 100);
                }
            });
        });
    });

    $(document).ready(function() {
        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);

        // Clear URL parameters to prevent message from showing again on refresh
        if (window.history.replaceState && window.location.search) {
            var clean_url = window.location.origin + window.location.pathname;
            window.history.replaceState(null, null, clean_url);
        }
    });

    $('#createTaskModal').on('hidden.bs.modal', function() {
        $('#taskModalTitle').text('Create New Task');
        $('#task_submit_btn').text('Create Task');
        $('#taskForm')[0].reset();
        $('#task_id').val('');
        $('#assignmentStatusInfo').remove();
    });
    </script>
</body>

</html>