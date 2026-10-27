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

// Get user details
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check today's attendance status
$today = date('Y-m-d');
$attendance_query = "SELECT * FROM attendance_summary WHERE user_id = :user_id AND date = :date";
$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bindParam(':user_id', $user_id);
$attendance_stmt->bindParam(':date', $today);
$attendance_stmt->execute();
$today_attendance = $attendance_stmt->fetch(PDO::FETCH_ASSOC);

// Get today's punches
$punches_query = "SELECT * FROM attendance_punches 
                  WHERE user_id = :user_id 
                  AND DATE(punch_time) = :date 
                  ORDER BY punch_time ASC";
$punches_stmt = $conn->prepare($punches_query);
$punches_stmt->bindParam(':user_id', $user_id);
$punches_stmt->bindParam(':date', $today);
$punches_stmt->execute();
$today_punches = $punches_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .attendance-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .punch-btn {
            width: 200px;
            height: 80px;
            font-size: 1.2rem;
            margin: 10px;
        }
        .timer-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fefefe;
        }
        .punches-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .break-badge {
            font-size: 0.8rem;
        }
        .current-status {
            font-size: 1.5rem;
            font-weight: bold;
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
                        
                            <a class="list-group-item list-group-item-action active" href="attendance.php">
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
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="card attendance-card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-clock"></i> Attendance System</h4>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                            <span id="current-time" class="timer-display">00:00:00</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Current Status -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h5 class="alert-heading">Today's Status</h5>
                                    <div class="current-status">
                                        <?php
                                        if ($today_attendance) {
                                            echo "<span class='badge bg-success'>Present</span>";
                                            echo " | First Clock-in: " . date('h:i A', strtotime($today_attendance['first_clock_in']));
                                            if ($today_attendance['last_clock_out']) {
                                                echo " | Last Clock-out: " . date('h:i A', strtotime($today_attendance['last_clock_out']));
                                            }
                                        } else {
                                            echo "<span class='badge bg-warning text-dark'>Not Clocked In</span>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mb-4">
                            <div class="col-md-12 text-center">
                                <div id="clock-buttons">
                                    <?php
                                    // Determine which buttons to show based on current status
                                    $last_punch = end($today_punches);
                                    $is_clocked_in = false;
                                    $is_lunch_active = false;
                                    $is_tea_active = false;
                                    $is_permission_active = false;
                                    
                                    foreach ($today_punches as $punch) {
                                        switch($punch['punch_type']) {
                                            case 'clock_in':
                                                $is_clocked_in = true;
                                                break;
                                            case 'clock_out':
                                                $is_clocked_in = false;
                                                break;
                                            case 'lunch_start':
                                                $is_lunch_active = true;
                                                break;
                                            case 'lunch_end':
                                                $is_lunch_active = false;
                                                break;
                                            case 'tea_break_start':
                                                $is_tea_active = true;
                                                break;
                                            case 'tea_break_end':
                                                $is_tea_active = false;
                                                break;
                                            case 'permission_start':
                                                $is_permission_active = true;
                                                break;
                                            case 'permission_end':
                                                $is_permission_active = false;
                                                break;
                                        }
                                    }
                                    ?>
                                    
                                    <button class="btn btn-success punch-btn" 
                                            onclick="markAttendance('clock_in')" 
                                            <?php echo ($is_clocked_in) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-sign-in-alt"></i><br>
                                        CLOCK IN
                                    </button>
                                    
                                    <button class="btn btn-danger punch-btn" 
                                            onclick="markAttendance('clock_out')"
                                            <?php echo (!$is_clocked_in) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-sign-out-alt"></i><br>
                                        CLOCK OUT
                                    </button>
                                    
                                    <button class="btn btn-warning punch-btn" 
                                            onclick="markAttendance('<?php echo $is_lunch_active ? "lunch_end" : "lunch_start"; ?>')"
                                            <?php echo (!$is_clocked_in) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-utensils"></i><br>
                                        <?php echo $is_lunch_active ? "END LUNCH" : "LUNCH"; ?>
                                    </button>
                                    
                                    <button class="btn btn-info punch-btn" 
                                            onclick="markAttendance('<?php echo $is_tea_active ? "tea_break_end" : "tea_break_start"; ?>')"
                                            <?php echo (!$is_clocked_in) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-coffee"></i><br>
                                        <?php echo $is_tea_active ? "END TEA" : "TEA BREAK"; ?>
                                    </button>
                                    
                                    <button class="btn btn-secondary punch-btn" 
                                            onclick="showPermissionModal()"
                                            <?php echo (!$is_clocked_in) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-user-clock"></i><br>
                                        PERMISSION
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Punches -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-history"></i> Today's Punches</h5>
                                    </div>
                                    <div class="card-body punches-list">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>Type</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="punches-table">
                                                    <?php
                                                    foreach ($today_punches as $punch) {
                                                        $badge_class = '';
                                                        switch($punch['punch_type']) {
                                                            case 'clock_in':
                                                                $badge_class = 'bg-success';
                                                                $type_text = 'Clock In';
                                                                break;
                                                            case 'clock_out':
                                                                $badge_class = 'bg-danger';
                                                                $type_text = 'Clock Out';
                                                                break;
                                                            case 'lunch_start':
                                                                $badge_class = 'bg-warning';
                                                                $type_text = 'Lunch Start';
                                                                break;
                                                            case 'lunch_end':
                                                                $badge_class = 'bg-warning';
                                                                $type_text = 'Lunch End';
                                                                break;
                                                            case 'tea_break_start':
                                                                $badge_class = 'bg-info';
                                                                $type_text = 'Tea Break Start';
                                                                break;
                                                            case 'tea_break_end':
                                                                $badge_class = 'bg-info';
                                                                $type_text = 'Tea Break End';
                                                                break;
                                                            case 'permission_start':
                                                                $badge_class = 'bg-secondary';
                                                                $type_text = 'Permission Start';
                                                                break;
                                                            case 'permission_end':
                                                                $badge_class = 'bg-secondary';
                                                                $type_text = 'Permission End';
                                                                break;
                                                            default:
                                                                $badge_class = 'bg-secondary';
                                                                $type_text = $punch['punch_type'];
                                                        }
                                                        ?>
                                                        <tr>
                                                            <td><?php echo date('h:i A', strtotime($punch['punch_time'])); ?></td>
                                                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo $type_text; ?></span></td>
                                                            <td><?php echo htmlspecialchars($punch['notes'] ?? '-'); ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    if (empty($today_punches)) {
                                                        echo '<tr><td colspan="3" class="text-center">No punches recorded today</td></tr>';
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
                </div>
            </div>
        </div>
    </div>

    <!-- Permission Modal -->
    <div class="modal fade" id="permissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Permission Break</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="permissionNotes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="permissionNotes" rows="3" placeholder="Reason for permission..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action</label><br>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-secondary" onclick="markAttendance('permission_start')">Start Permission</button>
                            <button type="button" class="btn btn-secondary" onclick="markAttendance('permission_end')">End Permission</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            $('#current-time').text(timeString);
        }
        setInterval(updateTime, 1000);
        updateTime();

        // Mark attendance
        function markAttendance(punchType, notes = '') {
            if (punchType === 'permission_start' || punchType === 'permission_end') {
                notes = $('#permissionNotes').val();
            }
            
            $.ajax({
                url: '../ajax/mark_attendance.php',
                type: 'POST',
                data: {
                    punch_type: punchType,
                    notes: notes
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('An error occurred. Please try again.');
                }
            });
        }

        // Show permission modal
        function showPermissionModal() {
            $('#permissionModal').modal('show');
        }

        // Close permission modal after action
        $(document).ready(function() {
            $('.modal').on('hidden.bs.modal', function() {
                $('#permissionNotes').val('');
            });
        });
    </script>
</body>
</html>