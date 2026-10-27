<?php
require_once '../../includes/auth.php';
$auth = new Auth();
$auth->requireRole(['management']);
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$month = $_GET['month'] ?? date('Y-m');
?>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Performance Reports</h1>
                <div>
                    <input type="month" id="reportMonth" class="form-control" value="<?php echo $month; ?>">
                </div>
            </div>
            
            <!-- Department Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Department Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Total Tasks</th>
                                    <th>Accepted</th>
                                    <th>Rejected</th>
                                    <th>Completed</th>
                                    <th>Completion Rate</th>
                                    <th>Avg. Time (Hours)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = "SELECT d.name, 
                                         COUNT(DISTINCT t.id) as total_tasks,
                                         SUM(CASE WHEN ta.accepted_at IS NOT NULL THEN 1 ELSE 0 END) as accepted,
                                         SUM(CASE WHEN ta.rejected_at IS NOT NULL THEN 1 ELSE 0 END) as rejected,
                                         SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
                                         ROUND(SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT t.id), 2) as completion_rate
                                         FROM departments d
                                         LEFT JOIN users u ON d.id = u.department_id
                                         LEFT JOIN task_assignments ta ON u.id = ta.assigned_to
                                         LEFT JOIN tasks t ON ta.task_id = t.id
                                         WHERE MONTH(t.created_at) = MONTH(:month) 
                                         AND YEAR(t.created_at) = YEAR(:month)
                                         GROUP BY d.id
                                         ORDER BY d.name";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([':month' => $month . '-01']);
                                
                                while($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['total_tasks']; ?></td>
                                    <td><?php echo $row['accepted']; ?></td>
                                    <td><?php echo $row['rejected']; ?></td>
                                    <td><?php echo $row['completed']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $row['completion_rate']; ?>%">
                                                <?php echo $row['completion_rate']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $row['avg_time'] ?? 'N/A'; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Task Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px;">
                                <?php
                                $query = "SELECT status, COUNT(*) as count 
                                         FROM tasks 
                                         WHERE MONTH(created_at) = MONTH(:month) 
                                         AND YEAR(created_at) = YEAR(:month)
                                         GROUP BY status";
                                $stmt = $conn->prepare($query);
                                $stmt->execute([':month' => $month . '-01']);
                                
                                $data = [];
                                $colors = [
                                    'completed' => '#28a745',
                                    'in_progress' => '#007bff',
                                    'pending' => '#ffc107',
                                    'rejected' => '#dc3545'
                                ];
                                
                                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $data[] = $row;
                                }
                                ?>
                                <div style="display: flex; height: 250px; align-items: flex-end; gap: 10px;">
                                    <?php foreach($data as $item): ?>
                                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                        <div style="width: 40px; background: <?php echo $colors[$item['status']] ?? '#6c757d'; ?>; 
                                             height: <?php echo ($item['count'] / max(array_column($data, 'count'))) * 200; ?>px;">
                                        </div>
                                        <small class="mt-2"><?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?></small>
                                        <strong><?php echo $item['count']; ?></strong>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Priority Distribution</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $query = "SELECT priority, COUNT(*) as count 
                                     FROM tasks 
                                     WHERE MONTH(created_at) = MONTH(:month) 
                                     AND YEAR(created_at) = YEAR(:month)
                                     GROUP BY priority";
                            $stmt = $conn->prepare($query);
                            $stmt->execute([':month' => $month . '-01']);
                            
                            $total = 0;
                            $priorities = [];
                            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $priorities[] = $row;
                                $total += $row['count'];
                            }
                            ?>
                            <div style="height: 250px; position: relative;">
                                <?php 
                                $startAngle = 0;
                                foreach($priorities as $item):
                                    $percentage = ($item['count'] / $total) * 100;
                                    $angle = ($percentage / 100) * 360;
                                ?>
                                <div style="position: absolute; width: 200px; height: 200px; 
                                     border-radius: 50%; border: 20px solid transparent;
                                     border-top-color: <?php 
                                     switch($item['priority']) {
                                         case 'high': echo '#dc3545'; break;
                                         case 'medium': echo '#ffc107'; break;
                                         case 'low': echo '#28a745'; break;
                                         default: echo '#007bff';
                                     }
                                     ?>;
                                     transform: rotate(<?php echo $startAngle; ?>deg);
                                     clip-path: polygon(0 0, 100% 0, 100% 50%, 0 50%);
                                     left: 50%; top: 50%; margin-left: -100px; margin-top: -100px;">
                                </div>
                                <?php 
                                $startAngle += $angle;
                                endforeach; 
                                ?>
                                <div style="position: absolute; width: 120px; height: 120px; 
                                     background: white; border-radius: 50%;
                                     left: 50%; top: 50%; margin-left: -60px; margin-top: -60px;">
                                    <div class="text-center" style="margin-top: 40px;">
                                        <h4><?php echo $total; ?></h4>
                                        <small>Total Tasks</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <?php foreach($priorities as $item): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width: 20px; height: 20px; background: <?php 
                                    switch($item['priority']) {
                                        case 'high': echo '#dc3545'; break;
                                        case 'medium': echo '#ffc107'; break;
                                        case 'low': echo '#28a745'; break;
                                        default: echo '#007bff';
                                    }
                                    ?>; margin-right: 10px;"></div>
                                    <span><?php echo ucfirst($item['priority']); ?>: <?php echo $item['count']; ?> tasks</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('reportMonth').addEventListener('change', function() {
    window.location.href = 'reports.php?month=' + this.value;
});
</script>

<?php require_once '../../includes/footer.php'; ?>