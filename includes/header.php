<?php
if(!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            border-left: 4px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }
        .sidebar .nav-link.active {
            background: rgba(52, 152, 219, 0.2);
            border-left-color: #3498db;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['user_id'])): ?>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tasks"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="d-flex align-items-center text-white">
                <span class="me-3">
                    <i class="fas fa-user"></i> 
                    <?php echo $_SESSION['full_name']; ?> 
                    (<?php echo ucfirst($_SESSION['role']); ?>)
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>