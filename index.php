<?php
session_start();

// Simple configuration
define('APP_NAME', 'Task Management System');

// Check if logged in
if(isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'employee';
    header("Location: dashboard/{$role}/");
    exit;
} else {
    header("Location: auth/login.php");
    exit;
}
?>