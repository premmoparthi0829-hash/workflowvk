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
    
    // Get task history for this employee (all tasks including old ones)
    $stmt = $db->prepare("
        SELECT t.*, 
               u.full_name as created_by_name,
               ta.id as assignment_id,
               ta.accepted_at,
               ta.rejected_at,
               ta.rejection_reason,
               DATEDIFF(NOW(), t.created_at) as days_old
        FROM tasks t
        JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
        LEFT JOIN users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $all_tasks = $stmt->fetchAll();
    
} catch(Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Get statistics
$total_tasks = count($all_tasks);
$completed_tasks = array_filter($all_tasks, function($task) {
    return $task['status'] === 'completed';
});
$rejected_tasks = array_filter($all_tasks, function($task) {
    return $task['status'] === 'rejected';
});
$active_tasks = array_filter($all_tasks, function($task) {
    return !in_array($task['status'], ['completed', 'rejected']);
});

// Group tasks by month for timeline view
$tasks_by_month = [];
foreach ($all_tasks as $task) {
    $month_year = date('F Y', strtotime($task['created_at']));
    if (!isset($tasks_by_month[$month_year])) {
        $tasks_by_month[$month_year] = [];
    }
    $tasks_by_month[$month_year][] = $task;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task History - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap Table CSS -->
    <link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css">
     <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 2px solid white;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
        }
        
        .timeline-date {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .task-card-history {
            border-left: 4px solid;
            margin-bottom: 15px;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .task-card-history:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .priority-low { border-left-color: #28a745; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-high { border-left-color: #dc3545; }
        .priority-critical { border-left-color: #343a40; }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .month-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .task-description {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .timeline-item.completed::before {
            background-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }
        
        .timeline-item.rejected::before {
            background-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
        }
        
        .timeline-item.active::before {
            background-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-buttons .btn {
            border-radius: 20px;
            padding: 5px 15px;
        }
        
        .export-buttons {
            margin-top: 20px;
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
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tasks"></i> My Tasks
                        </a>
                        <a href="task-history.php" class="list-group-item list-group-item-action active">
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
                    <h1 class="h2 mb-0">Task History</h1>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="exportTable('csv')"><i class="fas fa-file-csv"></i> CSV</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportTable('excel')"><i class="fas fa-file-excel"></i> Excel</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportTable('pdf')"><i class="fas fa-file-pdf"></i> PDF</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card bg-primary text-white" onclick="filterTasks('all')">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Tasks</h5>
                                <h2 class="mb-0"><?php echo $total_tasks; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-success text-white" onclick="filterTasks('completed')">
                            <div class="card-body text-center">
                                <h5 class="card-title">Completed</h5>
                                <h2 class="mb-0"><?php echo count($completed_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-danger text-white" onclick="filterTasks('rejected')">
                            <div class="card-body text-center">
                                <h5 class="card-title">Rejected</h5>
                                <h2 class="mb-0"><?php echo count($rejected_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card bg-warning text-white" onclick="filterTasks('active')">
                            <div class="card-body text-center">
                                <h5 class="card-title">Active</h5>
                                <h2 class="mb-0"><?php echo count($active_tasks); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="filter-buttons">
                            <button class="btn btn-outline-primary active" onclick="filterTasks('all')">All Tasks</button>
                            <button class="btn btn-outline-success" onclick="filterTasks('completed')">Completed</button>
                            <button class="btn btn-outline-danger" onclick="filterTasks('rejected')">Rejected</button>
                            <button class="btn btn-outline-warning" onclick="filterTasks('active')">Active</button>
                            <button class="btn btn-outline-info" onclick="filterTasks('pending')">Pending Acceptance</button>
                            <button class="btn btn-outline-secondary" onclick="filterTasks('this_month')">This Month</button>
                            <button class="btn btn-outline-dark" onclick="filterTasks('last_month')">Last Month</button>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline View -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-stream"></i> Task Timeline</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($all_tasks)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No task history available.
                            </div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach($tasks_by_month as $month => $month_tasks): ?>
                                <div class="month-header">
                                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> <?php echo $month; ?></h5>
                                    <small><?php echo count($month_tasks); ?> tasks</small>
                                </div>
                                
                                <?php foreach($month_tasks as $task): 
                                    $status_class = '';
                                    if($task['status'] === 'completed') $status_class = 'completed';
                                    elseif($task['status'] === 'rejected') $status_class = 'rejected';
                                    else $status_class = 'active';
                                ?>
                                <div class="timeline-item <?php echo $status_class; ?>" data-status="<?php echo $task['status']; ?>" data-month="<?php echo date('Y-m', strtotime($task['created_at'])); ?>">
                                    <div class="card task-card-history priority-<?php echo $task['priority']; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <div class="timeline-date">
                                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($task['created_at'])); ?>
                                                        <?php if($task['accepted_at']): ?>
                                                            <span class="ms-3"><i class="fas fa-check-circle text-success"></i> Accepted: <?php echo date('M d', strtotime($task['accepted_at'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        $priority_colors = [
                                                            'low' => 'success',
                                                            'medium' => 'warning',
                                                            'high' => 'danger',
                                                            'critical' => 'dark'
                                                        ];
                                                        echo $priority_colors[$task['priority']] ?? 'secondary';
                                                    ?> me-2">
                                                        <?php echo ucfirst($task['priority']); ?>
                                                    </span>
                                                    <span class="badge status-badge bg-<?php 
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
                                            </div>
                                            
                                            <p class="card-text task-description mb-2"><?php echo htmlspecialchars($task['description']); ?></p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> Assigned by: <?php echo $task['created_by_name']; ?>
                                                    <?php if($task['deadline']): ?>
                                                        <span class="ms-3"><i class="fas fa-clock"></i> Deadline: <?php echo date('M d, Y', strtotime($task['deadline'])); ?></span>
                                                    <?php endif; ?>
                                                </small>
                                                
                                                <?php if($task['rejection_reason']): ?>
                                                    <small class="text-danger">
                                                        <i class="fas fa-comment"></i> Reason: <?php echo htmlspecialchars($task['rejection_reason']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Table View -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Task History Table</h5>
                        <div class="text-white">
                            <i class="fas fa-info-circle"></i> <?php echo $total_tasks; ?> total tasks
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Bootstrap Table -->
                        <table id="taskHistoryTable" 
                               data-toggle="table"
                               data-search="true"
                               data-show-search-clear-button="true"
                               data-search-align="left"
                               data-pagination="true"
                               data-page-list="[10, 25, 50, 100, all]"
                               data-show-columns="true"
                               data-show-refresh="true"
                               data-show-export="true"
                               data-buttons-class="primary"
                               data-show-fullscreen="true"
                               data-sortable="true"
                               data-sort-class="table-active"
                               data-page-size="10"
                               class="table-hover">
                            <thead>
                                <tr>
                                    <th data-field="title" data-sortable="true">Task Title</th>
                                    <th data-field="created_at" data-sortable="true" data-formatter="dateFormatter">Created Date</th>
                                    <th data-field="deadline" data-sortable="true" data-formatter="dateFormatter">Deadline</th>
                                    <th data-field="status" data-sortable="true" data-formatter="statusFormatterTable">Status</th>
                                    <th data-field="priority" data-sortable="true" data-formatter="priorityFormatterTable">Priority</th>
                                    <th data-field="created_by" data-sortable="true">Assigned By</th>
                                    <th data-field="accepted_at" data-sortable="true" data-formatter="dateFormatter" data-visible="false">Accepted Date</th>
                                    <th data-field="rejected_at" data-sortable="true" data-formatter="dateFormatter" data-visible="false">Rejected Date</th>
                                    <th data-field="description" data-visible="false">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($all_tasks as $task): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                    <td><?php echo $task['created_at']; ?></td>
                                    <td><?php echo $task['deadline']; ?></td>
                                    <td><?php echo $task['status']; ?></td>
                                    <td><?php echo $task['priority']; ?></td>
                                    <td><?php echo $task['created_by_name']; ?></td>
                                    <td><?php echo $task['accepted_at']; ?></td>
                                    <td><?php echo $task['rejected_at']; ?></td>
                                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap Table JS -->
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/export/bootstrap-table-export.min.js"></script>
    <script src="https://unpkg.com/tableexport.jquery.plugin/tableExport.min.js"></script>
    
    <script>
        // Formatter functions for Bootstrap Table
        function dateFormatter(value, row, index) {
            if (!value) return '-';
            const date = new Date(value);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
        
        function statusFormatterTable(value, row, index) {
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
        
        function priorityFormatterTable(value, row, index) {
            const priorityColors = {
                'low': 'success',
                'medium': 'warning', 
                'high': 'danger',
                'critical': 'dark'
            };
            const color = priorityColors[value] || 'secondary';
            return `<span class="badge bg-${color}">${value.charAt(0).toUpperCase() + value.slice(1)}</span>`;
        }
        
        // Filter tasks function
        function filterTasks(filterType) {
            const timelineItems = document.querySelectorAll('.timeline-item');
            const filterButtons = document.querySelectorAll('.filter-buttons .btn');
            
            // Update active button
            filterButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(filterType) || 
                    (filterType === 'all' && btn.textContent === 'All Tasks')) {
                    btn.classList.add('active');
                }
            });
            
            // Show/hide timeline items based on filter
            timelineItems.forEach(item => {
                let showItem = false;
                const status = item.getAttribute('data-status');
                const month = item.getAttribute('data-month');
                const currentMonth = new Date().toISOString().slice(0, 7);
                const lastMonth = new Date(new Date().setMonth(new Date().getMonth() - 1)).toISOString().slice(0, 7);
                
                switch(filterType) {
                    case 'all':
                        showItem = true;
                        break;
                    case 'completed':
                        showItem = status === 'completed';
                        break;
                    case 'rejected':
                        showItem = status === 'rejected';
                        break;
                    case 'active':
                        showItem = !['completed', 'rejected'].includes(status);
                        break;
                    case 'pending':
                        showItem = status === 'pending';
                        break;
                    case 'this_month':
                        showItem = month === currentMonth;
                        break;
                    case 'last_month':
                        showItem = month === lastMonth;
                        break;
                    default:
                        showItem = true;
                }
                
                if (showItem) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Also filter table if available
            if (typeof $('#taskHistoryTable').bootstrapTable === 'function') {
                const table = $('#taskHistoryTable').bootstrapTable('getData');
                let filteredData = table;
                
                if (filterType !== 'all') {
                    filteredData = table.filter(row => {
                        const status = row.status;
                        const createdDate = new Date(row.created_at);
                        const currentMonth = new Date().getMonth();
                        const currentYear = new Date().getFullYear();
                        const lastMonth = new Date().getMonth() - 1;
                        
                        switch(filterType) {
                            case 'completed':
                                return status === 'completed';
                            case 'rejected':
                                return status === 'rejected';
                            case 'active':
                                return !['completed', 'rejected'].includes(status);
                            case 'pending':
                                return status === 'pending';
                            case 'this_month':
                                return createdDate.getMonth() === currentMonth && 
                                       createdDate.getFullYear() === currentYear;
                            case 'last_month':
                                return createdDate.getMonth() === lastMonth && 
                                       createdDate.getFullYear() === currentYear;
                            default:
                                return true;
                        }
                    });
                }
                
                $('#taskHistoryTable').bootstrapTable('load', filteredData);
            }
        }
        
        // Export table function
        function exportTable(type) {
            const table = $('#taskHistoryTable');
            let fileName = 'task_history_' + new Date().toISOString().slice(0,10);
            
            if (type === 'csv') {
                table.tableExport({type: 'csv', fileName: fileName});
            } else if (type === 'excel') {
                table.tableExport({type: 'excel', fileName: fileName});
            } else if (type === 'pdf') {
                table.tableExport({type: 'pdf', fileName: fileName});
            }
        }
        
        // Initialize Bootstrap Table
        $(function() {
            $('#taskHistoryTable').bootstrapTable({
                toolbar: '.toolbar',
                search: true,
                pagination: true,
                showColumns: true,
                showExport: true,
                showRefresh: true,
                showToggle: true,
                showFullscreen: true,
                
                // Customize the search
                formatSearch: function() {
                    return 'Search task history...';
                },
                
                // Customize export options
                exportTypes: ['csv', 'excel', 'pdf'],
                exportOptions: {
                    fileName: 'task_history_export_' + new Date().toISOString().slice(0,10)
                }
            });
            
            // Add custom search input styling
            $('.bootstrap-table .search input').attr('placeholder', 'Search task history...');
            $('.bootstrap-table .search').prepend('<span class="input-group-text"><i class="fas fa-search"></i></span>');
            
            // Make stat cards clickable for filtering
            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.cursor = 'pointer';
            });
        });
        
        // Initialize with all tasks shown
        document.addEventListener('DOMContentLoaded', function() {
            filterTasks('all');
        });
    </script>
</body>
</html>