<?php
require_once __DIR__ . '/../../config.php';

// Simple authentication
//session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'management') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['full_name'];

// Database connection
try {
    $db = getDB();
    $db_connected = true;

    // Get stats
    $stats = [];

    // Total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['total_users'] = $stmt->fetch()['count'];

    // Total departments
    $stmt = $db->query("SELECT COUNT(*) as count FROM departments");
    $stats['total_departments'] = $stmt->fetch()['count'];

    // Total tasks
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks");
    $stats['total_tasks'] = $stmt->fetch()['count'];

    // Completed tasks
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'completed'");
    $stats['completed_tasks'] = $stmt->fetch()['count'];

    // Get active tasks (not completed, not rejected)
    $stmt = $db->prepare("
    SELECT t.*, 
           u1.full_name as created_by_name,
           u2.full_name as assigned_to_name,
           d.name as department_name,
           DATEDIFF(t.deadline, CURDATE()) as days_remaining
    FROM tasks t
    LEFT JOIN users u1 ON t.created_by = u1.id
    LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.current_assignee = 1
    LEFT JOIN users u2 ON ta.assigned_to = u2.id
    LEFT JOIN departments d ON u2.department_id = d.id
    WHERE t.status NOT IN ('completed', 'rejected')
    ORDER BY 
        CASE t.priority 
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        t.deadline ASC
    LIMIT 20
");
    $stmt->execute();
    $active_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $db_connected = false;
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Workflow :: VK Logistics Hub</title>
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
                <!-- In the sidebar section of management/index.php -->
                <ul class="nav flex-column d-none d-md-block">
                    <li class="nav-item"><a class="nav-link text-white active bg-primary rounded" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="joining_applications.php"><i class="fas fa-file-signature"></i> Joining Form</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <!-- Add this line for Performance Reports -->
                    <li class="nav-item"><a class="nav-link text-white" href="performance.php"><i class="fas fa-chart-line"></i> Performance</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="admin_attendance_report.php"><i class="fas fa-chart-bar"></i> Attendance Report</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="manage_holidays.php"><i class="fas fa-calendar-day"></i> Manage Holidays</a></li>
            <li class="nav-item">
    <a class="nav-link text-white" href="manage_leaves.php">
        <i class="fas fa-envelope-open-text"></i> Manage Leaves
    </a>
</li>    
            </ul>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4">Dashboard Overview</h1>

                <?php if (!$db_connected): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Database Error:</strong> <?php echo $error_message; ?>
                        <p class="mt-2">
                            <a href="../install/database_setup.php" class="btn btn-warning btn-sm">
                                <i class="fas fa-cogs"></i> Run Database Setup
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Tasks</h6>
                                        <h2 class="mb-0"><?php echo $db_connected ? $stats['total_tasks'] : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-tasks fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Completed</h6>
                                        <h2 class="mb-0"><?php echo $db_connected ? $stats['completed_tasks'] : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Users</h6>
                                        <h2 class="mb-0"><?php echo $db_connected ? $stats['total_users'] : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Departments</h6>
                                        <h2 class="mb-0"><?php echo $db_connected ? $stats['total_departments'] : '0'; ?></h2>
                                    </div>
                                    <i class="fas fa-sitemap fa-2x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                        <?php if ($db_connected): ?>
                                            <a href="tasks.php" class="btn btn-book-now w-100" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                                                <i class="fas fa-plus"></i> Create Task
                                            </a>
                                        <?php else: ?>
                                            <a href="../install/database_setup.php" class="btn btn-danger w-100">
                                                <i class="fas fa-cogs"></i> Setup Database
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                        <a href="tasks.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-tasks"></i> Tasks
                                        </a>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                        <a href="departments.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-sitemap"></i> Departments
                                        </a>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                        <a href="users.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-users"></i> Users
                                        </a>
                                    </div>
                                    <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                        <a href="joining_applications.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-signature"></i> Joining Form
                                        </a>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Status</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($db_connected): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> <strong>Database:</strong> Connected successfully
                                </div>
                                <p>All features are now available:</p>
                                <ul>
                                    <li>✓ Create and manage departments</li>
                                    <li>✓ Add and manage users</li>
                                    <li>✓ Create and assign tasks</li>
                                    <li>✓ Track task progress</li>
                                </ul>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> <strong>Setup Required:</strong> 
                                    Database connection failed. Run setup to enable all features.
                                </div>
                                <a href="../install/database_setup.php" class="btn btn-warning w-100">
                                    <i class="fas fa-cogs"></i> Run Database Setup
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div> -->
                </div>
                <!-- Active Tasks Section -->
                <div class="mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0">
                            <i class="fas fa-tasks text-primary me-2"></i>Active Tasks
                            <span class="badge bg-primary ms-2"><?php echo count($active_tasks); ?></span>
                        </h4>
                        <a href="tasks.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i> View All Tasks
                        </a>
                    </div>

                    <?php if (empty($active_tasks)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No active tasks at the moment.
                        </div>
                    <?php else: ?>
                        <!-- Responsive grid - 4 per row on desktop, 1 on mobile -->
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                            <?php foreach ($active_tasks as $task): ?>
                                <?php
                                // Priority colors
                                $priority_colors = [
                                    'critical' => 'dark',
                                    'high' => 'danger',
                                    'medium' => 'warning',
                                    'low' => 'success'
                                ];
                                $priority_color = $priority_colors[$task['priority']] ?? 'secondary';

                                // Status colors
                                $status_colors = [
                                    'pending' => 'warning',
                                    'not_started' => 'secondary',
                                    'in_progress' => 'primary',
                                    'waiting_review' => 'info'
                                ];
                                $status_color = $status_colors[$task['status']] ?? 'secondary';

                                // Format status for display
                                $status_display = str_replace('_', ' ', $task['status']);
                                $status_display = ucwords($status_display);

                                // Days remaining
                                $days_remaining = $task['days_remaining'];
                                $deadline_class = 'text-success';
                                if ($days_remaining < 0) {
                                    $deadline_text = 'Overdue';
                                    $deadline_class = 'text-danger';
                                } elseif ($days_remaining == 0) {
                                    $deadline_text = 'Due today';
                                    $deadline_class = 'text-warning';
                                } elseif ($days_remaining < 3) {
                                    $deadline_text = $days_remaining . ' days left';
                                    $deadline_class = 'text-warning';
                                } else {
                                    $deadline_text = $days_remaining . ' days left';
                                }
                                ?>

                                <div class="col">
                                    <div class="card h-100 task-card shadow-sm border-hover">
                                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?php echo $priority_color; ?> me-1">
                                                    <?php echo ucfirst($task['priority']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo $status_display; ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">#<?php echo $task['id']; ?></small>
                                        </div>

                                        <div class="card-body py-3">
                                            <h6 class="card-title mb-2 text-truncate" title="<?php echo htmlspecialchars($task['title']); ?>">
                                                <?php echo htmlspecialchars($task['title']); ?>
                                            </h6>

                                            <p class="card-text small text-muted mb-3 line-clamp-2">
                                                <?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?>
                                                <?php if (strlen($task['description']) > 100): ?>...<?php endif; ?>
                                            </p>

                                            <div class="task-info">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Deadline:</small>
                                                    <small class="<?php echo $deadline_class; ?> fw-bold">
                                                        <i class="fas fa-calendar-day me-1"></i>
                                                        <?php echo date('M d', strtotime($task['deadline'])); ?>
                                                    </small>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">Assigned To:</small>
                                                    <small class="text-truncate ms-2" style="max-width: 120px;"
                                                        title="<?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned'); ?>">
                                                        <?php echo $task['assigned_to_name'] ?? 'Unassigned'; ?>
                                                    </small>
                                                </div>

                                                <?php if (!empty($task['department_name'])): ?>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">Department:</small>
                                                        <small class="text-truncate ms-2" style="max-width: 120px;">
                                                            <?php echo $task['department_name']; ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="card-footer bg-transparent py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo $deadline_text; ?>
                                                </small>
                                                <button class="btn btn-sm btn-outline-primary"
                                                    onclick="viewTask(<?php echo $task['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (count($active_tasks) >= 20): ?>
                            <div class="text-center mt-4">
                                <a href="tasks.php" class="btn btn-primary">
                                    <i class="fas fa-list me-1"></i> View All Active Tasks
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
                <form method="POST" action="tasks.php">
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
                                    <?php
                                    if ($db_connected):
                                        try {
                                            $stmt = $db->query("SELECT id, full_name, role FROM users WHERE is_active = 1 AND role IN ('employee', 'hod') ORDER BY full_name");
                                            $users = $stmt->fetchAll();
                                            foreach ($users as $user):
                                    ?>
                                                <option value="<?php echo $user['id']; ?>">
                                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                                </option>
                                    <?php
                                            endforeach;
                                        } catch (Exception $e) {
                                            echo '<option value="">Error loading users</option>';
                                        }
                                    else:
                                        echo '<option value="">Please setup database first</option>';
                                    endif;
                                    ?>
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
        var deadlineField = document.querySelector('input[name="deadline"]');
        if (deadlineField) {
            deadlineField.min = new Date().toISOString().split('T')[0];
        }
    </script>
</body>

</html>