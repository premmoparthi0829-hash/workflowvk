<?php
session_start();
require_once '../../config.php';
require_once '../../config/database.php';

// Check if user is admin/management
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
    header("Location: ../../auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get current year
$current_year = date('Y');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday'])) {
        // Add new holiday
        $name = trim($_POST['name']);
        $date = $_POST['date'];
        $type = $_POST['type'];
        $description = trim($_POST['description']);
        
        $query = "INSERT INTO holidays (name, date, type, description, created_by) 
                  VALUES (:name, :date, :type, :description, :created_by)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Holiday added successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to add holiday.';
        }
    } 
    elseif (isset($_POST['update_holiday'])) {
        // Update holiday
        $id = $_POST['holiday_id'];
        $name = trim($_POST['name']);
        $date = $_POST['date'];
        $type = $_POST['type'];
        $description = trim($_POST['description']);
        
        $query = "UPDATE holidays 
                  SET name = :name, date = :date, type = :type, description = :description 
                  WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Holiday updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update holiday.';
        }
    }
    elseif (isset($_POST['delete_holiday'])) {
        // Delete holiday
        $id = $_POST['holiday_id'];
        
        $query = "DELETE FROM holidays WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Holiday deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete holiday.';
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: manage_holidays.php');
    exit();
}

// Get all holidays for current and next year
$query = "SELECT h.*, u.full_name as created_by_name 
          FROM holidays h 
          LEFT JOIN users u ON h.created_by = u.id 
          WHERE YEAR(date) IN (:current_year, :next_year) 
          ORDER BY date";
$stmt = $conn->prepare($query);
$next_year = $current_year + 1;
$stmt->bindParam(':current_year', $current_year);
$stmt->bindParam(':next_year', $next_year);
$stmt->execute();
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group holidays by month for calendar view
$holidays_by_month = [];
foreach ($holidays as $holiday) {
    $month_year = date('F Y', strtotime($holiday['date']));
    if (!isset($holidays_by_month[$month_year])) {
        $holidays_by_month[$month_year] = [];
    }
    $holidays_by_month[$month_year][] = $holiday;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Holidays</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .holiday-calendar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .holiday-item {
            border-left: 4px solid;
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }
        .holiday-national {
            border-left-color: #dc3545;
        }
        .holiday-regional {
            border-left-color: #fd7e14;
        }
        .holiday-company {
            border-left-color: #20c997;
        }
        .month-header {
            background: #0d6efd;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .calendar-day {
            border: 1px solid #dee2e6;
            padding: 10px;
            min-height: 100px;
            position: relative;
        }
        .holiday-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            right: 5px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-calendar-day"></i> Manage Holidays</h4>
                <div>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addHolidayModal">
                        <i class="fas fa-plus"></i> Add New Holiday
                    </button>
                    <a href="index.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Current Year Holidays -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt"></i> Holidays for <?php echo $current_year; ?> - <?php echo $next_year; ?>
                    <span class="badge bg-light text-dark"><?php echo count($holidays); ?> Holidays</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($holidays_by_month)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> No holidays defined for the current and next year.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($holidays_by_month as $month_year => $month_holidays): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="month-header">
                                    <h6 class="mb-0"><?php echo $month_year; ?></h6>
                                </div>
                                <?php foreach ($month_holidays as $holiday): 
                                    $type_class = '';
                                    switch($holiday['type']) {
                                        case 'national': $type_class = 'holiday-national'; break;
                                        case 'regional': $type_class = 'holiday-regional'; break;
                                        case 'company': $type_class = 'holiday-company'; break;
                                    }
                                ?>
                                    <div class="holiday-item <?php echo $type_class; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($holiday['name']); ?></h6>
                                                <p class="mb-1">
                                                    <i class="fas fa-calendar"></i> 
                                                    <?php echo date('l, F j, Y', strtotime($holiday['date'])); ?>
                                                </p>
                                                <?php if ($holiday['description']): ?>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($holiday['description']); ?></p>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    Added by: <?php echo htmlspecialchars($holiday['created_by_name'] ?? 'System'); ?>
                                                    on <?php echo date('M d, Y', strtotime($holiday['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editHolidayModal"
                                                        onclick="editHoliday(<?php echo htmlspecialchars(json_encode($holiday)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteHoliday(<?php echo $holiday['id']; ?>, '<?php echo addslashes($holiday['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge 
                                                <?php 
                                                switch($holiday['type']) {
                                                    case 'national': echo 'bg-danger'; break;
                                                    case 'regional': echo 'bg-warning text-dark'; break;
                                                    case 'company': echo 'bg-success'; break;
                                                }
                                                ?>">
                                                <?php echo ucfirst($holiday['type']); ?> Holiday
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Holiday Calendar View -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar"></i> Calendar View</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    // Show next 6 months
                    for ($i = 0; $i < 6; $i++): 
                        $month_date = date('Y-m-01', strtotime("+$i months"));
                        $month_name = date('F Y', strtotime($month_date));
                        $days_in_month = date('t', strtotime($month_date));
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><?php echo $month_name; ?></h6>
                                </div>
                                <div class="card-body p-2">
                                    <div class="row">
                                        <?php for ($j = 1; $j <= $days_in_month; $j++): 
                                            $current_date = date('Y-m-d', strtotime($month_name . " $j"));
                                            $day_of_week = date('N', strtotime($current_date));
                                            $is_weekend = $day_of_week >= 6;
                                            
                                            // Check if holiday
                                            $is_holiday = false;
                                            $holiday_name = '';
                                            $holiday_type = '';
                                            
                                            foreach ($holidays as $holiday) {
                                                if ($holiday['date'] == $current_date) {
                                                    $is_holiday = true;
                                                    $holiday_name = $holiday['name'];
                                                    $holiday_type = $holiday['type'];
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="col-3 col-md-2 p-1">
                                                <div class="calendar-day <?php echo $is_weekend ? 'bg-light' : ''; ?> 
                                                    <?php echo $is_holiday ? 'border-primary' : ''; ?>">
                                                    <div class="text-center">
                                                        <small class="fw-bold"><?php echo $j; ?></small><br>
                                                        <small class="text-muted"><?php echo date('D', strtotime($current_date)); ?></small>
                                                    </div>
                                                    <?php if ($is_holiday): ?>
                                                        <div class="holiday-badge">
                                                            <span class="badge 
                                                                <?php 
                                                                switch($holiday_type) {
                                                                    case 'national': echo 'bg-danger'; break;
                                                                    case 'regional': echo 'bg-warning text-dark'; break;
                                                                    case 'company': echo 'bg-success'; break;
                                                                }
                                                                ?>"
                                                                title="<?php echo htmlspecialchars($holiday_name); ?>">
                                                                H
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Holiday Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Holiday Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Type *</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="national">National Holiday</option>
                                <option value="regional">Regional Holiday</option>
                                <option value="company" selected>Company Holiday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_holiday" class="btn btn-primary">Add Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Holiday Modal -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" id="edit_holiday_id" name="holiday_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Holiday</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Holiday Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date" class="form-label">Date *</label>
                            <input type="date" class="form-control" id="edit_date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_type" class="form-label">Type *</label>
                            <select class="form-select" id="edit_type" name="type" required>
                                <option value="national">National Holiday</option>
                                <option value="regional">Regional Holiday</option>
                                <option value="company">Company Holiday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_holiday" class="btn btn-primary">Update Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" id="delete_holiday_id" name="holiday_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the holiday: <strong id="delete_holiday_name"></strong>?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_holiday" class="btn btn-danger">Delete Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(document).ready(function() {
            // Initialize date picker for add form
            flatpickr("#date", {
                minDate: "today",
                dateFormat: "Y-m-d"
            });
            
            // Initialize date picker for edit form
            flatpickr("#edit_date", {
                dateFormat: "Y-m-d"
            });
            
            // Set default date to today for add form
            $('#date').val(new Date().toISOString().split('T')[0]);
        });
        
        function editHoliday(holiday) {
            $('#edit_holiday_id').val(holiday.id);
            $('#edit_name').val(holiday.name);
            $('#edit_date').val(holiday.date);
            $('#edit_type').val(holiday.type);
            $('#edit_description').val(holiday.description || '');
            
            $('#editHolidayModal').modal('show');
        }
        
        function deleteHoliday(id, name) {
            $('#delete_holiday_id').val(id);
            $('#delete_holiday_name').text(name);
            $('#deleteHolidayModal').modal('show');
        }
        
        // Auto-focus on first input when modal opens
        $('#addHolidayModal').on('shown.bs.modal', function () {
            $('#name').focus();
        });
        
        $('#editHolidayModal').on('shown.bs.modal', function () {
            $('#edit_name').focus();
        });
    </script>
</body>
</html>