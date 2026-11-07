<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Get user's leave balance (you need to create this table first, see below)
$balance_query = "SELECT * FROM employee_leave_balance WHERE user_id = :user_id";
$balance_stmt = $conn->prepare($balance_query);
$balance_stmt->bindParam(':user_id', $user_id);
$balance_stmt->execute();
$leave_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);

if (!$leave_balance) {
    // Initialize leave balance if not exists
    $init_query = "INSERT INTO employee_leave_balance (user_id, casual_leave, sick_leave, earned_leave) 
                   VALUES (:user_id, 12, 12, 15)";
    $init_stmt = $conn->prepare($init_query);
    $init_stmt->bindParam(':user_id', $user_id);
    $init_stmt->execute();
    
    // Re-fetch
    $balance_stmt->execute();
    $leave_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $leave_duration = ($_POST['leave_duration'] ?? 'full') === 'half' ? 'half' : 'full';
    $reason = trim($_POST['reason']);
    
    // Calculate total days (excluding weekends)
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    $total_days = 0.0;
    $dates = [];

    if ($leave_duration === 'half' && $start_date !== $end_date) {
        $error = 'Half-day leave must have the same start and end date.';
    }
    
    foreach ($period as $date) {
        $day_of_week = $date->format('N');
        if ($day_of_week < 6) { // Monday=1 to Friday=5
            $total_days += 1;
            $dates[] = $date->format('Y-m-d');
        }
    }

    if ($leave_duration === 'half' && $total_days > 0) {
        $total_days = 0.5;
    }

    if ($total_days <= 0 && !isset($error)) {
        $error = 'Select at least one weekday for leave.';
    }
    
    // Check if dates overlap with existing approved leave
    $check_query = "SELECT COUNT(*) as overlap FROM employee_leaves 
                    WHERE user_id = :user_id 
                    AND status = 'approved'
                    AND ((start_date BETWEEN :start_date AND :end_date) 
                         OR (end_date BETWEEN :start_date AND :end_date)
                         OR (start_date <= :start_date AND end_date >= :end_date))";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->bindParam(':start_date', $start_date);
    $check_stmt->bindParam(':end_date', $end_date);
    $check_stmt->execute();
    $overlap = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!isset($error) && $overlap['overlap'] > 0) {
        $error = "You already have approved leave during this period!";
    } 
    // Check leave balance for paid leaves
    elseif (!isset($error) && in_array($leave_type, ['casual', 'sick', 'earned'])) {
        $balance_field = $leave_type . '_leave';
        if ($leave_balance[$balance_field] < $total_days) {
            $error = "Insufficient " . ucfirst($leave_type) . " leave balance! Available: " . 
                    $leave_balance[$balance_field] . " days, Required: $total_days days";
        }
    }
    
    if (!isset($error)) {
        // Insert leave application
        $insert_query = "INSERT INTO employee_leaves (user_id, leave_type, start_date, end_date, total_days, reason) 
                         VALUES (:user_id, :leave_type, :start_date, :end_date, :total_days, :reason)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user_id);
        $insert_stmt->bindParam(':leave_type', $leave_type);
        $insert_stmt->bindParam(':start_date', $start_date);
        $insert_stmt->bindParam(':end_date', $end_date);
        $insert_stmt->bindParam(':total_days', $total_days);
        $insert_stmt->bindParam(':reason', $reason);
        
        if ($insert_stmt->execute()) {
            $success = "Leave application submitted successfully! Total days: " . rtrim(rtrim(number_format($total_days, 1), '0'), '.');
        } else {
            $error = "Failed to submit leave application.";
        }
    }
}

// Get user's leave history
$history_query = "SELECT * FROM employee_leaves 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
$history_stmt = $conn->prepare($history_query);
$history_stmt->bindParam(':user_id', $user_id);
$history_stmt->execute();
$leave_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user details
$user_query = "SELECT * FROM users WHERE id = :id";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bindParam(':id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$format_leave_days = static function ($days) {
    return rtrim(rtrim(number_format((float) $days, 1), '0'), '.');
};
$casual_leave_used = max(0, 12 - (float) ($leave_balance['casual_leave'] ?? 12));
$sick_leave_used = max(0, 12 - (float) ($leave_balance['sick_leave'] ?? 12));
$earned_leave_used = max(0, 15 - (float) ($leave_balance['earned_leave'] ?? 15));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
    .leave-balance-card {
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .leave-casual {
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    .leave-sick {
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }

    .leave-earned {
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .leave-unpaid {
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-approved {
        background-color: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background-color: #f8d7da;
        color: #721c24;
    }

    .status-cancelled {
        background-color: #d6d8d9;
        color: #1b1e21;
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
                <?php echo htmlspecialchars($user['full_name']); ?> (Employee)
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <div class="container-fluid">
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
                        <a href="task-history.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history"></i> Task History
                        </a>

                        <a class="list-group-item list-group-item-action" href="attendance.php">
                            <i class="fas fa-clock"></i> Attendance
                        </a>


                        <a class="list-group-item list-group-item-action" href="view_attendance.php">
                            <i class="fas fa-history"></i> Attendance History
                        </a>
                        <a class="list-group-item list-group-item-action active" href="apply_leave.php">
                                <i class="fas fa-umbrella-beach"></i> Apply Leave
                            </a>

                    </div>
                </div>
            </div>
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 mt-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-umbrella-beach"></i> Apply for Leave</h4>
                    </div>

                    <!-- Leave Balance -->
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Your Leave Balance</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card leave-balance-card leave-casual">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Casual Leave</h6>
                                                <h2><?php echo $format_leave_days($casual_leave_used); ?>/12</h2>
                                                <p class="card-text">Days Used / Allowance</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card leave-balance-card leave-sick">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Sick Leave</h6>
                                                <h2><?php echo $format_leave_days($sick_leave_used); ?>/12</h2>
                                                <p class="card-text">Days Used / Allowance</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card leave-balance-card leave-earned">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Earned Leave</h6>
                                                <h2><?php echo $format_leave_days($earned_leave_used); ?>/15</h2>
                                                <p class="card-text">Days Used / Allowance</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card leave-balance-card leave-unpaid">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Unpaid Leave</h6>
                                                <h2>∞</h2>
                                                <p class="card-text">Unlimited</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Leave Application Form -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">Apply for Leave</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label for="leave_type" class="form-label">Leave Type *</label>
                                                <select class="form-select" id="leave_type" name="leave_type" required>
                                                    <option value="">Select Leave Type</option>
                                                    <option value="casual">Casual Leave</option>
                                                    <option value="sick">Sick Leave</option>
                                                    <option value="earned">Earned Leave</option>
                                                    <option value="maternity">Maternity Leave</option>
                                                    <option value="paternity">Paternity Leave</option>
                                                    <option value="unpaid">Unpaid Leave</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="start_date" class="form-label">Start Date *</label>
                                                <input type="date" class="form-control" id="start_date"
                                                    name="start_date" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="end_date" class="form-label">End Date *</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date"
                                                    required>
                                            </div>
                                            <div class="mb-3">
                                                <span class="form-label d-block">Leave Duration *</span>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="leave_duration" id="duration_full" value="full" checked>
                                                    <label class="form-check-label" for="duration_full">Full Day</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="leave_duration" id="duration_half" value="half">
                                                    <label class="form-check-label" for="duration_half">Half Day</label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="reason" class="form-label">Reason *</label>
                                                <textarea class="form-control" id="reason" name="reason" rows="3"
                                                    placeholder="Please provide reason for leave..."
                                                    required></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle"></i> Weekends (Saturday & Sunday) are
                                                    automatically excluded from leave days calculation.
                                                </small>
                                            </div>
                                            <button type="submit" name="apply_leave" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Submit Application
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">My Leave History</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Dates</th>
                                                        <th>Days</th>
                                                        <th>Status</th>
                                                        <th>Applied On</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($leave_history as $leave): ?>
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
                                                            <?php echo date('D, d M Y', strtotime($leave['start_date'])); ?>
                                                            <?php if ($leave['start_date'] !== $leave['end_date']): ?>
                                                            - <?php echo date('D, d M Y', strtotime($leave['end_date'])); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo $format_leave_days($leave['total_days']); ?></td>
                                                        <td>
                                                            <span class="badge status-<?php echo $leave['status']; ?>">
                                                                <?php echo ucfirst($leave['status']); ?>
                                                            </span>
                                                            <?php if ($leave['status'] == 'approved' && $leave['approved_by']): ?>
                                                            <br><small class="text-muted">
                                                                By: <?php 
                                                                    // Get approver name
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
                                                        <td><?php echo date('d M Y', strtotime($leave['created_at'])); ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($leave_history)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">No leave applications found
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Leave Policy</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled mb-0">
                                            <li><i class="fas fa-check text-success"></i> Casual Leave: 12 days per year
                                            </li>
                                            <li><i class="fas fa-check text-success"></i> Sick Leave: 12 days per year
                                            </li>
                                            <li><i class="fas fa-check text-success"></i> Earned Leave: 15 days per year
                                            </li>
                                            <li><i class="fas fa-check text-success"></i> Maternity Leave: 26 weeks</li>
                                            <li><i class="fas fa-check text-success"></i> Paternity Leave: 15 days</li>
                                            <li><i class="fas fa-check text-success"></i> Apply at least 2 days in
                                                advance</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    $(document).ready(function() {
        function parseLocalDate(value) {
            var parts = value.split('-').map(Number);
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }

        // Initialize date pickers
        var endPicker;
        var startPicker = flatpickr("#start_date", {
            minDate: "today",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d-m-Y",
            onChange: function(selectedDates, dateStr) {
                if (dateStr && endPicker) {
                    endPicker.set('minDate', dateStr);
                    if (endPicker.selectedDates.length && endPicker.selectedDates[0] < selectedDates[0]) {
                        endPicker.setDate(selectedDates[0], true);
                    }
                }
            }
        });

        endPicker = flatpickr("#end_date", {
            minDate: "today",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d-m-Y"
        });

        // Set default start date to tomorrow
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        startPicker.setDate(tomorrow, false);

        // Set default end date to the day after tomorrow
        var dayAfter = new Date(tomorrow);
        dayAfter.setDate(dayAfter.getDate() + 1);
        endPicker.setDate(dayAfter, false);

        // Calculate days when dates change
        $('#start_date, #end_date, input[name="leave_duration"]').on('change', function() {
            calculateLeaveDays();
        });

        function calculateLeaveDays() {
            var start = $('#start_date').val();
            var end = $('#end_date').val();

            if (start && end) {
                var startDate = parseLocalDate(start);
                var endDate = parseLocalDate(end);

                if (startDate <= endDate) {
                    var totalDays = 0;
                    var weekdays = [];
                    var current = new Date(startDate);

                    while (current <= endDate) {
                        var day = current.getDay(); // 0=Sunday, 6=Saturday
                        if (day !== 0 && day !== 6) { // Exclude weekends
                            totalDays++;
                            weekdays.push(new Intl.DateTimeFormat(undefined, { weekday: 'long' }).format(current) + ', ' +
                                new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short' }).format(current));
                        }
                        current.setDate(current.getDate() + 1);
                    }

                    // Show calculated days
                    if (!$('#daysInfo').length) {
                        $('#reason').after('<div id="daysInfo" class="alert alert-info mt-2"></div>');
                    }
                    var isHalfDay = $('input[name="leave_duration"]:checked').val() === 'half';
                    if (isHalfDay && start !== end) {
                        endPicker.setDate(startDate, false);
                        endDate = new Date(startDate);
                        totalDays = startDate.getDay() !== 0 && startDate.getDay() !== 6 ? 1 : 0;
                        weekdays = totalDays ? [new Intl.DateTimeFormat(undefined, { weekday: 'long' }).format(startDate) + ', ' +
                            new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short' }).format(startDate)] : [];
                    }
                    var leaveDays = isHalfDay ? (totalDays ? '0.5' : '0') : String(totalDays);
                    var dayLabel = weekdays.length ? ' (' + weekdays.join(', ') + ')' : '';
                    $('#daysInfo').html('<i class="fas fa-calculator"></i> Calculated Leave Days: <strong>' +
                        leaveDays + '</strong>' + dayLabel + ' (weekends excluded)');
                }
            }
        }

        // Initial calculation
        calculateLeaveDays();
    });
    </script>
</body>

</html>
