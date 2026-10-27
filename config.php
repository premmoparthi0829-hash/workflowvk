<?php
date_default_timezone_set('Asia/Kolkata');
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application Configuration
define('APP_NAME', 'Workflow :: VK Logistics Hub');
define('APP_URL', 'http://localhost:8080/taskmanagementsystem');

// Database Configuration - USE CORRECT DATABASE NAME
define('DB_HOST', 'localhost');
define('DB_NAME', 'u938436441_wftmvkhub'); // Changed to your actual database name
define('DB_USER', 'u938436441_wfkmd');
define('DB_PASS', 'V!7wJ!OPi');

// Session management
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
function requireAuth($role = null) {
    if(!isset($_SESSION['user_id'])) {
        header("Location: " . APP_URL . "/auth/login.php");
        exit;
    }
    
    if($role && $_SESSION['role'] !== $role) {
        header("Location: " . APP_URL . "/dashboard/" . $_SESSION['role'] . "/");
        exit;
    }
}

// Database connection
function getDB() {
    static $db = null;
    if($db === null) {
        try {
            $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Show user-friendly error
            die("<div style='padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;'>
                <h3>Database Connection Error</h3>
                <p><strong>Error:</strong> " . $e->getMessage() . "</p>
                <p><strong>Database:</strong> " . DB_NAME . "</p>
                <p>Please check:</p>
                <ol>
                    <li>MySQL is running in XAMPP</li>
                    <li>Database 'taskmanagementsystem' exists in phpMyAdmin</li>
                    <li>Username and password are correct</li>
                </ol>
                <p><a href='../install/setup.php'>Click here to run setup</a></p>
            </div>");
        }
    }
    return $db;
}

// Helper functions
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}
?>