<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo 'Not logged in';
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? date('Y-m-d');

// Get attendance summary
$summary_query = "SELECT * FROM attendance_summary 
                  WHERE user_id = :user_id AND date = :date";
$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bindParam(':user_id', $user_id);
$summary_stmt->bindParam(':date', $date);
$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Get punches for the day
$punches_query = "SELECT * FROM attendance_punches 
                  WHERE user_id = :user_id 
                  AND DATE(punch_time) = :date 
                  ORDER BY punch_time ASC";
$punches_stmt = $conn->prepare($punches_query);
$punches_stmt->bindParam(':user_id', $user_id);
$punches_stmt->bindParam(':date', $date);
$punches_stmt->execute();
$punches = $punches_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate timeline
$timeline_html = '';
$last_time = null;
$last_type = null;

foreach ($punches as $punch) {
    $time = date('h:i A', strtotime($punch['punch_time']));
    $type_text = ucwords(str_replace('_', ' ', $punch['punch_type']));
    
    switch($punch['punch_type']) {
        case 'clock_in':
        case 'clock_out':
            $badge_class = 'bg-primary';
            break;
        case 'lunch_start':
        case 'lunch_end':
            $badge_class = 'bg-warning';
            break;
        case 'tea_break_start':
        case 'tea_break_end':
            $badge_class = 'bg-info';
            break;
        case 'permission_start':
        case 'permission_end':
            $badge_class = 'bg-secondary';
            break;
        default:
            $badge_class = 'bg-secondary';
    }
    
    $timeline_html .= '<div class="timeline-item mb-3">';
    $timeline_html .= '<div class="d-flex">';
    $timeline_html .= '<div class="flex-shrink-0">';
    $timeline_html .= '<span class="badge ' . $badge_class . '">' . $time . '</span>';
    $timeline_html .= '</div>';
    $timeline_html .= '<div class="flex-grow-1 ms-3">';
    $timeline_html .= '<h6 class="mt-0 mb-1">' . $type_text . '</h6>';
    if ($punch['notes']) {
        $timeline_html .= '<p class="text-muted mb-0">' . htmlspecialchars($punch['notes']) . '</p>';
    }
    $timeline_html .= '</div>';
    $timeline_html .= '</div>';
    $timeline_html .= '</div>';
}
?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6>Summary</h6>
            </div>
            <div class="card-body">
                <?php if ($summary): ?>
                    <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($summary['date'])); ?></p>
                    <p><strong>First In:</strong> <?php echo $summary['first_clock_in'] ? date('h:i A', strtotime($summary['first_clock_in'])) : 'N/A'; ?></p>
                    <p><strong>Last Out:</strong> <?php echo $summary['last_clock_out'] ? date('h:i A', strtotime($summary['last_clock_out'])) : 'N/A'; ?></p>
                    <p><strong>Work Hours:</strong> <?php echo number_format($summary['total_work_hours'], 2); ?> hrs</p>
                    <p><strong>Break Hours:</strong> <?php echo number_format($summary['total_break_hours'], 2); ?> hrs</p>
                    <p><strong>Permission Hours:</strong> <?php echo number_format($summary['total_permission_hours'], 2); ?> hrs</p>
                    <p><strong>Status:</strong> 
                        <span class="badge 
                            <?php 
                            switch($summary['status']) {
                                case 'present': echo 'bg-success'; break;
                                case 'half_day': echo 'bg-warning'; break;
                                case 'absent': echo 'bg-danger'; break;
                                case 'leave': echo 'bg-info'; break;
                            }
                            ?>
                        ">
                            <?php echo ucfirst($summary['status']); ?>
                        </span>
                    </p>
                <?php else: ?>
                    <p class="text-center text-muted">No attendance recorded for this day</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <h6>Daily Timeline</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($timeline_html)): ?>
                    <?php echo $timeline_html; ?>
                <?php else: ?>
                    <p class="text-center text-muted">No punches recorded for this day</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>