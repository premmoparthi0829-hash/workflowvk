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
    if(isset($_POST['create_department'])) {
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
    elseif(isset($_POST['update_department'])) {
        $id = $_POST['id'];
        $name = htmlspecialchars(trim($_POST['name']));
        $description = htmlspecialchars(trim($_POST['description']));
        
        if(!empty($name)) {
            try {
                $stmt = $db->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
                if($stmt->execute([$name, $description, $id])) {
                    $message = "Department updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to update department.";
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
}

// Handle delete request
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Check if department has users
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
        $stmt->execute([$id]);
        $user_count = $stmt->fetchColumn();
        
        if($user_count > 0) {
            $message = "Cannot delete department. There are users assigned to it.";
            $message_type = "danger";
        } else {
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            if($stmt->execute([$id])) {
                $message = "Department deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to delete department.";
                $message_type = "danger";
            }
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
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
    <!-- Bootstrap Table CSS -->
    <link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css">
     <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
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
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
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
                <!-- In the sidebar section of management/index.php -->
<ul class="nav flex-column d-none d-md-block">
    <li class="nav-item"><a class="nav-link text-white" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="tasks.php"><i class="fas fa-tasks"></i> Tasks</a></li>
    <li class="nav-item"><a class="nav-link text-white active bg-primary rounded" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="users.php"><i class="fas fa-users"></i> Users</a></li>
    <!-- Add this line for Performance Reports -->
    <li class="nav-item"><a class="nav-link text-white" href="performance.php"><i class="fas fa-chart-line"></i> Performance</a></li>
<li class="nav-item"><a class="nav-link text-white" href="admin_attendance_report.php"><i class="fas fa-chart-bar"></i> Attendance Report</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="manage_holidays.php"><i class="fas fa-calendar-day"></i> Manage Holidays</a></li>
                
</ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Department Management</h1>
                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createDeptModal">
                        <i class="fas fa-plus"></i> Add Department
                    </button>
                </div> -->
                <div class="d-flex justify-content-between align-items-center mb-4">

    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-link p-0 me-3 fs-4 text-primary">
            <i class="bi bi-arrow-left-circle"></i>
        </a>
        <h1 class="h2 mb-0">Department Management</h1>
    </div>

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
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Departments</h5>
                        <div class="text-white">
                            <i class="fas fa-info-circle"></i> Sort, search, and filter using table controls
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(empty($departments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No departments found. Create your first department.
                            </div>
                        <?php else: ?>
                            <!-- Bootstrap Table -->
                            <table id="departmentsTable" 
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
                                        <th data-field="id" data-sortable="true" data-width="100">ID</th>
                                        <th data-field="name" data-sortable="true">Department Name</th>
                                        <th data-field="description" data-sortable="true">Description</th>
                                        <th data-field="created_by" data-sortable="true">Created By</th>
                                        <th data-field="created_at" data-sortable="true" data-formatter="dateFormatter">Created Date</th>
                                        <th data-field="actions" data-formatter="actionsFormatter" data-align="center" data-width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['id']; ?></td>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['description']); ?></td>
                                        <td><?php echo $dept['created_by_name'] ?? 'System'; ?></td>
                                        <td><?php echo $dept['created_at']; ?></td>
                                        <td><?php echo $dept['id']; ?></td>
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

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editDeptId">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" id="editDeptName" class="form-control" required placeholder="e.g., IT Department">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="editDeptDescription" class="form-control" rows="3" placeholder="Brief description of the department"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
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
        
        function actionsFormatter(value, row, index) {
            return `
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="editDepartment(${value})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteDepartment(${value})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        }
        
        // Department actions functions
        function editDepartment(deptId) {
            // Find the department data from the table
            const table = $('#departmentsTable').bootstrapTable('getData');
            const department = table.find(d => d.id == deptId);
            
            if (department) {
                // Populate the edit modal with department data
                $('#editDeptId').val(department.id);
                $('#editDeptName').val(department.name);
                $('#editDeptDescription').val(department.description || '');
                
                // Show the edit modal
                $('#editDeptModal').modal('show');
            }
        }
        
        function deleteDepartment(deptId) {
            if (confirm(`Are you sure you want to delete this department? This action cannot be undone.`)) {
                window.location.href = `departments.php?delete=${deptId}`;
            }
        }
        
        // Initialize Bootstrap Table
        $(function() {
            $('#departmentsTable').bootstrapTable({
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
                    return 'Search departments...';
                },
                
                // Customize export options
                exportTypes: ['csv', 'excel', 'pdf'],
                exportOptions: {
                    fileName: 'departments_export_' + new Date().toISOString().slice(0,10)
                }
            });
            
            // Add custom search input styling
            $('.bootstrap-table .search input').attr('placeholder', 'Search departments...');
            $('.bootstrap-table .search').prepend('<span class="input-group-text"><i class="fas fa-search"></i></span>');
        });
        
        // Clear form when create modal is closed
        $('#createDeptModal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
        });
    </script>
</body>
</html>