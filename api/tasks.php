<?php
require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/EmailHandler.php';

$auth = new Auth();
$auth->requireLogin();

$db = new Database();
$conn = $db->getConnection();
$emailHandler = new EmailHandler($conn);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($data['title']) || empty($data['description']) || empty($data['deadline'])) {
                throw new Exception('All required fields must be filled');
            }

            $conn->beginTransaction();

            // Create task
            $query = "INSERT INTO tasks (title, description, priority, deadline, created_by) 
                     VALUES (:title, :description, :priority, :deadline, :created_by)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':title' => sanitize($data['title']),
                ':description' => sanitize($data['description']),
                ':priority' => $data['priority'] ?? 'medium',
                ':deadline' => $data['deadline'],
                ':created_by' => $_SESSION['user_id']
            ]);

            $task_id = $conn->lastInsertId();

            // Handle assignment
            if (!empty($data['assign_to'])) {
                $assign_to = $data['assign_to'];

                if (strpos($assign_to, 'hod_') === 0) {
                    // Assign to HOD
                    $dept_id = str_replace('hod_', '', $assign_to);
                    $assignment_type = 'department';

                    // Get HOD of department
                    $stmt = $conn->prepare("SELECT id FROM users WHERE department_id = :dept_id AND role = 'hod' LIMIT 1");
                    $stmt->execute([':dept_id' => $dept_id]);
                    $hod = $stmt->fetch();

                    if ($hod) {
                        $assignee_id = $hod['id'];
                    } else {
                        throw new Exception('No HOD found for this department');
                    }
                } else {
                    // Direct to employee
                    $assignee_id = str_replace('emp_', '', $assign_to);
                    $assignment_type = 'direct';
                }

                if (isset($assignee_id)) {
                    // Create assignment
                    $query = "INSERT INTO task_assignments (task_id, assigned_to, assigned_by, assignment_type) 
                             VALUES (:task_id, :assigned_to, :assigned_by, :assignment_type)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([
                        ':task_id' => $task_id,
                        ':assigned_to' => $assignee_id,
                        ':assigned_by' => $_SESSION['user_id'],
                        ':assignment_type' => $assignment_type
                    ]);

                    // Log action
                    logTaskAction($conn, $task_id, $_SESSION['user_id'], 'created', 'Task created and assigned');

                    // Send email notification
                    $emailHandler->sendTaskAssignmentEmail($task_id, $assignee_id);
                }
            }

            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Task created successfully']);
            break;

        case 'update_status':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['task_id']) || empty($data['status'])) {
                throw new Exception('Task ID and status are required');
            }

            $task_id = $data['task_id'];
            $new_status = $data['status'];

            // Get current status
            $stmt = $conn->prepare("SELECT status FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch();

            if (!$task) {
                throw new Exception('Task not found');
            }

            // Update task status
            $query = "UPDATE tasks SET status = :status WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':status' => $new_status,
                ':id' => $task_id
            ]);

            // Log action
            $log_query = "INSERT INTO task_logs (task_id, user_id, action, old_status, new_status) 
                         VALUES (:task_id, :user_id, 'status_update', :old_status, :new_status)";
            $stmt = $conn->prepare($log_query);
            $stmt->execute([
                ':task_id' => $task_id,
                ':user_id' => $_SESSION['user_id'],
                ':old_status' => $task['status'],
                ':new_status' => $new_status
            ]);

            // If completed, set completion time
            if ($new_status == 'completed') {
                $query = "UPDATE tasks SET completed_at = NOW() WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':id' => $task_id]);
            }

            // Send email notifications
            $emailHandler->sendStatusUpdateEmail($task_id, $new_status);

            echo json_encode(['success' => true, 'message' => 'Task status updated successfully']);
            break;

        case 'accept':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['assignment_id'])) {
                throw new Exception('Assignment ID is required');
            }

            $assignment_id = $data['assignment_id'];

            $query = "UPDATE task_assignments SET accepted_at = NOW() 
                     WHERE id = :id AND assigned_to = :user_id AND accepted_at IS NULL";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':id' => $assignment_id,
                ':user_id' => $_SESSION['user_id']
            ]);

            if ($stmt->rowCount() > 0) {
                // Update task status
                $stmt = $conn->prepare("SELECT task_id FROM task_assignments WHERE id = :id");
                $stmt->execute([':id' => $assignment_id]);
                $assignment = $stmt->fetch();

                $query = "UPDATE tasks SET status = 'not_started' WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':id' => $assignment['task_id']]);

                // Log action
                logTaskAction($conn, $assignment['task_id'], $_SESSION['user_id'], 'accepted', 'Task accepted');

                echo json_encode(['success' => true, 'message' => 'Task accepted successfully']);
            } else {
                throw new Exception('Failed to accept task');
            }
            break;

        case 'reject':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['assignment_id']) || empty($data['reason'])) {
                throw new Exception('Assignment ID and reason are required');
            }

            $assignment_id = $data['assignment_id'];
            $reason = sanitize($data['reason']);

            $query = "UPDATE task_assignments SET rejected_at = NOW(), rejection_reason = :reason 
                     WHERE id = :id AND assigned_to = :user_id AND rejected_at IS NULL";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':id' => $assignment_id,
                ':reason' => $reason,
                ':user_id' => $_SESSION['user_id']
            ]);

            if ($stmt->rowCount() > 0) {
                // Update task status
                $stmt = $conn->prepare("SELECT task_id FROM task_assignments WHERE id = :id");
                $stmt->execute([':id' => $assignment_id]);
                $assignment = $stmt->fetch();

                $query = "UPDATE tasks SET status = 'rejected' WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':id' => $assignment['task_id']]);

                // Log action
                logTaskAction($conn, $assignment['task_id'], $_SESSION['user_id'], 'rejected', 'Task rejected: ' . $reason);

                // Send rejection email
                $emailHandler->sendTaskRejectionEmail($assignment['task_id'], $reason);

                echo json_encode(['success' => true, 'message' => 'Task rejected successfully']);
            } else {
                throw new Exception('Failed to reject task');
            }
            break;

        case 'get_details':
            $task_id = $_GET['id'] ?? 0;

            $query = "SELECT t.*, 
                     creator.full_name as created_by_name,
                     assignee.full_name as assigned_to_name,
                     d.name as department_name,
                     ta.assigned_at,
                     ta.accepted_at,
                     ta.rejected_at,
                     ta.rejection_reason
                     FROM tasks t
                     LEFT JOIN users creator ON t.created_by = creator.id
                     LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.current_assignee = 1
                     LEFT JOIN users assignee ON ta.assigned_to = assignee.id
                     LEFT JOIN departments d ON assignee.department_id = d.id
                     WHERE t.id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch();

            if ($task) {
                ob_start();
?>
                <div class="task-details">
                    <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Description:</strong></p>
                            <p><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Priority:</strong> <?php echo getPriorityBadge($task['priority']); ?></p>
                            <p><strong>Status:</strong> <?php echo getStatusBadge($task['status']); ?></p>
                            <p><strong>Deadline:</strong> <?php echo formatDate($task['deadline']); ?></p>
                            <p><strong>Created By:</strong> <?php echo $task['created_by_name']; ?></p>
                            <p><strong>Assigned To:</strong> <?php echo $task['assigned_to_name'] ?? 'Not assigned'; ?></p>
                            <p><strong>Department:</strong> <?php echo $task['department_name'] ?? 'N/A'; ?></p>
                        </div>
                    </div>

                    <?php if ($task['rejection_reason']): ?>
                        <div class="alert alert-danger mt-3">
                            <strong>Rejection Reason:</strong>
                            <p><?php echo nl2br(htmlspecialchars($task['rejection_reason'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
<?php
                $html = ob_get_clean();
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                throw new Exception('Task not found');
            }
            break;

        case 'get':
            $task_id = $_GET['id'] ?? 0;

            if (empty($task_id)) {
                throw new Exception('Task ID is required');
            }

            $query = "SELECT t.*, 
              creator.full_name as created_by_name,
              assignee.full_name as assigned_to_name,
              assignee.id as assigned_to_id,
              ta.assigned_at,
              ta.accepted_at,
              ta.rejected_at,
              ta.rejection_reason
              FROM tasks t
              LEFT JOIN users creator ON t.created_by = creator.id
              LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.current_assignee = 1
              LEFT JOIN users assignee ON ta.assigned_to = assignee.id
              WHERE t.id = :id";

            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                echo json_encode(['success' => true, 'task' => $task]);
            } else {
                throw new Exception('Task not found');
            }
            break;
        case 'delete':
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }

                $task_id = $_POST['task_id'] ?? 0;

                if (empty($task_id)) {
                    throw new Exception('Task ID is required');
                }

                if (!isset($_SESSION['user_id'])) {
                    throw new Exception('You must be logged in to delete tasks');
                }

                $stmt = $conn->prepare("SELECT id, created_by FROM tasks WHERE id = :id");
                $stmt->execute([':id' => $task_id]);
                $task = $stmt->fetch();

                if (!$task) {
                    throw new Exception('Task not found');
                }

                if ($task['created_by'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin') {
                    throw new Exception('You do not have permission to delete this task');
                }

                $stmt = $conn->prepare("SELECT accepted_at FROM task_assignments WHERE task_id = :task_id AND accepted_at IS NOT NULL LIMIT 1");
                $stmt->execute([':task_id' => $task_id]);
                $accepted = $stmt->fetch();

                if ($accepted) {
                    throw new Exception('Cannot delete task - it has already been accepted by an employee');
                }

                // Delete without transaction - simpler
                $stmt = $conn->prepare("DELETE FROM task_logs WHERE task_id = :task_id");
                $stmt->execute([':task_id' => $task_id]);

                $stmt = $conn->prepare("DELETE FROM task_assignments WHERE task_id = :task_id");
                $stmt->execute([':task_id' => $task_id]);

                $stmt = $conn->prepare("DELETE FROM tasks WHERE id = :id");
                $stmt->execute([':id' => $task_id]);

                echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// function sanitize($input)
// {
//     return htmlspecialchars(strip_tags(trim($input)));
// }

function logTaskAction($conn, $task_id, $user_id, $action, $details = '')
{
    $query = "INSERT INTO task_logs (task_id, user_id, action, details) 
             VALUES (:task_id, :user_id, :action, :details)";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':task_id' => $task_id,
        ':user_id' => $user_id,
        ':action' => $action,
        ':details' => $details
    ]);
}
?>