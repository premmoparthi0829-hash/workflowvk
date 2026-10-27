<?php
session_start();

// Check authentication
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['full_name'] ?? 'Employee';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tasks"></i> Workflow
            </a>
            <div class="d-flex align-items-center text-white">
                <i class="fas fa-user me-2"></i>
                <?php echo $user_name; ?> (Employee)
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
                    <div class="card-header bg-red-vk text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Employee Panel</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-home"></i> Dashboard</a>
                        <a href="tasks.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tasks"></i> My Tasks
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="fas fa-history"></i> Task History
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Welcome to Employee Dashboard</h4>
                    </div>
                    <div class="card-body">
                        <h5>Hello, <?php echo $user_name; ?>!</h5>
                        <p>You have successfully logged in as Employee.</p>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> Tasks will appear here when assigned by
                            Management or HOD.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>