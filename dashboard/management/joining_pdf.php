<?php
/**
 * VK Logistics Hub - 9-Page High-Fidelity Bilingual PDF & Print Document
 * Matches: VK_Logistics_Hub_Bilingual_Joining_Form_and_Associate_Policy.pdf (Pages 1 - 9)
 * Location: dashboard/management/joining_pdf.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/joining_i18n.php';

$db = getDB();

$app_id = intval($_GET['id'] ?? 0);
$token = $_GET['token'] ?? null;

if (!$app_id && !$token) {
    die("Invalid request. Application ID or token required.");
}

if ($app_id) {
    $stmt = $db->prepare("SELECT a.*, d.name as department_name FROM employee_joining_applications a 
                          LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                          LEFT JOIN departments d ON p.department_id = d.id 
                          WHERE a.id = ?");
    $stmt->execute([$app_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("SELECT a.*, d.name as department_name FROM employee_joining_applications a 
                          LEFT JOIN employee_personal_details p ON a.id = p.application_id 
                          LEFT JOIN departments d ON p.department_id = d.id 
                          WHERE a.token = ?");
    $stmt->execute([$token]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($application) $app_id = $application['id'];
}

if (!$application) {
    die("Application record not found.");
}

// Fetch all normalized data
$personal = $db->query("SELECT * FROM employee_personal_details WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$present_address = $db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'present'")->fetch(PDO::FETCH_ASSOC);
$permanent_address = $db->query("SELECT * FROM employee_addresses WHERE application_id = {$app_id} AND address_type = 'permanent'")->fetch(PDO::FETCH_ASSOC);
$identity = $db->query("SELECT * FROM employee_identity_documents WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$emergency = $db->query("SELECT * FROM employee_emergency_contacts WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$ref1 = $db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 1")->fetch(PDO::FETCH_ASSOC);
$ref2 = $db->query("SELECT * FROM employee_references WHERE application_id = {$app_id} AND reference_number = 2")->fetch(PDO::FETCH_ASSOC);
$prev_emp = $db->query("SELECT * FROM employee_previous_employment WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$declaration = $db->query("SELECT * FROM employee_declarations WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$signature = $db->query("SELECT * FROM employee_signatures WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);
$approval = $db->query("SELECT * FROM employee_joining_approvals WHERE application_id = {$app_id}")->fetch(PDO::FETCH_ASSOC);

// Helper to safely render bilingual labels in PDF
function p_lbl($key) {
    $en = JoiningI18n::t($key, 'en');
    $te = JoiningI18n::t($key, 'te');
    if ($en === $te || empty($te)) return htmlspecialchars($en);
    return htmlspecialchars($en) . ' <span class="te-pdf">' . htmlspecialchars($te) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Joining_Form_<?php echo htmlspecialchars($application['application_no']); ?>_VK_Logistics_Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm 15mm 15mm;
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
            background: #e2e8f0;
            color: #0f172a;
            font-size: 11pt;
            line-height: 1.35;
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .pdf-page {
            width: 210mm;
            min-height: 297mm;
            padding: 16mm 18mm;
            margin: 15mm auto;
            background: white;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            position: relative;
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
                width: 100%;
                min-height: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }

        .pdf-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pdf-brand-title {
            font-size: 16pt;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin: 0;
        }

        .pdf-brand-tagline {
            font-size: 8.5pt;
            font-weight: 700;
            color: #2563eb;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .pdf-doc-meta {
            text-align: right;
            font-size: 8pt;
            font-weight: 700;
            color: #64748b;
        }

        .pdf-form-title-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #2563eb;
            padding: 8px 12px;
            margin-bottom: 14px;
        }

        .pdf-form-title-box h3 {
            margin: 0;
            font-size: 13pt;
            font-weight: 800;
            color: #0f172a;
        }

        .pdf-form-title-box p {
            margin: 2px 0 0 0;
            font-size: 8.5pt;
            color: #475569;
        }

        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9.5pt;
        }

        .pdf-table th, .pdf-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .pdf-table th {
            background-color: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
            width: 32%;
        }

        .pdf-sec-title {
            background: #0f172a;
            color: white;
            font-size: 10.5pt;
            font-weight: 700;
            padding: 5px 10px;
            margin: 12px 0 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .te-pdf {
            font-size: 8.5pt;
            font-weight: 500;
            color: #475569;
            margin-left: 4px;
        }

        .pdf-photo-box {
            width: 32mm;
            height: 40mm;
            border: 1px solid #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            text-align: center;
            overflow: hidden;
        }

        .pdf-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pdf-footer {
            position: absolute;
            bottom: 10mm;
            left: 18mm;
            right: 18mm;
            border-top: 1px solid #cbd5e1;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
            font-size: 7.5pt;
            color: #64748b;
        }

        .policy-p {
            font-size: 9pt;
            margin-bottom: 6px;
            text-align: justify;
        }

        .sig-box {
            border: 1px solid #cbd5e1;
            height: 24mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            padding: 4px;
        }

        .sig-box img {
            max-height: 20mm;
            max-width: 100%;
        }
    </style>
</head>
<body>

    <!-- Print Control Toolbar -->
    <div class="no-print">
        <div>
            <strong>VK Logistics Hub</strong> &bull; Official Employee Joining Record (Application: <?php echo htmlspecialchars($application['application_no']); ?>)
        </div>
        <div>
            <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 6px 16px; border-radius: 20px; font-weight: bold; cursor: pointer; margin-right: 8px;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <button onclick="window.close()" style="background: #475569; color: white; border: none; padding: 6px 14px; border-radius: 20px; cursor: pointer;">
                Close
            </button>
        </div>
    </div>

    <!-- ================= PAGE 1 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>EMPLOYEE RECORD</div>
                <div>HUMAN RESOURCES</div>
                <div style="font-family: monospace; color: #0f172a;"><?php echo htmlspecialchars($application['application_no']); ?></div>
            </div>
        </div>

        <div class="pdf-form-title-box">
            <h3>Employee Information & Joining Form / ఉద్యోగి సమాచారం & చేరిక ఫారమ్</h3>
            <p>For new and existing employees in all departments. Complete the details below and submit the form to HR.</p>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
            <div style="flex: 1; margin-right: 15px;">
                <table class="pdf-table" style="margin-bottom: 0;">
                    <tr>
                        <th><?php echo p_lbl('employee_type'); ?></th>
                        <td>
                            <strong>[ <?php echo $application['employee_type'] === 'new_joiner' ? 'X' : '&nbsp;'; ?> ]</strong> New joiner (కొత్త ఉద్యోగి) &nbsp;&nbsp;
                            <strong>[ <?php echo $application['employee_type'] === 'existing_employee' ? 'X' : '&nbsp;'; ?> ]</strong> Existing employee (ప్రస్తుత ఉద్యోగి)
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo p_lbl('submission_date'); ?></th>
                        <td><?php echo date('d-M-Y', strtotime($application['submitted_at'] ?? $application['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo p_lbl('employee_id'); ?></th>
                        <td style="font-family: monospace; font-weight: bold; color: #2563eb;">
                            <?php echo htmlspecialchars($approval['employee_id_assigned'] ?? $personal['employee_id'] ?? 'Pending HR Approval'); ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="pdf-photo-box">
                <?php if (!empty($personal['photo_path']) && file_exists(__DIR__ . '/../../' . $personal['photo_path'])): ?>
                    <img src="../../<?php echo htmlspecialchars($personal['photo_path']); ?>" alt="Photo">
                <?php else: ?>
                    <span style="font-size: 7.5pt; color: #94a3b8;">Passport-size<br>Photograph<br>(పాస్‌పోర్ట్ సైజు ఫోటో)</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>1. Employee Details / ఉద్యోగి వివరాలు</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('full_name'); ?></th>
                <td colspan="3"><strong><?php echo htmlspecialchars($personal['full_name'] ?? ''); ?></strong></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('father_name'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($personal['father_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('dob'); ?></th>
                <td><?php echo !empty($personal['dob']) ? date('d-M-Y', strtotime($personal['dob'])) : ''; ?></td>
                <th style="width: 20%;"><?php echo p_lbl('gender'); ?></th>
                <td><?php echo ucfirst($personal['gender'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('mobile_number'); ?></th>
                <td><?php echo htmlspecialchars($personal['mobile'] ?? ''); ?></td>
                <th><?php echo p_lbl('alternate_mobile'); ?></th>
                <td><?php echo htmlspecialchars($personal['alternate_mobile'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('email_address'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($personal['email'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('designation'); ?></th>
                <td><strong><?php echo htmlspecialchars($personal['designation'] ?? ''); ?></strong></td>
                <th><?php echo p_lbl('department'); ?></th>
                <td><?php echo htmlspecialchars($application['department_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('date_of_joining'); ?></th>
                <td><?php echo !empty($personal['joining_date']) ? date('d-M-Y', strtotime($personal['joining_date'])) : ''; ?></td>
                <th><?php echo p_lbl('blood_group'); ?></th>
                <td><?php echo htmlspecialchars($personal['blood_group'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('previous_experience'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($personal['previous_work_experience'] ?? 'Fresher / None'); ?></td>
            </tr>
        </table>

        <div class="pdf-sec-title">
            <span>2. Present Address / ప్రస్తుత చిరునామా</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('house_flat_no'); ?></th>
                <td><?php echo htmlspecialchars($present_address['house_flat_no'] ?? ''); ?></td>
                <th><?php echo p_lbl('street_area'); ?></th>
                <td><?php echo htmlspecialchars($present_address['street_area'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('village_town'); ?></th>
                <td><?php echo htmlspecialchars($present_address['village_town'] ?? ''); ?></td>
                <th><?php echo p_lbl('mandal'); ?></th>
                <td><?php echo htmlspecialchars($present_address['mandal'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('district'); ?></th>
                <td><?php echo htmlspecialchars($present_address['district'] ?? ''); ?></td>
                <th><?php echo p_lbl('state'); ?> / <?php echo p_lbl('pincode'); ?></th>
                <td><?php echo htmlspecialchars($present_address['state'] ?? ''); ?> - <strong><?php echo htmlspecialchars($present_address['pincode'] ?? ''); ?></strong></td>
            </tr>
        </table>

        <div class="pdf-sec-title">
            <span>3. Permanent Address / శాశ్వత చిరునామా</span>
        </div>
        <div style="font-size: 8.5pt; margin-bottom: 6px;">
            Same as present address? / ప్రస్తుత చిరునామా మరియు శాశ్వత చిరునామా ఒకటేనా? 
            <strong>[ <?php echo !empty($permanent_address['is_same_as_present']) ? 'X' : '&nbsp;'; ?> ] Yes / అవును</strong> &nbsp;&nbsp;
            <strong>[ <?php echo empty($permanent_address['is_same_as_present']) ? 'X' : '&nbsp;'; ?> ] No / కాదు</strong>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('house_flat_no'); ?></th>
                <td><?php echo htmlspecialchars($permanent_address['house_flat_no'] ?? $present_address['house_flat_no'] ?? ''); ?></td>
                <th><?php echo p_lbl('street_area'); ?></th>
                <td><?php echo htmlspecialchars($permanent_address['street_area'] ?? $present_address['street_area'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('village_town'); ?></th>
                <td><?php echo htmlspecialchars($permanent_address['village_town'] ?? $present_address['village_town'] ?? ''); ?></td>
                <th><?php echo p_lbl('district'); ?> / PIN</th>
                <td><?php echo htmlspecialchars($permanent_address['district'] ?? $present_address['district'] ?? ''); ?> - <?php echo htmlspecialchars($permanent_address['pincode'] ?? $present_address['pincode'] ?? ''); ?></td>
            </tr>
        </table>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Employee Joining Form</span>
            <span>Page 1 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 2 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>EMPLOYEE RECORD</div>
                <div>PAGE 2 OF 9</div>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>4. Identity Documents / గుర్తింపు పత్రాలు</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('aadhaar_number'); ?></th>
                <td style="font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($identity['aadhaar_masked'] ?? ''); ?></td>
                <th><?php echo p_lbl('other_id_proof'); ?></th>
                <td><?php echo htmlspecialchars($identity['other_id_type'] ?? 'None'); ?>: <?php echo htmlspecialchars($identity['other_id_number'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('documents_submitted'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($identity['documents_submitted'] ?? 'Aadhaar, PAN, Bank Passbook'); ?></td>
            </tr>
            <tr>
                <th>Verified By / ధృవీకరించిన వారు</th>
                <td colspan="3"><?php echo htmlspecialchars($approval['verified_by_name'] ?? 'HR Officer (Official Check)'); ?></td>
            </tr>
        </table>

        <div class="pdf-sec-title">
            <span>5. Emergency Contact Details / అత్యవసర సంప్రదింపు వివరాలు</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('full_name'); ?></th>
                <td><strong><?php echo htmlspecialchars($emergency['full_name'] ?? ''); ?></strong></td>
                <th><?php echo p_lbl('relationship'); ?></th>
                <td><?php echo htmlspecialchars($emergency['relationship'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('mobile_number'); ?></th>
                <td><?php echo htmlspecialchars($emergency['mobile'] ?? ''); ?></td>
                <th><?php echo p_lbl('alternate_mobile'); ?></th>
                <td><?php echo htmlspecialchars($emergency['alternate_mobile'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('address'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($emergency['address'] ?? ''); ?></td>
            </tr>
        </table>

        <div class="pdf-sec-title">
            <span>6. References / రిఫరెన్సులు</span>
        </div>
        <div style="font-weight: bold; font-size: 9.5pt; margin-bottom: 4px; color: #2563eb;">Reference 1 / రిఫరెన్స్ 1</div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('full_name'); ?></th>
                <td><?php echo htmlspecialchars($ref1['full_name'] ?? ''); ?></td>
                <th><?php echo p_lbl('relationship_how_known'); ?></th>
                <td><?php echo htmlspecialchars($ref1['relationship_how_known'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('mobile_number'); ?></th>
                <td><?php echo htmlspecialchars($ref1['mobile'] ?? ''); ?></td>
                <th><?php echo p_lbl('address'); ?></th>
                <td><?php echo htmlspecialchars($ref1['address'] ?? ''); ?></td>
            </tr>
        </table>

        <div style="font-weight: bold; font-size: 9.5pt; margin-top: 10px; margin-bottom: 4px; color: #2563eb;">Reference 2 / రిఫరెన్స్ 2</div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('full_name'); ?></th>
                <td><?php echo htmlspecialchars($ref2['full_name'] ?? ''); ?></td>
                <th><?php echo p_lbl('relationship_how_known'); ?></th>
                <td><?php echo htmlspecialchars($ref2['relationship_how_known'] ?? ''); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('mobile_number'); ?></th>
                <td><?php echo htmlspecialchars($ref2['mobile'] ?? ''); ?></td>
                <th><?php echo p_lbl('address'); ?></th>
                <td><?php echo htmlspecialchars($ref2['address'] ?? ''); ?></td>
            </tr>
        </table>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Employee Joining Form</span>
            <span>Page 2 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 3 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>EMPLOYEE RECORD</div>
                <div>PAGE 3 OF 9</div>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>7. Previous Employment / మునుపటి ఉద్యోగం</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th><?php echo p_lbl('prev_employer'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($prev_emp['employer_name'] ?? 'Fresher / No prior organization'); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('prev_designation'); ?></th>
                <td><?php echo htmlspecialchars($prev_emp['designation'] ?? '-'); ?></td>
                <th><?php echo p_lbl('prev_employer_phone'); ?></th>
                <td><?php echo htmlspecialchars($prev_emp['employer_phone'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th><?php echo p_lbl('reason_for_leaving'); ?></th>
                <td colspan="3"><?php echo htmlspecialchars($prev_emp['reason_for_leaving'] ?? '-'); ?></td>
            </tr>
        </table>

        <div style="margin-top: 40px; padding: 20px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
            <h5 style="margin-top: 0; color: #0f172a;">Candidate Work History Verification Status</h5>
            <p style="font-size: 8.5pt; color: #64748b; margin-bottom: 0;">
                All credentials, prior experience records, and relieving documents submitted by the employee are subject to verification under VK Logistics Hub onboarding protocols.
            </p>
        </div>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Employee Joining Form</span>
            <span>Page 3 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 4 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>TERMS & CONDITIONS</div>
                <div>PAGE 4 OF 9</div>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>8. Employment Terms and Conditions / ఉపాధి నిబంధనలు మరియు షరతులు</span>
        </div>
        <p style="font-size: 8.5pt; color: #475569; margin-bottom: 12px;">
            These terms apply to your employment with VK Logistics Hub. Please read each section carefully.
        </p>

        <!-- Term 1 -->
        <div style="margin-bottom: 12px; border-left: 3px solid #2563eb; padding-left: 8px;">
            <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a;">1. Probation and confirmation / ప్రొబేషన్ మరియు నిర్ధారణ</div>
            <p class="policy-p"><?php echo JoiningI18n::t('terms_clause1_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('terms_clause1_text1', 'te'); ?></p>
        </div>

        <!-- Term 2 -->
        <div style="margin-bottom: 12px; border-left: 3px solid #2563eb; padding-left: 8px;">
            <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a;">2. Leave and attendance / సెలవు మరియు హాజరు</div>
            <p class="policy-p"><?php echo JoiningI18n::t('terms_clause2_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('terms_clause2_text1', 'te'); ?></p>
        </div>

        <!-- Term 3 -->
        <div style="margin-bottom: 12px; border-left: 3px solid #2563eb; padding-left: 8px;">
            <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a;">3. Resignation, notice and handover / రాజీనామా, నోటీసు మరియు అప్పగింత</div>
            <p class="policy-p"><?php echo JoiningI18n::t('terms_clause3_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('terms_clause3_text1', 'te'); ?></p>
        </div>

        <!-- Term 4 -->
        <div style="margin-bottom: 12px; border-left: 3px solid #2563eb; padding-left: 8px;">
            <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a;">4. Company property and conduct / కంపెనీ ఆస్తి మరియు ప్రవర్తన</div>
            <p class="policy-p"><?php echo JoiningI18n::t('terms_clause4_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('terms_clause4_text1', 'te'); ?></p>
        </div>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Employment Terms & Conditions</span>
            <span>Page 4 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 5 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>ASSOCIATE POLICY SUMMARY</div>
                <div>PAGE 5 OF 9</div>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>9. Associate Leave and Pickup Incentive Policy / అసోసియేట్ సెలవు మరియు పికప్ ప్రోత్సాహక విధానం</span>
        </div>
        <p style="font-size: 8.5pt; color: #2563eb; font-weight: bold; margin-bottom: 10px;">
            Applicable to Pickup Associates / పికప్ అసోసియేట్‌లకు వర్తిస్తుంది
        </p>

        <div style="display: flex; gap: 15px; margin-bottom: 12px;">
            <div style="flex: 1;">
                <div style="font-weight: bold; font-size: 9pt; margin-bottom: 4px;">12 Designated Paid Holidays:</div>
                <table class="pdf-table" style="font-size: 8pt; margin-bottom: 0;">
                    <?php
                    $h_en = JoiningI18n::$translations['en']['holiday_list'];
                    for ($i = 0; $i < 6; $i++):
                    ?>
                        <tr><td><?php echo htmlspecialchars($h_en[$i]); ?></td></tr>
                    <?php endfor; ?>
                </table>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: bold; font-size: 9pt; margin-bottom: 4px;">&nbsp;</div>
                <table class="pdf-table" style="font-size: 8pt; margin-bottom: 0;">
                    <?php
                    for ($i = 6; $i < 12; $i++):
                    ?>
                        <tr><td><?php echo htmlspecialchars($h_en[$i]); ?></td></tr>
                    <?php endfor; ?>
                </table>
            </div>
        </div>

        <div style="font-weight: bold; font-size: 9pt; margin-top: 10px; margin-bottom: 4px;">Daily Pickup Targets & Extra Pickup Bonus Examples:</div>
        <table class="pdf-table" style="font-size: 8.5pt;">
            <tr style="background: #f1f5f9;">
                <th>Daily Target</th>
                <th>Completed Pickups</th>
                <th>Bonus Pickups</th>
            </tr>
            <tr>
                <td>3 Pickups</td>
                <td>3 Completed</td>
                <td>0 (Target Met)</td>
            </tr>
            <tr>
                <td>3 Pickups</td>
                <td>4 Completed</td>
                <td><strong>1 Bonus Pickup</strong></td>
            </tr>
            <tr>
                <td>4 Pickups</td>
                <td>4 Completed</td>
                <td>0 (Target Met)</td>
            </tr>
            <tr>
                <td>4 Pickups</td>
                <td>6 Completed</td>
                <td><strong>2 Bonus Pickups</strong></td>
            </tr>
        </table>

        <p style="font-size: 8pt; color: #475569;">
            * In addition, Telangana Formation Day (June 2) and May Day (May 1) are recognized per state statutory directives.
        </p>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Associate Policy Summary</span>
            <span>Page 5 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 6 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>DECLARATION & APPROVAL</div>
                <div>PAGE 6 OF 9</div>
            </div>
        </div>

        <div class="pdf-sec-title">
            <span>10. Employee Declaration / ఉద్యోగి డిక్లరేషన్</span>
        </div>
        <div style="font-size: 8.5pt; line-height: 1.4; margin-bottom: 12px;">
            <p class="policy-p"><strong>1. Accuracy of information:</strong> <?php echo JoiningI18n::t('decl_point1', 'en'); ?></p>
            <p class="policy-p"><strong>2. Employment terms:</strong> <?php echo JoiningI18n::t('decl_point2', 'en'); ?></p>
            <p class="policy-p"><strong>3. Company policies:</strong> <?php echo JoiningI18n::t('decl_point3', 'en'); ?></p>
            <p class="policy-p"><strong>4. Resignation and notice:</strong> <?php echo JoiningI18n::t('decl_point4', 'en'); ?></p>
            <p class="policy-p"><strong>5. Use of information:</strong> <?php echo JoiningI18n::t('decl_point5', 'en'); ?></p>
        </div>

        <table class="pdf-table" style="margin-bottom: 16px;">
            <tr>
                <th>Signature of Employee / ఉద్యోగి సంతకం</th>
                <th>Left Thumb Impression (Optional) / ఎడమ బొటనవేలు ముద్ర</th>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <div class="sig-box">
                        <?php if (!empty($signature['signature_image_path']) && file_exists(__DIR__ . '/../../' . $signature['signature_image_path'])): ?>
                            <img src="../../<?php echo htmlspecialchars($signature['signature_image_path']); ?>" alt="Signature">
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 8pt;">Digital Signature Recorded</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 7.5pt; color: #64748b; margin-top: 3px; text-align: center;">
                        Date: <?php echo !empty($signature['signed_at']) ? date('d-M-Y', strtotime($signature['signed_at'])) : date('d-M-Y'); ?> &bull; Name: <?php echo htmlspecialchars($personal['full_name'] ?? ''); ?>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="sig-box">
                        <?php if (!empty($signature['thumb_impression_path']) && file_exists(__DIR__ . '/../../' . $signature['thumb_impression_path'])): ?>
                            <img src="../../<?php echo htmlspecialchars($signature['thumb_impression_path']); ?>" alt="Thumb">
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 8pt;">(Ink impression optional)</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 7.5pt; color: #64748b; margin-top: 3px; text-align: center;">
                        Desig: <?php echo htmlspecialchars($personal['designation'] ?? ''); ?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="pdf-sec-title">
            <span>11. For HR / Office Use Only / హెచ్‌ఆర్ / కార్యాలయ ఉపయోగం కోసం మాత్రమే</span>
        </div>
        <table class="pdf-table">
            <tr>
                <th>Documents Verified By / ధృవీకరించిన వారు</th>
                <td><?php echo htmlspecialchars($approval['verified_by_name'] ?? 'HR Operations Team'); ?></td>
            </tr>
            <tr>
                <th>Employee ID Assigned / కేటాయించిన ఉద్యోగి ఐడీ</th>
                <td style="font-family: monospace; font-weight: bold; font-size: 11pt; color: #2563eb;">
                    <?php echo htmlspecialchars($approval['employee_id_assigned'] ?? $personal['employee_id'] ?? 'VK-EMP-XXXX'); ?>
                </td>
            </tr>
            <tr>
                <th>Date of Joining Confirmed / నిర్ధారించబడిన చేరిక తేదీ</th>
                <td><strong><?php echo !empty($approval['joining_date_confirmed']) ? date('d-M-Y', strtotime($approval['joining_date_confirmed'])) : date('d-M-Y'); ?></strong></td>
            </tr>
            <tr>
                <th>Approved By & Signature / ఆమోదించిన వారు మరియు సంతకం</th>
                <td>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($approval['verified_by_name'] ?? 'Authorized HR Signatory'); ?></strong><br>
                            <span style="font-size: 7.5pt; color: #64748b;">Human Resources Management &bull; VK Logistics Hub</span>
                        </div>
                        <div class="sig-box" style="width: 45mm; height: 18mm;">
                            <?php if (!empty($approval['hr_signature_path']) && file_exists(__DIR__ . '/../../' . $approval['hr_signature_path'])): ?>
                                <img src="../../<?php echo htmlspecialchars($approval['hr_signature_path']); ?>" alt="HR Sig">
                            <?php else: ?>
                                <span style="color: #10b981; font-size: 8pt; font-weight: bold;"><i class="fas fa-check-circle"></i> HR APPROVED</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Employee Declaration & Approval</span>
            <span>Page 6 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 7 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>ASSOCIATE POLICY</div>
                <div>PAGE 7 OF 9</div>
            </div>
        </div>

        <h4 style="text-align: center; margin: 0 0 15px 0; color: #0f172a;">
            Associate Leave & Pickup Incentive Policy<br>
            <span style="font-size: 10pt; font-weight: normal; color: #64748b;">అసోసియేట్ సెలవు మరియు పికప్ ప్రోత్సాహక విధానం</span>
        </h4>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">1. Purpose of this policy విధాన ఉద్దేశ్యం</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec1_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec1_text1', 'te'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">2. Annual leave and paid holidays వార్షిక సెలవు మరియు వేతనంతో కూడిన సెలవులు</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec2_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec2_text1', 'te'); ?></p>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec2_text2', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec2_text2', 'te'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">3. How to request leave సెలవును ఎలా అభ్యర్థించాలి</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec3_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec3_text1', 'te'); ?></p>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec3_text2', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec3_text2', 'te'); ?></p>
        </div>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Associate Policy Detail</span>
            <span>Page 7 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 8 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>ASSOCIATE POLICY</div>
                <div>PAGE 8 OF 9</div>
            </div>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">4. Returning after a Sunday, holiday or approved leave</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec4_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec4_text1', 'te'); ?></p>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec4_text2', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec4_text2', 'te'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">5. Sick leave and emergencies అనారోగ్య సెలవు మరియు అత్యవసర పరిస్థితులు</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec5_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec5_text1', 'te'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">6. Two-week induction and training రెండు వారాల పరిచయం మరియు శిక్షణ</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec6_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec6_text1', 'te'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">7. Agreed daily pickup target అంగీకరించిన రోజువారీ పికప్ లక్ష్యం</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec7_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec7_text1', 'te'); ?></p>
        </div>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Associate Policy Detail</span>
            <span>Page 8 of 9</span>
        </div>
    </div>

    <!-- ================= PAGE 9 ================= -->
    <div class="pdf-page">
        <div class="pdf-header">
            <div>
                <img src="../../assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB" style="max-height: 42px; width: auto; object-fit: contain;">
            </div>
            <div class="pdf-doc-meta">
                <div>ASSOCIATE POLICY SIGN-OFF</div>
                <div>PAGE 9 OF 9</div>
            </div>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">8. Bonus for extra pickups అదనపు పికప్‌లకు బోనస్</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec8_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec8_text1', 'te'); ?></p>
            <p class="policy-p"><strong>Example 1:</strong> <?php echo JoiningI18n::t('policy_sec8_example1', 'en'); ?></p>
            <p class="policy-p"><strong>Example 2:</strong> <?php echo JoiningI18n::t('policy_sec8_example2', 'en'); ?></p>
        </div>

        <div style="margin-bottom: 12px;">
            <div style="font-weight: bold; font-size: 10pt; color: #2563eb;">9. Shift hours and daily records షిఫ్ట్ గంటలు మరియు రోజువారీ రికార్డులు</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec9_text1', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec9_text1', 'te'); ?></p>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_sec9_text2', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_sec9_text2', 'te'); ?></p>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px;">
            <div style="font-weight: bold; font-size: 9.5pt; color: #0f172a; margin-bottom: 4px;">Policy administration విధాన నిర్వహణ</div>
            <p class="policy-p"><?php echo JoiningI18n::t('policy_admin_text', 'en'); ?></p>
            <p class="policy-p te-pdf" style="margin-left: 0;"><?php echo JoiningI18n::t('policy_admin_text', 'te'); ?></p>
            <div style="font-weight: bold; font-size: 9pt; margin-top: 10px; color: #2563eb;">
                HR Contact: hr@vkinnect.com | hr.vkinnect@gmail.com
            </div>
        </div>

        <div style="margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <div style="font-size: 9pt; font-weight: bold;">Associate Receipt & Agreement Sign-off:</div>
                    <div style="font-size: 8pt; color: #64748b;">Digitally acknowledged upon submission of joining form.</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 9pt; font-weight: bold;">VK Logistics Hub HR Directorate</div>
                    <div style="font-size: 8pt; color: #64748b;">Official Company Document &bull; Confidential</div>
                </div>
            </div>
        </div>

        <div class="pdf-footer">
            <span>VK Logistics Hub &bull; Policy Administration</span>
            <span>Page 9 of 9</span>
        </div>
    </div>

</body>
</html>
