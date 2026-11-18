<?php
require_once __DIR__ . '/../../config.php';

// Simple authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'management') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['full_name'];

// Database connection
try {
    $db = getDB();
    $db_connected = true;

    // Function to log task actions (if needed elsewhere)
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

    // Function to calculate performance
    function calculatePerformance($db, $user_id, $month, $year)
    {
        $start_date = "{$year}-{$month}-01";
        $end_date = date("Y-m-t", strtotime($start_date));

        try {
            // Calculate total tasks assigned
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_assigned
                FROM task_assignments ta
                WHERE ta.assigned_to = ?
                AND DATE(ta.assigned_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $total_assigned = $stmt->fetchColumn() ?: 0;

            // Calculate tasks accepted
            $stmt = $db->prepare("
                SELECT COUNT(*) as accepted
                FROM task_assignments ta
                WHERE ta.assigned_to = ?
                AND DATE(ta.accepted_at) BETWEEN ? AND ?
                AND ta.accepted_at IS NOT NULL
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $tasks_accepted = $stmt->fetchColumn() ?: 0;

            // Calculate tasks rejected
            $stmt = $db->prepare("
                SELECT COUNT(*) as rejected
                FROM task_assignments ta
                WHERE ta.assigned_to = ?
                AND DATE(ta.rejected_at) BETWEEN ? AND ?
                AND ta.rejected_at IS NOT NULL
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $tasks_rejected = $stmt->fetchColumn() ?: 0;

            // Calculate tasks completed and average time
            $stmt = $db->prepare("
                SELECT 
    COUNT(*) as completed,
    -- Average of exact hours (with decimals)
    AVG(TIMESTAMPDIFF(SECOND, ta.accepted_at, t.completed_at) / 3600.0) as avg_hours
FROM tasks t
JOIN task_assignments ta ON t.id = ta.task_id
WHERE ta.assigned_to = ?
AND DATE(t.completed_at) BETWEEN ? AND ?
AND t.status = 'completed'
AND ta.accepted_at IS NOT NULL
AND ta.rejected_at IS NULL
            ");
            $stmt->execute([$user_id, $start_date, $end_date]);
            $completed_data = $stmt->fetch();
            $tasks_completed = $completed_data['completed'] ?: 0;
            $avg_completion_hours = $completed_data['avg_hours'] ?: 0;

            // Calculate completion rate
            $completion_rate = $tasks_accepted > 0 ? round(($tasks_completed / $tasks_accepted) * 100, 2) : 0;

            return [
                'user_id' => $user_id,
                'month_year' => $start_date,
                'total_tasks_assigned' => $total_assigned,
                'tasks_accepted' => $tasks_accepted,
                'tasks_rejected' => $tasks_rejected,
                'tasks_completed' => $tasks_completed,
                'avg_completion_time_hours' => round($avg_completion_hours, 2),
                'completion_rate' => $completion_rate
            ];
        } catch (Exception $e) {
            error_log("Error calculating performance: " . $e->getMessage());
            return [];
        }
    }

    // Fetch all employees for dropdown
    $employees = [];
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.role = 'employee' AND u.is_active = 1 
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();

    // Default values
    $selected_employee = $_GET['employee_id'] ?? '';
    $selected_month = $_GET['month'] ?? date('m');
    $selected_year = $_GET['year'] ?? date('Y');

    // Fetch performance data if filters are selected
    $performance_data = [];
    $employee_info = [];
    $task_details = [];

    if ($selected_employee && $selected_month && $selected_year) {
        // Get employee info
        $stmt = $db->prepare("
            SELECT u.*, d.name as department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$selected_employee]);
        $employee_info = $stmt->fetch();

        // Get performance summary
        $month_year = "{$selected_year}-{$selected_month}-01";
        $stmt = $db->prepare("
            SELECT * FROM performance_summary 
            WHERE user_id = ? AND MONTH(month_year) = ? AND YEAR(month_year) = ?
        ");
        $stmt->execute([$selected_employee, $selected_month, $selected_year]);
        $performance_data = $stmt->fetch();

        // If no summary exists, calculate on the fly
        if (!$performance_data && $employee_info) {
            $performance_data = calculatePerformance($db, $selected_employee, $selected_month, $selected_year);
        }

        // Get detailed task list for the month
        $start_date = "{$selected_year}-{$selected_month}-01";
        $end_date = date("Y-m-t", strtotime($start_date));

        $stmt = $db->prepare("
            SELECT 
                t.id,
                t.title,
                t.description,
                t.priority,
                t.status,
                t.deadline,
                t.created_at,
                t.completed_at,
                u.full_name as created_by_name,
                ta.accepted_at,
                ta.rejected_at,
                ta.rejection_reason,
                TIMESTAMPDIFF(SECOND, ta.accepted_at, t.completed_at) / 60.0 as exact_minutes_taken
            FROM tasks t
            JOIN task_assignments ta ON t.id = ta.task_id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE ta.assigned_to = ?
            AND DATE(ta.accepted_at) BETWEEN ? AND ?
            AND ta.accepted_at IS NOT NULL
            AND ta.rejected_at IS NULL
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$selected_employee, $start_date, $end_date]);
        $task_details = $stmt->fetchAll();
    }
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
    <title>Performance Reports - Workflow :: VK Logistics Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .performance-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .progress-lg {
            height: 25px;
        }

        .task-list-item {
            border-left: 3px solid;
            padding-left: 10px;
            margin-bottom: 10px;
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

        .productivity-meter {
            width: 100px;
            height: 100px;
            position: relative;
        }

        .border-hover:hover {
            border-color: #0d6efd !important;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
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
                    <li class="nav-item"><a class="nav-link text-white" href="joining_applications.php"><i class="fas fa-file-signature"></i> Joining Form</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
                    <li class="nav-item"><a class="nav-link text-white active bg-primary rounded" href="performance.php"><i class="fas fa-chart-line"></i> Performance</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="admin_attendance_report.php"><i class="fas fa-chart-bar"></i> Attendance Report</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="manage_holidays.php"><i class="fas fa-calendar-day"></i> Manage Holidays</a></li>
                
                </ul>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1 class="h2 mb-4"><i class="fas fa-chart-line"></i> Employee Performance Reports</h1>

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

                <!-- Filter Form -->
                <div class="card mb-4 border-hover">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Performance Data</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Select Employee</label>
                                <select name="employee_id" class="form-control" required>
                                    <option value="">-- Select Employee --</option>
                                    <?php if ($db_connected): ?>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>" <?php echo $selected_employee == $emp['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo $emp['department_name']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">Database not connected</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Select Month</label>
                                <select name="month" class="form-control" required>
                                    <?php
                                    $months = [
                                        '01' => 'January',
                                        '02' => 'February',
                                        '03' => 'March',
                                        '04' => 'April',
                                        '05' => 'May',
                                        '06' => 'June',
                                        '07' => 'July',
                                        '08' => 'August',
                                        '09' => 'September',
                                        '10' => 'October',
                                        '11' => 'November',
                                        '12' => 'December'
                                    ];
                                    foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $selected_month == $num ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Select Year</label>
                                <select name="year" class="form-control" required>
                                    <?php
                                    $current_year = date('Y');
                                    for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_employee && $employee_info): ?>
                    <!-- Performance Summary -->
                    <div class="card performance-card border-hover">
                        <div class="card-header bg-white">
                            <h4 class="mb-0">
                                <i class="fas fa-user-circle"></i>
                                Performance Report for <?php echo htmlspecialchars($employee_info['full_name']); ?>
                                <small class="text-muted">
                                    (<?php echo $months[$selected_month] . ' ' . $selected_year; ?>)
                                </small>
                            </h4>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-building"></i> <?php echo $employee_info['department_name'] ?? 'No Department'; ?> |
                                <i class="fas fa-envelope"></i> <?php echo $employee_info['email']; ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <?php if ($performance_data): ?>
                                <div class="row">
                                    <!-- Performance Stats -->
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="stats-card">
                                                    <h6>Total Tasks Assigned</h6>
                                                    <h2 class="mb-0"><?php echo $performance_data['total_tasks_assigned']; ?></h2>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                                    <h6>Tasks Accepted</h6>
                                                    <h2 class="mb-0"><?php echo $performance_data['tasks_accepted']; ?></h2>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                                    <h6>Tasks Completed</h6>
                                                    <h2 class="mb-0"><?php echo $performance_data['tasks_completed']; ?></h2>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="stats-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                                    <h6>Completion Rate</h6>
                                                    <h2 class="mb-0"><?php echo $performance_data['completion_rate']; ?>%</h2>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Progress Bars -->
                                        <div class="mt-4">
                                            <h5>Performance Metrics</h5>
                                            <div class="mb-3">
                                                <label>Acceptance Rate</label>
                                                <div class="progress progress-lg">
                                                    <?php
                                                    $acceptance_rate = $performance_data['total_tasks_assigned'] > 0
                                                        ? round(($performance_data['tasks_accepted'] / $performance_data['total_tasks_assigned']) * 100, 2)
                                                        : 0;
                                                    $acceptance_color = $acceptance_rate >= 80 ? 'bg-success' : ($acceptance_rate >= 60 ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <div class="progress-bar <?php echo $acceptance_color; ?>"
                                                        role="progressbar"
                                                        style="width: <?php echo $acceptance_rate; ?>%">
                                                        <?php echo $acceptance_rate; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo $performance_data['tasks_accepted']; ?> out of <?php echo $performance_data['total_tasks_assigned']; ?> tasks accepted</small>
                                            </div>

                                            <div class="mb-3">
                                                <label>Completion Rate</label>
                                                <div class="progress progress-lg">
                                                    <?php
                                                    $completion_color = $performance_data['completion_rate'] >= 80 ? 'bg-success' : ($performance_data['completion_rate'] >= 60 ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <div class="progress-bar <?php echo $completion_color; ?>"
                                                        role="progressbar"
                                                        style="width: <?php echo $performance_data['completion_rate']; ?>%">
                                                        <?php echo $performance_data['completion_rate']; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo $performance_data['tasks_completed']; ?> out of <?php echo $performance_data['tasks_accepted']; ?> accepted tasks completed</small>
                                            </div>

                                            <div class="mb-3">
                                                <label>Average Completion Time</label>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <span class="h5 mb-0">
                                                        <?php echo number_format($performance_data['avg_completion_time_hours'], 1); ?> hours
                                                    </span>
                                                    <small class="text-muted ms-2">per task</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick Stats -->
                                    <div class="col-md-4">
                                        <div class="card bg-light border-hover">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="fas fa-chart-pie"></i> Quick Stats</h6>
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                                        Tasks Rejected
                                                        <span class="badge bg-danger rounded-pill">
                                                            <?php echo $performance_data['tasks_rejected']; ?>
                                                        </span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                                        Rejection Rate
                                                        <span class="badge bg-warning rounded-pill">
                                                            <?php
                                                            $rejection_rate = $performance_data['total_tasks_assigned'] > 0
                                                                ? round(($performance_data['tasks_rejected'] / $performance_data['total_tasks_assigned']) * 100, 2)
                                                                : 0;
                                                            echo $rejection_rate; ?>%
                                                        </span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                                        Pending Tasks
                                                        <span class="badge bg-info rounded-pill">
                                                            <?php
                                                            $pending_tasks = $performance_data['tasks_accepted'] - $performance_data['tasks_completed'] - $performance_data['tasks_rejected'];
                                                            echo max(0, $pending_tasks);
                                                            ?>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Export Options -->
                                        <div class="mt-3">
                                            <button class="btn btn-outline-primary w-100 mb-2" onclick="exportToPDF()">
                                                <i class="fas fa-file-pdf"></i> Export as PDF
                                            </button>
                                            <button class="btn btn-outline-success w-100" onclick="exportToExcel()">
                                                <i class="fas fa-file-excel"></i> Export as Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Task Details -->
                                <div class="mt-4">
                                    <h5><i class="fas fa-tasks"></i> Task Details</h5>
                                    <?php if (!empty($task_details)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Task ID</th>
                                                        <th>Title</th>
                                                        <th>Priority</th>
                                                        <th>Status</th>
                                                        <th>Assigned</th>
                                                        <th>Completed</th>
                                                        <th>Time Taken</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($task_details as $task): ?>
                                                        <tr>
                                                            <td>#<?php echo $task['id']; ?></td>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($task['title']); ?></div>
                                                                <small class="text-muted"><?php echo substr($task['description'], 0, 50); ?>...</small>
                                                            </td>
                                                            <td>
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
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php
                                                                                        $status_colors = [
                                                                                            'completed' => 'success',
                                                                                            'in_progress' => 'primary',
                                                                                            'waiting_review' => 'info'
                                                                                        ];
                                                                                        echo $status_colors[$task['status']] ?? 'warning';
                                                                                        ?>">
                                                                    <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($task['accepted_at'])); ?></td>
                                                            <td>
                                                                <?php if ($task['completed_at']): ?>
                                                                    <?php echo date('M d, Y', strtotime($task['completed_at'])); ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not completed</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($task['exact_minutes_taken']): ?>
                                                                    <?php echo number_format($task['exact_minutes_taken'], 1); ?> Minutes
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> No tasks found for this employee in the selected month.
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> No performance data available for the selected period.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($selected_employee): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Please select an employee and time period to view performance data.
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <!-- <div class="mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                    <a href="tasks.php" class="btn btn-book-now w-100">
                                        <i class="fas fa-tasks"></i> View All Tasks
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                    <a href="users.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                    <a href="departments.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-sitemap"></i> Departments
                                    </a>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                    <a href="index.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportToPDF() {
            alert('PDF export functionality will be implemented soon.');
            // You can implement PDF generation using jsPDF or server-side libraries
        }

        function exportToExcel() {
            alert('Excel export functionality will be implemented soon.');
            // You can implement Excel export using SheetJS or server-side libraries
        }

        // Auto-submit form when all filters are selected
        document.addEventListener('DOMContentLoaded', function() {
            const employeeSelect = document.querySelector('select[name="employee_id"]');
            const monthSelect = document.querySelector('select[name="month"]');
            const yearSelect = document.querySelector('select[name="year"]');

            if (employeeSelect && employeeSelect.value && monthSelect.value && yearSelect.value) {
                // Form is already submitted with values
            }
        });
    </script>
</body>

</html>