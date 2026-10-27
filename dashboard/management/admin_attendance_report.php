<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

// Check if user is admin/management
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
     header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get filters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

// Calculate date range
$start_date = date('Y-m-01', strtotime($month));
$end_date = date('Y-m-t', strtotime($month));
$total_days = date('t', strtotime($month));
$current_day = date('j');

// Get all departments
$dept_query = "SELECT * FROM departments ORDER BY name";
$dept_stmt = $conn->query($dept_query);
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all employees
$emp_query = "SELECT u.id, u.full_name, u.email, d.name as department_name 
              FROM users u 
              LEFT JOIN departments d ON u.department_id = d.id 
              WHERE u.is_active = 1 AND u.role IN ('employee', 'hod')
              ORDER BY u.full_name";
$emp_stmt = $conn->query($emp_query);
$all_employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filtered employees
$where_conditions = ["u.is_active = 1", "u.role IN ('employee', 'hod')"];
$params = [];

if (!empty($department_id)) {
    $where_conditions[] = "u.department_id = :dept_id";
    $params[':dept_id'] = $department_id;
}

if (!empty($employee_id)) {
    $where_conditions[] = "u.id = :emp_id";
    $params[':emp_id'] = $employee_id;
}

$where_clause = implode(" AND ", $where_conditions);
$filtered_emp_query = "SELECT u.id, u.full_name, u.email, d.name as department_name 
                       FROM users u 
                       LEFT JOIN departments d ON u.department_id = d.id 
                       WHERE $where_clause 
                       ORDER BY u.full_name";
$filtered_stmt = $conn->prepare($filtered_emp_query);
foreach ($params as $key => $value) {
    $filtered_stmt->bindValue($key, $value);
}
$filtered_stmt->execute();
$employees = $filtered_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get holidays for the month
$holiday_query = "SELECT * FROM holidays 
                  WHERE date BETWEEN :start_date AND :end_date 
                  ORDER BY date";
$holiday_stmt = $conn->prepare($holiday_query);
$holiday_stmt->bindParam(':start_date', $start_date);
$holiday_stmt->bindParam(':end_date', $end_date);
$holiday_stmt->execute();
$holidays = $holiday_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create holiday array for quick lookup
$holiday_dates = [];
foreach ($holidays as $holiday) {
    $holiday_dates[$holiday['date']] = $holiday['name'];
}

// Get leaves for the month
$leave_query = "SELECT l.*, u.full_name as employee_name 
                FROM employee_leaves l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.status = 'approved' 
                AND ((l.start_date BETWEEN :start_date AND :end_date) 
                     OR (l.end_date BETWEEN :start_date AND :end_date)
                     OR (l.start_date <= :start_date AND l.end_date >= :end_date))";
$leave_stmt = $conn->prepare($leave_query);
$leave_stmt->bindParam(':start_date', $start_date);
$leave_stmt->bindParam(':end_date', $end_date);
$leave_stmt->execute();
$leaves = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create leave array for quick lookup
$employee_leaves = [];
foreach ($leaves as $leave) {
    $start = new DateTime($leave['start_date']);
    $end = new DateTime($leave['end_date']);
    $end->modify('+1 day'); // Include end date
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        if ($date_str >= $start_date && $date_str <= $end_date) {
            if (!isset($employee_leaves[$leave['user_id']])) {
                $employee_leaves[$leave['user_id']] = [];
            }
            $employee_leaves[$leave['user_id']][$date_str] = $leave['leave_type'];
        }
    }
}

// Generate dates for the month
$dates = [];
for ($i = 1; $i <= $total_days; $i++) {
    $date = date('Y-m-d', strtotime($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT)));
    $dates[] = [
        'date' => $date,
        'day' => date('D', strtotime($date)),
        'is_holiday' => isset($holiday_dates[$date]),
        'holiday_name' => $holiday_dates[$date] ?? null,
        'is_weekend' => in_array(date('N', strtotime($date)), [6, 7])
    ];
}

// Calculate statistics for chart
$chart_data = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'holiday' => count($holiday_dates) * count($employees)
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .holiday-cell {
            background-color: #f8d7da !important;
            color: #721c24;
        }
        .weekend-cell {
            background-color: #f0f0f0 !important;
        }
        .present-cell {
            background-color: #d1e7dd !important;
            color: #0f5132;
        }
        .absent-cell {
            background-color: #f8d7da !important;
            color: #721c24;
        }
        .leave-cell {
            background-color: #cfe2ff !important;
            color: #084298;
        }
        .half-day-cell {
            background-color: #fff3cd !important;
            color: #664d03;
        }
        .punch-missed {
            color: #dc3545;
            font-weight: bold;
        }
        .attendance-table {
            font-size: 0.85rem;
        }
        .summary-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .fixed-header {
            position: sticky;
            top: 0;
            background: white;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .legend-item {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 5px;
            border: 1px solid #dee2e6;
        }
        .table-container {
            overflow-x: auto;
            max-height: 600px;
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-chart-bar"></i> Attendance Report</h4>
                    <div>
                        <?php if (count($employees) > 0): ?>
                        <a href="export_attendance.php?<?php echo http_build_query($_GET); ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-download"></i> Export Excel
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="month" class="form-label">Month</label>
                        <input type="month" class="form-control" id="month" name="month" 
                               value="<?php echo $month; ?>" max="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo ($department_id == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="employee_id" class="form-label">Employee</label>
                        <select class="form-select" id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($all_employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>" 
                                    <?php echo ($employee_id == $emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['full_name']); ?> 
                                    (<?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="admin_attendance_report.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
                
                <!-- Legend -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-light">
                            <h6 class="mb-2">Legend:</h6>
                            <span class="me-3"><span class="legend-item present-cell"></span> Present</span>
                            <span class="me-3"><span class="legend-item absent-cell"></span> Absent</span>
                            <span class="me-3"><span class="legend-item half-day-cell"></span> Half Day</span>
                            <span class="me-3"><span class="legend-item leave-cell"></span> Leave</span>
                            <span class="me-3"><span class="legend-item holiday-cell"></span> Holiday</span>
                            <span class="me-3"><span class="legend-item weekend-cell"></span> Weekend</span>
                            <span class="me-3"><i class="fas fa-exclamation-triangle text-danger"></i> Punch Missed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($employees) == 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> No employees found with the selected filters.
        </div>
        <?php else: ?>

        <!-- Summary Statistics -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card summary-card border-success">
                    <div class="card-body text-center">
                        <h5 class="card-title text-success">Employees</h5>
                        <h2><?php echo count($employees); ?></h2>
                        <p class="card-text">Total Filtered</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title text-primary">Working Days</h5>
                        <h2><?php echo $total_days - count($holiday_dates); ?></h2>
                        <p class="card-text">In <?php echo date('F Y', strtotime($month)); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title text-warning">Holidays</h5>
                        <h2><?php echo count($holiday_dates); ?></h2>
                        <p class="card-text">This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card summary-card border-info">
                    <div class="card-body text-center">
                        <h5 class="card-title text-info">Report Date</h5>
                        <h4><?php echo date('d M Y'); ?></h4>
                        <p class="card-text">Generated On</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Report Table -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-table"></i> Attendance Report for <?php echo date('F Y', strtotime($month)); ?>
                    <span class="badge bg-primary"><?php echo count($employees); ?> Employees</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table table-bordered table-hover attendance-table mb-0" id="attendanceTable">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th rowspan="2" style="min-width: 150px;">Employee</th>
                                <th rowspan="2" style="min-width: 120px;">Department</th>
                                <?php foreach ($dates as $date): ?>
                                    <th class="text-center <?php echo $date['is_holiday'] ? 'holiday-cell' : ($date['is_weekend'] ? 'weekend-cell' : ''); ?>" 
                                        style="min-width: 60px;">
                                        <?php echo date('d', strtotime($date['date'])); ?><br>
                                        <small><?php echo $date['day']; ?></small>
                                        <?php if ($date['is_holiday']): ?>
                                            <br><small class="text-danger" style="font-size: 0.7rem;"><?php echo substr($date['holiday_name'], 0, 10); ?></small>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; ?>
                                <th rowspan="2" style="min-width: 80px;">Present</th>
                                <th rowspan="2" style="min-width: 80px;">Absent</th>
                                <th rowspan="2" style="min-width: 80px;">Leave</th>
                                <th rowspan="2" style="min-width: 100px;">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_present_all = 0;
                            $total_absent_all = 0;
                            $total_leave_all = 0;
                            
                            foreach ($employees as $employee):
                                $emp_id = $employee['id'];
                                
                                // Get attendance for this employee for the month
                                $att_query = "SELECT * FROM attendance_summary 
                                              WHERE user_id = :user_id 
                                              AND date BETWEEN :start_date AND :end_date";
                                $att_stmt = $conn->prepare($att_query);
                                $att_stmt->bindParam(':user_id', $emp_id);
                                $att_stmt->bindParam(':start_date', $start_date);
                                $att_stmt->bindParam(':end_date', $end_date);
                                $att_stmt->execute();
                                $attendance_data = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Create attendance array for quick lookup
                                $employee_attendance = [];
                                foreach ($attendance_data as $att) {
                                    $employee_attendance[$att['date']] = $att;
                                }
                                
                                // Calculate statistics
                                $present_days = 0;
                                $absent_days = 0;
                                $leave_days = 0;
                                $half_days = 0;
                                $punch_missed_days = 0;
                            ?>
                            <tr data-employee-id="<?php echo $emp_id; ?>">
                                <td nowrap class="employee-name" style="cursor: pointer;" title="Click for details">
                                    <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></td>
                                
                                <?php foreach ($dates as $date): 
                                    $date_str = $date['date'];
                                    $is_weekend = $date['is_weekend'];
                                    $is_holiday = $date['is_holiday'];
                                    $is_past_date = $date_str <= date('Y-m-d');
                                    
                                    $cell_class = '';
                                    $cell_text = '';
                                    $tooltip = '';
                                    $has_missed_punch = false;
                                    
                                    if ($is_holiday) {
                                        $cell_class = 'holiday-cell';
                                        $cell_text = 'H';
                                        $tooltip = $date['holiday_name'];
                                    } 
                                    elseif ($is_weekend) {
                                        $cell_class = 'weekend-cell';
                                        $cell_text = '';
                                        $tooltip = 'Weekend';
                                    }
                                    elseif (isset($employee_leaves[$emp_id][$date_str])) {
                                        $cell_class = 'leave-cell';
                                        $cell_text = 'L';
                                        $leave_type = $employee_leaves[$emp_id][$date_str];
                                        $tooltip = ucfirst($leave_type) . ' Leave';
                                        $leave_days++;
                                        $chart_data['leave']++;
                                    }
                                    elseif (isset($employee_attendance[$date_str])) {
                                        $att = $employee_attendance[$date_str];
                                        
                                        switch($att['status']) {
                                            case 'present':
                                                $cell_class = 'present-cell';
                                                $cell_text = 'P';
                                                $present_days++;
                                                $chart_data['present']++;
                                                $tooltip = 'In: ' . ($att['first_clock_in'] ? date('h:i A', strtotime($att['first_clock_in'])) : 'N/A');
                                                if ($att['last_clock_out']) {
                                                    $tooltip .= ' | Out: ' . date('h:i A', strtotime($att['last_clock_out']));
                                                }
                                                if ($att['total_work_hours'] > 0) {
                                                    $tooltip .= ' | Hours: ' . number_format($att['total_work_hours'], 1);
                                                }
                                                break;
                                                
                                            case 'half_day':
                                                $cell_class = 'half-day-cell';
                                                $cell_text = 'HD';
                                                $half_days++;
                                                $present_days += 0.5;
                                                $chart_data['present'] += 0.5;
                                                $tooltip = 'Half Day';
                                                break;
                                                
                                            case 'absent':
                                                if ($is_past_date) {
                                                    $cell_class = 'absent-cell';
                                                    $cell_text = 'A';
                                                    $absent_days++;
                                                    $chart_data['absent']++;
                                                    $tooltip = 'Absent';
                                                } else {
                                                    $cell_text = '';
                                                    $tooltip = 'Future Date';
                                                }
                                                break;
                                                
                                            default:
                                                if ($is_past_date) {
                                                    $cell_class = 'absent-cell';
                                                    $cell_text = 'A';
                                                    $absent_days++;
                                                    $chart_data['absent']++;
                                                    $tooltip = 'Absent (No Punch)';
                                                }
                                        }
                                    } 
                                    else {
                                        // No attendance record
                                        if ($is_past_date && !$is_weekend && !$is_holiday) {
                                            $cell_class = 'absent-cell';
                                            $cell_text = 'A';
                                            $absent_days++;
                                            $chart_data['absent']++;
                                            $tooltip = 'Absent (No Record)';
                                        }
                                    }
                                ?>
                                <td class="text-center <?php echo $cell_class; ?>" 
                                    title="<?php echo $tooltip; ?>"
                                    style="cursor: help;">
                                    <?php echo $cell_text; ?>
                                </td>
                                <?php endforeach; ?>
                                
                                <td class="text-center fw-bold"><?php echo number_format($present_days + ($half_days * 0.5), 1); ?></td>
                                <td class="text-center fw-bold"><?php echo $absent_days; ?></td>
                                <td class="text-center fw-bold"><?php echo $leave_days; ?></td>
                                <td class="text-center fw-bold">
                                    <?php 
                                    $working_days = $total_days - count($holiday_dates) - (($total_days/7) * 2); // Approximate
                                    $attendance_percentage = $working_days > 0 ? round((($present_days + ($half_days * 0.5)) / $working_days) * 100, 1) : 0;
                                    echo $attendance_percentage . '%';
                                    
                                    // Color code percentage
                                    if ($attendance_percentage >= 90) {
                                        echo ' <span class="badge bg-success">Excellent</span>';
                                    } elseif ($attendance_percentage >= 75) {
                                        echo ' <span class="badge bg-primary">Good</span>';
                                    } elseif ($attendance_percentage >= 60) {
                                        echo ' <span class="badge bg-warning">Fair</span>';
                                    } else {
                                        echo ' <span class="badge bg-danger">Poor</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php
                                $total_present_all += $present_days + ($half_days * 0.5);
                                $total_absent_all += $absent_days;
                                $total_leave_all += $leave_days;
                            endforeach; 
                            ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <td colspan="2"><strong>Totals/Average</strong></td>
                                <?php foreach ($dates as $date): ?>
                                    <td class="text-center">
                                        <!-- Daily totals can be added here -->
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-center fw-bold"><?php echo number_format($total_present_all, 1); ?></td>
                                <td class="text-center fw-bold"><?php echo $total_absent_all; ?></td>
                                <td class="text-center fw-bold"><?php echo $total_leave_all; ?></td>
                                <td class="text-center fw-bold">
                                    <?php 
                                    $total_employees = count($employees);
                                    $total_working_days = ($total_days - count($holiday_dates)) * $total_employees;
                                    $avg_percentage = $total_working_days > 0 ? round(($total_present_all / $total_working_days) * 100, 1) : 0;
                                    echo $avg_percentage . '%';
                                    ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Monthly Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div style="height: 300px;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Statistics:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Working Days:</span>
                                <strong><?php echo $total_days - count($holiday_dates); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Holidays:</span>
                                <strong><?php echo count($holiday_dates); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Employees:</span>
                                <strong><?php echo count($employees); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Present Days:</span>
                                <strong><?php echo number_format($total_present_all, 1); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Absent Days:</span>
                                <strong><?php echo $total_absent_all; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total Leave Days:</span>
                                <strong><?php echo $total_leave_all; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Average Attendance Rate:</span>
                                <strong>
                                    <?php 
                                    $total_working_days = ($total_days - count($holiday_dates)) * count($employees);
                                    $avg_rate = $total_working_days > 0 ? round(($total_present_all / $total_working_days) * 100, 1) : 0;
                                    echo $avg_rate . '%';
                                    ?>
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="employeeDetails">
                    <!-- Loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        // Only initialize DataTable if table exists and has data
        var attendanceTable = $('#attendanceTable');
        
        if (attendanceTable.length && attendanceTable.find('tbody tr').length > 0) {
            try {
                var dataTable = attendanceTable.DataTable({
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    scrollX: true,
                    scrollCollapse: true,
                    fixedHeader: true,
                    order: [],
                    columnDefs: [
                        {
                            targets: [0, 1], // Make employee name and department sortable
                            orderable: true
                        },
                        {
                            targets: '_all',
                            orderable: false
                        }
                    ],
                    initComplete: function() {
                        console.log('DataTable initialized successfully');
                    },
                    error: function(settings, techNote, message) {
                        console.log('DataTables error:', message);
                        // Hide the table if DataTables fails
                        attendanceTable.hide();
                        $('#attendanceTable_wrapper').html(
                            '<div class="alert alert-warning">' +
                            'Could not load table with advanced features. Showing basic table instead.' +
                            '</div>' + attendanceTable.html()
                        );
                    }
                });
            } catch (error) {
                console.log('DataTable initialization failed:', error);
                // Fallback: Show table without DataTables
                $('#attendanceTable').addClass('table table-bordered');
            }
        } else {
            console.log('Table not found or empty, skipping DataTable initialization');
        }
        
        // Initialize chart only if we have data
        <?php if (count($employees) > 0): ?>
        initializeChart();
        <?php endif; ?>
        
        // Employee row click for details
        $(document).on('click', '.employee-name', function() {
            var row = $(this).closest('tr');
            var employeeId = row.data('employee-id');
            var employeeName = $(this).find('strong').text();
            
            if (employeeId) {
                loadEmployeeDetails(employeeId, employeeName);
            }
        });
    });
    
    function initializeChart() {
        var ctxElement = document.getElementById('attendanceChart');
        if (!ctxElement) {
            console.log('Chart canvas element not found');
            return;
        }
        
        try {
            var ctx = ctxElement.getContext('2d');
            
            // Prepare chart data
            var chartData = {
                labels: ['Present', 'Absent', 'Leave', 'Holidays'],
                datasets: [{
                    data: [
                        <?php echo $chart_data['present']; ?>,
                        <?php echo $chart_data['absent']; ?>,
                        <?php echo $chart_data['leave']; ?>,
                        <?php echo $chart_data['holiday']; ?>
                    ],
                    backgroundColor: [
                        '#d1e7dd',
                        '#f8d7da',
                        '#cfe2ff',
                        '#f8f9fa'
                    ],
                    borderColor: [
                        '#0f5132',
                        '#721c24',
                        '#084298',
                        '#6c757d'
                    ],
                    borderWidth: 1
                }]
            };
            
            var attendanceChart = new Chart(ctx, {
                type: 'pie',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        } catch (error) {
            console.log('Chart error:', error);
            $('#attendanceChart').parent().html(
                '<div class="alert alert-info">' +
                'Chart data: Present: <?php echo $chart_data["present"]; ?>, ' +
                'Absent: <?php echo $chart_data["absent"]; ?>, ' +
                'Leave: <?php echo $chart_data["leave"]; ?>, ' +
                'Holidays: <?php echo $chart_data["holiday"]; ?>' +
                '</div>'
            );
        }
    }
    
    function loadEmployeeDetails(employeeId, employeeName) {
        var month = '<?php echo $month; ?>';
        
        // Show loading
        $('#employeeDetails').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading details for ${employeeName}...</p>
            </div>
        `);
        
        $('#employeeModal .modal-title').text('Attendance Details - ' + employeeName);
        $('#employeeModal').modal('show');
        
        $.ajax({
            url: 'get_employee_attendance_details.php',
            type: 'GET',
            data: {
                employee_id: employeeId,
                month: month
            },
            success: function(response) {
                $('#employeeDetails').html(response);
            },
            error: function(xhr, status, error) {
                $('#employeeDetails').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> 
                        Failed to load employee details. Error: ${error}
                    </div>
                `);
            }
        });
    }
</script>
    <!-- <script>
        $(document).ready(function() {
            // Initialize DataTable with proper configuration
            $('#attendanceTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                scrollX: true,
                scrollY: 500,
                scrollCollapse: true,
                fixedHeader: {
                    header: true,
                    footer: true
                },
                order: [[0, 'asc']],
                columnDefs: [
                    {
                        targets: [0, 1], // Employee name and department columns
                        orderable: true
                    },
                    {
                        targets: '_all',
                        orderable: false
                    }
                ]
            });
            
            // Initialize chart only if we have data
            <?php if (count($employees) > 0): ?>
            initializeChart();
            <?php endif; ?>
            
            // Employee row click for details
            $('.employee-name').on('click', function() {
                var row = $(this).closest('tr');
                var employeeId = row.data('employee-id');
                var employeeName = $(this).find('strong').text();
                
                if (employeeId) {
                    loadEmployeeDetails(employeeId, employeeName);
                }
            });
        });
        
        function initializeChart() {
            var ctx = document.getElementById('attendanceChart');
            if (!ctx) {
                console.log('Chart canvas not found');
                return;
            }
            
            ctx = ctx.getContext('2d');
            
            // Prepare chart data
            var chartData = {
                labels: ['Present', 'Absent', 'Leave', 'Holidays'],
                datasets: [{
                    data: [
                        <?php echo $chart_data['present']; ?>,
                        <?php echo $chart_data['absent']; ?>,
                        <?php echo $chart_data['leave']; ?>,
                        <?php echo $chart_data['holiday']; ?>
                    ],
                    backgroundColor: [
                        '#d1e7dd', // Present - green
                        '#f8d7da', // Absent - red
                        '#cfe2ff', // Leave - blue
                        '#f8f9fa'  // Holiday - gray
                    ],
                    borderColor: [
                        '#0f5132',
                        '#721c24',
                        '#084298',
                        '#6c757d'
                    ],
                    borderWidth: 1
                }]
            };
            
            try {
                var attendanceChart = new Chart(ctx, {
                    type: 'pie',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.raw || 0;
                                        var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        var percentage = Math.round((value / total) * 100);
                                        return label + ': ' + value + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.log('Chart initialization error:', error);
                $('#attendanceChart').parent().html('<div class="alert alert-warning">Could not load chart</div>');
            }
        }
        
        function loadEmployeeDetails(employeeId, employeeName) {
            var month = '<?php echo $month; ?>';
            
            // Show loading
            $('#employeeDetails').html(`
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading details for ${employeeName}...</p>
                </div>
            `);
            
            $('#employeeModal .modal-title').text('Attendance Details - ' + employeeName);
            $('#employeeModal').modal('show');
            
            $.ajax({
                url: 'get_employee_attendance_details.php',
                type: 'GET',
                data: {
                    employee_id: employeeId,
                    month: month
                },
                success: function(response) {
                    $('#employeeDetails').html(response);
                },
                error: function() {
                    $('#employeeDetails').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Failed to load employee details.
                        </div>
                    `);
                }
            });
        }
    </script> -->
</body>
</html>