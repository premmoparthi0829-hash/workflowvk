<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

// Check if user is admin/HOD/management
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['management', 'hod'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_leave']) || isset($_POST['reject_leave'])) {
        $leave_id = $_POST['leave_id'];
        $action = isset($_POST['approve_leave']) ? 'approve' : 'reject';
        
        // Get leave details first
        $get_leave_query = "SELECT * FROM employee_leaves WHERE id = :id";
        $get_leave_stmt = $conn->prepare($get_leave_query);
        $get_leave_stmt->bindParam(':id', $leave_id);
        $get_leave_stmt->execute();
        $leave = $get_leave_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($leave) {
            // Check if user has permission (HOD can only approve their department)
            if ($user_role === 'hod') {
                $check_dept_query = "SELECT u.department_id 
                                     FROM employee_leaves l 
                                     JOIN users u ON l.user_id = u.id 
                                     WHERE l.id = :leave_id";
                $check_dept_stmt = $conn->prepare($check_dept_query);
                $check_dept_stmt->bindParam(':leave_id', $leave_id);
                $check_dept_stmt->execute();
                $dept_info = $check_dept_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get HOD's department
                $hod_dept_query = "SELECT department_id FROM users WHERE id = :user_id";
                $hod_dept_stmt = $conn->prepare($hod_dept_query);
                $hod_dept_stmt->bindParam(':user_id', $user_id);
                $hod_dept_stmt->execute();
                $hod_dept = $hod_dept_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($dept_info['department_id'] != $hod_dept['department_id']) {
                    $_SESSION['error_message'] = "You can only approve leaves from your department.";
                    header('Location: manage_leaves.php');
                    exit();
                }
            }
            
            // Update leave status
            $update_query = "UPDATE employee_leaves 
                             SET status = :status, 
                                 approved_by = :approved_by,
                                 approved_at = NOW()
                             WHERE id = :id";
            $update_stmt = $conn->prepare($update_query);
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $update_stmt->bindParam(':status', $status);
            $update_stmt->bindParam(':approved_by', $user_id);
            $update_stmt->bindParam(':id', $leave_id);
            
            if ($update_stmt->execute()) {
                // If approved and it's a paid leave, deduct from balance
                if ($action === 'approve' && in_array($leave['leave_type'], ['casual', 'sick', 'earned'])) {
                    $balance_field = $leave['leave_type'] . '_leave';
                    
                    // Update leave balance
                    $balance_query = "UPDATE employee_leave_balance 
                                      SET $balance_field = $balance_field - :days,
                                          last_updated = NOW()
                                      WHERE user_id = :user_id";
                    $balance_stmt = $conn->prepare($balance_query);
                    $balance_stmt->bindParam(':days', $leave['total_days']);
                    $balance_stmt->bindParam(':user_id', $leave['user_id']);
                    $balance_stmt->execute();
                }
                
                $_SESSION['success_message'] = "Leave " . $status . " successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to update leave status.";
            }
        }
    }
}

// Get pending leaves based on user role
if ($user_role === 'management') {
    // Admin can see all pending leaves
    $pending_query = "SELECT l.*, u.full_name, u.email, u.department_id, d.name as department_name 
                      FROM employee_leaves l 
                      JOIN users u ON l.user_id = u.id 
                      LEFT JOIN departments d ON u.department_id = d.id 
                      WHERE l.status = 'pending' 
                      ORDER BY l.created_at ASC";
    $pending_stmt = $conn->prepare($pending_query);
} else {
    // HOD can only see leaves from their department
    $pending_query = "SELECT l.*, u.full_name, u.email, u.department_id, d.name as department_name 
                      FROM employee_leaves l 
                      JOIN users u ON l.user_id = u.id 
                      LEFT JOIN departments d ON u.department_id = d.id 
                      WHERE l.status = 'pending' 
                      AND u.department_id = (SELECT department_id FROM users WHERE id = :user_id) 
                      ORDER BY l.created_at ASC";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->bindParam(':user_id', $user_id);
}

$pending_stmt->execute();
$pending_leaves = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all leaves (for management view)
if ($user_role === 'management') {
    $all_query = "SELECT l.*, u.full_name, u.email, u.department_id, d.name as department_name 
                  FROM employee_leaves l 
                  JOIN users u ON l.user_id = u.id 
                  LEFT JOIN departments d ON u.department_id = d.id 
                  ORDER BY l.created_at DESC 
                  LIMIT 100";
    $all_stmt = $conn->prepare($all_query);
    $all_stmt->execute();
    $all_leaves = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user details
$user_query = "SELECT * FROM users WHERE id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindParam(':id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leaves</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .leave-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .leave-casual { border-left-color: #17a2b8; }
        .leave-sick { border-left-color: #ffc107; }
        .leave-earned { border-left-color: #28a745; }
        .leave-maternity { border-left-color: #007bff; }
        .leave-paternity { border-left-color: #6f42c1; }
        .leave-unpaid { border-left-color: #dc3545; }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-tasks"></i> Manage Leave Applications</h4>
                <div>
                    <span class="badge bg-light text-dark">
                        <?php echo $user_role === 'management' ? 'Administrator' : 'HOD'; ?>
                    </span>
                    <!-- <?php if ($user_role === 'management'): ?>
                        <a href="apply_leave.php" class="btn btn-light btn-sm ms-2">
                            <i class="fas fa-plus"></i> Apply Leave
                        </a>
                    <?php endif; ?> -->
                    <a href="<?php echo $user_role === 'management' ? 'index.php' : 'index.php'; ?>" 
                       class="btn btn-light btn-sm ms-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="card-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Pending Leaves -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Pending Approvals
                            <span class="badge bg-light text-dark"><?php echo count($pending_leaves); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_leaves)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-check-circle"></i> No pending leave applications.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($pending_leaves as $leave): 
                                    $leave_class = 'leave-' . $leave['leave_type'];
                                ?>
                                    <div class="col-md-6">
                                        <div class="card leave-card <?php echo $leave_class; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php echo htmlspecialchars($leave['full_name']); ?>
                                                        </h6>
                                                        <p class="mb-1 text-muted">
                                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($leave['email']); ?><br>
                                                            <i class="fas fa-building"></i> <?php echo htmlspecialchars($leave['department_name'] ?? 'No Department'); ?>
                                                        </p>
                                                        <p class="mb-1">
                                                            <strong>Leave Type:</strong>
                                                            <span class="badge 
                                                                <?php 
                                                                switch($leave['leave_type']) {
                                                                    case 'casual': echo 'bg-info'; break;
                                                                    case 'sick': echo 'bg-warning text-dark'; break;
                                                                    case 'earned': echo 'bg-success'; break;
                                                                    case 'maternity': echo 'bg-primary'; break;
                                                                    case 'paternity': echo 'bg-info'; break;
                                                                    case 'unpaid': echo 'bg-danger'; break;
                                                                }
                                                                ?>">
                                                                <?php echo ucfirst($leave['leave_type']); ?>
                                                            </span>
                                                        </p>
                                                        <p class="mb-1">
                                                            <strong>Dates:</strong> 
                                                            <?php echo date('d M Y', strtotime($leave['start_date'])); ?> to 
                                                            <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                                        </p>
                                                        <p class="mb-1"><strong>Days:</strong> <?php echo $leave['total_days']; ?> working days</p>
                                                        <?php if ($leave['reason']): ?>
                                                            <p class="mb-1"><strong>Reason:</strong> <?php echo htmlspecialchars($leave['reason']); ?></p>
                                                        <?php endif; ?>
                                                        <p class="mb-0 text-muted">
                                                            <small>
                                                                Applied on: <?php echo date('d M Y, h:i A', strtotime($leave['created_at'])); ?>
                                                            </small>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                        <button type="submit" name="approve_leave" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button type="submit" name="reject_leave" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                    <?php if ($user_role === 'management'): ?>
                                                        <button class="btn btn-info btn-sm" 
                                                                onclick="viewEmployeeLeaves(<?php echo $leave['user_id']; ?>, '<?php echo addslashes($leave['full_name']); ?>')">
                                                            <i class="fas fa-eye"></i> View All
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- All Leaves (Management Only) -->
                <?php if ($user_role === 'management'): ?>
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-history"></i> All Leave Applications
                                <span class="badge bg-light text-dark"><?php echo count($all_leaves); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Dates</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th>Applied On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_leaves as $leave): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($leave['department_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        switch($leave['leave_type']) {
                                                            case 'casual': echo 'bg-info'; break;
                                                            case 'sick': echo 'bg-warning text-dark'; break;
                                                            case 'earned': echo 'bg-success'; break;
                                                            case 'maternity': echo 'bg-primary'; break;
                                                            case 'paternity': echo 'bg-info'; break;
                                                            case 'unpaid': echo 'bg-danger'; break;
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst($leave['leave_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M', strtotime($leave['start_date'])); ?> - 
                                                    <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                                                </td>
                                                <td><?php echo $leave['total_days']; ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                        switch($leave['status']) {
                                                            case 'pending': echo 'bg-warning text-dark'; break;
                                                            case 'approved': echo 'bg-success'; break;
                                                            case 'rejected': echo 'bg-danger'; break;
                                                            case 'cancelled': echo 'bg-secondary'; break;
                                                        }
                                                        ?>">
                                                        <?php echo ucfirst($leave['status']); ?>
                                                    </span>
                                                    <?php if ($leave['status'] == 'approved' && $leave['approved_by']): ?>
                                                        <br><small class="text-muted">
                                                            By: <?php 
                                                                $approver_query = "SELECT full_name FROM users WHERE id = :id";
                                                                $approver_stmt = $conn->prepare($approver_query);
                                                                $approver_stmt->bindParam(':id', $leave['approved_by']);
                                                                $approver_stmt->execute();
                                                                $approver = $approver_stmt->fetch(PDO::FETCH_ASSOC);
                                                                echo $approver['full_name'] ?? 'Admin';
                                                            ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($leave['status'] == 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                            <button type="submit" name="approve_leave" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="submit" name="reject_leave" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-sm" 
                                                            onclick="viewEmployeeLeaves(<?php echo $leave['user_id']; ?>, '<?php echo addslashes($leave['full_name']); ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Employee Leaves Modal -->
    <div class="modal fade" id="employeeLeavesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Leave History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="employeeLeavesContent">
                    <!-- Loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewEmployeeLeaves(userId, employeeName) {
            $.ajax({
                url: '../ajax/get_employee_leaves.php',
                type: 'GET',
                data: { employee_id: userId },
                beforeSend: function() {
                    $('#employeeLeavesContent').html(`
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading leave history for ${employeeName}...</p>
                        </div>
                    `);
                },
                success: function(response) {
                    $('#employeeLeavesModal .modal-title').text('Leave History - ' + employeeName);
                    $('#employeeLeavesContent').html(response);
                    $('#employeeLeavesModal').modal('show');
                }
            });
        }
    </script>
</body>
</html>