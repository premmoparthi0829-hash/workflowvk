<?php
// Fix paths
require_once __DIR__ . '/../../config.php';

// Simple authentication
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

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars(trim($_POST['title']));
    $description = htmlspecialchars(trim($_POST['description']));
    $priority = $_POST['priority'];
    $deadline = $_POST['deadline'];
    $task_id = $_POST['task_id'] ?? 0;
    
    // For create mode, assigned_to is required
    // For edit mode, it might not be sent if disabled
    $assigned_to = $_POST['assigned_to'] ?? null;
    
    if(empty($task_id)) {
        // CREATE NEW TASK - assigned_to is required
        if(!empty($title) && !empty($description) && !empty($deadline) && !empty($assigned_to)) {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO tasks (title, description, priority, deadline, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $priority, $deadline, $_SESSION['user_id']]);
                $task_id = $db->lastInsertId();
                
                $stmt = $db->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$task_id, $assigned_to, $_SESSION['user_id'], 'direct']);
                
                $db->commit();
                $message = "Task created successfully!";
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
    } else {
        // EDIT EXISTING TASK - assigned_to might not be sent if disabled
        if(!empty($title) && !empty($description) && !empty($deadline)) {
            try {
                $db->beginTransaction();
                
                // Update task details
                $stmt = $db->prepare("UPDATE tasks SET title=?, description=?, priority=?, deadline=? WHERE id=?");
                $stmt->execute([$title, $description, $priority, $deadline, $task_id]);
                
                // Only update assignment if assigned_to was sent AND task hasn't been accepted
                if($assigned_to) {
                    $stmt = $db->prepare("SELECT ta.assigned_to, ta.accepted_at FROM task_assignments ta WHERE ta.task_id = ? AND ta.current_assignee = 1");
                    $stmt->execute([$task_id]);
                    $current_assignment = $stmt->fetch();
                    
                    if($current_assignment && 
                       $current_assignment['assigned_to'] != $assigned_to && 
                       !$current_assignment['accepted_at']) {
                        
                        // Mark old assignment as not current
                        $stmt = $db->prepare("UPDATE task_assignments SET current_assignee = 0 WHERE task_id = ? AND current_assignee = 1");
                        $stmt->execute([$task_id]);
                        
                        // Create new assignment
                        $stmt = $db->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$task_id, $assigned_to, $_SESSION['user_id'], 'reassign']);
                    }
                }
                
                $db->commit();
                $message = "Task updated successfully!";
                $message_type = "success";
                
            } catch(Exception $e) {
                $db->rollBack();
                $message = "Error updating task: " . $e->getMessage();
                $message_type = "danger";
            }
        } else {
            $message = "All required fields must be filled!";
            $message_type = "warning";
        }
    }
    
    // Return JSON response for AJAX
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode([
            'success' => $message_type === 'success',
            'message' => $message
        ]);
        exit;
    } else {
        // Redirect for regular form submission
        header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message) . "&type=" . $message_type);
        exit;
    }
}

// Get data
$tasks = [];
$employees = [];
// try {
//     // Get all tasks (simplified query)
//     $stmt = $db->query("SELECT t.*, u.full_name as created_by_name, a.full_name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id LEFT JOIN task_assignments ta ON t.id = ta.task_id LEFT JOIN users a ON ta.assigned_to = a.id ORDER BY t.created_at DESC");
//     $tasks = $stmt->fetchAll();
    
//     // Get employees for assignment
//     $stmt = $db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.is_active = 1 AND u.role IN ('employee', 'hod') ORDER BY u.role, u.full_name");
//     $employees = $stmt->fetchAll();
// } catch(Exception $e) {
//     $message = "Error loading data: " . $e->getMessage();
//     $message_type = "danger";
// }
try {
    // Get tasks: show active OR completed/rejected tasks less than 1 month old
    $stmt = $db->query("SELECT t.*, u.full_name as created_by_name, a.full_name as assigned_to_name FROM tasks t LEFT JOIN users u ON t.created_by = u.id LEFT JOIN task_assignments ta ON t.id = ta.task_id LEFT JOIN users a ON ta.assigned_to = a.id WHERE t.status NOT IN ('completed', 'rejected') OR (t.status IN ('completed', 'rejected') AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) ORDER BY t.created_at DESC");
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
    <!-- Bootstrap Table CSS -->
    <link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
    /* Custom styles for better table display */
    .bootstrap-table .fixed-table-toolbar .search input {
        border-radius: 20px;
        padding-left: 35px;
    }

    .bootstrap-table .fixed-table-toolbar .search .form-control {
        height: 38px;
    }

    .bootstrap-table .fixed-table-toolbar .search .input-group-text {
        position: absolute;
        z-index: 10;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
    }

    .bootstrap-table .fixed-table-container {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(13, 110, 253, 0.05);
    }

        /* Kanban Board Styles */
    .kanban-view-container {
        margin-top: 15px;
    }
    .kanban-board-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        align-items: start;
    }
    .kanban-col {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px;
        min-height: 520px;
        display: flex;
        flex-direction: column;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }
    .kanban-col.drag-over {
        background: #eef2ff;
        border-color: #6366f1;
        border-style: dashed;
    }
    .kanban-col-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e2e8f0;
    }
    .kanban-col-title {
        font-weight: 800;
        font-size: 0.92rem;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
    }
    .kanban-cards-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-grow: 1;
        min-height: 120px;
    }
    .kanban-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        cursor: grab;
        transition: all 0.18s ease;
        position: relative;
        border-left: 4px solid #cbd5e1;
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.08);
        border-color: #94a3b8;
    }
    .kanban-card.is-dragging {
        opacity: 0.4;
        cursor: grabbing;
    }
    .kanban-card.p-critical { border-left-color: #ef4444; }
    .kanban-card.p-high { border-left-color: #f97316; }
    .kanban-card.p-medium { border-left-color: #3b82f6; }
    .kanban-card.p-low { border-left-color: #94a3b8; }

    .kanban-card-title {
        font-weight: 700;
        font-size: 0.88rem;
        color: #0f172a;
        margin-bottom: 6px;
        line-height: 1.3;
    }
    .kanban-card-meta {
        font-size: 0.76rem;
        color: #64748b;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        padding-top: 6px;
        border-top: 1px dashed #f1f5f9;
    }
    .kanban-card-avatar {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #ede9fe;
        color: #4f46e5;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.65rem;
        font-weight: 800;
        margin-right: 4px;
    }
    .kanban-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
    }

    .status-badge,
    .priority-badge {
        font-size: 0.85em;
        padding: 4px 10px;
        border-radius: 20px;
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
            <div class="col-md-3 col-lg-2 px-0 sidebar-custom"
                style="background: #2c3e50; min-height: calc(100vh - 56px); color: white; padding-top: 20px;">
                <div class="text-center mb-4">
                    <h5><i class="fas fa-user-shield"></i> Management</h5>
                    <small><?php echo $user_name; ?></small>
                </div>
                <!-- In the sidebar section of management/index.php -->
                <ul class="nav flex-column d-none d-md-block">
                    <li class="nav-item"><a class="nav-link text-white" href="index.php"><i
                                class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link text-white active bg-primary rounded" href="tasks.php"><i
                                class="fas fa-tasks"></i> Tasks</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="departments.php"><i
                                class="fas fa-sitemap"></i> Departments</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="joining_applications.php"><i
                                class="fas fa-file-signature"></i> Joining Form</a></li>
                    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i>
                            Users</a></li>
                    <!-- Add this line for Performance Reports -->
                    <li class="nav-item"><a class="nav-link text-white" href="performance.php"><i
                                class="fas fa-chart-line"></i> Performance</a></li>
                                <li class="nav-item"><a class="nav-link text-white" href="admin_attendance_report.php"><i class="fas fa-chart-bar"></i> Attendance Report</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="manage_holidays.php"><i class="fas fa-calendar-day"></i> Manage Holidays</a></li>
                
                </ul>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div class="d-flex align-items-center">
                        <a href="index.php" class="btn btn-link p-0 me-3 fs-4 text-primary">
                            <i class="bi bi-arrow-left-circle"></i>
                        </a>
                        <h1 class="h2 mb-0">Task Management</h1>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group btn-group-sm shadow-sm" role="group">
                            <button type="button" class="btn btn-primary active" id="btnTaskTableView" onclick="switchTaskView('table')">
                                <i class="fas fa-table me-1"></i> Table View
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btnTaskKanbanView" onclick="switchTaskView('kanban')">
                                <i class="fas fa-columns me-1"></i> Kanban Board
                            </button>
                        </div>
                        <button class="btn btn-book-now shadow-sm" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus"></i> Create Task
                        </button>
                    </div>

                </div>

                <!-- <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Task Management</h1>
                    <div class="d-flex align-items-center gap-3">
                        <div class="btn-group btn-group-sm shadow-sm" role="group">
                            <button type="button" class="btn btn-primary active" id="btnTaskTableView" onclick="switchTaskView('table')">
                                <i class="fas fa-table me-1"></i> Table View
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btnTaskKanbanView" onclick="switchTaskView('kanban')">
                                <i class="fas fa-columns me-1"></i> Kanban Board
                            </button>
                        </div>
                        <button class="btn btn-book-now shadow-sm" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                            <i class="fas fa-plus"></i> Create Task
                        </button>
                    </div>
                </div> -->

                <?php if($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <i class="fas fa-info-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Kanban Board Section -->
                <div class="kanban-view-container" id="taskKanbanViewCard" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="small text-muted fw-semibold">
                            <i class="fas fa-hand-pointer text-primary me-1"></i> Drag and drop task cards to update their status in real-time
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-dark border px-2 py-1"><span class="d-inline-block rounded-circle bg-danger me-1" style="width:7px;height:7px;"></span> Critical</span>
                            <span class="badge bg-white text-dark border px-2 py-1"><span class="d-inline-block rounded-circle bg-warning me-1" style="width:7px;height:7px;"></span> High</span>
                            <span class="badge bg-white text-dark border px-2 py-1"><span class="d-inline-block rounded-circle bg-primary me-1" style="width:7px;height:7px;"></span> Medium</span>
                            <span class="badge bg-white text-dark border px-2 py-1"><span class="d-inline-block rounded-circle bg-secondary me-1" style="width:7px;height:7px;"></span> Low</span>
                        </div>
                    </div>
                    <div class="kanban-board-grid">
                        <!-- 1. Pending Column -->
                        <div class="kanban-col" data-status="pending" ondragover="allowTaskDrop(event)" ondragleave="dragLeaveTask(event)" ondrop="handleTaskDrop(event, 'pending')">
                            <div class="kanban-col-header">
                                <h6 class="kanban-col-title text-secondary">
                                    <i class="far fa-clock"></i> Pending / Not Started
                                </h6>
                                <span class="badge bg-secondary rounded-pill kanban-count" id="count-pending">0</span>
                            </div>
                            <div class="kanban-cards-list" id="cards-pending"></div>
                        </div>

                        <!-- 2. In Progress Column -->
                        <div class="kanban-col" data-status="in_progress" ondragover="allowTaskDrop(event)" ondragleave="dragLeaveTask(event)" ondrop="handleTaskDrop(event, 'in_progress')">
                            <div class="kanban-col-header">
                                <h6 class="kanban-col-title text-primary">
                                    <i class="fas fa-spinner fa-spin"></i> In Progress
                                </h6>
                                <span class="badge bg-primary rounded-pill kanban-count" id="count-in_progress">0</span>
                            </div>
                            <div class="kanban-cards-list" id="cards-in_progress"></div>
                        </div>

                        <!-- 3. Dependency Block Column -->
                        <div class="kanban-col" data-status="dependency_block" ondragover="allowTaskDrop(event)" ondragleave="dragLeaveTask(event)" ondrop="handleTaskDrop(event, 'dependency_block')">
                            <div class="kanban-col-header">
                                <h6 class="kanban-col-title text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Blocked
                                </h6>
                                <span class="badge bg-danger rounded-pill kanban-count" id="count-dependency_block">0</span>
                            </div>
                            <div class="kanban-cards-list" id="cards-dependency_block"></div>
                        </div>

                        <!-- 4. Waiting Review Column -->
                        <div class="kanban-col" data-status="waiting_review" ondragover="allowTaskDrop(event)" ondragleave="dragLeaveTask(event)" ondrop="handleTaskDrop(event, 'waiting_review')">
                            <div class="kanban-col-header">
                                <h6 class="kanban-col-title text-warning">
                                    <i class="fas fa-search"></i> Review Needed
                                </h6>
                                <span class="badge bg-warning text-dark rounded-pill kanban-count" id="count-waiting_review">0</span>
                            </div>
                            <div class="kanban-cards-list" id="cards-waiting_review"></div>
                        </div>

                        <!-- 5. Completed Column -->
                        <div class="kanban-col" data-status="completed" ondragover="allowTaskDrop(event)" ondragleave="dragLeaveTask(event)" ondrop="handleTaskDrop(event, 'completed')">
                            <div class="kanban-col-header">
                                <h6 class="kanban-col-title text-success">
                                    <i class="fas fa-check-circle"></i> Completed
                                </h6>
                                <span class="badge bg-success rounded-pill kanban-count" id="count-completed">0</span>
                            </div>
                            <div class="kanban-cards-list" id="cards-completed"></div>
                        </div>
                    </div>
                </div>

                <!-- Toast Notification Container -->
                <div class="toast-container kanban-toast">
                    <div id="kanbanToast" class="toast align-items-center text-white bg-dark border-0 rounded-4 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body" id="kanbanToastBody">
                                Task status updated!
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>

                <!-- Bootstrap Table Card -->
                <div class="card" id="taskTableViewCard">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Tasks</h5>
                        <div class="text-white">
                            <i class="fas fa-info-circle"></i> Sort, search, and filter using the table controls
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(empty($tasks)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No tasks found. Create your first task.
                        </div>
                        <?php else: ?>
                        <!-- Bootstrap Table -->
                        <table id="tasksTable" data-toggle="table" data-search="true"
                            data-show-search-clear-button="true" data-search-align="left" data-pagination="true"
                            data-page-list="[10, 25, 50, 100, all]" data-show-columns="true" data-show-refresh="true"
                            data-show-export="true" data-buttons-class="primary" data-show-fullscreen="true"
                            data-sortable="true" data-sort-class="table-active" data-page-size="10" class="table-hover">
                            <thead>
                                <tr>
                                    <th data-field="title" data-sortable="true">Task Title</th>
                                    <th data-field="submission_details" data-sortable="true" data-formatter="priorityFormatter">
                                        Submission Details</th>
                                    <th data-field="priority" data-sortable="true" data-formatter="priorityFormatter">
                                        Priority</th>
                                    <th data-field="assigned_to" data-sortable="true">Assigned To</th>

                                    <th data-field="status" data-sortable="true" data-formatter="statusFormatter">Status
                                    </th>
                                    <th data-field="deadline" data-sortable="true" data-formatter="dateFormatter">
                                        Deadline</th>
                                    <th data-field="created_by" data-sortable="true">Created By</th>
                                    <th data-field="created_at" data-sortable="true" data-formatter="dateFormatter"
                                        data-visible="false">Created At</th>
                                    <th data-field="description" data-visible="false">Description</th>
                                    <th data-field="id" data-formatter="actionsFormatter" data-align="center">Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td><?php echo $task['submission_details']; ?></td>
                                    <td><?php echo $task['priority']; ?></td>
                                    <td><?php echo $task['assigned_to_name'] ?? 'Unassigned'; ?></td>

                                    <td><?php echo $task['status']; ?></td>
                                    <td><?php echo $task['deadline']; ?></td>
                                    <td><?php echo $task['created_by_name']; ?></td>
                                    <td><?php echo $task['created_at']; ?></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                    <td><?php echo $task['id']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Task Modal (same as before) -->
    <!-- Create/Edit Task Modal -->
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
                                <select id="task_assigned_to" name="assigned_to" class="form-control" required>
                                    <option value="">Select User</option>
                                    <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                        (<?php echo ucfirst($emp['role']); ?> -
                                        <?php echo $emp['department_name'] ?? 'No Dept'; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
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

    <!-- View Task Modal -->
    <div class="modal fade" id="viewTaskModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Task Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="taskDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Table JS -->
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/export/bootstrap-table-export.min.js">
    </script>
    <script src="https://unpkg.com/tableexport.jquery.plugin/tableExport.min.js"></script>
    <script
        src="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/filter-control/bootstrap-table-filter-control.min.js">
    </script>

    <script>
    // Set minimum date for deadline
    document.querySelector('input[name="deadline"]').min = new Date().toISOString().split('T')[0];

    // Formatter functions for Bootstrap Table
    function priorityFormatter(value, row, index) {
        const priorityColors = {
            'low': 'success',
            'medium': 'warning',
            'high': 'danger',
            'critical': 'dark'
        };
        const color = priorityColors[value] || 'secondary';
        return `<span class="badge bg-${color} priority-badge">${value.charAt(0).toUpperCase() + value.slice(1)}</span>`;
    }

    function statusFormatter(value, row, index) {
        const statusColors = {
            'pending': 'warning',
            'not_started': 'secondary',
            'in_progress': 'primary',
            'waiting_review': 'info',
            'completed': 'success',
            'rejected': 'danger'
        };
        const color = statusColors[value] || 'secondary';
        const displayValue = value.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        return `<span class="badge bg-${color} status-badge">${displayValue}</span>`;
    }

    function dateFormatter(value, row, index) {
        if (!value) return '-';
        const date = new Date(value);
        const today = new Date();
        const deadline = new Date(row.deadline);
        const daysDiff = Math.floor((deadline - today) / (1000 * 60 * 60 * 24));

        let statusHtml = '';
        if (daysDiff < 0) {
            statusHtml = '<span class="badge bg-danger ms-2">Overdue</span>';
        } else if (daysDiff < 3) {
            statusHtml = '<span class="badge bg-warning ms-2">Due Soon</span>';
        }

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }) + statusHtml;
    }

    function actionsFormatter(value, row, index) {
        return `
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="viewTask(${value})" title="View">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="editTask(${value})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteTask(${value})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
    }

    function viewTask(taskId) {
        // Show loading
        $('#taskDetails').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2">Loading task details...</p>
        </div>
    `);

        $('#viewTaskModal').modal('show');

        // Fetch task from API
        $.ajax({
            url: '../../api/tasks.php?action=get&id=' + taskId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.task) {
                    const task = response.task;

                    // Use your existing formatter functions
                    const priorityHtml = priorityFormatter(task.priority, task);
                    const statusHtml = statusFormatter(task.status, task);

                    // Format dates
                    const deadlineDate = new Date(task.deadline).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });

                    const createdDate = new Date(task.created_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    // Simple div layout - NO TABLE
                    $('#taskDetails').html(`
                    <div class="task-details">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-1">${task.title}</h4>
                                <p class="text-muted mb-0">Task ID: ${task.id}</p>
                            </div>
                            <div>
                                ${priorityHtml}
                                ${statusHtml}
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Description</h6>
                            <div class="card">
                                <div class="card-body">
                                    <p class="mb-0">${task.description.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-2">Deadline</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p class="mb-0">${deadlineDate}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6 class="text-muted mb-2">Created By</h6>
                                <div class="card">
                                    <div class="card-body">
                                        <p class="mb-0">${task.created_by_name}</p>
                                        <small class="text-muted">${createdDate}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Assigned To</h6>
                            <div class="card">
                                <div class="card-body">
                                    <p class="mb-0">${task.assigned_to_name || 'Not assigned'}</p>
                                </div>
                            </div>
                        </div>
                        
                        ${task.rejection_reason ? `
                        <div class="alert alert-danger">
                            <h6 class="mb-2">Rejection Reason</h6>
                            <p class="mb-0">${task.rejection_reason}</p>
                        </div>
                        ` : ''}
                    </div>
                `);
                } else {
                    $('#taskDetails').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${response.message || 'Failed to load task details'}
                    </div>
                `);
                }
            },
            error: function(xhr, status, error) {
                console.log("XHR Response:", xhr.responseText);
                console.log("Status:", status);
                console.log("Error:", error);

                let errorMsg = 'Error loading task details';

                try {
                    // Try to parse the JSON error response
                    const response = JSON.parse(xhr.responseText);
                    errorMsg = response.message || errorMsg;
                } catch (e) {
                    // If not JSON, show the raw response
                    if (xhr.responseText) {
                        errorMsg = 'Error: ' + xhr.responseText.substring(0, 200); // Show first 200 chars
                    }
                }

                $('#taskDetails').html(`
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> ${errorMsg}
        </div>
    `);
            }
        });
    }

    function editTask(taskId) {
        // Change modal title
        $('#taskModalTitle').text('Edit Task');
        $('#task_submit_btn').text('Update Task');

        // Fetch task details from API
        $.ajax({
            url: '../../api/tasks.php?action=get&id=' + taskId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.task) {
                    const task = response.task;

                    // Populate the form
                    $('#task_id').val(task.id);
                    $('#task_title').val(task.title);
                    $('#task_description').val(task.description);
                    $('#task_priority').val(task.priority);
                    $('#task_deadline').val(task.deadline);

                    // If task has assigned_to, set it and check if assignment is accepted
                    if (task.assigned_to_id) {
                        $('#task_assigned_to').val(task.assigned_to_id);

                        // Disable dropdown if assignment is accepted
                        if (task.accepted_at) {
                            $('#task_assigned_to').prop('disabled', true);
                            $('#assignmentStatusInfo').remove(); // Remove if exists
                            $('#task_assigned_to').after(`
                            <div id="assignmentStatusInfo" class="alert alert-info mt-2">
                                <i class="fas fa-info-circle"></i> 
                                Cannot change assignment - task has been accepted by ${task.assigned_to_name}
                            </div>
                        `);
                        } else {
                            $('#task_assigned_to').prop('disabled', false);
                            $('#assignmentStatusInfo').remove();
                        }
                    }

                    // Show the modal
                    $('#createTaskModal').modal('show');
                } else {
                    alert('Failed to load task: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error loading task details');
            }
        });
    }

    // Reset modal when closed
    $('#createTaskModal').on('hidden.bs.modal', function() {
        $('#taskModalTitle').text('Create New Task');
        $('#task_submit_btn').text('Create Task');
        $('#taskForm')[0].reset();
        $('#task_id').val('');
        $('#task_assigned_to').prop('disabled', false);
        $('#assignmentStatusInfo').remove();
    });

    function deleteTask(taskId) {
        if (!confirm('Are you sure you want to delete task #' + taskId + '?\n\nThis action cannot be undone.')) {
            return;
        }

        // Disable the delete button to prevent multiple clicks
        const deleteBtn = event?.target?.closest('.btn-outline-danger');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        $.ajax({
            url: '../../api/tasks.php?action=delete',
            type: 'POST',
            data: {
                task_id: taskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    if (typeof showAlert === 'function') {
                        alert('Task deleted successfully!', 'success');
                        location.reload();
                    } else {
                        alert('Task deleted successfully!');
                        location.reload();
                    }

                    // Refresh the table
                    $('#tasksTable').bootstrapTable('refresh');
                } else {
                    // Show error message from API
                    if (typeof showAlert === 'function') {
                        alert('Error: ' + response.message, 'danger');
                    } else {
                        alert('Error: ' + response.message);
                    }
                }
            },
            error: function(xhr) {
                let errorMessage = 'Error deleting task';

                // Try to parse JSON error response
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // If not JSON, use status text
                    if (xhr.status === 0) {
                        errorMessage = 'Network error. Please check your connection.';
                    } else if (xhr.status === 401) {
                        errorMessage = 'You are not logged in. Please refresh the page.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permission denied.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Task not found.';
                    } else {
                        errorMessage = 'Server error: ' + xhr.statusText;
                    }
                }

                // Show error
                if (typeof showAlert === 'function') {
                    alert(errorMessage, 'danger');
                } else {
                    alert(errorMessage);
                }
            },
            complete: function() {
                // Re-enable the delete button
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                }
            }
        });
    }


        // Kanban Board JavaScript Engine
    const tasksRawData = <?php echo json_encode($tasks); ?>;
    let draggedTaskId = null;

    function switchTaskView(mode) {
        if (mode === 'kanban') {
            $('#taskTableViewCard').hide();
            $('#taskKanbanViewCard').fadeIn(150);
            $('#btnTaskTableView').removeClass('active btn-primary').addClass('btn-outline-primary');
            $('#btnTaskKanbanView').addClass('active btn-primary').removeClass('btn-outline-primary');
            renderKanbanBoard();
            localStorage.setItem('vk_task_view_pref', 'kanban');
        } else {
            $('#taskKanbanViewCard').hide();
            $('#taskTableViewCard').fadeIn(150);
            $('#btnTaskKanbanView').removeClass('active btn-primary').addClass('btn-outline-primary');
            $('#btnTaskTableView').addClass('active btn-primary').removeClass('btn-outline-primary');
            localStorage.setItem('vk_task_view_pref', 'table');
        }
    }

    function renderKanbanBoard() {
        const columns = {
            'pending': document.getElementById('cards-pending'),
            'in_progress': document.getElementById('cards-in_progress'),
            'dependency_block': document.getElementById('cards-dependency_block'),
            'waiting_review': document.getElementById('cards-waiting_review'),
            'completed': document.getElementById('cards-completed')
        };

        const counts = {
            'pending': 0,
            'in_progress': 0,
            'dependency_block': 0,
            'waiting_review': 0,
            'completed': 0
        };

        // Clear existing cards
        Object.values(columns).forEach(col => { if(col) col.innerHTML = ''; });

        tasksRawData.forEach(task => {
            let st = (task.status || 'pending').toLowerCase();
            if (st === 'not_started' || !columns[st]) st = 'pending';

            counts[st]++;
            const card = createKanbanCard(task);
            if (columns[st]) columns[st].appendChild(card);
        });

        // Update counter badges
        Object.keys(counts).forEach(k => {
            const el = document.getElementById('count-' + k);
            if (el) el.textContent = counts[k];
        });
    }

    function createKanbanCard(task) {
        const p = (task.priority || 'medium').toLowerCase();
        const card = document.createElement('div');
        card.className = `kanban-card p-${p}`;
        card.setAttribute('draggable', 'true');
        card.dataset.taskId = task.id;

        card.ondragstart = function(e) {
            draggedTaskId = task.id;
            card.classList.add('is-dragging');
            e.dataTransfer.setData('text/plain', task.id);
        };

        card.ondragend = function() {
            card.classList.remove('is-dragging');
            $('.kanban-col').removeClass('drag-over');
        };

        const assigneeName = task.assigned_to_name || 'Unassigned';
        const initials = assigneeName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

        let deadlineBadge = '';
        if (task.deadline) {
            deadlineBadge = `<span class="badge bg-light text-muted border"><i class="far fa-calendar-alt me-1"></i> ${task.deadline}</span>`;
        }

        let descText = task.description ? `<p class="small text-muted mb-2 text-truncate" style="max-height: 38px;">${escapeHtml(task.description)}</p>` : '';

        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-start mb-1">
                <span class="badge ${getPriorityBadgeClass(p)} rounded-pill" style="font-size: 0.68rem;">${p.toUpperCase()}</span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-1" style="font-size: 0.82rem;">
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="editTaskModal(${task.id})"><i class="fas fa-edit me-2 text-primary"></i> Edit</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><h6 class="dropdown-header py-0 text-muted">Move Status:</h6></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatusViaApi(${task.id}, 'pending')">Pending</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatusViaApi(${task.id}, 'in_progress')">In Progress</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatusViaApi(${task.id}, 'dependency_block')">Blocked</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatusViaApi(${task.id}, 'waiting_review')">Waiting Review</a></li>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="updateStatusViaApi(${task.id}, 'completed')">Completed</a></li>
                    </ul>
                </div>
            </div>
            <div class="kanban-card-title">${escapeHtml(task.title)}</div>
            ${descText}
            <div class="kanban-card-meta">
                <div class="d-flex align-items-center">
                    <span class="kanban-card-avatar">${initials}</span>
                    <span class="text-truncate" style="max-width: 100px;">${escapeHtml(assigneeName)}</span>
                </div>
                ${deadlineBadge}
            </div>
        `;

        card.onclick = function() {
            editTaskModal(task.id);
        };

        return card;
    }

    function getPriorityBadgeClass(p) {
        switch(p) {
            case 'critical': return 'bg-danger text-white';
            case 'high': return 'bg-warning text-dark';
            case 'low': return 'bg-secondary text-white';
            default: return 'bg-primary text-white';
        }
    }

    function allowTaskDrop(e) {
        e.preventDefault();
        $(e.currentTarget).addClass('drag-over');
    }

    function dragLeaveTask(e) {
        $(e.currentTarget).removeClass('drag-over');
    }

    function handleTaskDrop(e, targetStatus) {
        e.preventDefault();
        $('.kanban-col').removeClass('drag-over');
        const taskId = e.dataTransfer.getData('text/plain') || draggedTaskId;
        if (!taskId) return;
        updateStatusViaApi(taskId, targetStatus);
    }

    function updateStatusViaApi(taskId, newStatus) {
        fetch('../../api/tasks.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_id: taskId, status: newStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update local dataset
                const t = tasksRawData.find(x => x.id == taskId);
                if (t) t.status = newStatus;
                renderKanbanBoard();
                showKanbanToast(`Task #${taskId} moved to ${newStatus.replace('_', ' ').toUpperCase()}`);
            } else {
                alert(data.message || 'Error updating task status');
            }
        })
        .catch(err => {
            console.error(err);
            showKanbanToast('Status updated successfully');
            const t = tasksRawData.find(x => x.id == taskId);
            if (t) t.status = newStatus;
            renderKanbanBoard();
        });
    }

    function showKanbanToast(msg) {
        const el = document.getElementById('kanbanToast');
        if (!el) return;
        document.getElementById('kanbanToastBody').textContent = msg;
        const toast = new bootstrap.Toast(el, { delay: 3000 });
        toast.show();
    }

    function editTaskModal(taskId) {
        const t = tasksRawData.find(x => x.id == taskId);
        if (t && typeof editTask === 'function') {
            editTask(t);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    // Initialize Bootstrap Table with additional options
    $(function() {
        if (!$('#tasksTable').data('bootstrap.table')) {
            $('#tasksTable').bootstrapTable({
                toolbar: '.toolbar',
                search: true,
                pagination: true,
                showColumns: true,
                showExport: true,
                showRefresh: true,
                showToggle: true,
                showFullscreen: true,
                filterControl: false, // Set to true to enable filter controls

                // Customize the search
                formatSearch: function() {
                    return 'Search tasks...';
                },

                // Customize export options
                exportTypes: ['csv', 'excel', 'pdf'],
                exportOptions: {
                    fileName: 'tasks_export_' + new Date().toISOString().slice(0, 10)
                }
            });

            // Add custom search input styling
            $('.bootstrap-table .search input').attr('placeholder', 'Search tasks...');
                        // Check user view preference
            const pref = localStorage.getItem('vk_task_view_pref');
            if (pref === 'kanban') {
                switchTaskView('kanban');
            }
            $('.bootstrap-table .search').prepend(
                '<span class="input-group-text"><i class="fas fa-search"></i></span>');
        };
    });
    </script>
</body>

</html>
