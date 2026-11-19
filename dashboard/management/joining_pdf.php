<?php
/**
 * VK Logistics Hub - 9-Page High-Fidelity Bilingual PDF & Print Document
 * Matches EXACTLY: VK_Logistics_Hub_Bilingual_Joining_Form_and_Associate_Policy.pdf (Pages 1 - 9)
 * Location: dashboard/management/joining_pdf.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/joining_i18n.php';

$db = getDB();

$app_id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$is_preview = isset($_POST['is_preview']) || isset($_GET['is_preview']) || isset($_GET['preview']) || empty($app_id);

$application = null;
if ($app_id > 0) {
    try {
        $stmt = $db->prepare("SELECT a.*, d.name as department_name FROM employee_joining_applications a 
                              LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                              LEFT JOIN departments d ON p.department_id = d.id 
                              WHERE a.id = ?");
        $stmt->execute([$app_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {}
}

if (!$application && !empty($token)) {
    try {
        $stmt = $db->prepare("SELECT a.*, d.name as department_name FROM employee_joining_applications a 
                              LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                              LEFT JOIN departments d ON p.department_id = d.id 
                              WHERE a.token = ?");
        $stmt->execute([$token]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($application) $app_id = intval($application['id']);
    } catch(Exception $e) {}
}

if (!$application) {
    $application = [
        'id' => 0,
        'application_no' => 'VK-JOIN-' . date('Ym') . '-DRAFT',
        'employee_type' => 'new_joiner',
        'created_at' => date('Y-m-d H:i:s'),
        'submitted_at' => date('Y-m-d H:i:s'),
        'department_name' => 'General Logistics'
    ];
    $is_preview = true;
}

// Fetch all normalized data
$personal = ($app_id ? $db->query("SELECT * FROM employee_personal_details WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$present_address = ($app_id ? $db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'present'")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$permanent_address = ($app_id ? $db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'permanent'")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$identity = ($app_id ? $db->query("SELECT * FROM employee_identity_documents WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$emergency = ($app_id ? $db->query("SELECT * FROM employee_emergency_contacts WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$ref1 = ($app_id ? $db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 1")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$ref2 = ($app_id ? $db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 2")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$prev_emp = ($app_id ? $db->query("SELECT * FROM employee_previous_employment WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$declaration = ($app_id ? $db->query("SELECT * FROM employee_declarations WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$signature = ($app_id ? $db->query("SELECT * FROM employee_signatures WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];
$approval = ($app_id ? $db->query("SELECT * FROM employee_joining_approvals WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC) : []) ?: [];

// Live Form Values Override (for real-time filling preview)
if (!empty($_POST)) {
    if (isset($_POST['full_name']) && $_POST['full_name'] !== '') $personal['full_name'] = $_POST['full_name'];
    if (isset($_POST['father_name']) && $_POST['father_name'] !== '') $personal['father_name'] = $_POST['father_name'];
    if (isset($_POST['dob']) && $_POST['dob'] !== '') $personal['dob'] = $_POST['dob'];
    if (isset($_POST['gender']) && $_POST['gender'] !== '') $personal['gender'] = $_POST['gender'];
    if (isset($_POST['mobile']) && $_POST['mobile'] !== '') $personal['mobile'] = $_POST['mobile'];
    if (isset($_POST['alternate_mobile']) && $_POST['alternate_mobile'] !== '') $personal['alternate_mobile'] = $_POST['alternate_mobile'];
    if (isset($_POST['email']) && $_POST['email'] !== '') $personal['email'] = $_POST['email'];
    if (isset($_POST['designation']) && $_POST['designation'] !== '') $personal['designation'] = $_POST['designation'];
    if (!empty($_POST['department_id'])) {
        $dep_id = intval($_POST['department_id']);
        if ($dep_id) {
            $d_stmt = $db->query("SELECT name FROM departments WHERE id = {$dep_id}");
            $d_row = $d_stmt ? $d_stmt->fetch() : null;
            if ($d_row) $application['department_name'] = $d_row['name'];
        }
    }
    if (isset($_POST['joining_date']) && $_POST['joining_date'] !== '') $personal['joining_date'] = $_POST['joining_date'];
    if (isset($_POST['nationality']) && $_POST['nationality'] !== '') $personal['nationality'] = $_POST['nationality'];
    if (isset($_POST['blood_group']) && $_POST['blood_group'] !== '') $personal['blood_group'] = $_POST['blood_group'];
    if (isset($_POST['previous_work_experience']) && $_POST['previous_work_experience'] !== '') $personal['previous_work_experience'] = $_POST['previous_work_experience'];
    if (isset($_POST['employee_type']) && $_POST['employee_type'] !== '') $application['employee_type'] = $_POST['employee_type'];
    if (isset($_POST['employee_id']) && $_POST['employee_id'] !== '') $personal['employee_id'] = $_POST['employee_id'];

    if (isset($_POST['present_house_flat_no']) && $_POST['present_house_flat_no'] !== '') $present_address['house_flat_no'] = $_POST['present_house_flat_no'];
    if (isset($_POST['present_street_area']) && $_POST['present_street_area'] !== '') $present_address['street_area'] = $_POST['present_street_area'];
    if (isset($_POST['present_village_town']) && $_POST['present_village_town'] !== '') $present_address['village_town'] = $_POST['present_village_town'];
    if (isset($_POST['present_mandal']) && $_POST['present_mandal'] !== '') $present_address['mandal'] = $_POST['present_mandal'];
    if (isset($_POST['present_district']) && $_POST['present_district'] !== '') $present_address['district'] = $_POST['present_district'];
    if (isset($_POST['present_state']) && $_POST['present_state'] !== '') $present_address['state'] = $_POST['present_state'];
    if (isset($_POST['present_pincode']) && $_POST['present_pincode'] !== '') $present_address['pincode'] = $_POST['present_pincode'];

    $same_as_present = !empty($_POST['same_as_present']);
    $present_address['is_same_as_present'] = $same_as_present ? 1 : 0;
    if ($same_as_present) {
        $permanent_address = $present_address;
        $permanent_address['address_type'] = 'permanent';
    } else {
        if (isset($_POST['perm_house_flat_no']) && $_POST['perm_house_flat_no'] !== '') $permanent_address['house_flat_no'] = $_POST['perm_house_flat_no'];
        if (isset($_POST['perm_street_area']) && $_POST['perm_street_area'] !== '') $permanent_address['street_area'] = $_POST['perm_street_area'];
        if (isset($_POST['perm_village_town']) && $_POST['perm_village_town'] !== '') $permanent_address['village_town'] = $_POST['perm_village_town'];
        if (isset($_POST['perm_mandal']) && $_POST['perm_mandal'] !== '') $permanent_address['mandal'] = $_POST['perm_mandal'];
        if (isset($_POST['perm_district']) && $_POST['perm_district'] !== '') $permanent_address['district'] = $_POST['perm_district'];
        if (isset($_POST['perm_state']) && $_POST['perm_state'] !== '') $permanent_address['state'] = $_POST['perm_state'];
        if (isset($_POST['perm_pincode']) && $_POST['perm_pincode'] !== '') $permanent_address['pincode'] = $_POST['perm_pincode'];
    }

    if (isset($_POST['aadhaar_number']) && $_POST['aadhaar_number'] !== '') {
        $identity['aadhaar_masked'] = $_POST['aadhaar_number'];
        $identity['aadhaar_number'] = $_POST['aadhaar_number'];
    } elseif (isset($_POST['aadhaar_masked']) && $_POST['aadhaar_masked'] !== '') {
        $identity['aadhaar_masked'] = $_POST['aadhaar_masked'];
    }
    if (isset($_POST['other_id_type']) && $_POST['other_id_type'] !== '') $identity['other_id_type'] = $_POST['other_id_type'];
    if (isset($_POST['other_id_number']) && $_POST['other_id_number'] !== '') $identity['other_id_number'] = $_POST['other_id_number'];

    if (isset($_POST['bank_holder_name']) && $_POST['bank_holder_name'] !== '') $identity['bank_holder_name'] = $_POST['bank_holder_name'];
    if (isset($_POST['bank_name']) && $_POST['bank_name'] !== '') $identity['bank_name'] = $_POST['bank_name'];
    if (isset($_POST['bank_branch']) && $_POST['bank_branch'] !== '') $identity['bank_branch'] = $_POST['bank_branch'];
    if (isset($_POST['bank_account_no']) && $_POST['bank_account_no'] !== '') $identity['bank_account_no'] = $_POST['bank_account_no'];
    if (isset($_POST['bank_ifsc']) && $_POST['bank_ifsc'] !== '') $identity['bank_ifsc'] = $_POST['bank_ifsc'];

    if (isset($_POST['emergency_name']) && $_POST['emergency_name'] !== '') $emergency['full_name'] = $_POST['emergency_name'];
    if (isset($_POST['emergency_rel']) && $_POST['emergency_rel'] !== '') $emergency['relationship'] = $_POST['emergency_rel'];
    if (isset($_POST['emergency_mobile']) && $_POST['emergency_mobile'] !== '') $emergency['mobile'] = $_POST['emergency_mobile'];
    if (isset($_POST['emergency_alt_mobile']) && $_POST['emergency_alt_mobile'] !== '') $emergency['alternate_mobile'] = $_POST['emergency_alt_mobile'];
    if (isset($_POST['emergency_address']) && $_POST['emergency_address'] !== '') $emergency['address'] = $_POST['emergency_address'];

    if (isset($_POST['ref1_name']) && $_POST['ref1_name'] !== '') $ref1['full_name'] = $_POST['ref1_name'];
    if (isset($_POST['ref1_rel']) && $_POST['ref1_rel'] !== '') {
        $ref1['relationship'] = $_POST['ref1_rel'];
        $ref1['relationship_how_known'] = $_POST['ref1_rel'];
    }
    if (isset($_POST['ref1_mobile']) && $_POST['ref1_mobile'] !== '') $ref1['mobile'] = $_POST['ref1_mobile'];
    if (isset($_POST['ref1_address']) && $_POST['ref1_address'] !== '') $ref1['address'] = $_POST['ref1_address'];

    if (isset($_POST['ref2_name']) && $_POST['ref2_name'] !== '') $ref2['full_name'] = $_POST['ref2_name'];
    if (isset($_POST['ref2_rel']) && $_POST['ref2_rel'] !== '') {
        $ref2['relationship'] = $_POST['ref2_rel'];
        $ref2['relationship_how_known'] = $_POST['ref2_rel'];
    }
    if (isset($_POST['ref2_mobile']) && $_POST['ref2_mobile'] !== '') $ref2['mobile'] = $_POST['ref2_mobile'];
    if (isset($_POST['ref2_address']) && $_POST['ref2_address'] !== '') $ref2['address'] = $_POST['ref2_address'];

    if (isset($_POST['prev_employer']) && $_POST['prev_employer'] !== '') $prev_emp['employer_name'] = $_POST['prev_employer'];
    elseif (isset($_POST['prev_company']) && $_POST['prev_company'] !== '') $prev_emp['employer_name'] = $_POST['prev_company'];

    if (isset($_POST['prev_desig']) && $_POST['prev_desig'] !== '') $prev_emp['designation'] = $_POST['prev_desig'];
    elseif (isset($_POST['prev_role']) && $_POST['prev_role'] !== '') $prev_emp['designation'] = $_POST['prev_role'];

    if (isset($_POST['prev_phone']) && $_POST['prev_phone'] !== '') $prev_emp['employer_phone'] = $_POST['prev_phone'];
    if (isset($_POST['prev_reason']) && $_POST['prev_reason'] !== '') $prev_emp['reason_for_leaving'] = $_POST['prev_reason'];

    if (!empty($_POST['signature_data'])) {
        $signature['signature_data'] = $_POST['signature_data'];
    }
}

// Value helper
function val($val, $fallback = '') {
    $t = trim((string)$val);
    return $t !== '' ? htmlspecialchars($t) : $fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <base href="<?php echo defined('APP_URL') ? APP_URL . '/dashboard/management/' : '/dashboard/management/'; ?>">
    <title>VK_Logistics_Hub_Joining_Form_<?php echo htmlspecialchars($application['application_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Telugu:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            background: #cbd5e1;
            color: #0f172a;
            font-size: 9pt;
            line-height: 1.35;
        }

        .te {
            font-family: 'Noto Sans Telugu', 'Plus Jakarta Sans', sans-serif;
        }

        .no-print {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: #0f172a;
            color: white;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        }

        .pdf-page {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            padding: 12mm 15mm 12mm 15mm;
            margin: 15mm auto;
            background: #ffffff;
            box-shadow: 0 4px 25px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            page-break-after: always;
            page-break-inside: avoid;
        }

        @media print {
            body {
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .pdf-page {
                width: 210mm;
                height: 297mm;
                max-height: 297mm;
                margin: 0;
                padding: 12mm 15mm 12mm 15mm;
                box-shadow: none;
                page-break-after: always;
            }
        }

        /* Top Header Rule */
        .page-hdr {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #007a87;
            margin-bottom: 12px;
        }

        .page-hdr-logo img {
            max-height: 38px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .page-hdr-title {
            color: #007a87;
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* Category Tag */
        .cat-tag {
            color: #007a87;
            font-size: 7.5pt;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        /* Page Main Title */
        .page-title {
            color: #132c48;
            font-size: 19pt;
            font-weight: 800;
            line-height: 1.15;
            margin: 0 0 2px 0;
            letter-spacing: -0.3px;
        }

        .page-subtitle-te {
            color: #007a87;
            font-size: 10.5pt;
            font-weight: 600;
            margin: 0 0 8px 0;
            line-height: 1.25;
        }

        .bilingual-notice {
            font-size: 8pt;
            color: #334155;
            line-height: 1.35;
            margin-bottom: 10px;
        }

        /* Section Headings */
        .sec-hdr {
            font-size: 9.8pt;
            font-weight: 700;
            color: #132c48;
            margin: 10px 0 6px 0;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .sec-hdr .te {
            color: #007a87;
            font-size: 8.8pt;
            font-weight: 600;
        }

        /* Form Tables */
        .vk-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.4pt;
        }

        .vk-tbl td {
            border: 1px solid #cbd5e1;
            padding: 4.8px 7px;
            vertical-align: middle;
        }

        .lbl-col {
            width: 32%;
            background: #ffffff;
        }

        .lbl-en {
            font-weight: 600;
            color: #132c48;
            font-size: 8.2pt;
            display: block;
            line-height: 1.2;
        }

        .lbl-te {
            color: #475569;
            font-size: 7.4pt;
            display: block;
            line-height: 1.2;
            margin-top: 1px;
        }

        .val-col {
            width: 68%;
            color: #0f172a;
            font-weight: 600;
            font-size: 8.6pt;
        }

        /* Meta Box on Page 1 */
        .meta-box-wrap {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            align-items: stretch;
        }

        .meta-box-left {
            flex: 1;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 5px;
            font-size: 8.2pt;
        }

        .photo-box {
            width: 95px;
            height: 108px;
            border: 1px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #fafafa;
            overflow: hidden;
            flex-shrink: 0;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Two-column layout for reading pages 4, 5, 6 */
        .two-col-wrap {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 8px;
        }

        .col-block {
            margin-bottom: 12px;
        }

        .col-block-title {
            font-size: 9.2pt;
            font-weight: 700;
            color: #132c48;
            margin-bottom: 3px;
        }

        .col-block-text {
            font-size: 8pt;
            line-height: 1.38;
            color: #334155;
            text-align: justify;
        }

        /* Policy Pages 7, 8, 9 */
        .policy-sec-num {
            color: #007a87;
            font-size: 11pt;
            font-weight: 700;
            margin: 12px 0 4px 0;
        }

        .policy-p {
            font-size: 8.4pt;
            line-height: 1.45;
            color: #334155;
            margin-bottom: 8px;
            text-align: justify;
        }

        .callout-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 3px solid #007a87;
            padding: 8px 12px;
            margin: 10px 0;
            font-size: 8pt;
        }

        /* Footer */
        .page-ftr {
            position: absolute;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            border-top: 1px solid #007a87;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.6pt;
            color: #475569;
        }

        .ftr-brand {
            font-weight: 600;
            color: #334155;
        }

        .ftr-page {
            font-weight: 600;
            color: #334155;
        }

        .chk-box {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1px solid #475569;
            text-align: center;
            line-height: 10px;
            font-size: 8pt;
            font-weight: bold;
            margin-right: 4px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <!-- STANDALONE ACTION TOOLBAR (ONLY VISIBLE IN DIRECT TAB/STANDALONE - HIDDEN IN IFRAME PREVIEW) -->
    <div class="no-print" id="standaloneBar" style="display: none; position: sticky; top: 0; z-index: 99999; background: #0f172a; color: white; padding: 12px 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.25);">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">
            <div style="font-weight: 700; font-size: 14.5px; display: flex; align-items: center; gap: 10px;">
                <div style="background: rgba(239, 68, 68, 0.2); color: #ef4444; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-file-pdf fs-5"></i>
                </div>
                <span>VK Logistics Hub &bull; Employee Joining Form (<?php echo htmlspecialchars($application['application_no'] ?? ''); ?>)</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" onclick="window.print()" style="background: #2563eb; color: #ffffff; border: none; padding: 9px 22px; border-radius: 20px; font-weight: 700; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(37,99,235,0.35);">
                    <i class="fas fa-print"></i> Print / Save as PDF
                </button>
                <button type="button" onclick="window.close()" style="background: rgba(255,255,255,0.12); color: #ffffff; border: 1px solid rgba(255,255,255,0.25); padding: 9px 18px; border-radius: 20px; font-weight: 600; font-size: 13.5px; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>
    <script>
    if (window.self === window.top) {
        var sb = document.getElementById('standaloneBar');
        if (sb) sb.style.display = 'block';
    }
    window.addEventListener('message', function(e) {
        if (e.data && (e.data.type === 'vk_trigger_print' || e.data.type === 'vk_print_pdf')) {
            window.focus();
            window.print();
        }
    });
    </script>
    <?php if (!empty($_GET['auto_print']) || !empty($_POST['auto_print'])): ?>
    <script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.focus();
            window.print();
        }, 400);
    });
    </script>
    <?php endif; ?>

    <!-- ========================================== PAGE 1 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">EMPLOYEE RECORD</div>
        <h1 class="page-title">Employee Information & Joining Form</h1>
        <div class="page-subtitle-te te">ఉద్యోగి సమాచారం మరియు చేరిక ఫారమ్</div>

        <div class="bilingual-notice">
            For new and existing employees in all departments. Complete the details below and submit the form to HR. / 
            <span class="te">అన్ని విభాగాలలోని కొత్త మరియు ప్రస్తుత ఉద్యోగుల కోసం. క్రింది వివరాలను పూరించి ఫారమ్‌ను హెచ్‌ఆర్‌కు సమర్పించండి.</span>
        </div>

        <!-- Meta box & Photo frame -->
        <div class="meta-box-wrap">
            <div class="meta-box-left">
                <div>
                    <strong>Employee type / <span class="te">ఉద్యోగి రకం</span>:</strong> &nbsp;&nbsp;
                    <span class="chk-box"><?php echo $application['employee_type'] === 'new_joiner' ? '&#10003;' : ''; ?></span> New joiner / <span class="te">కొత్త ఉద్యోగి</span> &nbsp;&nbsp;&nbsp;
                    <span class="chk-box"><?php echo $application['employee_type'] === 'existing_employee' ? '&#10003;' : ''; ?></span> Existing / <span class="te">ప్రస్తుత ఉద్యోగి</span>
                </div>
                <div style="margin-top: 4px;">
                    <strong>Submission date / <span class="te">సమర్పించిన తేదీ</span>:</strong> 
                    <span style="border-bottom: 1px solid #475569; padding: 0 10px; font-weight: bold;">
                        <?php echo date('d-M-Y', strtotime($application['submitted_at'] ?? $application['created_at'])); ?>
                    </span>
                </div>
            </div>
            <div class="photo-box">
                <?php if (!empty($personal['photo_path']) && file_exists(__DIR__ . '/../../' . $personal['photo_path'])): ?>
                    <img src="<?php echo APP_URL . '/' . htmlspecialchars($personal['photo_path']); ?>" alt="Photo">
                <?php else: ?>
                    <span style="font-size: 7.2pt; color: #94a3b8; line-height: 1.3;">Passport-size<br>photo /<br><span class="te">పాస్‌పోర్ట్ సైజు ఫోటో</span></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="sec-hdr">
            <span>1. Employee details</span>
            <span class="te">ఉద్యోగి వివరాలు</span>
        </div>

        <table class="vk-tbl">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Employee ID</span><span class="lbl-te te">ఉద్యోగి ఐడీ</span></td>
                <td class="val-col" style="font-family: monospace; color: #007a87;"><?php echo val($approval['employee_id_assigned'] ?? $personal['employee_id'], '-'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Full name as shown on ID</span><span class="lbl-te te">గుర్తింపు పత్రం ప్రకారం పూర్తి పేరు</span></td>
                <td class="val-col"><?php echo val($personal['full_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Father’s name</span><span class="lbl-te te">తండ్రి పేరు</span></td>
                <td class="val-col"><?php echo val($personal['father_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Date of birth</span><span class="lbl-te te">పుట్టిన తేదీ</span></td>
                <td class="val-col"><?php echo !empty($personal['dob']) ? date('d-M-Y', strtotime($personal['dob'])) : ''; ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Gender</span><span class="lbl-te te">లింగం</span></td>
                <td class="val-col"><?php echo ucfirst($personal['gender'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Mobile number</span><span class="lbl-te te">ప్రాథమిక మొబైల్ నంబర్</span></td>
                <td class="val-col"><?php echo val($personal['mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Alternate mobile number</span><span class="lbl-te te">ప్రత్యామ్నాయ మొబైల్ నంబర్</span></td>
                <td class="val-col"><?php echo val($personal['alternate_mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Email address</span><span class="lbl-te te">ఈమెయిల్ చిరునామా</span></td>
                <td class="val-col"><?php echo val($personal['email']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Designation / job role</span><span class="lbl-te te">ఉద్యోగ హోదా</span></td>
                <td class="val-col"><?php echo val($personal['designation']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Department</span><span class="lbl-te te">విభాగం</span></td>
                <td class="val-col"><?php echo val($application['department_name'] ?? 'Operations / Logistics'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Date of joining</span><span class="lbl-te te">చేరిన తేదీ</span></td>
                <td class="val-col"><?php echo !empty($personal['joining_date']) ? date('d-M-Y', strtotime($personal['joining_date'])) : ''; ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Nationality</span><span class="lbl-te te">జాతీయత</span></td>
                <td class="val-col"><?php echo val($personal['nationality'], 'Indian'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Blood group (optional)</span><span class="lbl-te te">రక్త సమూహం (ఐచ్ఛికం)</span></td>
                <td class="val-col"><?php echo val($personal['blood_group']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Previous work experience</span><span class="lbl-te te">గత పని అనుభవం</span></td>
                <td class="val-col"><?php echo val($personal['previous_work_experience'], 'Fresher'); ?></td>
            </tr>
        </table>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 1</span>
        </div>
    </div>

    <!-- ========================================== PAGE 2 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">EMPLOYEE RECORD</div>
        <h1 class="page-title">Addresses & Documents</h1>
        <div class="page-subtitle-te te">చిరునామాలు మరియు పత్రాలు</div>

        <div class="sec-hdr">
            <span>2. Present address</span>
            <span class="te">ప్రస్తుత చిరునామా</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 12px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">House / flat number</span><span class="lbl-te te">ఇంటి / ఫ్లాట్ నంబర్</span></td>
                <td class="val-col"><?php echo val($present_address['house_flat_no']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Street / area</span><span class="lbl-te te">వీధి / ప్రాంతం</span></td>
                <td class="val-col"><?php echo val($present_address['street_area']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Village / town</span><span class="lbl-te te">గ్రామం / పట్టణం</span></td>
                <td class="val-col"><?php echo val($present_address['village_town']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Mandal</span><span class="lbl-te te">మండలం</span></td>
                <td class="val-col"><?php echo val($present_address['mandal']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">District</span><span class="lbl-te te">జిల్లా</span></td>
                <td class="val-col"><?php echo val($present_address['district']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">State</span><span class="lbl-te te">రాష్ట్రం</span></td>
                <td class="val-col"><?php echo val($present_address['state']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">PIN code</span><span class="lbl-te te">పిన్ కోడ్</span></td>
                <td class="val-col"><?php echo val($present_address['pincode']); ?></td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>3. Permanent address</span>
            <span class="te">శాశ్వత చిరునామా</span>
        </div>

        <div style="font-size: 8.2pt; margin-bottom: 6px;">
            <strong>Same as present address? / <span class="te">ప్రస్తుత చిరునామాతో సమానమా?</span></strong> &nbsp;&nbsp;&nbsp;
            <span class="chk-box"><?php echo !empty($permanent_address['is_same_as_present']) ? '&#10003;' : ''; ?></span> Yes / <span class="te">అవును</span> &nbsp;&nbsp;&nbsp;&nbsp;
            <span class="chk-box"><?php echo empty($permanent_address['is_same_as_present']) ? '&#10003;' : ''; ?></span> No / <span class="te">కాదు</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 12px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Permanent address if different</span><span class="lbl-te te">వేరైతే శాశ్వత చిరునామా</span></td>
                <td class="val-col">
                    <?php 
                    if (!empty($permanent_address['is_same_as_present'])) {
                        echo 'Same as present address';
                    } else {
                        $full_perm = array_filter([
                            $permanent_address['house_flat_no'] ?? '',
                            $permanent_address['street_area'] ?? '',
                            $permanent_address['village_town'] ?? '',
                            $permanent_address['mandal'] ?? '',
                            $permanent_address['district'] ?? '',
                            $permanent_address['state'] ?? '',
                            $permanent_address['pincode'] ?? ''
                        ]);
                        echo htmlspecialchars(implode(', ', $full_perm) ?: '-');
                    }
                    ?>
                </td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>4. Identity documents</span>
            <span class="te">గుర్తింపు పత్రాలు</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 12px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Aadhaar (masked preferred)</span><span class="lbl-te te">ఆధార్ (మాస్క్ చేసినది ప్రాధాన్యం)</span></td>
                <td class="val-col" style="font-family: monospace;"><?php echo val($identity['aadhaar_masked'] ?? $identity['aadhaar_number']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Other ID proof (optional)</span><span class="lbl-te te">ఇతర గుర్తింపు పత్రం (ఐచ్ఛికం)</span></td>
                <td class="val-col"><?php echo val($identity['other_id_type'] ? ($identity['other_id_type'] . ': ' . $identity['other_id_number']) : 'None'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Documents submitted</span><span class="lbl-te te">సమర్పించిన పత్రాలు</span></td>
                <td class="val-col">
                    <span class="chk-box">&#10003;</span> Aadhaar &nbsp;&nbsp;
                    <span class="chk-box">&#10003;</span> PAN Card &nbsp;&nbsp;
                    <span class="chk-box">&#10003;</span> Bank Passbook / Cheque &nbsp;&nbsp;
                    <span class="chk-box">&nbsp;</span> Driving License
                </td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Bank payroll details</span><span class="lbl-te te">జీతం క్రెడిట్ బ్యాంక్ వివరాలు</span></td>
                <td class="val-col">
                    <?php if (!empty($identity['bank_account_no']) || !empty($identity['bank_name'])): ?>
                        <strong><?php echo val($identity['bank_name']); ?></strong> (<?php echo val($identity['bank_branch']); ?>) &bull; 
                        A/C: <span style="font-family: monospace;"><?php echo val($identity['bank_account_no']); ?></span> &bull; 
                        IFSC: <span style="font-family: monospace;"><?php echo val($identity['bank_ifsc']); ?></span>
                    <?php else: ?>
                        <span style="color: #94a3b8;">[ Bank Details Pending ]</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Verified by</span><span class="lbl-te te">ధృవీకరించినవారు</span></td>
                <td class="val-col"><?php echo val($approval['verified_by_name'] ?? 'HR / Admin Official Check'); ?></td>
            </tr>
        </table>

        <div class="two-col-wrap" style="margin-top: 14px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px 14px;">
            <div style="font-size: 7.8pt; color: #334155; line-height: 1.35;">
                Provide Aadhaar only where lawfully required. Prefer masked Aadhaar. Only authorised staff may access these details. The company verifies all documents submitted for identity, address and statutory compliance.
            </div>
            <div class="te" style="font-size: 7.6pt; color: #334155; line-height: 1.35;">
                చట్టబద్ధంగా అవసరమైన చోట మాత్రమే ఆధార్ వివరాలను అందించండి. మాస్క్ చేసిన ఆధార్‌ను ప్రాధాన్యంగా ఇవ్వండి. అధికారిక సిబ్బంది మాత్రమే ఈ వివరాలను చూస్తారు. గుర్తింపు, చిరునామా మరియు చట్టబద్ధమైన నిబంధనల కోసం సంస్థ సమర్పించిన అన్ని పత్రాలను ధృవీకరిస్తుంది.
            </div>
        </div>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 2</span>
        </div>
    </div>

    <!-- ========================================== PAGE 3 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">EMPLOYEE RECORD</div>
        <h1 class="page-title">Contacts & Employment History</h1>
        <div class="page-subtitle-te te">సంప్రదింపు వివరాలు మరియు మునుపటి ఉద్యోగ చరిత్ర</div>

        <div class="sec-hdr">
            <span>5. Emergency contact</span>
            <span class="te">అత్యవసర సంప్రదింపు</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 10px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Full name</span><span class="lbl-te te">పూర్తి పేరు</span></td>
                <td class="val-col"><?php echo val($emergency['full_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Relationship</span><span class="lbl-te te">సంబంధం</span></td>
                <td class="val-col"><?php echo val($emergency['relationship']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Mobile number</span><span class="lbl-te te">మొబైల్ నంబర్</span></td>
                <td class="val-col"><?php echo val($emergency['mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Alternate number</span><span class="lbl-te te">ప్రత్యామ్నాయ నంబర్</span></td>
                <td class="val-col"><?php echo val($emergency['alternate_mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Address</span><span class="lbl-te te">చిరునామా</span></td>
                <td class="val-col"><?php echo val($emergency['address']); ?></td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>6. Reference 1</span>
            <span class="te">మొదటి పరిచయ వ్యక్తి</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 10px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Full name</span><span class="lbl-te te">పూర్తి పేరు</span></td>
                <td class="val-col"><?php echo val($ref1['full_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Relationship / how known</span><span class="lbl-te te">సంబంధం / పరిచయం</span></td>
                <td class="val-col"><?php echo val($ref1['relationship_how_known']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Mobile number</span><span class="lbl-te te">మొబైల్ నంబర్</span></td>
                <td class="val-col"><?php echo val($ref1['mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Address</span><span class="lbl-te te">చిరునామా</span></td>
                <td class="val-col"><?php echo val($ref1['address']); ?></td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>7. Reference 2</span>
            <span class="te">రెండవ పరిచయ వ్యక్తి</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 10px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Full name</span><span class="lbl-te te">పూర్తి పేరు</span></td>
                <td class="val-col"><?php echo val($ref2['full_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Relationship / how known</span><span class="lbl-te te">సంబంధం / పరిచయం</span></td>
                <td class="val-col"><?php echo val($ref2['relationship_how_known']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Mobile number</span><span class="lbl-te te">మొబైల్ నంబర్</span></td>
                <td class="val-col"><?php echo val($ref2['mobile']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Address</span><span class="lbl-te te">చిరునామా</span></td>
                <td class="val-col"><?php echo val($ref2['address']); ?></td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>8. Previous employment</span>
            <span class="te">గత ఉద్యోగ వివరాలు</span>
        </div>

        <table class="vk-tbl">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Previous employer</span><span class="lbl-te te">గత సంస్థ పేరు</span></td>
                <td class="val-col"><?php echo val($prev_emp['employer_name'], 'Fresher'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Previous designation</span><span class="lbl-te te">గతంలో ఉద్యోగ హోదా</span></td>
                <td class="val-col"><?php echo val($prev_emp['designation'], '-'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Previous employer phone</span><span class="lbl-te te">గత సంస్థ ఫోన్ నంబర్</span></td>
                <td class="val-col"><?php echo val($prev_emp['employer_phone'], '-'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Reason for leaving</span><span class="lbl-te te">వదిలివేయడానికి కారణం</span></td>
                <td class="val-col"><?php echo val($prev_emp['reason_for_leaving'], '-'); ?></td>
            </tr>
        </table>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 3</span>
        </div>
    </div>

    <!-- ========================================== PAGE 4 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">EMPLOYMENT TERMS</div>
        <h1 class="page-title">Employment Terms</h1>
        <div class="page-subtitle-te te">ఉద్యోగ నిబంధనలు</div>

        <div class="two-col-wrap">
            <!-- English Column -->
            <div>
                <div class="col-block">
                    <div class="col-block-title">Probation and confirmation</div>
                    <div class="col-block-text">
                        New employees serve a three-month probation from their joining date. Management reviews attendance, punctuality, performance, conduct and suitability before confirming employment.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">Leave and attendance</div>
                    <div class="col-block-text">
                        Follow the leave policy for your role. Pickup associates should follow the attached Associate Leave &amp; Pickup Incentive Policy. Report on time and tell your manager if you will be late or absent.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">Resignation, notice and handover</div>
                    <div class="col-block-text">
                        Resign in writing, even if you are leaving in the first few days or during probation. Serve the 30-day notice period where your appointment terms and law allow it. Management can approve a shorter notice period or waive it in writing.
                        <br><br>
                        Hand over pending work and return company property, ID cards, uniforms, documents and equipment. Any notice-pay recovery must be allowed by your contract and the law. The company settles earned wages and final dues in line with applicable law.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">Company property and conduct</div>
                    <div class="col-block-text">
                        Use company property and vehicles carefully. Report any loss or damage immediately. Treat customers and colleagues with respect, follow management instructions and keep company and customer information confidential.
                    </div>
                </div>
            </div>

            <!-- Telugu Column -->
            <div class="te">
                <div class="col-block">
                    <div class="col-block-title">పరిశీలన కాలం మరియు నిర్ధారణ</div>
                    <div class="col-block-text">
                        కొత్తగా చేరిన ఉద్యోగులు చేరిన తేదీ నుండి మూడు నెలల పరిశీలన కాలంలో ఉంటారు. ఉద్యోగ నిర్ధారణకు ముందు యాజమాన్యం హాజరు, సమయపాలన, పనితీరు, ప్రవర్తన మరియు సరిపోలుతనాన్ని సమీక్షిస్తుంది.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">సెలవులు మరియు హాజరు</div>
                    <div class="col-block-text">
                        మీ ఉద్యోగానికి సంబంధించిన సెలవు విధానాన్ని పాటించండి. పికప్ అసోసియేట్లు జతచేసిన అసోసియేట్ సెలవు &amp; పికప్ ప్రోత్సాహక పాలసీని పాటించాలి. సమయానికి విధులకు హాజరవ్వండి మరియు ఆలస్యమైనా లేదా గైర్హాజరైనా మేనేజర్‌కు తెలియజేయండి.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">రాజీనామా, నోటీసు మరియు అప్పగింత</div>
                    <div class="col-block-text">
                        మొదటి కొద్ది రోజుల్లో లేదా ప్రొబేషన్ సమయంలో నిష్క్రమించినా రాతపూర్వకంగా రాజీనామా చేయండి. నియామక నిబంధనలు మరియు చట్టం అనుమతించిన మేరకు 30 రోజుల నోటీసు కాలాన్ని పూర్తి చేయండి. యాజమాన్యం తక్కువ నోటీసు కాలాన్ని ఆమోదించవచ్చు లేదా రాతపూర్వకంగా మినహాయించవచ్చు.
                        <br><br>
                        పెండింగ్ పనులను మరియు సంస్థ ఆస్తులు, గుర్తింపు కార్డులు, యూనిఫాంలు, పత్రాలు మరియు పరికరాలను తిరిగి అప్పగించండి. నోటీసు వేతన రికవరీ ఒప్పందం మరియు చట్టం ప్రకారం అనుమతించబడాలి. సంస్థ సంపాదించిన వేతనాలు మరియు తుది చెల్లింపులను వర్తించే చట్టం ప్రకారం పరిష్కరిస్తుంది.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">సంస్థ ఆస్తులు మరియు ప్రవర్తన</div>
                    <div class="col-block-text">
                        సంస్థ ఆస్తులు మరియు వాహనాలను జాగ్రత్తగా ఉపయోగించండి. ఏదైనా నష్టం లేదా నష్టాన్ని వెంటనే నివేదించండి. కస్టమర్లు మరియు సహోద్యోగులతో గౌరవంగా ప్రవర్తించండి, యాజమాన్య సూచనలను పాటించండి మరియు సంస్థ మరియు కస్టమర్ సమాచారాన్ని గోప్యంగా ఉంచండి.
                    </div>
                </div>
            </div>
        </div>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 4</span>
        </div>
    </div>

    <!-- ========================================== PAGE 5 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">PICKUP ASSOCIATES</div>
        <h1 class="page-title">Leave &amp; Pickup Incentives</h1>
        <div class="page-subtitle-te te">సెలవులు మరియు పికప్ ప్రోత్సాహకాలు</div>

        <div class="bilingual-notice" style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 10px;">
            This summary is for pickup associates. The full policy is attached at the end of this document. / 
            <span class="te">ఈ సారాంశం పికప్ అసోసియేట్ల కోసం. పూర్తి పాలసీ ఈ పత్రం చివర జతచేయబడింది.</span>
        </div>

        <div class="two-col-wrap">
            <!-- English Column -->
            <div>
                <div class="col-block">
                    <div class="col-block-title">Paid leave and holidays</div>
                    <div class="col-block-text">
                        12 paid annual leave days and 12 listed paid public or festival holidays each calendar year. Company annual leave can be used after probation. Paid public holidays apply during probation. Statutory leave applies from the date you qualify.
                        <br><br>
                        Request planned leave at least seven days ahead. Get written approval from your manager and HR. For sickness or an emergency, notify them immediately. Submit a medical certificate for sick leave and any supporting documents HR requests.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">Daily target and extra pickup bonus</div>
                    <div class="col-block-text">
                        Your written salary agreement sets a daily target of 3 or 4 completed pickups. For each verified pickup above your target, you earn the bonus rate HR confirms in writing. For example, 4 completed pickups against a target of 3 earn 1 bonus pickup.
                        <br><br>
                        The extra pickup bonus is the only company incentive for this role. Tell your supervisor if a pickup may run past your shift. Log your actual hours and pickup details. Payroll pays any overtime required by law.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">Training</div>
                    <div class="col-block-text">
                        The first two weeks are for induction and training. If you leave early, HR checks what pay is due for work and any training that qualifies for pay.
                    </div>
                </div>
            </div>

            <!-- Telugu Column -->
            <div class="te">
                <div class="col-block">
                    <div class="col-block-title">చెల్లింపు సెలవులు మరియు పండుగ సెలవులు</div>
                    <div class="col-block-text">
                        ప్రతి క్యాలెండర్ సంవత్సరంలో 12 చెల్లింపు వార్షిక సెలవులు మరియు 12 నిర్దేశిత చెల్లింపు పబ్లిక్ లేదా పండుగ సెలవులు ఉంటాయి. కంపెనీ వార్షిక సెలవులను ప్రొబేషన్ తర్వాత ఉపయోగించవచ్చు. ప్రొబేషన్ సమయంలో పబ్లిక్ హాలిడేస్ వర్తిస్తాయి. చట్టబద్ధమైన సెలవులు అర్హత పొందిన తేదీ నుండి వర్తిస్తాయి.
                        <br><br>
                        ముందుగా ప్లాన్ చేసిన సెలవుల కోసం కనీసం ఏడు రోజుల ముందు దరఖాస్తు చేసుకోండి. మీ మేనేజర్ మరియు హెచ్ఆర్ నుండి రాతపూర్వక ఆమోదం పొందండి. అనారోగ్యం లేదా అత్యవసర పరిస్థితికి వెంటనే వారికి తెలియజేయండి. సిక్ లీవ్ కోసం మెడికల్ సర్టిఫికేట్ మరియు హెచ్ఆర్ అడిగిన సంబంధిత పత్రాలను సమర్పించండి.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">రోజువారీ లక్ష్యం మరియు అదనపు పికప్ బోనస్</div>
                    <div class="col-block-text">
                        మీ వేతన ఒప్పందం రోజుకు 3 లేదా 4 పూర్తి చేసిన పికప్‌ల లక్ష్యాన్ని నిర్దేశిస్తుంది. మీ లక్ష్యానికి మించి ధృవీకరించబడిన ప్రతి పికప్‌కు, హెచ్ఆర్ రాతపూర్వకంగా ధృవీకరించిన బోనస్ రేటును సంపాదిస్తారు. ఉదాహరణకు, 3 లక్ష్యానికి 4 పికప్‌లు పూర్తి చేస్తే 1 బోనస్ పికప్ లభిస్తుంది.
                        <br><br>
                        ఈ ఉద్యోగానికి అదనపు పికప్ బోనస్ మాత్రమే సంస్థ ప్రోత్సాహకం. పికప్ షిఫ్ట్ సమయం దాటిపోయే అవకాశం ఉంటే సూపర్‌వైజర్‌కు చెప్పండి. అసలు పని గంటలు మరియు పికప్ వివరాలను నమోదు చేయండి. చట్టం ప్రకారం అవసరమైన ఓవర్‌టైమ్‌ను పేరోల్ చెల్లిస్తుంది.
                    </div>
                </div>

                <div class="col-block">
                    <div class="col-block-title">శిక్షణ</div>
                    <div class="col-block-text">
                        మొదటి రెండు వారాలు పరిచయం మరియు శిక్షణ కోసం. మీరు ముందుగా నిష్క్రమిస్తే, పని మరియు చెల్లింపుకు అర్హత ఉన్న ఏదైనా శిక్షణ కోసం ఎంత వేతనం చెల్లించాలో హెచ్ఆర్ లెక్కిస్తుంది.
                    </div>
                </div>
            </div>
        </div>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 5</span>
        </div>
    </div>

    <!-- ========================================== PAGE 6 ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <div class="cat-tag">SIGNATURES</div>
        <h1 class="page-title">Employee Declaration &amp; HR Approval</h1>
        <div class="page-subtitle-te te">ఉద్యోగి ప్రకటన మరియు హెచ్‌ఆర్ ఆమోదం</div>

        <div class="two-col-wrap" style="margin-bottom: 10px;">
            <div style="font-size: 7.8pt; line-height: 1.35; color: #334155; text-align: justify;">
                I confirm that the information and documents I provided are accurate to the best of my knowledge. I have read and understood the employment terms and the policies that apply to my role. If I leave, I will notify management and complete the applicable notice and handover.
                <br><br>
                I agree to follow company rules and authorise the company to use my data for employment administration, payroll, statutory compliance and emergency contact, with appropriate safeguards.
            </div>
            <div class="te" style="font-size: 7.6pt; line-height: 1.35; color: #334155; text-align: justify;">
                నేను అందించిన సమాచారం మరియు పత్రాలు నా జ్ఞానం మేరకు సరైనవని ధృవీకరిస్తున్నాను. నేను ఉద్యోగ నిబంధనలను మరియు నా ఉద్యోగానికి వర్తించే పాలసీలను చదివాను మరియు అర్థం చేసుకున్నాను. నేను విడిచిపెట్టే పక్షంలో, నేను యాజమాన్యానికి తెలియజేస్తాను మరియు వర్తించే నోటీసు మరియు అప్పగింతను పూర్తి చేస్తాను.
                <br><br>
                కంపెనీ నియమాలను పాటించడానికి నేను అంగీకరిస్తున్నాను మరియు ఉద్యోగ పరిపాలన, పేరోల్, చట్టబద్ధమైన అవసరాలు మరియు అత్యవసర సంప్రదింపుల కోసం తగిన భద్రతలతో నా డేటాను ఉపయోగించడానికి కంపెనీకి అధికారం ఇస్తున్నాను.
            </div>
        </div>

        <div style="font-size: 8.2pt; margin-bottom: 8px;">
            <span class="chk-box">&#10003;</span> 
            <strong>Pickup associates only.</strong> I acknowledge receipt of the attached Associate Leave &amp; Pickup Incentive Policy. / 
            <span class="te">పికప్ అసోసియేట్లు మాత్రమే. జతచేసిన అసోసియేట్ సెలవు &amp; పికప్ ఇన్సెంటివ్ పాలసీని అందుకున్నట్లు ధృవీకరిస్తున్నాను.</span>
        </div>

        <div class="sec-hdr">
            <span>Employee confirmation</span>
            <span class="te">ఉద్యోగి ధృవీకరణ</span>
        </div>

        <table class="vk-tbl" style="margin-bottom: 10px;">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Employee name</span><span class="lbl-te te">ఉద్యోగి పేరు</span></td>
                <td class="val-col"><?php echo val($personal['full_name']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Employee ID</span><span class="lbl-te te">ఉద్యోగి ఐడీ</span></td>
                <td class="val-col" style="font-family: monospace;"><?php echo val($approval['employee_id_assigned'] ?? $personal['employee_id'], '-'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Designation</span><span class="lbl-te te">ఉద్యోగ హోదా</span></td>
                <td class="val-col"><?php echo val($personal['designation']); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Date</span><span class="lbl-te te">తేదీ</span></td>
                <td class="val-col"><?php echo date('d-M-Y', strtotime($signature['signed_at'] ?? $application['submitted_at'] ?? $application['created_at'])); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Employee signature</span><span class="lbl-te te">ఉద్యోగి సంతకం</span></td>
                <td class="val-col" style="height: 52px; vertical-align: middle;">
                    <?php if (!empty($signature['signature_image_path']) && file_exists(__DIR__ . '/../../' . $signature['signature_image_path'])): ?>
                        <img src="<?php echo APP_URL . '/' . htmlspecialchars($signature['signature_image_path']); ?>" alt="Signature" style="max-height: 44px; max-width: 180px;">
                    <?php elseif (!empty($signature['signature_data'])): ?>
                        <img src="<?php echo htmlspecialchars($signature['signature_data']); ?>" alt="Signature" style="max-height: 44px; max-width: 180px;">
                    <?php else: ?>
                        <span style="color: #94a3b8; font-size: 7.8pt;">[ Signature on file / సంతకం ]</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Left thumb impression</span><span class="lbl-te te">ఎడమ చేతి బొటనవేలు ముద్ర</span></td>
                <td class="val-col" style="height: 52px; vertical-align: middle;">
                    <?php if (!empty($signature['thumb_impression_path']) && file_exists(__DIR__ . '/../../' . $signature['thumb_impression_path'])): ?>
                        <img src="<?php echo APP_URL . '/' . htmlspecialchars($signature['thumb_impression_path']); ?>" alt="Thumb" style="max-height: 44px; max-width: 90px;">
                    <?php else: ?>
                        <div style="width: 44px; height: 44px; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; justify-content: center; font-size: 6.5pt; color: #94a3b8; text-align: center;">Thumb</div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="sec-hdr">
            <span>For office use only</span>
            <span class="te">కార్యాలయ ఉపయోగానికి మాత్రమే</span>
        </div>

        <table class="vk-tbl">
            <tr>
                <td class="lbl-col"><span class="lbl-en">Documents verified by</span><span class="lbl-te te">పత్రాలను ధృవీకరించిన అధికారి</span></td>
                <td class="val-col"><?php echo val($approval['verified_by_name'] ?? 'HR Operations'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Employee ID assigned</span><span class="lbl-te te">కేటాయించిన ఉద్యోగి ఐడీ</span></td>
                <td class="val-col" style="font-family: monospace; font-weight: bold; color: #007a87;"><?php echo val($approval['employee_id_assigned'], 'Confirmed'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Joining date confirmed</span><span class="lbl-te te">నిర్ధారించిన చేరిక తేదీ</span></td>
                <td class="val-col"><?php echo !empty($approval['joining_date_confirmed']) ? date('d-M-Y', strtotime($approval['joining_date_confirmed'])) : (!empty($personal['joining_date']) ? date('d-M-Y', strtotime($personal['joining_date'])) : ''); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">Approved by</span><span class="lbl-te te">ఆమోదించిన అధికారి</span></td>
                <td class="val-col"><?php echo val($approval['approved_by_name'] ?? 'HR Management'); ?></td>
            </tr>
            <tr>
                <td class="lbl-col"><span class="lbl-en">HR / Admin signature</span><span class="lbl-te te">హెచ్‌ఆర్ / అడ్మిన్ సంతకం</span></td>
                <td class="val-col" style="height: 40px; vertical-align: middle;">
                    <?php if (!empty($approval['hr_signature_path']) && file_exists(__DIR__ . '/../../' . $approval['hr_signature_path'])): ?>
                        <img src="<?php echo APP_URL . '/' . htmlspecialchars($approval['hr_signature_path']); ?>" alt="HR Signature" style="max-height: 34px;">
                    <?php else: ?>
                        <span style="color: #64748b; font-size: 7.8pt;">[ Verified &amp; Approved &bull; HR Desk ]</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; Employee Joining Form</span>
            <span class="ftr-page">Page 6</span>
        </div>
    </div>

    <!-- ========================================== PAGE 7 (Policy Page 1) ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <h1 class="page-title" style="font-size: 20pt; margin-top: 8px;">Associate Leave &amp;<br>Pickup Incentive Policy</h1>
        
        <div style="font-size: 8.5pt; color: #334155; margin: 8px 0 14px 0;">
            <strong>Applies to:</strong> VK Logistics Hub pickup associates employed by the company<br>
            <strong>Policy owner:</strong> Human Resources
        </div>

        <div class="policy-sec-num">1. Annual leave and paid holidays</div>
        <p class="policy-p">
            The company provides <strong>12 paid annual leave days</strong> and <strong>12 designated paid public or festival holidays</strong> each year. The annual leave and holiday year runs from <strong>1 January to 31 December</strong>.
        </p>
        <p class="policy-p">
            You can use company annual leave once you pass probation. Paid public holidays apply during probation. You can take any leave the law gives you from the date you qualify, even during probation.
        </p>
        <p class="policy-p">
            You earn one extra day of paid annual leave for each full year you work here, starting after your first year. You can carry unused leave into the next year if HR approves it in writing or the law requires it.
        </p>

        <div class="policy-sec-num">2. Designated paid public and festival holidays</div>
        <table class="vk-tbl" style="margin-bottom: 8px; font-size: 8pt;">
            <tr>
                <td style="width: 50%; padding: 4px 8px;">1. New Year's Day - 1 January</td>
                <td style="width: 50%; padding: 4px 8px;">7. Independence Day - 15 August</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;">2. Sankranthi</td>
                <td style="padding: 4px 8px;">8. Vinayaka Chavithi</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;">3. Republic Day - 26 January</td>
                <td style="padding: 4px 8px;">9. Gandhi Jayanti - 2 October</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;">4. Ramzan (Eid al-Fitr)</td>
                <td style="padding: 4px 8px;">10. Dasara</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;">5. Ugadi</td>
                <td style="padding: 4px 8px;">11. Diwali</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px;">6. Holi</td>
                <td style="padding: 4px 8px;">12. Christmas - 25 December</td>
            </tr>
        </table>
        <p class="policy-p" style="font-size: 7.8pt; color: #64748b;">
            HR publishes festival dates each year and observes additional mandatory paid holidays where applicable, including May Day (1 May) and Telangana Formation Day (2 June).
        </p>

        <div class="policy-sec-num">3. How to request leave</div>
        <p class="policy-p">
            Request planned leave at least <strong>7 days ahead</strong> through the HR portal or by email. Get written approval from your manager and HR before taking leave or booking travel. Approval depends on team coverage and workload.
        </p>
        <p class="policy-p">
            You can usually take up to <strong>3 annual leave days in a row</strong>. Ask for leave either before or after a weekend or public holiday. Get written approval from HR and Management for leave on both sides or for more than 3 days in a row. During festivals and busy periods, approval depends on team coverage.
        </p>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; www.vklogisticshub.com</span>
            <span class="ftr-page">Page 1</span>
        </div>
    </div>

    <!-- ========================================== PAGE 8 (Policy Page 2) ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <h1 class="page-title" style="font-size: 20pt; margin-top: 8px;">Attendance, Sick Leave<br>&amp; Initial Training</h1>

        <div class="policy-sec-num">4. Returning after a Sunday, holiday or approved leave</div>
        <p class="policy-p">
            Report on your first scheduled working day after a Sunday, other weekly rest day, public holiday or approved leave. If you need to extend leave, obtain written approval from your Reporting Manager and HR.
        </p>
        <p class="policy-p">
            HR records unapproved time missed on a scheduled working day as <strong>Leave Without Pay (LWP)</strong>, subject to applicable law. If Sunday is your normal rest day and you miss Monday without approval, Monday is treated as LWP. The same rule applies on the first scheduled working day after a public holiday or approved leave.
        </p>
        <p class="policy-p">
            HR reviews related absences together. Sundays, public holidays and approved paid leave stay paid when the law or your employment terms protect that pay. If you were scheduled and lawfully required to work on a Sunday and missed the shift without approval, the missed work time may be unpaid.
        </p>
        <p class="policy-p">
            Repeated unauthorised absence or failure to report or communicate may lead to disciplinary action under company procedure.
        </p>

        <div class="policy-sec-num">5. Sick leave and emergencies</div>
        <p class="policy-p">
            Notify your Reporting Manager and HR immediately. For an emergency, explain the reason and expected duration as soon as reasonably possible. For sick leave, submit a medical certificate from a registered medical practitioner immediately, along with other relevant documents HR requests.
        </p>
        <p class="policy-p">
            If you cannot get the documents straight away, tell HR and send them as soon as you get them. HR will apply any sick leave you qualify for by law.
        </p>

        <div class="policy-sec-num">6. Two-week induction and training</div>
        <p class="policy-p">
            <strong>Your first two weeks are for induction and training.</strong> HR and Operations record your attendance and activities. If you leave early, HR checks what pay is due for work performed and for any training time payable under the law or your employment terms.
        </p>
        <p class="policy-p">
            <strong>Example: leaving after 5 days.</strong> If none of the five days qualify for payment, no payment is due for those days. If you worked or attended training that qualifies for pay, payroll pays what you earned.
        </p>

        <div class="callout-box" style="margin-top: 15px;">
            <div style="font-weight: 700; color: #132c48; margin-bottom: 2px;">HR contact</div>
            <div style="color: #475569;">Human Resources &bull; hr@vkinnect.com &nbsp;|&nbsp; hr.vkinnect@gmail.com</div>
        </div>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; www.vklogisticshub.com</span>
            <span class="ftr-page">Page 2</span>
        </div>
    </div>

    <!-- ========================================== PAGE 9 (Policy Page 3) ========================================== -->
    <div class="pdf-page">
        <div class="page-hdr">
            <div class="page-hdr-logo">
                <img src="<?php echo APP_URL; ?>/assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB">
            </div>
            <div class="page-hdr-title">HUMAN RESOURCES</div>
        </div>

        <h1 class="page-title" style="font-size: 20pt; margin-top: 8px;">Pickup Targets &amp; Bonuses</h1>

        <div class="policy-sec-num">7. Agreed daily pickup target</div>
        <p class="policy-p">
            Check your written salary agreement for your daily target. It will be <strong>3 or 4 completed pickups</strong> per scheduled working day.
        </p>
        <p class="policy-p">
            If you miss your target, record why. Operations will check how many pickups were assigned, distance, parcel weight, time spent, cancellations and delays outside your control. HR handles any confirmed performance or attendance issues under company procedure. Missing the target does not automatically reduce your base pay.
        </p>

        <div class="policy-sec-num">8. Bonus for extra pickups</div>
        <p class="policy-p">
            You earn a bonus for each verified pickup above your daily target. HR gives you the rate in writing. Operations checks the booking records and sends the verified count to payroll.
        </p>
        <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 6px 10px; font-size: 8pt; margin-bottom: 8px;">
            <strong>3-pickup target:</strong> 3 completed = daily target met. 4 completed = 1 bonus pickup.<br>
            <strong>4-pickup target:</strong> 4 completed = daily target met. 6 completed = 2 bonus pickups.
        </div>

        <div class="policy-sec-num">9. Shift hours and daily records</div>
        <p class="policy-p">
            Complete pickups within your scheduled shift. If a pickup is likely to run past your shift, tell your supervisor so they can reassign or reschedule it. Record the hours you actually worked, including any time beyond your shift.
        </p>
        <p class="policy-p">
            The extra pickup bonus is the only company incentive for this role. Payroll pays any overtime the law requires.
        </p>
        <p class="policy-p">
            Your daily log must show shift and pickup start and finish times, booking references, pickup status, distance, parcel weight in kg and reasons for missed pickups or time worked beyond your shift. Your supervisor checks it before sending it to HR and payroll.
        </p>

        <div class="policy-sec-num">Policy administration</div>
        <p class="policy-p">
            HR will communicate any policy amendments through official company channels. Applicable legal entitlements take precedence.
        </p>

        <div class="page-ftr">
            <span class="ftr-brand">VK Logistics Hub &nbsp;|&nbsp; www.vklogisticshub.com</span>
            <span class="ftr-page">Page 3</span>
        </div>
    </div>

</body>
</html>
