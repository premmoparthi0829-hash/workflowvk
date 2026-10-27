<?php
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'not_started' => 'secondary',
        'in_progress' => 'primary',
        'waiting_review' => 'info',
        'completed' => 'success',
        'rejected' => 'danger'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    $display = str_replace('_', ' ', ucfirst($status));
    
    return '<span class="badge bg-' . $color . '">' . $display . '</span>';
}

function getPriorityBadge($priority) {
    $badges = [
        'low' => 'success',
        'medium' => 'warning',
        'high' => 'danger',
        'critical' => 'dark'
    ];
    
    $color = $badges[$priority] ?? 'secondary';
    
    return '<span class="badge bg-' . $color . '">' . ucfirst($priority) . '</span>';
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function isDeadlineNear($deadline, $days = 3) {
    $today = new DateTime();
    $deadlineDate = new DateTime($deadline);
    $interval = $today->diff($deadlineDate);
    
    return $interval->days <= $days && $interval->invert == 0;
}

function logActivity($db, $user_id, $action, $details = '') {
    $query = "INSERT INTO activity_logs (user_id, action, details) VALUES (:user_id, :action, :details)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':details' => $details
    ]);
}
?>