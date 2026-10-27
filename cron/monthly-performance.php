<?php
/**
 * Monthly Performance Report Generator & Cleanup Cron Job
 * Should run on 15th of every month via crontab:
 * 0 0 15 * * /usr/bin/php /path/to/your/project/cron/monthly-performance.php
 */

// Prevent direct browser access
if(php_sapi_name() !== 'cli') {
    die('Access denied. This script can only be run from command line.');
}

require_once __DIR__ . '/../config.php';

$start_time = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Starting monthly performance report generation...\n";

try {
    $db = getDB();
    
    // Calculate previous month
    $previous_month = date('Y-m-01', strtotime('first day of last month'));
    $month_name = date('F Y', strtotime($previous_month));
    
    echo "Processing data for: $month_name\n";
    
    // Get all active employees
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.role = 'employee' AND u.is_active = 1
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();
    
    $total_employees = count($employees);
    $processed = 0;
    $errors = 0;
    
    echo "Found $total_employees active employees\n";
    
    foreach($employees as $employee) {
        try {
            echo "Processing: {$employee['full_name']}... ";
            
            // Calculate start and end dates for previous month
            $start_date = date('Y-m-01', strtotime($previous_month));
            $end_date = date('Y-m-t', strtotime($previous_month));
            
            // Calculate performance metrics
            $performance = calculateMonthlyPerformance($db, $employee['id'], $start_date, $end_date);
            
            // Save to performance_summary table
            if($performance) {
                // Check if record already exists
                $check_stmt = $db->prepare("
                    SELECT id FROM performance_summary 
                    WHERE user_id = ? AND month_year = ?
                ");
                $check_stmt->execute([$employee['id'], $performance['month_year']]);
                
                if($check_stmt->fetch()) {
                    // Update existing record
                    $update_stmt = $db->prepare("
                        UPDATE performance_summary SET
                            total_tasks_assigned = ?,
                            tasks_accepted = ?,
                            tasks_rejected = ?,
                            tasks_completed = ?,
                            avg_completion_time_hours = ?,
                            completion_rate = ?,
                            last_updated = NOW()
                        WHERE user_id = ? AND month_year = ?
                    ");
                    $update_stmt->execute([
                        $performance['total_tasks_assigned'],
                        $performance['tasks_accepted'],
                        $performance['tasks_rejected'],
                        $performance['tasks_completed'],
                        $performance['avg_completion_time_hours'],
                        $performance['completion_rate'],
                        $employee['id'],
                        $performance['month_year']
                    ]);
                    echo "Updated performance summary. ";
                } else {
                    // Insert new record
                    $insert_stmt = $db->prepare("
                        INSERT INTO performance_summary 
                        (user_id, month_year, total_tasks_assigned, tasks_accepted, 
                         tasks_rejected, tasks_completed, avg_completion_time_hours, 
                         completion_rate, last_updated)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $insert_stmt->execute([
                        $employee['id'],
                        $performance['month_year'],
                        $performance['total_tasks_assigned'],
                        $performance['tasks_accepted'],
                        $performance['tasks_rejected'],
                        $performance['tasks_completed'],
                        $performance['avg_completion_time_hours'],
                        $performance['completion_rate']
                    ]);
                    echo "Created performance summary. ";
                }
                
                // Note: Email functionality would be added here if you have EmailHandler
                // $email_sent = $emailHandler->sendPerformanceReportEmail(...);
                
                echo "✓\n";
            } else {
                echo "No data for this month. ✓\n";
            }
            
            $processed++;
            
        } catch(Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            error_log("Error processing employee {$employee['id']}: " . $e->getMessage());
            $errors++;
        }
    }
    
    // Archive and delete old logs (older than 3 months)
    echo "\nCleaning up old logs...\n";
    $archive_date = date('Y-m-d', strtotime('-3 months'));
    
    // Delete old logs from task_logs
    $delete_stmt = $db->prepare("DELETE FROM task_logs WHERE created_at < ?");
    $delete_stmt->execute([$archive_date]);
    $deleted_count = $delete_stmt->rowCount();
    
    echo "Deleted $deleted_count old log records\n";
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Completed!\n";
    echo "Total time: " . round(microtime(true) - $start_time, 2) . " seconds\n";
    echo "Employees processed: $processed/$total_employees\n";
    echo "Errors: $errors\n";
    
} catch(Exception $e) {
    error_log("Monthly cron job failed: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Calculate monthly performance for an employee
 */
function calculateMonthlyPerformance($db, $user_id, $start_date, $end_date) {
    try {
        // Calculate total tasks assigned
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_assigned
            FROM task_assignments ta
            WHERE ta.assigned_to = ?
            AND DATE(ta.assigned_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $total_assigned = $stmt->fetchColumn() ?: 0;
        
        // Calculate tasks accepted
        $stmt = $db->prepare("
            SELECT COUNT(*) as accepted
            FROM task_assignments ta
            WHERE ta.assigned_to = ?
            AND DATE(ta.accepted_at) BETWEEN ? AND ?
            AND ta.accepted_at IS NOT NULL
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $tasks_accepted = $stmt->fetchColumn() ?: 0;
        
        // Calculate tasks rejected
        $stmt = $db->prepare("
            SELECT COUNT(*) as rejected
            FROM task_assignments ta
            WHERE ta.assigned_to = ?
            AND DATE(ta.rejected_at) BETWEEN ? AND ?
            AND ta.rejected_at IS NOT NULL
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $tasks_rejected = $stmt->fetchColumn() ?: 0;
        
        // Calculate tasks completed and average time
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as completed,
                AVG(TIMESTAMPDIFF(HOUR, ta.accepted_at, t.completed_at)) as avg_hours
            FROM tasks t
            JOIN task_assignments ta ON t.id = ta.task_id
            WHERE ta.assigned_to = ?
            AND DATE(t.completed_at) BETWEEN ? AND ?
            AND t.status = 'completed'
            AND ta.accepted_at IS NOT NULL
            AND ta.rejected_at IS NULL
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $completed_data = $stmt->fetch();
        $tasks_completed = $completed_data['completed'] ?: 0;
        $avg_completion_hours = $completed_data['avg_hours'] ?: 0;
        
        // Calculate completion rate
        $completion_rate = $tasks_accepted > 0 ? round(($tasks_completed / $tasks_accepted) * 100, 2) : 0;
        
        return [
            'user_id' => $user_id,
            'month_year' => $start_date,
            'total_tasks_assigned' => $total_assigned,
            'tasks_accepted' => $tasks_accepted,
            'tasks_rejected' => $tasks_rejected,
            'tasks_completed' => $tasks_completed,
            'avg_completion_time_hours' => round($avg_completion_hours, 2),
            'completion_rate' => $completion_rate,
            'start_date' => $start_date,
            'end_date' => $end_date
        ];
        
    } catch(Exception $e) {
        throw new Exception("Failed to calculate performance for user $user_id: " . $e->getMessage());
    }
}
?>