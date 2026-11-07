<?php
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch holidays
$h_stmt = $conn->query("SELECT * FROM holidays ORDER BY date ASC");
$holidays = $h_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tasks with deadlines
$t_stmt = $conn->query("SELECT t.id, t.title, t.description, t.priority, t.deadline, u.full_name as assignee 
                        FROM tasks t 
                        LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.current_assignee = 1 
                        LEFT JOIN users u ON ta.assigned_to = u.id");
$tasks = $t_stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "vk_logistics_calendar_" . date('Ymd') . ".ics";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//VK Logistics Hub//Task & Holiday Calendar//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:VK Logistics Operations Calendar\r\n";
echo "X-WR-TIMEZONE:Asia/Kolkata\r\n";

// Add Holidays
foreach ($holidays as $h) {
    $date = str_replace('-', '', $h['date']);
    $uid = "holiday-" . $h['id'] . "@vklogisticshub.com";
    $summary = "[Holiday] " . addcslashes($h['name'], ",;");
    $desc = addcslashes($h['description'] ?? '', ",;");
    
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART;VALUE=DATE:" . $date . "\r\n";
    echo "SUMMARY:" . $summary . "\r\n";
    if (!empty($desc)) {
        echo "DESCRIPTION:" . $desc . "\r\n";
    }
    echo "CATEGORIES:HOLIDAY\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "END:VEVENT\r\n";
}

// Add Task Deadlines
foreach ($tasks as $t) {
    if (empty($t['deadline'])) continue;
    $date = str_replace('-', '', $t['deadline']);
    $uid = "task-" . $t['id'] . "@vklogisticshub.com";
    $summary = "[Deadline: " . ucfirst($t['priority']) . "] " . addcslashes($t['title'], ",;");
    $desc = "Assignee: " . ($t['assignee'] ?? 'Unassigned') . "\\n" . addcslashes($t['description'] ?? '', ",;");
    
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $uid . "\r\n";
    echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    echo "DTSTART;VALUE=DATE:" . $date . "\r\n";
    echo "SUMMARY:" . $summary . "\r\n";
    echo "DESCRIPTION:" . $desc . "\r\n";
    echo "CATEGORIES:TASK\r\n";
    echo "STATUS:CONFIRMED\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";
exit;

