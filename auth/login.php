<?php
require_once '../config.php';

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/" . $_SESSION['role'] . "/");
    exit;
}

$error = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if($user) {
                // Use password_verify() to check against hashed password in database
                if(password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['department_id'] = $user['department_id'];
                    
                    // Update last login
                    // $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    // $stmt->execute([$user['id']]);
                    
                    header("Location: ../dashboard/{$user['role']}/");
                    exit;
                } else {
                    $error = "Invalid password";
                }
            } else {
                $error = "User not found or account is inactive";
            }
        } catch(Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #fff 0%, #fefefe 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h3><i class="fas fa-tasks"></i> <?php echo APP_NAME; ?></h3>
                        <p class="mb-0">Sign in to your account</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" required autofocus>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="text-center d-grid">
                                <button type="submit" class="btn btn-book-now btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-4 p-3 bg-light rounded border">
                            <h6 class="mb-2 text-dark"><i class="fas fa-key text-primary"></i> <strong>Quick Test Credentials:</strong></h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                                    <thead class="table-light">
                                        <tr><th>Role</th><th>Username</th><th>Password</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-danger">Admin / Management</span></td>
                                            <td><code>admin</code></td>
                                            <td><code>admin123</code></td>
                                            <td><button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" onclick="fillCreds('admin', 'admin123')">Use</button></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success">HOD</span></td>
                                            <td><code>hod1</code></td>
                                            <td><code>hod123</code></td>
                                            <td><button type="button" class="btn btn-xs btn-outline-success py-0 px-2" onclick="fillCreds('hod1', 'hod123')">Use</button></td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-primary">User / Employee</span></td>
                                            <td><code>emp1</code></td>
                                            <td><code>emp123</code></td>
                                            <td><button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" onclick="fillCreds('emp1', 'emp123')">Use</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fillCreds(username, password) {
            document.querySelector('input[name="username"]').value = username;
            document.querySelector('input[name="password"]').value = password;
        }
    </script>
</body>
</html>