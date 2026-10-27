<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHandler {
    private $mail;
    private $db;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setupMailer();
        
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    private function setupMailer() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.hostinger.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = 'support@vklogisticshub.com';
            $this->mail->Password = 'vikKumar@Vem6';
            $this->mail->SMTPSecure = 'none';
            $this->mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
            $this->mail->Port = 587;
            
            $this->mail->setFrom('support@vklogisticshub.com', 'Workflow - VK Logistics Hub');
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
        }
    }
    
    public function sendTaskAssignmentEmail($task_id, $assignee_id) {
        try {
            // Get task details
            $query = "SELECT t.*, u.email, u.full_name, 
                     creator.full_name as creator_name
                     FROM tasks t
                     JOIN task_assignments ta ON t.id = ta.task_id
                     JOIN users u ON ta.assigned_to = u.id
                     JOIN users creator ON t.created_by = creator.id
                     WHERE t.id = :task_id AND ta.assigned_to = :assignee_id
                     AND ta.current_assignee = 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':task_id' => $task_id, ':assignee_id' => $assignee_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$task) return false;
            
            // Get assignee email
            $stmt = $this->db->prepare("SELECT email, full_name FROM users WHERE id = :id");
            $stmt->execute([':id' => $assignee_id]);
            $assignee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->mail->addAddress($assignee['email'], $assignee['full_name']);
            $this->mail->Subject = 'New Task Assigned: ' . $task['title'];
            
            // Email body
            $body = $this->getTaskAssignmentTemplate($task);
            $this->mail->isHTML(true);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Assignment Email Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendStatusUpdateEmail($task_id, $new_status) {
        try {
            // Get task details
            $query = "SELECT t.*, u.full_name as assignee_name, 
                     creator.email as creator_email, creator.full_name as creator_name,
                     hod.email as hod_email, hod.full_name as hod_name
                     FROM tasks t
                     JOIN task_assignments ta ON t.id = ta.task_id AND ta.current_assignee = 1
                     JOIN users u ON ta.assigned_to = u.id
                     JOIN users creator ON t.created_by = creator.id
                     LEFT JOIN users hod ON u.department_id = hod.department_id AND hod.role = 'hod'
                     WHERE t.id = :task_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':task_id' => $task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$task) return false;
            
            // Send to creator (management)
            $this->mail->addAddress($task['creator_email'], $task['creator_name']);
            
            // Send to HOD if exists
            if($task['hod_email']) {
                $this->mail->addAddress($task['hod_email'], $task['hod_name']);
            }
            
            $this->mail->Subject = 'Task Status Updated: ' . $task['title'];
            
            $body = $this->getStatusUpdateTemplate($task, $new_status);
            $this->mail->isHTML(true);
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Status Email Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    public function sendTaskRejectionEmail($task_id, $rejection_reason) {
    try {
        // Get task and creator info
        $query = "SELECT t.*, creator.email as creator_email, creator.full_name as creator_name
                 FROM tasks t
                 JOIN users creator ON t.created_by = creator.id
                 WHERE t.id = :task_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':task_id' => $task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$task) return false;
        
        // Send to task creator
        $this->mail->addAddress($task['creator_email'], $task['creator_name']);
        $this->mail->Subject = 'Task Rejected: ' . $task['title'];
        
        // Simple email body
        $body = "<h2>Task Rejected</h2>
                <p><strong>Task:</strong> {$task['title']}</p>
                <p><strong>Description:</strong> {$task['description']}</p>
                <p><strong>Rejection Reason:</strong> {$rejection_reason}</p>
                <p>Login to view details.</p>";
        
        $this->mail->isHTML(true);
        $this->mail->Body = $body;
        
        $this->mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Rejection Email Error: " . $e->getMessage());
        return false;
    }
}
    
    private function getTaskAssignmentTemplate($task) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4e73df; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fc; }
                .task-details { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .btn { display: inline-block; padding: 10px 20px; background: #4e73df; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Task Assigned</h2>
                </div>
                <div class='content'>
                    <p>Hello {$task['full_name']},</p>
                    <p>You have been assigned a new task by {$task['creator_name']}.</p>
                    
                    <div class='task-details'>
                        <h3>{$task['title']}</h3>
                        <p><strong>Description:</strong> {$task['description']}</p>
                        <p><strong>Priority:</strong> " . ucfirst($task['priority']) . "</p>
                        <p><strong>Deadline:</strong> " . date('F d, Y', strtotime($task['deadline'])) . "</p>
                        <p><strong>Status:</strong> Pending Acceptance</p>
                    </div>
                    
                    <p>Please log in to your account to accept or reject this task.</p>
                    <p><a href='" . APP_URL . "/dashboard/employee/' class='btn'>View Task</a></p>
                    
                    <p>Best regards,<br>Task Management System</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getStatusUpdateTemplate($task, $new_status) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4e73df; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f8f9fc; }
                .status-badge { 
                    display: inline-block; 
                    padding: 5px 15px; 
                    border-radius: 20px; 
                    color: white; 
                    font-weight: bold; 
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Task Status Updated</h2>
                </div>
                <div class='content'>
                    <p>Task <strong>{$task['title']}</strong> status has been updated.</p>
                    
                    <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Task:</strong> {$task['title']}</p>
                        <p><strong>Updated By:</strong> {$task['assignee_name']}</p>
                        <p><strong>New Status:</strong> 
                            <span class='status-badge' style='background: " . $this->getStatusColor($new_status) . ";'>
                                " . str_replace('_', ' ', ucfirst($new_status)) . "
                            </span>
                        </p>
                        <p><strong>Updated At:</strong> " . date('F d, Y H:i:s') . "</p>
                    </div>
                    
                    <p>You can view the task details by logging into the system.</p>
                    
                    <p>Best regards,<br>Task Management System</p>
                </div>
            </div>
        </body>
        </html>";
    }
    public function sendPerformanceReportEmail($to_email, $employee_name, $performance_data, $month_name) {
    $subject = "Your Performance Report for $month_name - " . APP_NAME;
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .stat-card { background: white; padding: 15px; border-radius: 5px; text-align: center; }
            .stat-value { font-size: 24px; font-weight: bold; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Performance Report</h1>
                <p>$month_name</p>
            </div>
            <div class='content'>
                <h3>Hello $employee_name,</h3>
                <p>Here is your performance summary for $month_name:</p>
                
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-value'>{$performance_data['total_tasks_assigned']}</div>
                        <div>Tasks Assigned</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-value'>{$performance_data['tasks_accepted']}</div>
                        <div>Tasks Accepted</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-value'>{$performance_data['tasks_completed']}</div>
                        <div>Tasks Completed</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-value'>{$performance_data['completion_rate']}%</div>
                        <div>Completion Rate</div>
                    </div>
                </div>
                
                <p>Average time per task: {$performance_data['avg_completion_time_hours']} hours</p>
                
                <p>You can view detailed reports in your dashboard.</p>
                
                <p>Best regards,<br>The Management Team</p>
            </div>
            <div class='footer'>
                <p>" . APP_NAME . " | " . COMPANY_NAME . "</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $this->sendEmail($to_email, $subject, $html);
}

public function sendManagementSummaryEmail($to_email, $manager_name, $summary_data) {
    $subject = "Monthly Performance Summary Report - " . date('F Y');
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .summary-card { background: white; padding: 15px; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Monthly Performance Summary</h1>
                <p>{$summary_data['month']}</p>
            </div>
            <div class='content'>
                <h3>Hello $manager_name,</h3>
                <p>The monthly performance report generation has been completed. Here's the summary:</p>
                
                <div class='summary-grid'>
                    <div class='summary-card'>
                        <strong>Total Employees Processed:</strong><br>
                        {$summary_data['processed']}/{$summary_data['total_employees']}
                    </div>
                    <div class='summary-card'>
                        <strong>Errors Encountered:</strong><br>
                        {$summary_data['errors']}
                    </div>
                    <div class='summary-card'>
                        <strong>Logs Archived:</strong><br>
                        {$summary_data['logs_archived']} records
                    </div>
                    <div class='summary-card'>
                        <strong>Logs Deleted:</strong><br>
                        {$summary_data['logs_deleted']} records
                    </div>
                </div>
                
                <p><strong>Processing Time:</strong> {$summary_data['processing_time']} seconds</p>
                
                <p>Individual performance reports have been sent to each employee.</p>
                
                <p>Best regards,<br>System Administrator</p>
            </div>
            <div class='footer'>
                <p>" . APP_NAME . " | " . COMPANY_NAME . "</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $this->sendEmail($to_email, $subject, $html);
}
    
    private function getStatusColor($status) {
        switch($status) {
            case 'completed': return '#28a745';
            case 'in_progress': return '#007bff';
            case 'waiting_review': return '#ffc107';
            case 'not_started': return '#6c757d';
            default: return '#dc3545';
        }
    }
}
?>