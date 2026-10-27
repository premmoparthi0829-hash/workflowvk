<nav class="sidebar">
    <div class="sidebar-header text-center py-4">
        <h5 class="text-white"><i class="fas fa-user-shield"></i> Management</h5>
        <small class="text-light"><?php echo $_SESSION['full_name']; ?></small>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : ''; ?>" href="departments.php">
                <i class="fas fa-sitemap"></i> Departments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users"></i> Users
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>" href="tasks.php">
                <i class="fas fa-tasks"></i> Tasks
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity.php' ? 'active' : ''; ?>" href="activity.php">
                <i class="fas fa-history"></i> Activity Logs
            </a>
        </li>
    </ul>
</nav>