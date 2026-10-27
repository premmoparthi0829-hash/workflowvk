<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$punch_type = $_POST['punch_type'];
$notes = $_POST['notes'] ?? '';

try {
    $conn->beginTransaction();
    
    // Insert punch record
    $insert_query = "INSERT INTO attendance_punches (user_id, punch_type, punch_time, notes) 
                     VALUES (:user_id, :punch_type, NOW(), :notes)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bindParam(':user_id', $user_id);
    $insert_stmt->bindParam(':punch_type', $punch_type);
    $insert_stmt->bindParam(':notes', $notes);
    $insert_stmt->execute();
    
    $today = date('Y-m-d');
    
    // Update attendance summary
    if ($punch_type === 'clock_in') {
        // Check if summary exists for today
        $check_query = "SELECT id FROM attendance_summary WHERE user_id = :user_id AND date = :date";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->bindParam(':date', $today);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            // Create new summary
            $summary_query = "INSERT INTO attendance_summary (user_id, date, first_clock_in, status) 
                              VALUES (:user_id, :date, NOW(), 'present')";
            $summary_stmt = $conn->prepare($summary_query);
            $summary_stmt->bindParam(':user_id', $user_id);
            $summary_stmt->bindParam(':date', $today);
            $summary_stmt->execute();
        } else {
            // Update existing summary with first clock in
            $update_query = "UPDATE attendance_summary 
                             SET first_clock_in = COALESCE(first_clock_in, NOW()),
                                 status = 'present'
                             WHERE user_id = :user_id AND date = :date";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user_id);
            $update_stmt->bindParam(':date', $today);
            $update_stmt->execute();
        }
    } 
    elseif ($punch_type === 'clock_out') {
        // Update last clock out
        $update_query = "UPDATE attendance_summary 
                         SET last_clock_out = NOW()
                         WHERE user_id = :user_id AND date = :date";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id);
        $update_stmt->bindParam(':date', $today);
        $update_stmt->execute();
    }
    
    // Calculate work hours
    calculateDailyHours($conn, $user_id, $today); // FIXED: Removed $this->
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Function to calculate daily hours
function calculateDailyHours($conn, $user_id, $date) {
    // Get all punches for the day
    $query = "SELECT * FROM attendance_punches 
              WHERE user_id = :user_id 
              AND DATE(punch_time) = :date 
              ORDER BY punch_time ASC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    $punches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $work_hours = 0;
    $break_hours = 0;
    $permission_hours = 0;
    
    $last_clock_in = null;
    $last_lunch_start = null;
    $last_tea_start = null;
    $last_permission_start = null;
    
    foreach ($punches as $punch) {
        switch($punch['punch_type']) {
            case 'clock_in':
                $last_clock_in = strtotime($punch['punch_time']);
                break;
            case 'clock_out':
                if ($last_clock_in) {
                    $work_hours += (strtotime($punch['punch_time']) - $last_clock_in) / 3600;
                    $last_clock_in = null;
                }
                break;
            case 'lunch_start':
                $last_lunch_start = strtotime($punch['punch_time']);
                break;
            case 'lunch_end':
                if ($last_lunch_start) {
                    $break_hours += (strtotime($punch['punch_time']) - $last_lunch_start) / 3600;
                    $last_lunch_start = null;
                }
                break;
            case 'tea_break_start':
                $last_tea_start = strtotime($punch['punch_time']);
                break;
            case 'tea_break_end':
                if ($last_tea_start) {
                    $break_hours += (strtotime($punch['punch_time']) - $last_tea_start) / 3600;
                    $last_tea_start = null;
                }
                break;
            case 'permission_start':
                $last_permission_start = strtotime($punch['punch_time']);
                break;
            case 'permission_end':
                if ($last_permission_start) {
                    $permission_hours += (strtotime($punch['punch_time']) - $last_permission_start) / 3600;
                    $last_permission_start = null;
                }
                break;
        }
    }
    
    // Update summary with calculated hours
    $update_query = "UPDATE attendance_summary 
                     SET total_work_hours = :work_hours,
                         total_break_hours = :break_hours,
                         total_permission_hours = :permission_hours
                     WHERE user_id = :user_id AND date = :date";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':work_hours', $work_hours);
    $update_stmt->bindParam(':break_hours', $break_hours);
    $update_stmt->bindParam(':permission_hours', $permission_hours);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->bindParam(':date', $date);
    $update_stmt->execute();
}
?>