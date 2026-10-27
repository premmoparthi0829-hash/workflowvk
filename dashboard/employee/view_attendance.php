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

// Get date range from URL or default to current month
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$start_date = date('Y-m-01', strtotime($month));
$end_date = date('Y-m-t', strtotime($month));

// Get attendance history
$query = "SELECT * FROM attendance_summary 
          WHERE user_id = :user_id 
          AND date BETWEEN :start_date AND :end_date
          ORDER BY date DESC";
$stmt = $conn->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Attendance History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
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
                        <a href="task-history.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history"></i> Task History
                        </a>
                        
                            <a class="list-group-item list-group-item-action" href="attendance.php">
                                <i class="fas fa-clock"></i> Attendance
                            </a>
                       
                       
                            <a class="list-group-item list-group-item-action active" href="view_attendance.php">
                                <i class="fas fa-history"></i> Attendance History
                            </a>
                            <a class="list-group-item list-group-item-action" href="apply_leave.php">
                                <i class="fas fa-umbrella-beach"></i> Apply Leave
                            </a>
                        
                    </div>
                </div>
            </div>
             <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-history"></i> My Attendance History</h4>
                <p>Employee: <?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>
            
            <div class="card-body">
                <!-- Month Selector -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <form method="GET" class="row g-3">
                            <div class="col-auto">
                                <label for="month" class="form-label">Select Month:</label>
                            </div>
                            <div class="col-auto">
                                <input type="month" class="form-control" id="month" name="month" 
                                       value="<?php echo $month; ?>" max="<?php echo date('Y-m'); ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary">View</button>
                                <a href="attendance.php" class="btn btn-secondary">Back to Attendance</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <?php
                $total_days = count($attendance_history);
                $present_days = 0;
                $half_days = 0;
                $absent_days = 0;
                $total_work_hours = 0;
                
                foreach ($attendance_history as $record) {
                    switch($record['status']) {
                        case 'present':
                            $present_days++;
                            break;
                        case 'half_day':
                            $half_days++;
                            break;
                        case 'absent':
                            $absent_days++;
                            break;
                    }
                    $total_work_hours += $record['total_work_hours'];
                }
                ?>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h5 class="card-title">Present</h5>
                                <h2><?php echo $present_days; ?></h2>
                                <p>Days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <h5 class="card-title">Half Days</h5>
                                <h2><?php echo $half_days; ?></h2>
                                <p>Days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body text-center">
                                <h5 class="card-title">Absent</h5>
                                <h2><?php echo $absent_days; ?></h2>
                                <p>Days</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body text-center">
                                <h5 class="card-title">Work Hours</h5>
                                <h2><?php echo number_format($total_work_hours, 1); ?></h2>
                                <p>Hours</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>First In</th>
                                <th>Last Out</th>
                                <th>Work Hours</th>
                                <th>Break Hours</th>
                                <th>Permission Hours</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($attendance_history as $record) {
                                $day_name = date('l', strtotime($record['date']));
                                $status_badge = '';
                                switch($record['status']) {
                                    case 'present':
                                        $status_badge = '<span class="badge bg-success">Present</span>';
                                        break;
                                    case 'half_day':
                                        $status_badge = '<span class="badge bg-warning">Half Day</span>';
                                        break;
                                    case 'absent':
                                        $status_badge = '<span class="badge bg-danger">Absent</span>';
                                        break;
                                    case 'leave':
                                        $status_badge = '<span class="badge bg-info">Leave</span>';
                                        break;
                                }
                                ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($record['date'])); ?></td>
                                    <td><?php echo $day_name; ?></td>
                                    <td><?php echo $record['first_clock_in'] ? date('h:i A', strtotime($record['first_clock_in'])) : '-'; ?></td>
                                    <td><?php echo $record['last_clock_out'] ? date('h:i A', strtotime($record['last_clock_out'])) : '-'; ?></td>
                                    <td><?php echo number_format($record['total_work_hours'], 2); ?> hrs</td>
                                    <td><?php echo number_format($record['total_break_hours'], 2); ?> hrs</td>
                                    <td><?php echo number_format($record['total_permission_hours'], 2); ?> hrs</td>
                                    <td><?php echo $status_badge; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="viewDailyDetails('<?php echo $record['date']; ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            }
                            
                            if (empty($attendance_history)) {
                                echo '<tr><td colspan="9" class="text-center">No attendance records found for this month</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

             </div>
        </div>
        
    </div>

    <!-- Modal for daily details -->
    <div class="modal fade" id="dailyDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Daily Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dailyDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDailyDetails(date) {
            $.ajax({
                url: '../ajax/get_daily_details.php',
                type: 'GET',
                data: { date: date },
                success: function(response) {
                    $('#dailyDetailsContent').html(response);
                    $('#dailyDetailsModal').modal('show');
                }
            });
        }
    </script>
</body>
</html>