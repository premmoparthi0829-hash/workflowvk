<?php
require_once '../../config.php';
requireAuth('hod');

$user_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Tasks - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-dark bg-success sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-tasks"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user-tie me-2"></i>
                <?php echo $user_name; ?> (HOD)
                <a href="../../auth/logout.php" class="btn btn-outline-light btn-sm ms-3">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-tie"></i> HOD Panel</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-users"></i> My Employees
                        </a>
                        <a href="tasks.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-tasks"></i> Department Tasks
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Department Tasks</h4>
                    </div>
                    <div class="card-body">
                        <h5>Hello, <?php echo $user_name; ?>!</h5>
                        <p>As Head of Department, you can:</p>
                        <ul>
                            <li>View tasks assigned to your department</li>
                            <li>Create tasks for employees in your department</li>
                            <li>Reassign tasks within your department</li>
                            <li>Monitor task progress</li>
                        </ul>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> Database setup required to view and manage tasks.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>