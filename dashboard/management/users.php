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
    if(isset($_POST['create_user'])) {
        $username = htmlspecialchars(trim($_POST['username']));
        $email = htmlspecialchars(trim($_POST['email']));
        $password = $_POST['password'];
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $role = $_POST['role'];
        $department_id = $_POST['department_id'] ?: null;
        $position = htmlspecialchars(trim($_POST['position']));
        
        if(!empty($username) && !empty($email) && !empty($password) && !empty($full_name) && !empty($role)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, role, department_id, position) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                if($stmt->execute([$username, $email, $hashed_password, $full_name, $role, $department_id, $position])) {
                    $message = "User created successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to create user.";
                    $message_type = "danger";
                }
            } catch(Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "danger";
            }
        } else {
            $message = "All required fields must be filled!";
            $message_type = "warning";
        }
    }
    elseif(isset($_POST['update_user'])) {
        $id = $_POST['id'];
        $username = htmlspecialchars(trim($_POST['username']));
        $email = htmlspecialchars(trim($_POST['email']));
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $role = $_POST['role'];
        $department_id = $_POST['department_id'] ?: null;
        $position = htmlspecialchars(trim($_POST['position']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle password update if provided
        $password_update = '';
        $params = [$username, $email, $full_name, $role, $department_id, $position, $is_active, $id];
        
        if(!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $password_update = ", password = ?";
            array_splice($params, 7, 0, $hashed_password);
        }
        
        if(!empty($username) && !empty($email) && !empty($full_name) && !empty($role)) {
            try {
                $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, department_id = ?, position = ?, is_active = ?$password_update WHERE id = ?";
                $stmt = $db->prepare($sql);
                
                if($stmt->execute($params)) {
                    $message = "User updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to update user.";
                    $message_type = "danger";
                }
            } catch(Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "danger";
            }
        } else {
            $message = "All required fields must be filled!";
            $message_type = "warning";
        }
    }
}

// Handle delete request
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Prevent deleting own account
        if($id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account!";
            $message_type = "danger";
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            if($stmt->execute([$id])) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to delete user.";
                $message_type = "danger";
            }
        }
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get users and departments
$users = [];
$departments = [];
try {
    $stmt = $db->query("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.full_name");
    $users = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT * FROM departments ORDER BY name");
    $departments = $stmt->fetchAll();
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
    <title>Users - Workflow :: VK Logistics Hub</title>
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
    <li class="nav-item"><a class="nav-link text-white" href="departments.php"><i class="fas fa-sitemap"></i> Departments</a></li>
    <li class="nav-item"><a class="nav-link text-white" href="joining_applications.php"><i class="fas fa-file-signature"></i> Joining Form</a></li>
    <li class="nav-item"><a class="nav-link text-white active bg-primary rounded" href="users.php"><i class="fas fa-users"></i> Users</a></li>
    <!-- Add this line for Performance Reports -->
    <li class="nav-item"><a class="nav-link text-white" href="performance.php"><i class="fas fa-chart-line"></i> Performance</a></li>
<li class="nav-item"><a class="nav-link text-white" href="admin_attendance_report.php"><i class="fas fa-chart-bar"></i> Attendance Report</a></li>
               <li class="nav-item"><a class="nav-link text-white" href="manage_holidays.php"><i class="fas fa-calendar-day"></i> Manage Holidays</a></li>
                
</ul>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">User Management</h1>
                    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div> -->
                <div class="d-flex justify-content-between align-items-center mb-4">

    <div class="d-flex align-items-center">
        <a href="index.php" class="btn btn-link p-0 me-3 fs-4 text-primary">
            <i class="bi bi-arrow-left-circle"></i>
        </a>
        <h1 class="h2 mb-0">User Management</h1>
    </div>

    <button class="btn btn-book-now" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-user-plus"></i> Add User
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
                        <h5 class="mb-0">All Users</h5>
                        <div class="text-white">
                            <i class="fas fa-info-circle"></i> Sort, search, and filter using table controls
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Bootstrap Table -->
                        <table id="usersTable" 
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
                                    <th data-field="id" data-sortable="true" data-width="80">ID</th>
                                    <th data-field="username" data-sortable="true">Username</th>
                                    <th data-field="full_name" data-sortable="true">Full Name</th>
                                    <th data-field="email" data-sortable="true">Email</th>
                                    <th data-field="role" data-sortable="true" data-formatter="roleFormatter">Role</th>
                                    <th data-field="department" data-sortable="true">Department</th>
                                    <th data-field="position" data-sortable="true">Position</th>
                                    <th data-field="status" data-sortable="true" data-formatter="statusFormatter" data-width="100">Status</th>
                                    <th data-field="actions" data-formatter="actionsFormatter" data-align="center" data-width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['role']; ?></td>
                                    <td><?php echo $user['department_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($user['position'] ?? '-'); ?></td>
                                    <td><?php echo $user['is_active']; ?></td>
                                    <td><?php echo $user['id']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role" class="form-control" required>
                                    <option value="">Select Role</option>
                                    <option value="management">Management</option>
                                    <option value="hod">Head of Department (HOD)</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., Software Developer">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="editUserId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" id="editUsername" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" id="editEmail" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" name="password" id="editPassword" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" id="editFullName" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select name="role" id="editRole" class="form-control" required>
                                    <option value="">Select Role</option>
                                    <option value="management">Management</option>
                                    <option value="hod">Head of Department (HOD)</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" id="editDepartmentId" class="form-control">
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" id="editPosition" class="form-control" placeholder="e.g., Software Developer">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" id="editIsActive" class="form-check-input">
                            <label class="form-check-label" for="editIsActive">Active User</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
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
        function roleFormatter(value, row, index) {
            const roleColors = {
                'management': 'danger',
                'hod': 'success',
                'employee': 'primary'
            };
            const color = roleColors[value] || 'secondary';
            return `<span class="badge bg-${color}">${value.charAt(0).toUpperCase() + value.slice(1)}</span>`;
        }
        
        function statusFormatter(value, row, index) {
            if (value == 1) {
                return '<span class="badge bg-success">Active</span>';
            } else {
                return '<span class="badge bg-secondary">Inactive</span>';
            }
        }
        
        function actionsFormatter(value, row, index) {
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            const isCurrentUser = (value == currentUserId);
            
            let buttons = `
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="editUser(${value})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>`;
            
            if (!isCurrentUser) {
                buttons += `
                    <button type="button" class="btn btn-outline-danger" onclick="deleteUser(${value})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>`;
            }
            
            buttons += `</div>`;
            
            return buttons;
        }
        
        // User actions functions
        function editUser(userId) {
            // Find the user data from the table
            const table = $('#usersTable').bootstrapTable('getData');
            const user = table.find(u => u.id == userId);
            
            if (user) {
                // Populate the edit modal with user data
                $('#editUserId').val(user.id);
                $('#editUsername').val(user.username);
                $('#editEmail').val(user.email);
                $('#editFullName').val(user.full_name);
                $('#editRole').val(user.role);
                $('#editDepartmentId').val(user.department_id || '');
                $('#editPosition').val(user.position || '');
                $('#editIsActive').prop('checked', user.is_active == 1);
                
                // Show the edit modal
                $('#editUserModal').modal('show');
            }
        }
        
        function deleteUser(userId) {
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            
            if (userId == currentUserId) {
                alert('You cannot delete your own account!');
                return;
            }
            
            if (confirm(`Are you sure you want to delete this user? This action cannot be undone.`)) {
                window.location.href = `users.php?delete=${userId}`;
            }
        }
        
        // Initialize Bootstrap Table
        $(function() {
            $('#usersTable').bootstrapTable({
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
                    return 'Search users...';
                },
                
                // Customize export options
                exportTypes: ['csv', 'excel', 'pdf'],
                exportOptions: {
                    fileName: 'users_export_' + new Date().toISOString().slice(0,10)
                }
            });
            
            // Add custom search input styling
            $('.bootstrap-table .search input').attr('placeholder', 'Search users...');
            $('.bootstrap-table .search').prepend('<span class="input-group-text"><i class="fas fa-search"></i></span>');
        });
        
        // Clear form when create modal is closed
        $('#createUserModal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
        });
    </script>
</body>
</html>