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

// Get filters from query string
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Get filtered employees (same logic as report page)
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

// Generate dates for the month
$start_date = date('Y-m-01', strtotime($month));
$end_date = date('Y-m-t', strtotime($month));
$total_days = date('t', strtotime($month));

// Start HTML output for Excel
echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>Attendance Report</title>';
echo '<style>';
echo 'td { border: 1px solid #ddd; padding: 5px; }';
echo 'th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 5px; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<h2>Attendance Report - ' . date('F Y', strtotime($month)) . '</h2>';
echo '<p>Generated on: ' . date('d-M-Y H:i:s') . '</p>';
echo '<p>Total Employees: ' . count($employees) . '</p>';

echo '<table border="1">';
echo '<tr>';
echo '<th>Employee Name</th>';
echo '<th>Department</th>';

// Date headers
for ($i = 1; $i <= $total_days; $i++) {
    $date = date('Y-m-d', strtotime($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT)));
    echo '<th>' . date('d', strtotime($date)) . '<br>' . date('D', strtotime($date)) . '</th>';
}

echo '<th>Present</th>';
echo '<th>Absent</th>';
echo '<th>Leave</th>';
echo '<th>%</th>';
echo '</tr>';

foreach ($employees as $employee) {
    $emp_id = $employee['id'];
    
    // Get attendance for this employee
    $att_query = "SELECT * FROM attendance_summary 
                  WHERE user_id = :user_id 
                  AND date BETWEEN :start_date AND :end_date";
    $att_stmt = $conn->prepare($att_query);
    $att_stmt->bindParam(':user_id', $emp_id);
    $att_stmt->bindParam(':start_date', $start_date);
    $att_stmt->bindParam(':end_date', $end_date);
    $att_stmt->execute();
    $attendance_data = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create attendance array
    $employee_attendance = [];
    foreach ($attendance_data as $att) {
        $employee_attendance[$att['date']] = $att;
    }
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars($employee['full_name']) . '</td>';
    echo '<td>' . htmlspecialchars($employee['department_name'] ?? 'N/A') . '</td>';
    
    $present_days = 0;
    $absent_days = 0;
    $leave_days = 0;
    
    for ($i = 1; $i <= $total_days; $i++) {
        $date = date('Y-m-d', strtotime($month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT)));
        $day_of_week = date('N', strtotime($date));
        
        $status = '';
        
        if (isset($employee_attendance[$date])) {
            $att = $employee_attendance[$date];
            switch($att['status']) {
                case 'present':
                    $status = 'P';
                    $present_days++;
                    break;
                case 'half_day':
                    $status = 'HD';
                    $present_days += 0.5;
                    break;
                case 'absent':
                    $status = 'A';
                    $absent_days++;
                    break;
                default:
                    $status = '';
            }
        } else {
            if ($day_of_week >= 6) {
                $status = 'W'; // Weekend
            } else {
                $status = 'A'; // Absent (no record)
                $absent_days++;
            }
        }
        
        echo '<td>' . $status . '</td>';
    }
    
    $working_days = $total_days - 8; // Approximate (excluding weekends)
    $attendance_percentage = $working_days > 0 ? round(($present_days / $working_days) * 100, 1) : 0;
    
    echo '<td>' . number_format($present_days, 1) . '</td>';
    echo '<td>' . $absent_days . '</td>';
    echo '<td>' . $leave_days . '</td>';
    echo '<td>' . $attendance_percentage . '%</td>';
    echo '</tr>';
}

echo '</table>';
echo '</body>';
echo '</html>';