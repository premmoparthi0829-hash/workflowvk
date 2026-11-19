<?php
date_default_timezone_set('Asia/Kolkata');
// Error handling: log errors without displaying code internals to users
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Application Configuration
define('APP_NAME', 'Workflow :: VK Logistics Hub');

if (!defined('APP_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
    define('APP_URL', $protocol . $host);
}

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '8889');
define('DB_NAME', 'taskmanagementsystem');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// AI & Document Intelligence Configuration
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

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
            $dsn = "mysql:host=" . DB_HOST;
            if (defined('DB_PORT') && DB_PORT) {
                $dsn .= ";port=" . DB_PORT;
            }
            $dsn .= ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Show user-friendly error
            die("<div style='padding:20px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;'>
                <h3>Database Connection Error</h3>
                <p><strong>Error:</strong> " . $e->getMessage() . "</p>
                <p><strong>Database:</strong> " . DB_NAME . "</p>
                <p>Please check MySQL connection settings.</p>
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