<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

// Check if user is admin/HOD/management
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['management', 'hod'])) {
    echo 'Unauthorized access';
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$employee_id = $_GET['employee_id'] ?? 0;

// Get employee details
$emp_query = "SELECT u.*, d.name as department_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.id = :id";
$emp_stmt = $conn->prepare($emp_query);
$emp_stmt->bindParam(':id', $employee_id);
$emp_stmt->execute();
$employee = $emp_stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo '<div class="alert alert-danger">Employee not found</div>';
    exit();
}

// Get leave balance
$balance_query = "SELECT * FROM employee_leave_balance WHERE user_id = :user_id";
$balance_stmt = $conn->prepare($balance_query);
$balance_stmt->bindParam(':user_id', $employee_id);
$balance_stmt->execute();
$leave_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);

// Get leave history
$history_query = "SELECT * FROM employee_leaves 
                  WHERE user_id = :user_id 
                  ORDER BY start_date DESC";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bindParam(':user_id', $employee_id);
$history_stmt->execute();
$leaves = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats = [
    'total_applied' => 0,
    'total_approved' => 0,
    'total_rejected' => 0,
    'total_pending' => 0,
    'total_days_approved' => 0
];

foreach ($leaves as $leave) {
    $stats['total_applied']++;
    
    switch($leave['status']) {
        case 'approved':
            $stats['total_approved']++;
            $stats['total_days_approved'] += $leave['total_days'];
            break;
        case 'rejected':
            $stats['total_rejected']++;
            break;
        case 'pending':
            $stats['total_pending']++;
            break;
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h5><?php echo htmlspecialchars($employee['full_name']); ?></h5>
            <p class="text-muted">
                <?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?> | 
                <?php echo htmlspecialchars($employee['email']); ?>
            </p>
            <hr>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-title text-primary">Casual Leave</h6>
                    <h3><?php echo $leave_balance['casual_leave'] ?? 0; ?></h3>
                    <p class="card-text">Days Available</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-title text-warning">Sick Leave</h6>
                    <h3><?php echo $leave_balance['sick_leave'] ?? 0; ?></h3>
                    <p class="card-text">Days Available</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-title text-success">Earned Leave</h6>
                    <h3><?php echo $leave_balance['earned_leave'] ?? 0; ?></h3>
                    <p class="card-text">Days Available</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-title text-info">Approved Days</h6>
                    <h3><?php echo $stats['total_days_approved']; ?></h3>
                    <p class="card-text">This Year</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6>Leave History</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                    <th>Approved/Rejected By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaves as $leave): ?>
                                    <tr>
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
                                        <td><?php echo htmlspecialchars($leave['reason'] ?: '-'); ?></td>
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
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?></td>
                                        <td>
                                            <?php if ($leave['approved_by']): 
                                                $approver_query = "SELECT full_name FROM users WHERE id = :id";
                                                $approver_stmt = $conn->prepare($approver_query);
                                                $approver_stmt->bindParam(':id', $leave['approved_by']);
                                                $approver_stmt->execute();
                                                $approver = $approver_stmt->fetch(PDO::FETCH_ASSOC);
                                            ?>
                                                <?php echo htmlspecialchars($approver['full_name'] ?? 'Admin'); ?><br>
                                                <small class="text-muted">
                                                    <?php echo $leave['approved_at'] ? date('d M Y', strtotime($leave['approved_at'])) : ''; ?>
                                                </small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($leaves)): ?>
                                    <tr><td colspan="7" class="text-center">No leave applications found</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>