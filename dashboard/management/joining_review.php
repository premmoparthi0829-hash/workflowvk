<?php
/**
 * VK Logistics Hub - HR Employee Joining Review & Approval Portal
 * Location: dashboard/management/joining_review.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/joining_i18n.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'management') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_name = $_SESSION['full_name'];
$user_id = $_SESSION['user_id'];
$db = getDB();

$app_id = intval($_GET['id'] ?? 0);
if (!$app_id) {
    header("Location: joining_applications.php");
    exit;
}

// Fetch Application
$stmt = $db->prepare("SELECT a.*, d.name as department_name 
                      FROM employee_joining_applications a 
                      LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                      LEFT JOIN departments d ON p.department_id = d.id 
                      WHERE a.id = ?");
$stmt->execute([$app_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Application not found.");
}

$flash_msg = '';
$flash_type = 'success';

// Handle HR Action Form Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hr_action'])) {
    $action = $_POST['hr_action'];
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($action === 'approve') {
        $assigned_emp_id = trim($_POST['assigned_emp_id'] ?? '');
        $confirmed_doj = trim($_POST['confirmed_doj'] ?? date('Y-m-d'));
        $verified_by_name = trim($_POST['verified_by_name'] ?? $user_name);
        $create_user_account = isset($_POST['create_user_account']) ? 1 : 0;
        $hr_signature_data = $_POST['hr_signature_data'] ?? '';
        
        if (empty($assigned_emp_id)) {
            $flash_msg = "Please assign an Employee ID for confirmation.";
            $flash_type = 'danger';
        } else {
            try {
                $db->beginTransaction();
                
                // Save HR digital signature if drawn
                $hr_sig_path = null;
                if (!empty($hr_signature_data) && strpos($hr_signature_data, 'data:image/png;base64,') === 0) {
                    $data_img = str_replace('data:image/png;base64,', '', $hr_signature_data);
                    $data_img = str_replace(' ', '+', $data_img);
                    $decoded = base64_decode($data_img);
                    if ($decoded !== false) {
                        $hr_sig_filename = 'sig_hr_' . $app_id . '_' . time() . '.png';
                        $dest = __DIR__ . '/../../uploads/signatures/' . $hr_sig_filename;
                        if (file_put_contents($dest, $decoded)) {
                            $hr_sig_path = 'uploads/signatures/' . $hr_sig_filename;
                        }
                    }
                }
                
                // Upsert approval record
                $stmt = $db->prepare("INSERT INTO employee_joining_approvals (
                    application_id, verified_by_name, verified_by_user_id, verified_at,
                    employee_id_assigned, joining_date_confirmed, approved_by_user_id,
                    hr_signature_path, internal_remarks
                ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    verified_by_name = VALUES(verified_by_name),
                    verified_by_user_id = VALUES(verified_by_user_id),
                    verified_at = NOW(),
                    employee_id_assigned = VALUES(employee_id_assigned),
                    joining_date_confirmed = VALUES(joining_date_confirmed),
                    approved_by_user_id = VALUES(approved_by_user_id),
                    hr_signature_path = COALESCE(VALUES(hr_signature_path), hr_signature_path),
                    internal_remarks = VALUES(internal_remarks)");
                $stmt->execute([
                    $app_id, $verified_by_name, $user_id, $assigned_emp_id, $confirmed_doj,
                    $user_id, $hr_sig_path, $remarks
                ]);
                
                // Update personal details with confirmed Employee ID & Joining Date
                $stmt = $db->prepare("UPDATE employee_personal_details SET 
                    employee_id = ?, 
                    joining_date = ? 
                    WHERE application_id = ?");
                $stmt->execute([$assigned_emp_id, $confirmed_doj, $app_id]);
                
                // Update application status
                $old_status = $application['status'];
                $stmt = $db->prepare("UPDATE employee_joining_applications SET 
                    status = 'approved', 
                    approved_at = NOW(), 
                    updated_at = NOW() 
                    WHERE id = ?");
                $stmt->execute([$app_id]);
                
                // Record status history
                $stmt = $db->prepare("INSERT INTO employee_joining_status_history (
                    application_id, from_status, to_status, changed_by_user_id, changed_by_role, remarks
                ) VALUES (?, ?, 'approved', ?, 'management', ?)");
                $stmt->execute([$app_id, $old_status, $user_id, "Approved by HR. Assigned Employee ID: {$assigned_emp_id}. Remarks: {$remarks}"]);
                
                // If requested, provision user account in master users table
                if ($create_user_account) {
                    $stmt = $db->prepare("SELECT * FROM employee_personal_details WHERE application_id = ?");
                    $stmt->execute([$app_id]);
                    $p = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($p && !empty($p['email'])) {
                        // Check if user already exists
                        $u_chk = $db->prepare("SELECT id FROM users WHERE email = ?");
                        $u_chk->execute([$p['email']]);
                        $existing_user = $u_chk->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$existing_user) {
                            $temp_pass = password_hash('VK@' . date('Y'), PASSWORD_DEFAULT);
                            $username = strtolower(explode('@', $p['email'])[0]);
                            // Ensure unique username
                            $un_chk = $db->prepare("SELECT id FROM users WHERE username = ?");
                            $un_chk->execute([$username]);
                            if ($un_chk->fetch()) {
                                $username .= '_' . rand(100, 999);
                            }
                            
                            $ins_user = $db->prepare("INSERT INTO users (
                                username, email, password, full_name, role, department_id, is_active, created_at
                            ) VALUES (?, ?, ?, ?, 'employee', ?, 1, NOW())");
                            $ins_user->execute([
                                $username, $p['email'], $temp_pass, $p['full_name'], $p['department_id']
                            ]);
                        }
                    }
                }
                
                $db->commit();
                $flash_msg = "Application #{$application['application_no']} approved successfully! Employee ID {$assigned_emp_id} confirmed.";
                $flash_type = 'success';
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error approving application [$app_id]: " . $e->getMessage());
                $flash_msg = "Unable to complete approval at this time. Please check the assigned details and try again.";
                $flash_type = 'danger';
            }
        }
    } elseif ($action === 'request_correction') {
        if (empty($remarks)) {
            $flash_msg = "Please provide specific correction instructions for the candidate.";
            $flash_type = 'warning';
        } else {
            try {
                $db->beginTransaction();
                $old_status = $application['status'];
                $db->prepare("UPDATE employee_joining_applications SET status = 'correction_required', updated_at = NOW() WHERE id = ?")->execute([$app_id]);
                $db->prepare("INSERT INTO employee_joining_status_history (application_id, from_status, to_status, changed_by_user_id, changed_by_role, remarks) VALUES (?, ?, 'correction_required', ?, 'management', ?)")
                   ->execute([$app_id, $old_status, $user_id, "Correction requested: " . $remarks]);
                $db->commit();
                $flash_msg = "Correction request recorded. Status updated to 'Correction Required'.";
                $flash_type = 'warning';
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error requesting correction on application [$app_id]: " . $e->getMessage());
                $flash_msg = "Unable to submit correction request at this time. Please try again.";
                $flash_type = 'danger';
            }
        }
    } elseif ($action === 'reject') {
        try {
            $db->beginTransaction();
            $old_status = $application['status'];
            $db->prepare("UPDATE employee_joining_applications SET status = 'rejected', updated_at = NOW() WHERE id = ?")->execute([$app_id]);
            $db->prepare("INSERT INTO employee_joining_status_history (application_id, from_status, to_status, changed_by_user_id, changed_by_role, remarks) VALUES (?, ?, 'rejected', ?, 'management', ?)")
               ->execute([$app_id, $old_status, $user_id, "Application rejected by HR. Reason: " . $remarks]);
            $db->commit();
            $flash_msg = "Application status set to 'Rejected'.";
            $flash_type = 'info';
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error rejecting application [$app_id]: " . $e->getMessage());
            $flash_msg = "Unable to reject application at this time. Please try again.";
            $flash_type = 'danger';
        }
    }
}

// Reload all data for display
$stmt = $db->prepare("SELECT a.*, d.name as department_name 
                      FROM employee_joining_applications a 
                      LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                      LEFT JOIN departments d ON p.department_id = d.id 
                      WHERE a.id = ?");
$stmt->execute([$app_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    header("Location: joining_applications.php");
    exit;
}

// Safe attribute getter helper function
if (!function_exists('getVal')) {
    function getVal($array, $key, $default = '-') {
        return (!empty($array) && is_array($array) && isset($array[$key]) && $array[$key] !== '') ? $array[$key] : $default;
    }
}

$personal = ($db->query("SELECT * FROM employee_personal_details WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$present_address = ($db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'present'")->fetch(PDO::FETCH_ASSOC)) ?: [];
$permanent_address = ($db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'permanent'")->fetch(PDO::FETCH_ASSOC)) ?: [];
$identity = ($db->query("SELECT * FROM employee_identity_documents WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$emergency = ($db->query("SELECT * FROM employee_emergency_contacts WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$ref1 = ($db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 1")->fetch(PDO::FETCH_ASSOC)) ?: [];
$ref2 = ($db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 2")->fetch(PDO::FETCH_ASSOC)) ?: [];
$prev_emp = ($db->query("SELECT * FROM employee_previous_employment WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$declaration = ($db->query("SELECT * FROM employee_declarations WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$signature = ($db->query("SELECT * FROM employee_signatures WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$uploaded_docs = ($db->query("SELECT * FROM employee_uploaded_documents WHERE application_id = {$app_id}")->fetchAll(PDO::FETCH_ASSOC)) ?: [];
$approval = ($db->query("SELECT * FROM employee_joining_approvals WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC)) ?: [];
$history = ($db->query("SELECT h.*, u.full_name as user_name FROM employee_joining_status_history h LEFT JOIN users u ON h.changed_by_user_id = u.id WHERE h.application_id = {$app_id} ORDER BY h.id DESC")->fetchAll(PDO::FETCH_ASSOC)) ?: [];

// Suggested Employee ID if not yet assigned
$suggested_emp_id = getVal($approval, 'employee_id_assigned', getVal($personal, 'employee_id', ''));
if (empty($suggested_emp_id)) {
    $next_seq = sprintf('%04d', $app_id + 100);
    $suggested_emp_id = 'VK-EMP-' . $next_seq;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Application #<?php echo htmlspecialchars($application['application_no']); ?> | VK Logistics Hub</title>
    <!-- Fonts & CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .review-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .review-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
        }

        .doc-thumbnail {
            width: 100%;
            height: 140px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            text-decoration: none;
            color: #475569;
            transition: all 0.2s;
        }

        .doc-thumbnail:hover {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #2563eb;
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 24px;
            bottom: -24px;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-badge {
            position: absolute;
            left: 0;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 2px #93c5fd;
        }

        .hr-signature-pad {
            border: 2px dashed #94a3b8;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
            width: 100%;
            height: 140px;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-light bg-primary sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand text-white fw-bold d-flex align-items-center gap-2" href="joining_applications.php">
                <i class="fas fa-arrow-left text-white me-2"></i> Onboarding Review Portal
            </a>
            <div class="d-flex align-items-center text-white">
                <a href="../../joining_form.php?token=<?php echo urlencode($application['token']); ?>" class="btn btn-outline-light btn-sm rounded-pill me-2">
                    <i class="fas fa-edit me-1"></i> Edit Form
                </a>
                <a href="joining_pdf.php?id=<?php echo $app_id; ?>&token=<?php echo urlencode($application['token']); ?>" target="_blank" class="btn btn-outline-light btn-sm rounded-pill me-2">
                    <i class="fas fa-file-pdf me-1"></i> Print / PDF Document
                </a>
                <span class="small"><i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($user_name); ?></span>
            </div>
        </div>
    </nav>

    <div class="container py-4">

        <!-- Top Header Card -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 p-4 bg-white rounded-4 border shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <?php
                $has_real_photo = !empty($personal['photo_path']) && file_exists(__DIR__ . '/../../' . $personal['photo_path']);
                ?>
                <?php if ($has_real_photo): ?>
                    <img src="../../<?php echo htmlspecialchars($personal['photo_path']); ?>" alt="Candidate" class="rounded-3 border" style="width: 72px; height: 85px; object-fit: cover;">
                <?php else: ?>
                    <div class="rounded-3 border d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 72px; height: 85px; background: #e0f2fe; color: #0284c7; font-size: 1.6rem;">
                        <?php
                        $fullNameStr = getVal($personal, 'full_name', 'VK');
                        $words = preg_split('/\s+/', trim($fullNameStr));
                        $in = '';
                        foreach ($words as $w) {
                            $c = preg_replace('/[^A-Za-z]/', '', $w);
                            if (!empty($c)) $in .= strtoupper(substr($c, 0, 1));
                            if (strlen($in) >= 2) break;
                        }
                        echo !empty($in) ? $in : 'VK';
                        ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars(getVal($personal, 'full_name', 'Applicant Details')); ?></h4>
                        <span class="badge bg-secondary font-monospace"><?php echo htmlspecialchars($application['application_no'] ?? ''); ?></span>
                    </div>
                    <div class="text-muted small">
                        <?php echo htmlspecialchars(getVal($personal, 'designation', 'Role Unassigned')); ?> &bull; 
                        <?php echo htmlspecialchars(!empty($application['department_name']) ? $application['department_name'] : 'Logistics'); ?> &bull; 
                        Target Joining: <strong><?php echo !empty($personal['joining_date']) ? date('d-M-Y', strtotime($personal['joining_date'])) : 'Pending'; ?></strong>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-3 mt-md-0">
                <?php
                $st = $application['status'];
                if ($st === 'approved') {
                    echo '<span class="badge bg-success fs-6 p-2 px-3 rounded-pill"><i class="fas fa-check-circle me-1"></i> APPROVED</span>';
                } elseif ($st === 'pending_hr_verification') {
                    echo '<span class="badge bg-warning text-dark fs-6 p-2 px-3 rounded-pill"><i class="fas fa-clock me-1"></i> PENDING VERIFICATION</span>';
                } elseif ($st === 'correction_required') {
                    echo '<span class="badge bg-danger fs-6 p-2 px-3 rounded-pill"><i class="fas fa-exclamation-triangle me-1"></i> CORRECTION REQUIRED</span>';
                } else {
                    echo '<span class="badge bg-info text-dark fs-6 p-2 px-3 rounded-pill"><i class="fas fa-pen me-1"></i> ' . strtoupper($st) . '</span>';
                }
                ?>
                <a href="#actionSection" class="btn btn-primary rounded-pill px-3 fw-bold">
                    <i class="fas fa-tasks me-1"></i> HR Actions
                </a>
            </div>
        </div>

        <?php if (!empty($flash_msg)): ?>
            <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show shadow-sm" role="alert">
                <?php echo $flash_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Submitted Application Dossier -->
            <div class="col-lg-8">

                <!-- Section 1: Personal Information -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-user-circle me-2 text-primary"></i> 1. Personal & Employment Information</span>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="info-label">Employee Type</div>
                                <div class="info-value"><?php echo (($application['employee_type'] ?? '') === 'new_joiner') ? 'New Joiner (కొత్త ఉద్యోగి)' : 'Existing Employee'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Assigned / Existing ID</div>
                                <div class="info-value font-monospace text-primary"><?php echo htmlspecialchars(getVal($approval, 'employee_id_assigned', getVal($personal, 'employee_id', 'Pending HR Assignment'))); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?php echo !empty($personal['dob']) ? date('d-M-Y', strtotime($personal['dob'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Father's Name</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($personal, 'father_name', '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Gender</div>
                                <div class="info-value text-capitalize"><?php echo htmlspecialchars(getVal($personal, 'gender', '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Blood Group</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($personal, 'blood_group', 'Not specified')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Primary Mobile</div>
                                <div class="info-value"><?php if (!empty($personal['mobile'])): ?><a href="tel:<?php echo htmlspecialchars($personal['mobile']); ?>"><?php echo htmlspecialchars($personal['mobile']); ?></a><?php else: ?>-<?php endif; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Alternate Mobile</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($personal, 'alternate_mobile', '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php if (!empty($personal['email'])): ?><a href="mailto:<?php echo htmlspecialchars($personal['email']); ?>"><?php echo htmlspecialchars($personal['email']); ?></a><?php else: ?>-<?php endif; ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Previous Work Experience</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($personal, 'previous_work_experience', 'None reported')); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Addresses -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-map-marked-alt me-2 text-primary"></i> 2 & 3. Address Details (Present & Permanent)</span>
                    </div>
                    <div class="p-4">
                        <div class="row g-4">
                            <div class="col-md-6 border-end-md">
                                <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Present Address (ప్రస్తుత చిరునామా)</h6>
                                <p class="mb-1 fw-bold"><?php echo htmlspecialchars($present_address['house_flat_no'] ?? ''); ?>, <?php echo htmlspecialchars($present_address['street_area'] ?? ''); ?></p>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($present_address['village_town'] ?? ''); ?>, <?php echo htmlspecialchars($present_address['mandal'] ?? ''); ?></p>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($present_address['district'] ?? ''); ?>, <?php echo htmlspecialchars($present_address['state'] ?? ''); ?> - <strong><?php echo htmlspecialchars($present_address['pincode'] ?? ''); ?></strong></p>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-dark mb-0">Permanent Address (శాశ్వత చిరునామా)</h6>
                                    <?php if (!empty($permanent_address['is_same_as_present'])): ?>
                                        <span class="badge bg-success-light text-success border">Same as Present</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 fw-bold"><?php echo htmlspecialchars($permanent_address['house_flat_no'] ?? ''); ?>, <?php echo htmlspecialchars($permanent_address['street_area'] ?? ''); ?></p>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($permanent_address['village_town'] ?? ''); ?>, <?php echo htmlspecialchars($permanent_address['mandal'] ?? ''); ?></p>
                                <p class="mb-0 text-muted"><?php echo htmlspecialchars($permanent_address['district'] ?? ''); ?>, <?php echo htmlspecialchars($permanent_address['state'] ?? ''); ?> - <strong><?php echo htmlspecialchars($permanent_address['pincode'] ?? ''); ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Identity & Uploaded Documents -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-id-card me-2 text-primary"></i> 4. Identity Documents & Uploads</span>
                    </div>
                    <div class="p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="info-label">Aadhaar Number (Masked)</div>
                                <div class="info-value font-monospace fs-5 text-dark"><?php echo htmlspecialchars($identity['aadhaar_masked'] ?? 'Not provided'); ?></div>
                                <small class="text-muted"><i class="fas fa-lock me-1"></i> Strictly protected under UIDAI statutory standards</small>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Alternate ID Proof</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars(getVal($identity, 'other_id_type', 'None')); ?>: 
                                    <span class="font-monospace fw-bold"><?php echo htmlspecialchars(getVal($identity, 'other_id_number', '-')); ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Documents Submitted Checklist</div>
                                <div class="info-value text-secondary"><?php echo htmlspecialchars(getVal($identity, 'documents_submitted', 'None checked')); ?></div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="fas fa-folder-open me-2 text-primary"></i> Uploaded Documents Verification</h6>
                        <?php if (empty($uploaded_docs)): ?>
                            <div class="alert alert-light border text-center py-3 text-muted">
                                <i class="fas fa-exclamation-triangle me-1"></i> No attached identity documents found.
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($uploaded_docs as $doc): 
                                    $is_pdf = strpos($doc['mime_type'], 'pdf') !== false || substr($doc['stored_file_path'], -4) === '.pdf';
                                ?>
                                    <div class="col-sm-6 col-md-4">
                                        <a href="../../<?php echo htmlspecialchars($doc['stored_file_path']); ?>" target="_blank" class="doc-thumbnail">
                                            <i class="<?php echo $is_pdf ? 'fas fa-file-pdf text-danger' : 'fas fa-file-image text-primary'; ?> fs-2 mb-2"></i>
                                            <div class="fw-bold small text-center px-2 text-truncate w-100"><?php echo htmlspecialchars($doc['document_type']); ?></div>
                                            <small class="text-muted" style="font-size: 0.72rem;"><?php echo round($doc['file_size_bytes'] / 1024, 1); ?> KB &bull; View</small>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 4: Emergency Contacts & References -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-user-friends me-2 text-primary"></i> 5 & 6. Emergency Contacts & References</span>
                    </div>
                    <div class="p-4">
                        <div class="p-3 bg-light rounded-3 border mb-4">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-ambulance me-2 text-danger"></i> Emergency Contact (అత్యవసర సంప్రదింపు)</h6>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <span class="info-label">Name:</span> <strong><?php echo htmlspecialchars($emergency['full_name'] ?? '-'); ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="info-label">Relationship:</span> <strong><?php echo htmlspecialchars($emergency['relationship'] ?? '-'); ?></strong>
                                </div>
                                <div class="col-md-4">
                                    <span class="info-label">Phone:</span> <strong><?php echo htmlspecialchars($emergency['mobile'] ?? '-'); ?></strong>
                                </div>
                                <div class="col-12 mt-2">
                                    <span class="info-label">Address:</span> <?php echo htmlspecialchars($emergency['address'] ?? '-'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 border-end-md">
                                <h6 class="fw-bold text-primary mb-2">Reference 1 (రిఫరెన్స్ 1)</h6>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($ref1['full_name'] ?? '-'); ?></strong> (<?php echo htmlspecialchars($ref1['relationship_how_known'] ?? '-'); ?>)</p>
                                <p class="mb-1 text-muted small"><i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($ref1['mobile'] ?? '-'); ?></p>
                                <p class="mb-0 text-muted small"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($ref1['address'] ?? '-'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-primary mb-2">Reference 2 (రిఫరెన్స్ 2)</h6>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($ref2['full_name'] ?? '-'); ?></strong> (<?php echo htmlspecialchars($ref2['relationship_how_known'] ?? '-'); ?>)</p>
                                <p class="mb-1 text-muted small"><i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($ref2['mobile'] ?? '-'); ?></p>
                                <p class="mb-0 text-muted small"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($ref2['address'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Previous Employment & Policies Acknowledgment -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-clipboard-check me-2 text-primary"></i> 7, 8 & 9. Work History & Policy Declarations</span>
                    </div>
                    <div class="p-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Previous Employment Details</h6>
                        <div class="row g-2 mb-4">
                            <div class="col-md-4">
                                <div class="info-label">Employer</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($prev_emp, 'employer_name', 'None reported')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Designation</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($prev_emp, 'designation', '-')); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-label">Reason for Leaving</div>
                                <div class="info-value"><?php echo htmlspecialchars(getVal($prev_emp, 'reason_for_leaving', '-')); ?></div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Declarations & Mandatory Acknowledgments</h6>
                        <ul class="list-group list-group-flush mb-4">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong>Employment Terms & Conditions:</strong><br>
                                    <small class="text-muted">Probation (3 months), Leave policy, 30 days notice period, company property & conduct.</small>
                                </div>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i> Acknowledged</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong>Pickup Associate Leave & Incentive Policy:</strong><br>
                                    <small class="text-muted">12 festival holidays, daily pickup quotas (3/4 target), bonus tiers table, shift logs.</small>
                                </div>
                                <?php if (!empty($declaration['pickup_policy_acknowledged'])): ?>
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i> Acknowledged</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Applicable / Optional</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong>Employee Declaration & Data Authorization:</strong><br>
                                    <small class="text-muted">Information accuracy, compliance with statutory norms, data use consent.</small>
                                </div>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i> Confirmed by Candidate</span>
                            </li>
                        </ul>

                        <!-- Employee Digital Signature & Thumb -->
                        <div class="row g-4 pt-2 border-top">
                            <div class="col-md-6 text-center border-end-md">
                                <div class="info-label mb-2">Employee Digital Signature (డిజిటల్ సంతకం)</div>
                                <?php if (!empty($signature['signature_image_path'])): ?>
                                    <div class="border rounded p-2 bg-light d-inline-block">
                                        <img src="../../<?php echo htmlspecialchars($signature['signature_image_path']); ?>" alt="Candidate Signature" style="max-height: 100px;">
                                    </div>
                                    <div class="text-muted small mt-1">Signed IP: <?php echo htmlspecialchars(getVal($signature, 'signed_ip', '')); ?> &bull; <?php echo !empty($signature['signed_at']) ? date('d-M-Y H:i', strtotime($signature['signed_at'])) : ''; ?></div>
                                <?php else: ?>
                                    <div class="alert alert-warning small mb-0">No signature recorded.</div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 text-center">
                                <div class="info-label mb-2">Left Thumb Impression (ఎడమ బొటనవేలు ముద్ర)</div>
                                <?php if (!empty($signature['thumb_impression_path'])): ?>
                                    <div class="border rounded p-2 bg-light d-inline-block">
                                        <img src="../../<?php echo htmlspecialchars($signature['thumb_impression_path']); ?>" alt="Thumb Impression" style="max-height: 100px;">
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small py-4">Optional impression not uploaded.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: HR Action Panel & Audit Trail -->
            <div class="col-lg-4" id="actionSection">

                <!-- HR Verification & Approval Box -->
                <div class="review-card border-primary">
                    <div class="review-header bg-primary text-white">
                        <span><i class="fas fa-stamp me-2"></i> Section 11: HR / Office Use Only</span>
                        <span class="badge bg-white text-primary">HR Verification</span>
                    </div>
                    <div class="p-4">
                        <form method="POST" id="hrActionForm">
                            <input type="hidden" name="hr_action" id="hrActionType" value="approve">
                            <input type="hidden" name="hr_signature_data" id="hrSignatureData" value="">

                            <!-- Employee ID Assignment -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Official Assigned Employee ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace fw-bold" name="assigned_emp_id" required 
                                    value="<?php echo htmlspecialchars($suggested_emp_id); ?>" placeholder="e.g. VK-EMP-1050">
                                <div class="form-text small">Final employee code to be stored in the master HR records.</div>
                            </div>

                            <!-- Confirmed Joining Date -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Confirmed Joining Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="confirmed_doj" required 
                                    value="<?php echo htmlspecialchars(getVal($approval, 'joining_date_confirmed', getVal($personal, 'joining_date', date('Y-m-d')))); ?>">
                            </div>

                            <!-- Verified By Name -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Documents Verified By</label>
                                <input type="text" class="form-control" name="verified_by_name" 
                                    value="<?php echo htmlspecialchars(getVal($approval, 'verified_by_name', $user_name)); ?>">
                            </div>

                            <!-- Verification Checklist -->
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="chkIdentity" checked>
                                    <label class="form-check-label small" for="chkIdentity">Identity Documents Verified</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="chkAddress" checked>
                                    <label class="form-check-label small" for="chkAddress">Present / Permanent Address Verified</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkBank" checked>
                                    <label class="form-check-label small" for="chkBank">Bank Passbook & Details Checked</label>
                                </div>
                            </div>

                            <!-- User Account Provisioning -->
                            <div class="form-check form-switch mb-3 p-3 bg-primary bg-opacity-10 border rounded-3">
                                <input class="form-check-input ms-0 me-2" type="checkbox" name="create_user_account" id="createUserSwitch" value="1" checked>
                                <label class="form-check-label fw-bold small text-primary" for="createUserSwitch">
                                    Auto-create VK Logistics System Login
                                </label>
                                <div class="small text-muted mt-1" style="font-size: 0.76rem;">
                                    Provisions employee login with default password <code>VK@<?php echo date('Y'); ?></code>.
                                </div>
                            </div>

                            <!-- HR Signature Canvas -->
                            <div class="mb-3">
                                <label class="form-label fw-bold small d-block">
                                    HR Officer Digital Signature
                                    <button type="button" class="btn btn-link btn-sm p-0 float-end text-danger" onclick="clearHrSig()">Clear</button>
                                </label>
                                <?php if (!empty($approval['hr_signature_path'])): ?>
                                    <div class="border rounded p-2 text-center bg-light mb-2">
                                        <img src="../../<?php echo htmlspecialchars($approval['hr_signature_path']); ?>" alt="HR Sig" style="max-height: 80px;">
                                    </div>
                                <?php endif; ?>
                                <canvas id="hrSigPad" class="hr-signature-pad"></canvas>
                            </div>

                            <!-- Internal Remarks -->
                            <div class="mb-4">
                                <label class="form-label fw-bold small">HR Verification Remarks</label>
                                <textarea class="form-control" name="remarks" id="hrRemarks" rows="2" placeholder="Internal notes, approval comments, or correction details..."><?php echo htmlspecialchars($approval['internal_remarks'] ?? ''); ?></textarea>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-success fw-bold py-2 rounded-pill shadow-sm" onclick="triggerApprove()">
                                    <i class="fas fa-check-circle me-1"></i> Approve & Issue Employee ID
                                </button>
                                <button type="button" class="btn btn-outline-warning text-dark fw-bold rounded-pill" onclick="triggerCorrection()">
                                    <i class="fas fa-exclamation-triangle me-1"></i> Request Candidate Correction
                                </button>
                                <button type="button" class="btn btn-outline-danger fw-bold rounded-pill" onclick="triggerReject()">
                                    <i class="fas fa-times-circle me-1"></i> Reject Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Status Audit Trail -->
                <div class="review-card">
                    <div class="review-header">
                        <span><i class="fas fa-history me-2 text-primary"></i> Application Status History</span>
                    </div>
                    <div class="p-4">
                        <?php if (empty($history)): ?>
                            <div class="text-muted small">No history entries recorded yet.</div>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($history as $h): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-badge"></div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.72rem;"><?php echo htmlspecialchars($h['to_status']); ?></span>
                                            <small class="text-muted" style="font-size: 0.72rem;"><?php echo date('d-M H:i', strtotime($h['created_at'])); ?></small>
                                        </div>
                                        <div class="small text-dark mt-1"><?php echo htmlspecialchars($h['remarks']); ?></div>
                                        <div class="text-muted" style="font-size: 0.72rem;">By: <?php echo htmlspecialchars($h['user_name'] ?: $h['changed_by_role']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // HR Signature Pad
        const hrCanvas = document.getElementById('hrSigPad');
        let hrCtx = null;
        let hrDrawing = false;
        let hrHasSignature = false;

        if (hrCanvas) {
            const rect = hrCanvas.getBoundingClientRect();
            hrCanvas.width = rect.width * 2;
            hrCanvas.height = rect.height * 2;
            hrCtx = hrCanvas.getContext('2d');
            hrCtx.scale(2, 2);
            hrCtx.strokeStyle = '#0f172a';
            hrCtx.lineWidth = 2;
            hrCtx.lineCap = 'round';

            function getPos(e) {
                const r = hrCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return { x: clientX - r.left, y: clientY - r.top };
            }

            hrCanvas.addEventListener('mousedown', (e) => {
                hrDrawing = true;
                const pos = getPos(e);
                hrCtx.beginPath();
                hrCtx.moveTo(pos.x, pos.y);
            });

            hrCanvas.addEventListener('mousemove', (e) => {
                if (!hrDrawing) return;
                const pos = getPos(e);
                hrCtx.lineTo(pos.x, pos.y);
                hrCtx.stroke();
                hrHasSignature = true;
            });

            hrCanvas.addEventListener('mouseup', () => { hrDrawing = false; });
            hrCanvas.addEventListener('mouseleave', () => { hrDrawing = false; });

            hrCanvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                hrDrawing = true;
                const pos = getPos(e);
                hrCtx.beginPath();
                hrCtx.moveTo(pos.x, pos.y);
            }, { passive: false });

            hrCanvas.addEventListener('touchmove', (e) => {
                if (!hrDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                hrCtx.lineTo(pos.x, pos.y);
                hrCtx.stroke();
                hrHasSignature = true;
            }, { passive: false });

            hrCanvas.addEventListener('touchend', () => { hrDrawing = false; });
        }

        function clearHrSig() {
            if (hrCanvas && hrCtx) {
                hrCtx.clearRect(0, 0, hrCanvas.width, hrCanvas.height);
                hrHasSignature = false;
            }
        }

        function captureHrSignature() {
            if (hrCanvas && hrHasSignature) {
                document.getElementById('hrSignatureData').value = hrCanvas.toDataURL('image/png');
            }
        }

        function triggerApprove() {
            captureHrSignature();
            document.getElementById('hrActionType').value = 'approve';
            if (confirm('Confirm approval for application #<?php echo $application['application_no']; ?>?')) {
                document.getElementById('hrActionForm').submit();
            }
        }

        function triggerCorrection() {
            const remarks = document.getElementById('hrRemarks').value.trim();
            if (!remarks) {
                alert('Please enter specific remarks explaining what the candidate needs to correct.');
                document.getElementById('hrRemarks').focus();
                return;
            }
            document.getElementById('hrActionType').value = 'request_correction';
            if (confirm('Request candidate corrections for this application?')) {
                document.getElementById('hrActionForm').submit();
            }
        }

        function triggerReject() {
            const remarks = document.getElementById('hrRemarks').value.trim();
            if (!remarks) {
                alert('Please enter the reason for rejection in the remarks field.');
                document.getElementById('hrRemarks').focus();
                return;
            }
            document.getElementById('hrActionType').value = 'reject';
            if (confirm('Are you sure you want to REJECT application #<?php echo $application['application_no']; ?>?')) {
                document.getElementById('hrActionForm').submit();
            }
        }
    </script>
</body>
</html>
