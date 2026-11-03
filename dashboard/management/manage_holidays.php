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
$current_year = (int)date('Y');

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

// Get all holidays
$query = "SELECT h.*, u.full_name as created_by_name 
          FROM holidays h 
          LEFT JOIN users u ON h.created_by = u.id 
          ORDER BY date ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map holidays by date (YYYY-MM-DD) and calculate metrics
$holidays_by_date = [];
$type_counts = ['all' => count($holidays), 'national' => 0, 'regional' => 0, 'company' => 0];
$today_date = date('Y-m-d');
$next_holiday = null;

foreach ($holidays as $holiday) {
    $date_key = $holiday['date'];
    if (!isset($holidays_by_date[$date_key])) {
        $holidays_by_date[$date_key] = [];
    }
    $holidays_by_date[$date_key][] = $holiday;
    
    // Type normalization
    $t = strtolower($holiday['type'] ?? 'company');
    if ($t === 'gazetted') $t = 'regional';
    if (isset($type_counts[$t])) {
        $type_counts[$t]++;
    } else {
        $type_counts['company']++;
    }
    
    // Find next upcoming holiday
    if (!$next_holiday && $holiday['date'] >= $today_date) {
        $next_holiday = $holiday;
    }
}

// Compute Next Holiday tagline details
$next_holiday_info = null;
if ($next_holiday) {
    $today_ts = strtotime($today_date);
    $hol_ts = strtotime($next_holiday['date']);
    $diff_days = (int)round(($hol_ts - $today_ts) / 86400);
    
    if ($diff_days === 0) {
        $days_badge = "Today!";
        $days_text = "is Today!";
    } elseif ($diff_days === 1) {
        $days_badge = "Tomorrow";
        $days_text = "is Tomorrow";
    } else {
        $days_badge = "in {$diff_days} days";
        $days_text = "is in {$diff_days} days";
    }
    
    $htype = strtolower($next_holiday['type'] ?? 'company');
    if ($htype === 'gazetted') $htype = 'regional';
    
    $next_holiday_info = [
        'name' => $next_holiday['name'],
        'date' => $next_holiday['date'],
        'formatted_date' => date('l, M d, Y', $hol_ts),
        'diff_days' => $diff_days,
        'days_badge' => $days_badge,
        'days_text' => $days_text,
        'type' => $htype,
        'description' => $next_holiday['description'] ?? ''
    ];
}

// Group holidays by month for list view
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
    <title>Holidays & Calendar - <?php echo defined('APP_NAME') ? APP_NAME : 'Workflow'; ?></title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f7f9fc;
            color: #0f172a;
        }

        /* Top Page Header */
        .cal-top-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 16px 24px;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }
        .cal-header-icon {
            width: 48px;
            height: 48px;
            background: #ede9fe;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 1.35rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.12);
        }
        .cal-main-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }
        .cal-sub-title {
            font-size: 0.82rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }

        /* Center View Toggle Switch */
        .cal-view-toggle {
            background: #f1f5f9;
            padding: 4px;
            border-radius: 30px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid #e2e8f0;
        }
        .cal-toggle-pill {
            border: none;
            background: transparent;
            padding: 7px 16px;
            border-radius: 24px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .cal-toggle-pill.active {
            background: #4f46e5;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.32);
        }

        /* Next Upcoming Holiday Tagline Banner */
        .next-holiday-tagline {
            background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
            border: 1px solid #e0e7ff;
            border-radius: 18px;
            padding: 14px 22px;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.05);
            transition: all 0.2s ease;
            margin-bottom: 24px;
        }
        .next-holiday-tagline:hover {
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.09);
            border-color: #c7d2fe;
        }
        .next-holiday-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #ede9fe;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(79, 70, 229, 0.15);
        }
        .next-holiday-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .next-holiday-countdown-badge {
            background: #4f46e5;
            color: #ffffff;
            font-size: 0.78rem;
            font-weight: 800;
            padding: 3px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
        }
        .next-holiday-headline {
            font-size: 0.95rem;
            color: #334155;
            font-weight: 500;
        }
        .next-holiday-name {
            font-weight: 800;
            color: #0f172a;
        }

        /* 1. LIST VIEW STYLES */
        .holiday-list-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #eef2f6;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 28px;
        }
        .holiday-month-title {
            font-size: 1rem;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 14px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #eff6ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .holiday-list-item {
            border-left: 4px solid;
            margin-bottom: 12px;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #eef2f6;
            border-left-width: 4px;
            transition: all 0.2s ease;
        }
        .holiday-list-item:hover {
            transform: translateX(3px);
            background: #f1f5f9;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .holiday-list-item.national { border-left-color: #dc2626; }
        .holiday-list-item.regional { border-left-color: #f59e0b; }
        .holiday-list-item.company { border-left-color: #10b981; }

        /* Filter buttons */
        .filter-btn-pill {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            transition: all 0.15s ease;
        }
        .filter-btn-pill:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .filter-btn-pill.active {
            background: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
        }

        /* 2. CALENDAR VIEW SECTION STYLES */
        .cal-section-card {
            background: #ffffff;
            border-radius: 22px;
            border: 1px solid #eef2f6;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        /* Calendar Controls */
        .cal-nav-btn {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.18s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cal-nav-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            transform: translateY(-1px);
        }
        .cal-range-pill {
            height: 38px;
            padding: 0 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.84rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.18s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .cal-range-pill:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .cal-today-pill {
            height: 38px;
            padding: 0 20px;
            border-radius: 12px;
            border: none;
            background: #4f46e5;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.84rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.18s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
        }
        .cal-today-pill:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.38);
        }

        /* 6-Month Cards Grid */
        .month-card-wrapper {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #eef2f6;
            padding: 22px 20px 20px 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .month-card-wrapper:hover {
            box-shadow: 0 10px 26px rgba(79, 70, 229, 0.08);
        }
        /* Top-right corner lavender gradient aura */
        .month-card-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 140px;
            height: 100px;
            background: radial-gradient(circle at 100% 0%, rgba(167, 139, 250, 0.24) 0%, rgba(221, 214, 254, 0.12) 45%, transparent 70%);
            pointer-events: none;
            border-top-right-radius: 20px;
        }

        .month-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }
        .month-card-header i {
            color: #4f46e5;
            font-size: 1.15rem;
        }
        .month-card-title {
            font-size: 1.08rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.2px;
        }

        /* 7-column calendar grid */
        .cal-7col-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            text-align: center;
        }

        .cal-7col-weekday {
            font-size: 0.76rem;
            font-weight: 700;
            color: #475569;
            padding: 4px 0 6px 0;
            user-select: none;
        }

        /* Day tiles */
        .cal-tile {
            height: 38px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
            user-select: none;
        }
        .cal-tile:hover {
            transform: scale(1.08);
            z-index: 5;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .cal-tile.empty {
            cursor: default;
            background: transparent;
            pointer-events: none;
        }
        .cal-tile.col-weekday {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid rgba(226, 232, 240, 0.7);
        }

        /* Today active pill (like day 11 in screenshot) */
        .cal-tile.is-today-active {
            background: #4f46e5 !important;
            color: #ffffff !important;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.42);
        }

        /* Holiday day tile styles */
        .cal-tile.holiday-national {
            background: #fee2e2 !important;
            color: #b91c1c !important;
            border: 1px solid #fca5a5;
            font-weight: 800;
        }
        .cal-tile.holiday-regional {
            background: #fef3c7 !important;
            color: #b45309 !important;
            border: 1px solid #fde68a;
            font-weight: 800;
        }
        .cal-tile.holiday-company {
            background: #d1fae5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0;
            font-weight: 800;
        }

        /* Indicator dot */
        .cal-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            margin-top: 1px;
        }
        .cal-dot.national { background: #dc2626; }
        .cal-dot.regional { background: #d97706; }
        .cal-dot.company { background: #059669; }
        .cal-dot.today { background: #ffffff; }

        /* Legend Bar */
        .cal-legend-bar {
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #eef2f6;
            padding: 14px 20px;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-4">
        <!-- Top Control Header -->
        <div class="cal-top-card">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <!-- Left Title -->
                <div class="d-flex align-items-center gap-3">
                    <div class="cal-header-icon">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h2 class="cal-main-title mb-0">Calendar & Holiday Schedule</h2>
                        <div class="cal-sub-title">
                            Plan, schedule and manage your events 
                            <?php if ($next_holiday_info): ?>
                                &middot; <span class="text-primary fw-bold">Next: <?php echo htmlspecialchars($next_holiday_info['name']); ?> (<?php echo $next_holiday_info['days_badge']; ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Center: View Switcher (Both Views / List View / Calendar View) -->
                <div class="cal-view-toggle">
                    <button type="button" class="cal-toggle-pill active" id="btnToggleBoth" onclick="setViewMode('both')">
                        <i class="fas fa-columns"></i> Both Views
                    </button>
                    <button type="button" class="cal-toggle-pill" id="btnToggleList" onclick="setViewMode('list')">
                        <i class="fas fa-list"></i> List View
                    </button>
                    <button type="button" class="cal-toggle-pill" id="btnToggleCalendar" onclick="setViewMode('calendar')">
                        <i class="far fa-calendar-alt"></i> Calendar View
                    </button>
                </div>

                <!-- Right Action Buttons -->
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="openAddHolidayModal()">
                        <i class="fas fa-plus me-1"></i> Add Holiday
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Back to Dashboard">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Next Holiday Tagline Banner -->
        <?php if ($next_holiday_info): ?>
            <div class="next-holiday-tagline">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="next-holiday-icon-box">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <span class="next-holiday-label">UPCOMING HOLIDAY</span>
                                <span class="next-holiday-countdown-badge">
                                    <i class="far fa-clock"></i> <?php echo $next_holiday_info['days_badge']; ?>
                                </span>
                            </div>
                            <div class="next-holiday-headline">
                                Next holiday is <span class="next-holiday-name"><?php echo htmlspecialchars($next_holiday_info['name']); ?></span> &mdash; <?php echo $next_holiday_info['days_text']; ?> on <span class="text-dark fw-bold"><?php echo $next_holiday_info['formatted_date']; ?></span>
                                <?php if (!empty($next_holiday_info['description'])): ?>
                                    <span class="text-muted small fw-normal ms-1">(<?php echo htmlspecialchars($next_holiday_info['description']); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="badge <?php 
                            switch($next_holiday_info['type']) {
                                case 'national': echo 'bg-danger'; break;
                                case 'regional': echo 'bg-warning text-dark'; break;
                                case 'company': echo 'bg-success'; break;
                                default: echo 'bg-secondary';
                            }
                        ?> rounded-pill px-3 py-2 fw-bold text-uppercase" style="letter-spacing: 0.5px;">
                            <?php echo ucfirst($next_holiday_info['type']); ?> Holiday
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: LIST VIEW (FIRST) -->
        <div id="sectionListView">
            <div class="holiday-list-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-3">
                    <div>
                        <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="fas fa-list text-primary"></i> All Scheduled Holidays
                        </h4>
                        <p class="text-muted small mb-0">Total <?php echo count($holidays); ?> holidays configured in the system</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted fw-bold me-1">Filter:</span>
                        <button class="filter-btn-pill active" onclick="filterList('all', this)">All</button>
                        <button class="filter-btn-pill" onclick="filterList('national', this)">National</button>
                        <button class="filter-btn-pill" onclick="filterList('regional', this)">Regional</button>
                        <button class="filter-btn-pill" onclick="filterList('company', this)">Company</button>
                    </div>
                </div>

                <?php if (empty($holidays_by_month)): ?>
                    <div class="alert alert-light text-center py-5">
                        <i class="fas fa-calendar-times text-muted fs-1 mb-3"></i>
                        <h5>No holidays scheduled</h5>
                        <button class="btn btn-primary btn-sm rounded-pill mt-2" onclick="openAddHolidayModal()">
                            <i class="fas fa-plus me-1"></i> Add First Holiday
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($holidays_by_month as $m_name => $m_holidays): ?>
                            <div class="col-md-6 col-lg-4 mb-4 holiday-month-group">
                                <div class="holiday-month-title">
                                    <span><?php echo $m_name; ?></span>
                                    <span class="badge bg-light text-primary rounded-pill px-2 border"><?php echo count($m_holidays); ?></span>
                                </div>
                                <?php foreach ($m_holidays as $h): 
                                    $htype = strtolower($h['type'] ?? 'company');
                                    if ($htype === 'gazetted') $htype = 'regional';
                                ?>
                                    <div class="holiday-list-item <?php echo $htype; ?>" data-type="<?php echo $htype; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($h['name']); ?></h6>
                                                <div class="small text-muted mb-1">
                                                    <i class="far fa-calendar-alt me-1 text-primary"></i> 
                                                    <?php echo date('D, M d, Y', strtotime($h['date'])); ?>
                                                </div>
                                                <?php if (!empty($h['description'])): ?>
                                                    <p class="small text-muted mb-2"><?php echo htmlspecialchars($h['description']); ?></p>
                                                <?php endif; ?>
                                                <span class="badge <?php 
                                                    switch($htype) {
                                                        case 'national': echo 'bg-danger'; break;
                                                        case 'regional': echo 'bg-warning text-dark'; break;
                                                        case 'company': echo 'bg-success'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                ?> rounded-pill">
                                                    <?php echo ucfirst($htype); ?>
                                                </span>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editHoliday(<?php echo htmlspecialchars(json_encode($h)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteHoliday(<?php echo $h['id']; ?>, '<?php echo addslashes($h['name']); ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 2: CALENDAR VIEW (UNDER LIST VIEW) -->
        <div id="sectionCalendarView">
            <div class="cal-section-card">
                <!-- Calendar Section Top Bar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="cal-header-icon" style="background: #ede9fe; color: #4f46e5;">
                            <i class="far fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h3 class="cal-main-title mb-0">Calendar View</h3>
                            <div class="cal-sub-title">Plan, schedule and manage your events</div>
                        </div>
                    </div>

                    <!-- 6-Month Navigation Controls -->
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button class="cal-nav-btn" onclick="navStep(-6)" title="Previous 6 Months">
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <div class="dropdown">
                            <button class="cal-range-pill dropdown-toggle" type="button" id="rangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="far fa-calendar text-primary"></i>
                                <span id="rangeDisplayLabel">Nov 2026 – Apr 2027</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 py-2" id="rangeDropdownMenu">
                                <!-- Populated dynamically by JS -->
                            </ul>
                        </div>

                        <button class="cal-nav-btn" onclick="navStep(6)" title="Next 6 Months">
                            <i class="fas fa-chevron-right"></i>
                        </button>

                        <button class="cal-today-pill" onclick="goToTodayWindow()">
                            Today
                        </button>
                    </div>
                </div>

                <!-- 6-Month Cards Grid (3x2) -->
                <div class="row g-4" id="sixMonthGrid">
                    <!-- Populated dynamically by renderSixMonths() -->
                </div>

                <!-- Legend Bar -->
                <div class="cal-legend-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-3 small fw-bold text-muted">
                        <span class="d-flex align-items-center gap-2">
                            <span class="d-inline-block rounded-2" style="width: 14px; height: 14px; background: #4f46e5;"></span>
                            Today
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="d-inline-block rounded-2" style="width: 14px; height: 14px; background: #fee2e2; border: 1px solid #f87171;"></span>
                            National Holiday
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="d-inline-block rounded-2" style="width: 14px; height: 14px; background: #fef3c7; border: 1px solid #fbbf24;"></span>
                            Regional Holiday
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="d-inline-block rounded-2" style="width: 14px; height: 14px; background: #d1fae5; border: 1px solid #34d399;"></span>
                            Company Holiday
                        </span>
                    </div>
                    <div class="small text-muted fw-semibold">
                        <i class="fas fa-mouse-pointer text-primary me-1"></i> Click any date to add a holiday &middot; Click holiday to edit
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Holiday Modal -->
    <div class="modal fade" id="addHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="POST">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">
                            <i class="far fa-calendar-plus text-primary me-2"></i> Add New Holiday
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">Holiday Name *</label>
                            <input type="text" class="form-control rounded-3" id="name" name="name" required placeholder="e.g. Diwali, Independence Day">
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label fw-semibold">Date *</label>
                            <input type="date" class="form-control rounded-3" id="date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label fw-semibold">Holiday Type *</label>
                            <select class="form-select rounded-3" id="type" name="type" required>
                                <option value="national">National Holiday</option>
                                <option value="regional">Regional Holiday</option>
                                <option value="company" selected>Company Holiday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label fw-semibold">Description (Optional)</label>
                            <textarea class="form-control rounded-3" id="description" name="description" rows="3" placeholder="Additional notes or branch applicability..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_holiday" class="btn btn-primary rounded-pill px-4">Save Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Holiday Modal -->
    <div class="modal fade" id="editHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="POST">
                    <input type="hidden" id="edit_holiday_id" name="holiday_id">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-edit text-primary me-2"></i> Edit Holiday
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label fw-semibold">Holiday Name *</label>
                            <input type="text" class="form-control rounded-3" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_date" class="form-label fw-semibold">Date *</label>
                            <input type="date" class="form-control rounded-3" id="edit_date" name="date" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_type" class="form-label fw-semibold">Holiday Type *</label>
                            <select class="form-select rounded-3" id="edit_type" name="type" required>
                                <option value="national">National Holiday</option>
                                <option value="regional">Regional Holiday</option>
                                <option value="company">Company Holiday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label fw-semibold">Description (Optional)</label>
                            <textarea class="form-control rounded-3" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_holiday" class="btn btn-primary rounded-pill px-4">Update Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteHolidayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <form method="POST">
                    <input type="hidden" id="delete_holiday_id" name="holiday_id">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger">
                            <i class="fas fa-trash-alt me-2"></i> Delete Holiday
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="mb-1">Are you sure you want to delete <strong id="delete_holiday_name"></strong>?</p>
                        <p class="text-muted small">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_holiday" class="btn btn-danger rounded-pill px-4">Yes, Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Server Data
        const allHolidays = <?php echo json_encode($holidays); ?>;
        const holidaysByDate = {};
        allHolidays.forEach(h => {
            if (!holidaysByDate[h.date]) {
                holidaysByDate[h.date] = [];
            }
            holidaysByDate[h.date].push(h);
        });

        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const monthShortNames = [
            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
        ];

        // Real Today
        const realToday = new Date();
        const realTodayStr = formatDate(realToday.getFullYear(), realToday.getMonth(), realToday.getDate());

        // Six-Month Window Starting Date
        let startWindowYear = realToday.getFullYear();
        let startWindowMonth = realToday.getMonth();

        $(document).ready(function() {
            // Flatpickr initialization
            flatpickr("#date", { dateFormat: "Y-m-d" });
            flatpickr("#edit_date", { dateFormat: "Y-m-d" });
            $('#date').val(realTodayStr);

            // Populate range dropdown
            buildRangeDropdown();

            // Initial Render of Calendar
            renderSixMonths();
        });

        function formatDate(y, m, d) {
            const mm = String(m + 1).padStart(2, '0');
            const dd = String(d).padStart(2, '0');
            return `${y}-${mm}-${dd}`;
        }

        // View Mode: 'both' (default), 'list', 'calendar'
        function setViewMode(mode) {
            $('.cal-toggle-pill').removeClass('active');
            if (mode === 'both') {
                $('#btnToggleBoth').addClass('active');
                $('#sectionListView').fadeIn(150);
                $('#sectionCalendarView').fadeIn(150);
            } else if (mode === 'list') {
                $('#btnToggleList').addClass('active');
                $('#sectionListView').fadeIn(150);
                $('#sectionCalendarView').hide();
            } else if (mode === 'calendar') {
                $('#btnToggleCalendar').addClass('active');
                $('#sectionListView').hide();
                $('#sectionCalendarView').fadeIn(150);
            }
        }

        // Navigation by step (+6 or -6 months)
        function navStep(monthsDelta) {
            let totalMonths = startWindowYear * 12 + startWindowMonth + monthsDelta;
            startWindowYear = Math.floor(totalMonths / 12);
            startWindowMonth = totalMonths % 12;
            renderSixMonths();
            buildRangeDropdown();
        }

        function goToTodayWindow() {
            startWindowYear = realToday.getFullYear();
            startWindowMonth = realToday.getMonth();
            renderSixMonths();
            buildRangeDropdown();
        }

        function setWindowRange(year, month) {
            startWindowYear = year;
            startWindowMonth = month;
            renderSixMonths();
            buildRangeDropdown();
        }

        function buildRangeDropdown() {
            const menu = document.getElementById('rangeDropdownMenu');
            if (!menu) return;
            menu.innerHTML = '';

            const baseYear = realToday.getFullYear();
            const ranges = [
                { y: baseYear - 1, m: 4, label: `May ${baseYear - 1} – Oct ${baseYear - 1}` },
                { y: baseYear - 1, m: 10, label: `Nov ${baseYear - 1} – Apr ${baseYear}` },
                { y: baseYear, m: 4, label: `May ${baseYear} – Oct ${baseYear}` },
                { y: baseYear, m: 10, label: `Nov ${baseYear} – Apr ${baseYear + 1}` },
                { y: baseYear + 1, m: 4, label: `May ${baseYear + 1} – Oct ${baseYear + 1}` },
                { y: baseYear + 1, m: 10, label: `Nov ${baseYear + 1} – Apr ${baseYear + 2}` }
            ];

            ranges.forEach(r => {
                const li = document.createElement('li');
                const isCurrent = (r.y === startWindowYear && r.m === startWindowMonth);
                li.innerHTML = `<a class="dropdown-item ${isCurrent ? 'active fw-bold' : ''}" href="javascript:void(0)" onclick="setWindowRange(${r.y}, ${r.m})">${r.label}</a>`;
                menu.appendChild(li);
            });
        }

        // Render the 6 Month Cards Grid
        function renderSixMonths() {
            const container = document.getElementById('sixMonthGrid');
            if (!container) return;
            container.innerHTML = '';

            const endMonthDate = new Date(startWindowYear, startWindowMonth + 5, 1);
            const rangeStr = `${monthShortNames[startWindowMonth]} ${startWindowYear} – ${monthShortNames[endMonthDate.getMonth()]} ${endMonthDate.getFullYear()}`;
            $('#rangeDisplayLabel').text(rangeStr);

            for (let i = 0; i < 6; i++) {
                const curDate = new Date(startWindowYear, startWindowMonth + i, 1);
                const curY = curDate.getFullYear();
                const curM = curDate.getMonth();

                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-xl-4';

                const card = document.createElement('div');
                card.className = 'month-card-wrapper';

                // Month Card Header (Icon + Month Year)
                const header = document.createElement('div');
                header.className = 'month-card-header';
                header.innerHTML = `
                    <i class="far fa-calendar-alt"></i>
                    <h5 class="month-card-title">${monthNames[curM]} ${curY}</h5>
                `;
                card.appendChild(header);

                // Weekday Row (Sun, Mon, Tue, Wed, Thu, Fri, Sat)
                const weekdayRow = document.createElement('div');
                weekdayRow.className = 'cal-7col-grid mb-2';
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(wName => {
                    const wCell = document.createElement('div');
                    wCell.className = 'cal-7col-weekday';
                    wCell.textContent = wName;
                    weekdayRow.appendChild(wCell);
                });
                card.appendChild(weekdayRow);

                // Days Grid
                const daysGrid = document.createElement('div');
                daysGrid.className = 'cal-7col-grid';

                const firstDayOfWeek = new Date(curY, curM, 1).getDay(); // 0 = Sun ... 6 = Sat
                const daysInMonth = new Date(curY, curM + 1, 0).getDate();

                // 1. Leading empty cells
                for (let e = 0; e < firstDayOfWeek; e++) {
                    const emptyCell = document.createElement('div');
                    emptyCell.className = 'cal-tile empty';
                    daysGrid.appendChild(emptyCell);
                }

                // 2. Days of this month
                for (let d = 1; d <= daysInMonth; d++) {
                    const dateStr = formatDate(curY, curM, d);
                    const dayOfWeek = new Date(curY, curM, d).getDay();
                    const isSunday = (dayOfWeek === 0);
                    const isSaturday = (dayOfWeek === 6);
                    const isToday = (dateStr === realTodayStr);

                    const tile = document.createElement('div');
                    let tileClasses = ['cal-tile'];

                    // Base tile styling (uniform for all days including Sat & Sun)
                    tileClasses.push('col-weekday');

                    // Check for holidays
                    const hList = holidaysByDate[dateStr] || [];
                    let holidayType = null;
                    if (hList.length > 0) {
                        let t = (hList[0].type || 'company').toLowerCase();
                        if (t === 'gazetted') t = 'regional';
                        holidayType = t;
                        tileClasses.push(`holiday-${t}`);

                        const hNames = hList.map(h => escapeHtml(h.name)).join(', ');
                        tile.setAttribute('data-bs-toggle', 'tooltip');
                        tile.setAttribute('title', `${hNames} (${t.toUpperCase()})`);
                    }

                    // Active Today styling
                    if (isToday) {
                        tileClasses.push('is-today-active');
                    }

                    tile.className = tileClasses.join(' ');
                    tile.innerHTML = `<span>${d}</span>`;

                    // Dot indicator
                    if (hList.length > 0) {
                        const dot = document.createElement('div');
                        dot.className = `cal-dot ${holidayType}`;
                        tile.appendChild(dot);
                    } else if (isToday) {
                        const dot = document.createElement('div');
                        dot.className = 'cal-dot today';
                        tile.appendChild(dot);
                    }

                    tile.onclick = function() {
                        if (hList.length > 0) {
                            editHoliday(hList[0]);
                        } else {
                            openAddHolidayModal(dateStr);
                        }
                    };

                    daysGrid.appendChild(tile);
                }

                card.appendChild(daysGrid);
                col.appendChild(card);
                container.appendChild(col);
            }

            $('[data-bs-toggle="tooltip"]').tooltip();
        }

        // List View Filtering
        function filterList(type, btn) {
            $('.filter-btn-pill').removeClass('active');
            $(btn).addClass('active');

            if (type === 'all') {
                $('.holiday-list-item').show();
                $('.holiday-month-group').show();
            } else {
                $('.holiday-list-item').hide();
                $(`.holiday-list-item[data-type="${type}"]`).show();

                $('.holiday-month-group').each(function() {
                    const visibleItems = $(this).find(`.holiday-list-item[data-type="${type}"]`).length;
                    if (visibleItems === 0) $(this).hide();
                    else $(this).show();
                });
            }
        }

        // Modal Action Helpers
        function openAddHolidayModal(prefillDate) {
            if (prefillDate) {
                $('#date').val(prefillDate);
            } else {
                $('#date').val(realTodayStr);
            }
            $('#name').val('');
            $('#description').val('');
            $('#type').val('company');
            $('#addHolidayModal').modal('show');
        }

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

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[m];
            });
        }

        // Modal auto-focus
        $('#addHolidayModal').on('shown.bs.modal', function () {
            $('#name').focus();
        });
        $('#editHolidayModal').on('shown.bs.modal', function () {
            $('#edit_name').focus();
        });
    </script>
</body>
</html>
