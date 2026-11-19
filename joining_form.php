<?php
/**
 * VK Logistics Hub - Electronic Employee Joining Form (Bilingual: English + Telugu)
 * Converted from: VK_Logistics_Hub_Bilingual_Joining_Form_and_Associate_Policy.pdf (Pages 1 - 9)
 * Stylized in TCS iON / Digialm Recruitment Portal Format (BEL / Government Application Form Style)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/joining_i18n.php';

$db = getDB();

// Determine language (default to bilingual/en)
$lang = isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'te', 'bilingual']) ? $_GET['lang'] : 'bilingual';
$is_embedded = (isset($_GET['embedded']) && $_GET['embedded'] == '1') || (isset($_POST['is_embedded']) && $_POST['is_embedded'] == '1');

// Look up application by token or create new if permitted
$token = $_GET['token'] ?? null;
$application = null;
$personal = null;
$present_address = null;
$permanent_address = null;
$identity = null;
$emergency = null;
$ref1 = null;
$ref2 = null;
$prev_emp = null;
$declaration = null;
$signature = null;
$uploaded_docs = [];

if ($token) {
    $stmt = $db->prepare("SELECT * FROM employee_joining_applications WHERE token = ?");
    $stmt->execute([$token]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no token or not found, handle standalone new application creation
if (!$application) {
    // Generate new unique application reference safely without collisions
    $yearMonth = date('Ym');
    $stmt = $db->query("SELECT application_no FROM employee_joining_applications WHERE application_no LIKE 'VK-JOIN-{$yearMonth}-%' ORDER BY id DESC LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $max_num = 0;
    if ($row && !empty($row['application_no']) && preg_match('/-(\d+)$/', $row['application_no'], $m)) {
        $max_num = intval($m[1]);
    }
    $cstmt = $db->query("SELECT COUNT(*) as cnt FROM employee_joining_applications WHERE application_no LIKE 'VK-JOIN-{$yearMonth}-%'");
    $cnt = $cstmt ? ($cstmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) : 0;
    $next_num = max($max_num, $cnt) + 1;
    
    $new_token = null;
    $inserted = false;
    for ($attempt = 0; $attempt < 15; $attempt++) {
        $app_no = sprintf('VK-JOIN-%s-%04d', $yearMonth, $next_num + $attempt);
        $new_token = bin2hex(random_bytes(24));
        try {
            $ins = $db->prepare("INSERT INTO employee_joining_applications (application_no, employee_type, token, status, created_at) VALUES (?, 'new_joiner', ?, 'in_progress', NOW())");
            $ins->execute([$app_no, $new_token]);
            $app_id = $db->lastInsertId();
            $inserted = true;
            break;
        } catch (PDOException $pe) {
            if ($pe->getCode() == 23000 || strpos($pe->getMessage(), '1062') !== false) {
                continue;
            }
            error_log("Joining Form Token Creation Error: " . $pe->getMessage());
            break;
        }
    }
    
    if ($inserted && $new_token) {
        $emb_param = $is_embedded ? '&embedded=1' : '';
        header("Location: joining_form.php?token={$new_token}&lang={$lang}{$emb_param}");
        exit;
    } else {
        error_log("Failed to initialize joining application after multiple attempts");
        die("Unable to initialize new application at this time. Please try again.");
    }
}

$app_id = $application['id'];

// If application is already submitted or approved, show confirmation/status view unless correction_required or HR management
$is_editable = in_array($application['status'], ['draft', 'sent_to_employee', 'in_progress', 'correction_required']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'management');

// Load existing data if any
$stmt = $db->prepare("SELECT * FROM employee_personal_details WHERE application_id = ?");
$stmt->execute([$app_id]);
$personal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_addresses WHERE application_id = ? AND address_type = 'present'");
$stmt->execute([$app_id]);
$present_address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_addresses WHERE application_id = ? AND address_type = 'permanent'");
$stmt->execute([$app_id]);
$permanent_address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_identity_documents WHERE application_id = ?");
$stmt->execute([$app_id]);
$identity = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_emergency_contacts WHERE application_id = ?");
$stmt->execute([$app_id]);
$emergency = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_references WHERE application_id = ? AND reference_number = 1");
$stmt->execute([$app_id]);
$ref1 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_references WHERE application_id = ? AND reference_number = 2");
$stmt->execute([$app_id]);
$ref2 = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_previous_employment WHERE application_id = ?");
$stmt->execute([$app_id]);
$prev_emp = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_declarations WHERE application_id = ?");
$stmt->execute([$app_id]);
$declaration = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_signatures WHERE application_id = ?");
$stmt->execute([$app_id]);
$signature = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("SELECT * FROM employee_uploaded_documents WHERE application_id = ?");
$stmt->execute([$app_id]);
$uploaded_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for dropdown
$departments = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle POST Submission (Draft save or Final Submit)
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_editable) {
    $action = $_POST['action'] ?? 'save_draft'; // 'save_draft' or 'submit_form'
    
    // Form fields
    $employee_type = $_POST['employee_type'] ?? 'new_joiner';
    $employee_id = trim($_POST['employee_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other']) ? $_POST['gender'] : 'male';
    $mobile = trim($_POST['mobile'] ?? '');
    $alternate_mobile = trim($_POST['alternate_mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $joining_date = trim($_POST['joining_date'] ?? '');
    $nationality = trim($_POST['nationality'] ?? 'Indian');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $previous_work_experience = trim($_POST['previous_work_experience'] ?? '');
    
    // Clean dates to valid Y-m-d or null
    $dob_clean = (!empty($dob) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) ? $dob : null;
    $joining_date_clean = (!empty($joining_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $joining_date)) ? $joining_date : null;
    
    // Present address
    $p_house = trim($_POST['present_house_flat_no'] ?? '');
    $p_street = trim($_POST['present_street_area'] ?? '');
    $p_village = trim($_POST['present_village_town'] ?? '');
    $p_mandal = trim($_POST['present_mandal'] ?? '');
    $p_district = trim($_POST['present_district'] ?? '');
    $p_state = trim($_POST['present_state'] ?? '');
    $p_pincode = trim($_POST['present_pincode'] ?? '');
    
    // Permanent address
    $same_as_present = isset($_POST['same_as_present']) ? 1 : 0;
    if ($same_as_present) {
        $perm_house = $p_house;
        $perm_street = $p_street;
        $perm_village = $p_village;
        $perm_mandal = $p_mandal;
        $perm_district = $p_district;
        $perm_state = $p_state;
        $perm_pincode = $p_pincode;
    } else {
        $perm_house = trim($_POST['perm_house_flat_no'] ?? '');
        $perm_street = trim($_POST['perm_street_area'] ?? '');
        $perm_village = trim($_POST['perm_village_town'] ?? '');
        $perm_mandal = trim($_POST['perm_mandal'] ?? '');
        $perm_district = trim($_POST['perm_district'] ?? '');
        $perm_state = trim($_POST['perm_state'] ?? '');
        $perm_pincode = trim($_POST['perm_pincode'] ?? '');
    }
    
    // Identity details
    $aadhaar_raw = preg_replace('/\D/', '', $_POST['aadhaar_number'] ?? '');
    $aadhaar_last4 = substr($aadhaar_raw, -4);
    $aadhaar_masked = strlen($aadhaar_raw) === 12 
        ? 'XXXX-XXXX-' . $aadhaar_last4 
        : (trim($_POST['aadhaar_number'] ?? ''));
        
    $other_id_type = trim($_POST['other_id_type'] ?? '');
    $other_id_number = trim($_POST['other_id_number'] ?? '');
    $documents_submitted = isset($_POST['docs_submitted']) && is_array($_POST['docs_submitted']) 
        ? implode(', ', $_POST['docs_submitted']) 
        : trim($_POST['documents_submitted'] ?? '');
        
    // Bank details (Mandatory)
    $bank_holder_name = trim($_POST['bank_holder_name'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_branch = trim($_POST['bank_branch'] ?? '');
    $bank_account_no = trim($_POST['bank_account_no'] ?? '');
    $bank_ifsc = strtoupper(trim($_POST['bank_ifsc'] ?? ''));
        
    // Emergency contact (Mandatory)
    $emg_name = trim($_POST['emergency_name'] ?? '');
    $emg_rel = trim($_POST['emergency_rel'] ?? '');
    $emg_mobile = trim($_POST['emergency_mobile'] ?? '');
    $emg_alt_mobile = trim($_POST['emergency_alt_mobile'] ?? '');
    $emg_address = trim($_POST['emergency_address'] ?? '');
    
    // References (Optional / Skippable)
    $ref1_name = trim($_POST['ref1_name'] ?? '');
    $ref1_rel = trim($_POST['ref1_rel'] ?? '');
    $ref1_mobile = trim($_POST['ref1_mobile'] ?? '');
    $ref1_address = trim($_POST['ref1_address'] ?? '');
    
    $ref2_name = trim($_POST['ref2_name'] ?? '');
    $ref2_rel = trim($_POST['ref2_rel'] ?? '');
    $ref2_mobile = trim($_POST['ref2_mobile'] ?? '');
    $ref2_address = trim($_POST['ref2_address'] ?? '');
    
    // Previous employment (Optional / Skippable)
    $prev_employer = trim($_POST['prev_employer'] ?? '');
    $prev_desig = trim($_POST['prev_desig'] ?? '');
    $prev_phone = trim($_POST['prev_phone'] ?? '');
    $prev_reason = trim($_POST['prev_reason'] ?? '');
    
    // Declarations & Policies (All Ticks Mandatory)
    $terms_ack = isset($_POST['terms_acknowledged']) ? 1 : 0;
    $data_consent = isset($_POST['data_consent_acknowledged']) ? 1 : 0;
    $pickup_policy_ack = isset($_POST['pickup_policy_acknowledged']) ? 1 : 0;
    $is_pickup_role = (stripos($designation, 'Pickup') !== false || stripos($designation, 'Delivery') !== false);
    
    // Signature data
    $signature_data = $_POST['signature_data'] ?? '';
    
    // Mandatory checks if final submit
    if ($action === 'submit_form') {
        // Personal details all mandatory
        if (empty($full_name)) $errors[] = "Full Name is required / పూర్తి పేరు అవసరం.";
        if (empty($father_name)) $errors[] = "Father's Name is required / తండ్రి పేరు అవసరం.";
        if (empty($dob)) $errors[] = "Date of Birth is required / పుట్టిన తేదీ అవసరం.";
        if (empty($gender)) $errors[] = "Gender is required / లింగం ఎంచుకోవాలి.";
        if (empty($blood_group)) $errors[] = "Blood Group is required / రక్త వర్గం అవసరం.";
        if (empty($designation)) $errors[] = "Designation is required / ఉద్యోగ హోదా అవసరం.";
        if (empty($department_id)) $errors[] = "Department is required / విభాగం అవసరం.";
        if (empty($joining_date)) $errors[] = "Date of Joining is required / చేరిక తేదీ అవసరం.";
        if (empty($nationality)) $errors[] = "Nationality is required / జాతీయత తప్పనిసరి.";

        // Aadhaar mandatory
        if (empty($aadhaar_masked) || strlen($aadhaar_raw) !== 12) {
            $errors[] = "Valid 12-digit Aadhaar Number is mandatory / 12 అంకెల ఆధార్ సంఖ్య తప్పనిసరి.";
        }

        // Bank details all mandatory
        if (empty($bank_holder_name)) $errors[] = "Name of Bank Account Holder is mandatory / ఖాతాదారుని పేరు తప్పనిసరి.";
        if (empty($bank_name)) $errors[] = "Bank Name is mandatory / బ్యాంకు పేరు తప్పనిసరి.";
        if (empty($bank_branch)) $errors[] = "Bank Branch is mandatory / బ్యాంకు బ్రాంచ్ తప్పనిసరి.";
        if (empty($bank_account_no)) $errors[] = "Bank Account Number is mandatory / బ్యాంకు ఖాతా సంఖ్య తప్పనిసరి.";
        if (empty($bank_ifsc)) $errors[] = "Bank IFSC Code is mandatory / బ్యాంకు IFSC కోడ్ తప్పనిసరి.";

        // Contact details all mandatory
        if (empty($mobile) || strlen(preg_replace('/\D/', '', $mobile)) !== 10) {
            $errors[] = "Valid 10-digit Mobile Number is required / 10 అంకెల మొబైల్ నంబర్ అవసరం.";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid Email Address is required / సరైన ఈమెయిల్ చిరునామా అవసరం.";
        }

        // Address details all mandatory
        if (empty($p_house) || empty($p_street) || empty($p_village) || empty($p_mandal) || empty($p_district) || empty($p_state) || empty($p_pincode)) {
            $errors[] = "Present address details (all fields) are mandatory / ప్రస్తుత చిరునామా వివరాలన్నీ తప్పనిసరి.";
        }
        if (!$same_as_present) {
            if (empty($perm_house) || empty($perm_street) || empty($perm_village) || empty($perm_mandal) || empty($perm_district) || empty($perm_state) || empty($perm_pincode)) {
                $errors[] = "Permanent address details (all fields) are mandatory / శాశ్వత చిరునామా వివరాలన్నీ తప్పనిసరి.";
            }
        }

        // Emergency contact all mandatory
        if (empty($emg_name) || empty($emg_rel) || empty($emg_mobile) || empty($emg_address)) {
            $errors[] = "Emergency contact details are mandatory / అత్యవసర సంప్రదింపు వివరాలన్నీ తప్పనిసరి.";
        }

        // All ticks mandatory
        if (!$terms_ack) {
            $errors[] = "You must acknowledge and accept the Employment Terms & Conditions / ఉపాధి నిబంధనలను అంగీకరించాలి.";
        }
        if (!$pickup_policy_ack) {
            $errors[] = "You must acknowledge receipt of the Associate Leave & Incentive Policy / పికప్ అసోసియేట్ పాలసీని అంగీకరించాలి.";
        }
        if (!$data_consent) {
            $errors[] = "You must complete the Employee Declaration and Data Authorization / ఉద్యోగి డిక్లరేషన్‌ను అంగీకరించాలి.";
        }

        $has_sig_upload = (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK);
        if (empty($signature_data) && empty($signature['signature_image_path']) && !$has_sig_upload) {
            $errors[] = "Employee signature is required / ఉద్యోగి సంతకం తప్పనిసరి.";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // 1. Handle Photo Upload
            $photo_path = $personal['photo_path'] ?? null;
            if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $photo_filename = 'photo_' . $app_id . '_' . time() . '.' . $ext;
                    $dest = __DIR__ . '/uploads/photos/' . $photo_filename;
                    if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $dest)) {
                        $photo_path = 'uploads/photos/' . $photo_filename;
                    }
                }
            }
            
            // 2. Handle Thumb Impression Upload
            $thumb_path = $signature['thumb_impression_path'] ?? null;
            if (isset($_FILES['thumb_impression']) && $_FILES['thumb_impression']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['thumb_impression']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $thumb_filename = 'thumb_' . $app_id . '_' . time() . '.' . $ext;
                    $dest = __DIR__ . '/uploads/signatures/' . $thumb_filename;
                    if (move_uploaded_file($_FILES['thumb_impression']['tmp_name'], $dest)) {
                        $thumb_path = 'uploads/signatures/' . $thumb_filename;
                    }
                }
            }
            
            // 3. Handle Employee Signature (Upload File or Canvas Base64)
            $sig_path = $signature['signature_image_path'] ?? null;
            if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                    $sig_filename = 'sig_emp_' . $app_id . '_' . time() . '.' . $ext;
                    $dest = __DIR__ . '/uploads/signatures/' . $sig_filename;
                    if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $dest)) {
                        $sig_path = 'uploads/signatures/' . $sig_filename;
                    }
                }
            } elseif (!empty($signature_data) && strpos($signature_data, 'data:image/png;base64,') === 0) {
                $data_img = str_replace('data:image/png;base64,', '', $signature_data);
                $data_img = str_replace(' ', '+', $data_img);
                $decoded = base64_decode($data_img);
                if ($decoded !== false) {
                    $sig_filename = 'sig_emp_' . $app_id . '_' . time() . '.png';
                    $dest = __DIR__ . '/uploads/signatures/' . $sig_filename;
                    if (file_put_contents($dest, $decoded)) {
                        $sig_path = 'uploads/signatures/' . $sig_filename;
                    }
                }
            }
            
            // 4. Update Application Status
            $new_status = ($action === 'submit_form') ? 'pending_hr_verification' : 'in_progress';
            $old_status = $application['status'];
            
            $stmt = $db->prepare("UPDATE employee_joining_applications SET 
                employee_type = ?, 
                status = ?, 
                submitted_at = " . ($action === 'submit_form' ? 'NOW()' : 'submitted_at') . ", 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$employee_type, $new_status, $app_id]);
            
            // 5. Upsert Personal Details
            $stmt = $db->prepare("INSERT INTO employee_personal_details (
                application_id, employee_id, full_name, father_name, dob, gender, mobile, 
                alternate_mobile, email, designation, department_id, joining_date, blood_group, nationality,
                previous_work_experience, photo_path, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                employee_id = VALUES(employee_id),
                full_name = VALUES(full_name),
                father_name = VALUES(father_name),
                dob = VALUES(dob),
                gender = VALUES(gender),
                mobile = VALUES(mobile),
                alternate_mobile = VALUES(alternate_mobile),
                email = VALUES(email),
                designation = VALUES(designation),
                department_id = VALUES(department_id),
                joining_date = VALUES(joining_date),
                blood_group = VALUES(blood_group),
                nationality = VALUES(nationality),
                previous_work_experience = VALUES(previous_work_experience),
                photo_path = COALESCE(VALUES(photo_path), photo_path),
                updated_at = NOW()");
            $stmt->execute([
                $app_id, 
                !empty($employee_id) ? $employee_id : null, 
                !empty($full_name) ? $full_name : null, 
                !empty($father_name) ? $father_name : null, 
                $dob_clean, 
                $gender, 
                !empty($mobile) ? $mobile : null,
                !empty($alternate_mobile) ? $alternate_mobile : null, 
                !empty($email) ? $email : null, 
                !empty($designation) ? $designation : null, 
                $department_id, 
                $joining_date_clean, 
                !empty($blood_group) ? $blood_group : null,
                !empty($nationality) ? $nationality : 'Indian',
                !empty($previous_work_experience) ? $previous_work_experience : null, 
                $photo_path
            ]);
            
            // 6. Upsert Addresses (Present & Permanent)
            if ($present_address) {
                $up = $db->prepare("UPDATE employee_addresses SET house_flat_no = ?, street_area = ?, village_town = ?, mandal = ?, district = ?, state = ?, pincode = ? WHERE id = ?");
                $up->execute([$p_house, $p_street, $p_village, $p_mandal, $p_district, $p_state, $p_pincode, $present_address['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO employee_addresses (application_id, address_type, house_flat_no, street_area, village_town, mandal, district, state, pincode, is_same_as_present) VALUES (?, 'present', ?, ?, ?, ?, ?, ?, ?, 0)");
                $ins->execute([$app_id, $p_house, $p_street, $p_village, $p_mandal, $p_district, $p_state, $p_pincode]);
            }
            
            if ($permanent_address) {
                $up = $db->prepare("UPDATE employee_addresses SET house_flat_no = ?, street_area = ?, village_town = ?, mandal = ?, district = ?, state = ?, pincode = ?, is_same_as_present = ? WHERE id = ?");
                $up->execute([$perm_house, $perm_street, $perm_village, $perm_mandal, $perm_district, $perm_state, $perm_pincode, $same_as_present, $permanent_address['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO employee_addresses (application_id, address_type, house_flat_no, street_area, village_town, mandal, district, state, pincode, is_same_as_present) VALUES (?, 'permanent', ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$app_id, $perm_house, $perm_street, $perm_village, $perm_mandal, $perm_district, $perm_state, $perm_pincode, $same_as_present]);
            }
            
            // 7. Upsert Identity Documents & Bank Details
            $stmt = $db->prepare("INSERT INTO employee_identity_documents (
                application_id, aadhaar_masked, aadhaar_last4, other_id_type, other_id_number, documents_submitted,
                bank_holder_name, bank_name, bank_branch, bank_account_no, bank_ifsc
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                aadhaar_masked = VALUES(aadhaar_masked),
                aadhaar_last4 = VALUES(aadhaar_last4),
                other_id_type = VALUES(other_id_type),
                other_id_number = VALUES(other_id_number),
                documents_submitted = VALUES(documents_submitted),
                bank_holder_name = VALUES(bank_holder_name),
                bank_name = VALUES(bank_name),
                bank_branch = VALUES(bank_branch),
                bank_account_no = VALUES(bank_account_no),
                bank_ifsc = VALUES(bank_ifsc)");
            $stmt->execute([
                $app_id, 
                !empty($aadhaar_masked) ? $aadhaar_masked : null, 
                !empty($aadhaar_last4) ? $aadhaar_last4 : null, 
                !empty($other_id_type) ? $other_id_type : null, 
                !empty($other_id_number) ? $other_id_number : null, 
                !empty($documents_submitted) ? $documents_submitted : null,
                !empty($bank_holder_name) ? $bank_holder_name : null,
                !empty($bank_name) ? $bank_name : null,
                !empty($bank_branch) ? $bank_branch : null,
                !empty($bank_account_no) ? $bank_account_no : null,
                !empty($bank_ifsc) ? $bank_ifsc : null
            ]);
            
            // 8. Upsert Emergency Contact
            $stmt = $db->prepare("INSERT INTO employee_emergency_contacts (
                application_id, full_name, relationship, mobile, alternate_mobile, address
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                relationship = VALUES(relationship),
                mobile = VALUES(mobile),
                alternate_mobile = VALUES(alternate_mobile),
                address = VALUES(address)");
            $stmt->execute([$app_id, $emg_name, $emg_rel, $emg_mobile, $emg_alt_mobile, $emg_address]);
            
            // 9. Upsert References
            if ($ref1) {
                $up = $db->prepare("UPDATE employee_references SET full_name = ?, relationship_how_known = ?, mobile = ?, address = ? WHERE id = ?");
                $up->execute([$ref1_name, $ref1_rel, $ref1_mobile, $ref1_address, $ref1['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO employee_references (application_id, reference_number, full_name, relationship_how_known, mobile, address) VALUES (?, 1, ?, ?, ?, ?)");
                $ins->execute([$app_id, $ref1_name, $ref1_rel, $ref1_mobile, $ref1_address]);
            }
            if ($ref2) {
                $up = $db->prepare("UPDATE employee_references SET full_name = ?, relationship_how_known = ?, mobile = ?, address = ? WHERE id = ?");
                $up->execute([$ref2_name, $ref2_rel, $ref2_mobile, $ref2_address, $ref2['id']]);
            } else {
                $ins = $db->prepare("INSERT INTO employee_references (application_id, reference_number, full_name, relationship_how_known, mobile, address) VALUES (?, 2, ?, ?, ?, ?)");
                $ins->execute([$app_id, $ref2_name, $ref2_rel, $ref2_mobile, $ref2_address]);
            }
            
            // 10. Upsert Previous Employment
            $stmt = $db->prepare("INSERT INTO employee_previous_employment (
                application_id, employer_name, designation, employer_phone, reason_for_leaving
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                employer_name = VALUES(employer_name),
                designation = VALUES(designation),
                employer_phone = VALUES(employer_phone),
                reason_for_leaving = VALUES(reason_for_leaving)");
            $stmt->execute([$app_id, $prev_employer, $prev_desig, $prev_phone, $prev_reason]);
            
            // 11. Upsert Declaration
            $stmt = $db->prepare("INSERT INTO employee_declarations (
                application_id, terms_acknowledged, data_consent_acknowledged, pickup_policy_acknowledged, declaration_date
            ) VALUES (?, ?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE
                terms_acknowledged = VALUES(terms_acknowledged),
                data_consent_acknowledged = VALUES(data_consent_acknowledged),
                pickup_policy_acknowledged = VALUES(pickup_policy_acknowledged),
                declaration_date = CURDATE()");
            $stmt->execute([$app_id, $terms_ack, $data_consent, $pickup_policy_ack]);
            
            // 12. Upsert Signature
            if ($sig_path || $thumb_path) {
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $stmt = $db->prepare("INSERT INTO employee_signatures (
                    application_id, signature_image_path, thumb_impression_path, signed_ip, signed_at
                ) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    signature_image_path = COALESCE(VALUES(signature_image_path), signature_image_path),
                    thumb_impression_path = COALESCE(VALUES(thumb_impression_path), thumb_impression_path),
                    signed_ip = VALUES(signed_ip),
                    signed_at = NOW()");
                $stmt->execute([$app_id, $sig_path, $thumb_path, $client_ip]);
            }
            
            // 13. Handle Document Uploads
            $doc_types = [
                'aadhaar_doc' => 'Aadhaar Card',
                'pan_doc' => 'PAN Card',
                'dl_doc' => 'Driving License',
                'bank_doc' => 'Bank Passbook / Cheque',
                'exp_cert_doc' => 'Experience Certificate'
            ];
            
            foreach ($doc_types as $input_key => $doc_label) {
                if (isset($_FILES[$input_key]) && $_FILES[$input_key]['error'] === UPLOAD_ERR_OK) {
                    $orig_name = $_FILES[$input_key]['name'];
                    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    $size = $_FILES[$input_key]['size'];
                    $mime = $_FILES[$input_key]['type'];
                    
                    if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                        $stored_name = 'doc_' . $app_id . '_' . $input_key . '_' . time() . '.' . $ext;
                        $dest = __DIR__ . '/uploads/documents/' . $stored_name;
                        if (move_uploaded_file($_FILES[$input_key]['tmp_name'], $dest)) {
                            $stored_rel = 'uploads/documents/' . $stored_name;
                            $doc_ins = $db->prepare("INSERT INTO employee_uploaded_documents (application_id, document_type, original_file_name, stored_file_path, file_size_bytes, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
                            $doc_ins->execute([$app_id, $doc_label, $orig_name, $stored_rel, $size, $mime]);
                        }
                    }
                }
            }
            
            // 14. Record Status History
            if ($old_status !== $new_status) {
                $hist = $db->prepare("INSERT INTO employee_joining_status_history (application_id, from_status, to_status, changed_by_role, remarks) VALUES (?, ?, ?, 'employee', ?)");
                $remarks = ($action === 'submit_form') ? 'Application submitted by candidate.' : 'Application draft saved.';
                $hist->execute([$app_id, $old_status, $new_status, $remarks]);
            }
            
            $db->commit();
            
            // Refresh application data
            $stmt = $db->prepare("SELECT * FROM employee_joining_applications WHERE id = ?");
            $stmt->execute([$app_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            $is_editable = in_array($application['status'], ['draft', 'sent_to_employee', 'in_progress', 'correction_required']);
            
            if ($action === 'submit_form') {
                $success_message = "Your Electronic Joining Form has been successfully submitted! Application ID: " . htmlspecialchars($application['application_no']);
            } else {
                $success_message = "Your draft has been saved successfully. You can return anytime using your secure link.";
            }
            
            // Reload refreshed entities
            $stmt = $db->prepare("SELECT * FROM employee_personal_details WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $personal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM employee_addresses WHERE application_id = ? AND address_type = 'present'");
            $stmt->execute([$app_id]);
            $present_address = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_addresses WHERE application_id = ? AND address_type = 'permanent'");
            $stmt->execute([$app_id]);
            $permanent_address = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_identity_documents WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $identity = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_emergency_contacts WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $emergency = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_references WHERE application_id = ? AND reference_number = 1");
            $stmt->execute([$app_id]);
            $ref1 = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_references WHERE application_id = ? AND reference_number = 2");
            $stmt->execute([$app_id]);
            $ref2 = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_previous_employment WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $prev_emp = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_declarations WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $declaration = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_signatures WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $signature = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT * FROM employee_uploaded_documents WHERE application_id = ?");
            $stmt->execute([$app_id]);
            $uploaded_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Joining Form Save Error [App ID: $app_id]: " . $e->getMessage());
            $errors[] = "Unable to process and save your information at this time. Please ensure the entered data is correct and try again. / మీ వివరాలను ప్రస్తుతం భద్రపరచడం సాధ్యం కాలేదు. దయచేసి ఎంటర్ చేసిన వివరాలు సరైనవేనా అని సరిచూసుకొని మళ్లీ ప్రయత్నించండి.";
        }
    }
}

// Helper function to render bilingual labels
function lbl($key, $lang = 'bilingual') {
    if ($lang === 'en') {
        return htmlspecialchars(JoiningI18n::t($key, 'en'));
    } elseif ($lang === 'te') {
        return htmlspecialchars(JoiningI18n::t($key, 'te'));
    } else {
        // Bilingual display: English with Telugu translation
        $en = JoiningI18n::t($key, 'en');
        $te = JoiningI18n::t($key, 'te');
        if ($en === $te || empty($te)) {
            return htmlspecialchars($en);
        }
        return htmlspecialchars($en) . ' <span class="te-text text-muted fw-normal" style="font-size: 0.9em; font-family: sans-serif;">' . htmlspecialchars($te) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VK Logistics Hub Application Form</title>
    <!-- Fonts & Bootstrap 5 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Ramabhadra&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bel-purple: #1e1145;
            --bel-purple-dark: #150a33;
            --bel-purple-light: #2b1763;
            --bel-slate-head: #eaedf1;
            --bel-border: #ced4da;
            --bel-border-card: #d5d9df;
            --bel-bg: #f5f6f8;
            --bel-text: #1e293b;
            --bel-label: #334155;
            --bel-red: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: var(--bel-bg);
            color: var(--bel-text);
            margin: 0;
            padding: 0;
            font-size: 15px;
            line-height: 1.6;
        }

        <?php if ($is_embedded): ?>
        html, body {
            overflow: hidden !important;
            overflow-y: hidden !important;
            overflow-x: hidden !important;
            height: auto !important;
        }
        <?php endif; ?>

        .te-text {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Tahoma, sans-serif;
        }

        /* OCR Auto-fill Highlighting */
        .ocr-autofilled {
            background-color: #f0fdf4 !important;
            border-color: #22c55e !important;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25) !important;
            transition: all 0.3s ease;
        }
        .cursor-pointer {
            cursor: pointer;
        }

        /* 1. TOP WHITE CORPORATE BANNER (BEL STYLE) */
        .bel-top-logo-banner {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .bel-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .bel-brand-logo-img {
            max-height: 84px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            display: block;
        }

        /* 2. SOLID DEEP PURPLE NAV BAR */
        .bel-top-nav-bar {
            background-color: var(--bel-purple);
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

        .bel-nav-container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .bel-nav-title {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
        }

        .bel-nav-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .bel-upload-btn {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .bel-upload-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .bel-lang-group {
            display: inline-flex;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            overflow: hidden;
            background: rgba(0,0,0,0.15);
        }

        .bel-lang-pill {
            padding: 6px 14px;
            font-size: 13px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .bel-lang-pill:hover {
            color: #ffffff;
            background: rgba(255,255,255,0.15);
        }

        .bel-lang-pill.active {
            background: #ffffff;
            color: var(--bel-purple);
        }

        .bel-nav-btn {
            background: rgba(255,255,255,0.1);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .bel-nav-btn:hover {
            background: #ffffff;
            color: var(--bel-purple);
        }

        .bel-preview-btn {
            background: #ffffff;
            color: #1e1145 !important;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .bel-preview-btn:hover {
            background: #f1f5f9;
            color: #dc2626 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .bel-logout-btn {
            background: #ffffff;
            color: var(--bel-purple);
            border: none;
            border-radius: 4px;
            padding: 6px 18px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: all 0.2s ease;
        }

        .bel-logout-btn:hover {
            background: #f1f5f9;
            color: var(--bel-purple);
            transform: translateY(-1px);
        }

        /* 3. MAIN PAGE CONTAINER */
        .bel-content-wrapper {
            max-width: 1280px;
            margin: 20px auto 40px;
            padding: 0 16px;
        }

        /* 4. REFERENCE & STATUS INFO STRIP */
        .bel-info-strip {
            background: #ffffff;
            border: 1px solid var(--bel-border-card);
            border-radius: 6px;
            padding: 12px 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            font-size: 14.5px;
        }

        /* PERMANENT FIX FOR STATUS PILL (NO BOOTSTRAP OVERRIDES / NO CLIPPING) */
        .status-pill {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: auto !important;
            min-height: 28px !important;
            max-height: 32px !important;
            overflow: visible !important;
            padding: 5px 16px !important;
            border-radius: 20px !important;
            font-size: 12px !important;
            font-weight: 800 !important;
            line-height: 1.3 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            white-space: nowrap !important;
            vertical-align: middle !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .status-pill.st-in-progress {
            background-color: #e0e7ff !important;
            color: #3730a3 !important;
            border: 1px solid #c7d2fe !important;
        }

        .status-pill.st-pending {
            background-color: #fef3c7 !important;
            color: #92400e !important;
            border: 1px solid #fde68a !important;
        }

        .status-pill.st-approved {
            background-color: #dcfce7 !important;
            color: #166534 !important;
            border: 1px solid #bbf7d0 !important;
        }

        .status-pill.st-correction {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
            border: 1px solid #fecaca !important;
        }

        /* 5. INSTRUCTIONS ACCORDION CARD */
        .bel-section-card {
            background: #ffffff;
            border: 1px solid var(--bel-border-card);
            border-radius: 6px;
            margin-bottom: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
        }

        .bel-section-head {
            background-color: var(--bel-slate-head);
            border-bottom: 1px solid var(--bel-border-card);
            padding: 13px 22px;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bel-section-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
        }

        .bel-toggle-btn {
            background: transparent;
            border: none;
            color: #1e293b;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
        }

        .bel-section-body {
            padding: 24px 26px;
            background: #ffffff;
        }

        .bel-note-red {
            border: 1px dashed #fca5a5;
            background-color: #fef2f2;
            padding: 14px 18px;
            border-radius: 5px;
            color: #991b1b;
            font-size: 13.5px;
            line-height: 1.6;
        }

        .bel-note-red strong {
            color: #b91c1c;
            font-weight: 800;
        }

        .bel-note-dashed {
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            padding: 12px 18px;
            border-radius: 5px;
            color: #334155;
            margin-bottom: 16px;
            font-size: 13.5px;
            line-height: 1.6;
        }

        /* 6. PRIMARY TABS BAR (BEL / DIGIALM STYLE) */
        .bel-tabs-bar {
            background-color: var(--bel-purple);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            border-radius: 6px 6px 0 0;
            white-space: nowrap;
        }

        .bel-tab-item {
            background: var(--bel-purple);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 5px;
            padding: 12px 22px;
            font-size: 13.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .bel-tab-item:hover {
            background: rgba(255,255,255,0.15);
            border-color: #ffffff;
        }

        .bel-tab-item.active {
            background: #ffffff !important;
            color: var(--bel-purple) !important;
            border-color: #ffffff !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        /* 7. FORM INPUTS & LABELS (DIGIALM ACCURACY) */
        .form-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--bel-label);
            margin-bottom: 6px;
            display: block;
        }

        .form-control, .form-select {
            font-size: 14px;
            height: 44px;
            border: 1.5px solid var(--bel-border);
            border-radius: 5px;
            padding: 8px 14px;
            color: #0f172a;
            background-color: #ffffff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        textarea.form-control {
            height: auto;
            min-height: 90px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--bel-purple);
            outline: 0;
            box-shadow: 0 0 0 3px rgba(30, 17, 69, 0.15);
        }

        .form-control[readonly], .form-control[disabled], .form-select[disabled] {
            background-color: #f1f3f6 !important;
            color: #475569 !important;
            cursor: not-allowed;
        }

        .input-group-text {
            font-size: 14px;
            font-weight: 600;
            background-color: #f1f3f6;
            border: 1.5px solid var(--bel-border);
            color: #475569;
            height: 44px;
            padding: 8px 14px;
        }

        .form-check {
            min-height: 1.7rem;
            display: flex !important;
            align-items: flex-start !important;
            gap: 12px !important;
            margin-bottom: 8px;
            padding-left: 0 !important;
        }

        .form-check .form-check-input,
        .form-check-input {
            width: 1.25em !important;
            height: 1.25em !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 3px !important;
            float: none !important;
            flex-shrink: 0 !important;
            cursor: pointer;
            border: 1.5px solid #94a3b8;
        }

        .form-check-input:checked {
            background-color: var(--bel-purple);
            border-color: var(--bel-purple);
        }

        .form-check-label {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
            line-height: 1.45;
            margin-bottom: 0;
        }

        .form-switch {
            padding-left: 2.5em !important;
            display: block !important;
        }

        .form-switch .form-check-input {
            width: 2.3em !important;
            height: 1.25em !important;
            border-radius: 2em !important;
            margin-left: -2.5em !important;
            margin-top: 0 !important;
            float: left !important;
        }

        /* 8. BOTTOM ACTION BAR (BEL STYLE) */
        .bel-bottom-bar {
            background-color: var(--bel-purple);
            padding: 14px 22px;
            border-radius: 0 0 6px 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: -1px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
        }

        .bel-btn-save-next {
            background-color: #ffffff;
            color: var(--bel-purple);
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 11px 32px;
            border-radius: 5px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        .bel-btn-save-next:hover {
            background-color: #f8fafc;
            color: var(--bel-purple-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .bel-btn-outline {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.7);
            border-radius: 5px;
            padding: 10px 22px;
            font-size: 13.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
        }

        .bel-btn-outline:hover {
            background-color: rgba(255,255,255,0.15);
            color: #ffffff;
            border-color: #ffffff;
        }

        .bel-btn-submit {
            background-color: #10b981;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 11px 32px;
            border-radius: 5px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            transition: all 0.2s ease;
            text-transform: uppercase;
        }

        .bel-btn-submit:hover {
            background-color: #059669;
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Photo and Signature Controls */
        .bel-photo-box {
            width: 160px;
            height: 200px;
            border: 2px dashed #94a3b8;
            border-radius: 6px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            margin: 0 auto 10px;
            position: relative;
        }

        .bel-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bel-sig-canvas {
            width: 100%;
            height: 190px;
            border: 2px dashed #94a3b8;
            border-radius: 6px;
            background: #ffffff;
            cursor: crosshair;
            touch-action: none;
        }

        .bonus-table th {
            background-color: #1e1145;
            color: white;
            font-size: 13.5px;
            padding: 11px 14px;
        }

        .bonus-table td {
            font-size: 13.5px;
            padding: 11px 14px;
            vertical-align: middle;
        }

        .policy-clause-box {
            background: #f8fafc;
            border-left: 4px solid var(--bel-purple);
            padding: 14px 18px;
            border-radius: 0 6px 6px 0;
            margin-bottom: 12px;
        }

        .policy-clause-box h6 {
            color: var(--bel-purple);
            font-weight: 700;
            font-size: 14.5px;
            margin-bottom: 6px;
        }

        @keyframes vkToastSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .vk-toast-popup {
            animation: vkToastSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
    </style>
</head>
<body>

<!-- Floating Notification Popup (Visible Without Scroll) -->
<div id="vkValidationToast" class="vk-toast-popup" style="display: none; position: fixed; top: 22px; right: 24px; z-index: 9999999; max-width: 440px; width: calc(100vw - 48px); background: #ffffff; color: #1e293b; border-radius: 12px; box-shadow: 0 14px 35px rgba(0,0,0,0.22), 0 3px 10px rgba(0,0,0,0.06); border-left: 5px solid #ef4444; padding: 14px 18px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
            <div style="background: #fee2e2; color: #dc2626; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; margin-top: 1px;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 13.5px; color: #0f172a;">Required Field / తప్పనిసరి వివరాలు</div>
                <div id="vkValidationToastMsg" style="font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.45;"></div>
            </div>
        </div>
        <button type="button" onclick="hideValidationToast()" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; padding: 0 4px; line-height: 1; border-radius: 4px;" title="Close">&times;</button>
    </div>
</div>

<!-- TOP CORPORATE BRANDING BANNER -->
<header>
    <div class="bel-top-logo-banner">
        <div class="bel-logo-container">
            <img src="assets/images/vk-logo.jpg" alt="VK LOGISTICS HUB - YOUR SHIPPING PARTNER" class="bel-brand-logo-img">
        </div>
    </div>

    <!-- SOLID DEEP PURPLE NAV BAR - UNIFIED SINGLE HEADER SECTION -->
    <div class="bel-top-nav-bar">
        <div class="bel-nav-container">
            <div class="bel-nav-title mb-0">
                <i class="fas fa-file-signature me-2"></i> VK Logistics Hub Application Form
            </div>
            <div class="bel-nav-actions">
                <?php if ($is_editable): ?>
                    <button type="button" class="bel-upload-btn" onclick="openOcrModal()">
                        <i class="fas fa-file-upload"></i> Upload Written Form
                    </button>
                <?php endif; ?>

                <button type="button" class="bel-nav-btn" id="btnToggleScanView" style="display: none;" onclick="toggleScanViewer()">
                    <i class="fas fa-eye me-1"></i> View Scan
                </button>

                <button type="button" class="bel-preview-btn" onclick="openPdfPreviewModal()" title="PDF Preview & Download">
                    <i class="fas fa-file-pdf text-danger me-1"></i> PDF Preview &amp; Download
                </button>

                <?php if ($is_embedded): ?>
                    <button type="button" class="bel-logout-btn" onclick="if(window.parent && window.parent.switchView) window.parent.switchView('list');">
                        <i class="fas fa-arrow-left me-1"></i> BACK TO LIST
                    </button>
                <?php else: ?>
                    <a href="dashboard/management/joining_applications.php" class="bel-logout-btn">
                        <i class="fas fa-sign-out-alt me-1"></i> LOGOUT
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- MAIN CONTAINER -->
<div class="bel-content-wrapper">

    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fs-4 me-3 text-success"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="mb-0 small"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
            <div class="d-flex">
                <i class="fas fa-exclamation-circle fs-4 me-3 text-danger mt-1"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Please correct the following errors / దయచేసి క్రింది వివరాలను సరిచూసుకోండి:</h6>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!$is_editable): ?>
        <div class="alert alert-info border-info mb-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Application Submitted:</strong> Your joining form is currently under HR review. Permitted details are displayed in read-only mode.
        </div>
    <?php endif; ?>

    <!-- MAIN FORM WRAPPER -->
    <form method="POST" enctype="multipart/form-data" id="joiningForm" novalidate>
        <input type="hidden" name="action" id="formAction" value="save_draft">
        <input type="hidden" name="is_embedded" value="<?php echo $is_embedded ? '1' : '0'; ?>">
        <input type="hidden" name="signature_data" id="signatureData" value="">

        <!-- 3. PRIMARY TABS BAR (BEL / DIGIALM STYLE) -->
        <div class="bel-tabs-bar">
            <div class="bel-tab-item active" id="tabNav-1" onclick="goToTab(1)">
                PERSONAL DETAILS
            </div>
            <div class="bel-tab-item" id="tabNav-2" onclick="goToTab(2)">
                COMMUNICATION DETAILS
            </div>
            <div class="bel-tab-item" id="tabNav-3" onclick="goToTab(3)">
                QUALIFICATION & EXPERIENCE
            </div>
            <div class="bel-tab-item" id="tabNav-4" onclick="goToTab(4)">
                TERMS & PICKUP POLICY
            </div>
            <div class="bel-tab-item" id="tabNav-5" onclick="goToTab(5)">
                DOCUMENT AND DECLARATION
            </div>
        </div>

        <!-- ================= TAB 1: PERSONAL DETAILS ================= -->
        <div class="tab-pane-content" id="tabContent-1">
            <!-- Section 1.1: Post Applied Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPostApplied', 'btnTogglePost')">
                    <span class="bel-section-title">Post Applied Details</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePost">[-]</button>
                </div>
                <div id="secPostApplied" class="bel-section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Application Number: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($application['application_no']); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('designation', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   name="designation" 
                                   id="designationSelect" 
                                   list="designationList" 
                                   placeholder="Enter Designation / Job Role" 
                                   value="<?php echo htmlspecialchars($personal['designation'] ?? ''); ?>" 
                                   oninput="checkRoleRequirements(this.value)" 
                                   required 
                                   <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            <datalist id="designationList">
                                <option value="Pickup Associate">
                                <option value="Delivery Associate">
                                <option value="Operations Assistant">
                                <option value="Warehouse Executive">
                                <option value="Hub Incharge">
                                <option value="Customer Support Executive">
                                <option value="Accounts Executive">
                                <option value="HR Executive">
                            </datalist>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('department', $lang); ?>: <span class="text-danger">*</span></label>
                            <select class="form-select" name="department_id" required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo ($personal['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('employee_type', $lang); ?>: <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employee_type" id="type_new" value="new_joiner" 
                                        <?php echo ($application['employee_type'] ?? 'new_joiner') === 'new_joiner' ? 'checked' : ''; ?> 
                                        onchange="toggleEmployeeIdField()" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="type_new">
                                        <?php echo lbl('new_joiner', $lang); ?>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="employee_type" id="type_existing" value="existing_employee" 
                                        <?php echo ($application['employee_type'] ?? '') === 'existing_employee' ? 'checked' : ''; ?> 
                                        onchange="toggleEmployeeIdField()" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="type_existing">
                                        <?php echo lbl('existing_employee', $lang); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('date_of_joining', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="joining_date" required value="<?php echo htmlspecialchars($personal['joining_date'] ?? date('Y-m-d')); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('nationality', $lang) ?: 'Nationality'; ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nationality" id="nationalityInput" required value="<?php echo htmlspecialchars($personal['nationality'] ?? 'Indian'); ?>" placeholder="e.g. Indian (భారతీయుడు)" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>

                        <div class="col-md-4" id="empIdContainer" style="<?php echo ($application['employee_type'] ?? 'new_joiner') === 'new_joiner' ? 'display:none;' : ''; ?>">
                            <label class="form-label"><?php echo lbl('employee_id', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="employee_id" value="<?php echo htmlspecialchars($personal['employee_id'] ?? ''); ?>" placeholder="e.g. VK-EMP-1024" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            <div class="small text-muted" style="font-size:13px;"><?php echo lbl('employee_id_auto_note', $lang); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 1.2: Personal Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPersonalDetails', 'btnTogglePersonal')">
                    <span class="bel-section-title">Personal Details</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePersonal">[-]</button>
                </div>
                <div id="secPersonalDetails" class="bel-section-body">
                    <div class="row g-3">
                        <!-- Passport Photo -->
                        <div class="col-md-3 text-center border-end-md">
                            <label class="form-label"><?php echo lbl('passport_photo', $lang); ?>: <span class="text-danger">*</span></label>
                            <div class="bel-photo-box" onclick="document.getElementById('passport_photo').click()">
                                <?php if (!empty($personal['photo_path']) && file_exists(__DIR__ . '/' . $personal['photo_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($personal['photo_path']); ?>" id="photo_preview" alt="Passport Photo">
                                <?php else: ?>
                                    <img src="assets/images/avatar-placeholder.png" id="photo_preview" style="display:none;" alt="Preview">
                                    <div id="photo_placeholder" class="text-center p-2 text-muted">
                                        <i class="fas fa-camera fs-2 mb-2 d-block"></i>
                                        <span style="font-size: 13.5px; font-weight: 600;">Upload Photo<br>ఫోటో అప్‌లోడ్</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_editable): ?>
                                <input type="file" class="d-none" id="passport_photo" name="passport_photo" accept="image/*" onchange="previewPhoto(this)">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('passport_photo').click()">
                                    <i class="fas fa-upload me-1"></i> Choose Photo
                                </button>
                            <?php endif; ?>
                            <div class="text-muted mt-2" style="font-size: 12.5px;">Max size 5MB (JPG/PNG)</div>
                        </div>

                        <!-- Personal fields -->
                        <div class="col-md-9">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo lbl('full_name', $lang); ?>: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" required value="<?php echo htmlspecialchars($personal['full_name'] ?? ''); ?>" placeholder="As per Aadhaar / Official ID" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label"><?php echo lbl('father_name', $lang); ?>: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="father_name" required value="<?php echo htmlspecialchars($personal['father_name'] ?? ''); ?>" placeholder="Father's Full Name" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label"><?php echo lbl('dob', $lang); ?> (DD/MM/YYYY): <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="dob" required value="<?php echo htmlspecialchars($personal['dob'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label"><?php echo lbl('gender', $lang); ?>: <span class="text-danger">*</span></label>
                                    <select class="form-select" name="gender" required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                        <option value="male" <?php echo ($personal['gender'] ?? 'male') === 'male' ? 'selected' : ''; ?>><?php echo lbl('gender_male', $lang); ?></option>
                                        <option value="female" <?php echo ($personal['gender'] ?? '') === 'female' ? 'selected' : ''; ?>><?php echo lbl('gender_female', $lang); ?></option>
                                        <option value="other" <?php echo ($personal['gender'] ?? '') === 'other' ? 'selected' : ''; ?>><?php echo lbl('gender_other', $lang); ?></option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label"><?php echo lbl('blood_group', $lang); ?>: <span class="text-danger">*</span></label>
                                    <select class="form-select" name="blood_group" required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                        <option value="">-- Select Blood Group --</option>
                                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $bg): ?>
                                            <option value="<?php echo $bg; ?>" <?php echo ($personal['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('previous_experience', $lang); ?>:</label>
                                    <input type="text" class="form-control" name="previous_work_experience" value="<?php echo htmlspecialchars($personal['previous_work_experience'] ?? ''); ?>" placeholder="e.g. 2 years experience in courier/logistics operations" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 1.3: Identity & Bank Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secIdentityBank', 'btnToggleIdBank')">
                    <span class="bel-section-title">Identity & Bank Details</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleIdBank">[-]</button>
                </div>
                <div id="secIdentityBank" class="bel-section-body">
                    <div class="bel-note-dashed">
                        <strong>Privacy & Statutory Protection:</strong> Aadhaar information is collected strictly as required by law for statutory compliance and payroll administration. Sensitive identification numbers are stored securely with masked display (e.g., XXXX-XXXX-1234).
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('aadhaar_number', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" name="aadhaar_number" id="aadhaar_input" required 
                                placeholder="XXXX-XXXX-1234" maxlength="14" 
                                value="<?php echo htmlspecialchars($identity['aadhaar_masked'] ?? ''); ?>" 
                                oninput="formatAadhaar(this)" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            <div class="small text-muted mt-1" style="font-size:13px;"><?php echo lbl('aadhaar_note', $lang); ?></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('other_id_proof', $lang); ?>:</label>
                            <select class="form-select" name="other_id_type" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                <option value="">-- Select Alternate ID --</option>
                                <option value="PAN Card" <?php echo ($identity['other_id_type'] ?? '') === 'PAN Card' ? 'selected' : ''; ?>>PAN Card</option>
                                <option value="Driving License" <?php echo ($identity['other_id_type'] ?? '') === 'Driving License' ? 'selected' : ''; ?>>Driving License</option>
                                <option value="Voter ID" <?php echo ($identity['other_id_type'] ?? '') === 'Voter ID' ? 'selected' : ''; ?>>Voter ID</option>
                                <option value="Passport" <?php echo ($identity['other_id_type'] ?? '') === 'Passport' ? 'selected' : ''; ?>>Passport</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ID Number / ఐడీ సంఖ్య:</label>
                            <input type="text" class="form-control" name="other_id_number" value="<?php echo htmlspecialchars($identity['other_id_number'] ?? ''); ?>" placeholder="e.g. ABCDE1234F" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>

                        <div class="col-12 mt-3">
                            <label class="form-label"><?php echo lbl('documents_submitted', $lang); ?>:</label>
                            <div class="row g-2">
                                <?php
                                $curr_docs = explode(', ', $identity['documents_submitted'] ?? '');
                                $check_list = [
                                    'Aadhaar Card' => 'Aadhaar Card (ఆధార్ కార్డు)',
                                    'PAN Card' => 'PAN Card (పాన్ కార్డు)',
                                    'Driving License' => 'Driving License (డ్రైవింగ్ లైసెన్స్ - For Delivery/Pickup)',
                                    'Bank Passbook / Cheque' => 'Bank Passbook / Cancelled Cheque (బ్యాంక్ పాస్‌బుక్)',
                                    'Educational Certificate' => '10th / Inter / Degree Certificate (విద్యార్హత పత్రాలు)',
                                    'Previous Relieving Letter' => 'Previous Relieving / Experience Letter (రిలీవింగ్ లెటర్)'
                                ];
                                foreach ($check_list as $c_val => $c_lbl):
                                    $is_chk = in_array($c_val, $curr_docs);
                                ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="docs_submitted[]" value="<?php echo $c_val; ?>" id="chk_<?php echo md5($c_val); ?>" <?php echo $is_chk ? 'checked' : ''; ?> <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="chk_<?php echo md5($c_val); ?>">
                                                <?php echo $c_lbl; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Account Info (Matching Digialm Image 3) -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="bel-note-red mb-3">
                            <strong>Note:</strong> Kindly provide the details for the bank account in which payroll and statutory salary deposits are to be credited.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Name of Account Holder: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="bank_holder_name" value="<?php echo htmlspecialchars($identity['bank_holder_name'] ?? $personal['full_name'] ?? ''); ?>" placeholder="Name as in Bank Passbook" <?php echo !$is_editable ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bank Name: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($identity['bank_name'] ?? ''); ?>" placeholder="e.g. State Bank of India, HDFC" <?php echo !$is_editable ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Bank Branch: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="bank_branch" value="<?php echo htmlspecialchars($identity['bank_branch'] ?? ''); ?>" placeholder="Branch Name" <?php echo !$is_editable ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank Account Number: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" name="bank_account_no" value="<?php echo htmlspecialchars($identity['bank_account_no'] ?? ''); ?>" placeholder="Bank Account Number" <?php echo !$is_editable ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bank IFSC Code: <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace text-uppercase" name="bank_ifsc" value="<?php echo htmlspecialchars($identity['bank_ifsc'] ?? ''); ?>" placeholder="e.g. SBIN0012345" <?php echo !$is_editable ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 1 Bottom Action Bar -->
            <div class="bel-bottom-bar">
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="saveDraft()">
                        <i class="fas fa-save me-1"></i> SAVE DRAFT
                    </button>
                    <button type="button" class="bel-btn-outline" onclick="openPdfPreviewModal()" title="Live Form PDF Preview">
                        <i class="fas fa-file-pdf text-danger me-1"></i> PDF PREVIEW
                    </button>
                </div>
                <button type="button" class="bel-btn-save-next" onclick="goToTab(2)">
                    SAVE & NEXT <i class="fas fa-chevron-right ms-1"></i>
                </button>
            </div>
        </div>

        <!-- ================= TAB 2: COMMUNICATION DETAILS ================= -->
        <div class="tab-pane-content" id="tabContent-2" style="display: none;">
            <!-- Section 2.1: Contact Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secContactDetails', 'btnToggleContact')">
                    <span class="bel-section-title">Contact Details</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleContact">[-]</button>
                </div>
                <div id="secContactDetails" class="bel-section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('mobile_number', $lang); ?>: <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">91</span>
                                <input type="tel" class="form-control" name="mobile" required maxlength="10" value="<?php echo htmlspecialchars($personal['mobile'] ?? ''); ?>" placeholder="10-digit mobile" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('alternate_mobile', $lang); ?>:</label>
                            <div class="input-group">
                                <span class="input-group-text">91</span>
                                <input type="tel" class="form-control" name="alternate_mobile" maxlength="10" value="<?php echo htmlspecialchars($personal['alternate_mobile'] ?? ''); ?>" placeholder="Alternate mobile" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('email_address', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($personal['email'] ?? ''); ?>" placeholder="candidate@example.com" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2.2: Present Address Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPresentAddress', 'btnTogglePresAddr')">
                    <span class="bel-section-title">Present Address Details (ప్రస్తుత చిరునామా)</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePresAddr">[-]</button>
                </div>
                <div id="secPresentAddress" class="bel-section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('house_flat_no', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_house" name="present_house_flat_no" required value="<?php echo htmlspecialchars($present_address['house_flat_no'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('street_area', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_street" name="present_street_area" required value="<?php echo htmlspecialchars($present_address['street_area'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('village_town', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_village" name="present_village_town" required value="<?php echo htmlspecialchars($present_address['village_town'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('mandal', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_mandal" name="present_mandal" required value="<?php echo htmlspecialchars($present_address['mandal'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('district', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_district" name="present_district" required value="<?php echo htmlspecialchars($present_address['district'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('state', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_state" name="present_state" required value="<?php echo htmlspecialchars($present_address['state'] ?? ''); ?>" placeholder="State (రాష్ట్రం)" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('pincode', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="p_pincode" name="present_pincode" required maxlength="6" value="<?php echo htmlspecialchars($present_address['pincode'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2.3: Permanent Address Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPermanentAddress', 'btnTogglePermAddr')">
                    <span class="bel-section-title">Permanent Address Details (శాశ్వత చిరునామా)</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePermAddr">[-]</button>
                </div>
                <div id="secPermanentAddress" class="bel-section-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 p-2 bg-light rounded border">
                        <span class="fw-bold text-dark"><?php echo lbl('same_as_present', $lang); ?></span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="sameAsPresentToggle" name="same_as_present" 
                                <?php echo (!empty($permanent_address['is_same_as_present']) || empty($permanent_address)) ? 'checked' : ''; ?> 
                                onchange="toggleSameAddress(this.checked)" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <div id="permanentAddressContainer" class="row g-3" style="<?php echo (!empty($permanent_address['is_same_as_present']) || empty($permanent_address)) ? 'display:none;' : ''; ?>">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('house_flat_no', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_house" name="perm_house_flat_no" value="<?php echo htmlspecialchars($permanent_address['house_flat_no'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('street_area', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_street" name="perm_street_area" value="<?php echo htmlspecialchars($permanent_address['street_area'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('village_town', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_village" name="perm_village_town" value="<?php echo htmlspecialchars($permanent_address['village_town'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('mandal', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_mandal" name="perm_mandal" value="<?php echo htmlspecialchars($permanent_address['mandal'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('district', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_district" name="perm_district" value="<?php echo htmlspecialchars($permanent_address['district'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('state', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_state" name="perm_state" value="<?php echo htmlspecialchars($permanent_address['state'] ?? ''); ?>" placeholder="State (రాష్ట్రం)" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><?php echo lbl('pincode', $lang); ?>:</label>
                            <input type="text" class="form-control" id="perm_pincode" name="perm_pincode" maxlength="6" value="<?php echo htmlspecialchars($permanent_address['pincode'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2.4: Emergency Contact Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secEmergencyContact', 'btnToggleEmg')">
                    <span class="bel-section-title">Emergency Contact Details (అత్యవసర సంప్రదింపు వివరాలు)</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleEmg">[-]</button>
                </div>
                <div id="secEmergencyContact" class="bel-section-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('full_name', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="emergency_name" required value="<?php echo htmlspecialchars($emergency['full_name'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('relationship', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="emergency_rel" required value="<?php echo htmlspecialchars($emergency['relationship'] ?? ''); ?>" placeholder="e.g. Father, Spouse, Brother" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('mobile_number', $lang); ?>: <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">91</span>
                                <input type="tel" class="form-control" name="emergency_mobile" required maxlength="10" value="<?php echo htmlspecialchars($emergency['mobile'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo lbl('alternate_mobile', $lang); ?>:</label>
                            <div class="input-group">
                                <span class="input-group-text">91</span>
                                <input type="tel" class="form-control" name="emergency_alt_mobile" maxlength="10" value="<?php echo htmlspecialchars($emergency['alternate_mobile'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label"><?php echo lbl('address', $lang); ?>: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="emergency_address" required value="<?php echo htmlspecialchars($emergency['address'] ?? ''); ?>" placeholder="Complete postal address" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2 Bottom Action Bar -->
            <div class="bel-bottom-bar">
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="saveDraft()">
                        <i class="fas fa-save me-1"></i> SAVE DRAFT
                    </button>
                    <button type="button" class="bel-btn-outline" onclick="openPdfPreviewModal()" title="Live Form PDF Preview">
                        <i class="fas fa-file-pdf text-danger me-1"></i> PDF PREVIEW
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="goToTab(1)">
                        <i class="fas fa-chevron-left me-1"></i> PREVIOUS
                    </button>
                    <button type="button" class="bel-btn-save-next" onclick="goToTab(3)">
                        SAVE & NEXT <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= TAB 3: QUALIFICATION & EXPERIENCE ================= -->
        <div class="tab-pane-content" id="tabContent-3" style="display: none;">
            <!-- Section 3.1: Previous Employment Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPrevEmp', 'btnTogglePrevEmp')">
                    <span class="bel-section-title">Previous Employment Details (మునుపటి ఉద్యోగ వివరాలు)</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePrevEmp">[-]</button>
                </div>
                <div id="secPrevEmp" class="bel-section-body">
                    <div class="mb-3 p-2 bg-light rounded border">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="fresherToggle" onchange="toggleFresher(this.checked)">
                            <label class="form-check-label fw-bold" for="fresherToggle">
                                I am a fresher / no prior work experience (నేను ఫ్రెషర్ / ఎటువంటి మునుపటి పని అనుభవం లేదు)
                            </label>
                        </div>
                    </div>

                    <div id="prevEmpContainer" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('prev_employer', $lang); ?>:</label>
                            <input type="text" class="form-control" name="prev_employer" id="prev_employer" value="<?php echo htmlspecialchars($prev_emp['employer_name'] ?? ''); ?>" placeholder="Company / Organization Name" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('prev_designation', $lang); ?>:</label>
                            <input type="text" class="form-control" name="prev_desig" id="prev_desig" value="<?php echo htmlspecialchars($prev_emp['designation'] ?? ''); ?>" placeholder="Job title / role" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('prev_employer_phone', $lang); ?>:</label>
                            <input type="tel" class="form-control" name="prev_phone" id="prev_phone" value="<?php echo htmlspecialchars($prev_emp['employer_phone'] ?? ''); ?>" placeholder="HR or Manager Phone" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('reason_for_leaving', $lang); ?>:</label>
                            <input type="text" class="form-control" name="prev_reason" id="prev_reason" value="<?php echo htmlspecialchars($prev_emp['reason_for_leaving'] ?? ''); ?>" placeholder="e.g. Career growth, Relocation" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3.2: References Details -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secReferences', 'btnToggleRefs')">
                    <span class="bel-section-title">References Details (రిఫరెన్సులు - కనీసం ఇద్దరు వ్యక్తులు)</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleRefs">[-]</button>
                </div>
                <div id="secReferences" class="bel-section-body">
                    <div class="row g-4">
                        <!-- Ref 1 -->
                        <div class="col-md-6 border-end-md">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><?php echo lbl('reference_1', $lang); ?> <span class="badge bg-secondary fw-normal">Optional / ఐచ్ఛికం</span></h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('full_name', $lang); ?>:</label>
                                    <input type="text" class="form-control" name="ref1_name" value="<?php echo htmlspecialchars($ref1['full_name'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('relationship_how_known', $lang); ?>:</label>
                                    <input type="text" class="form-control" name="ref1_rel" value="<?php echo htmlspecialchars($ref1['relationship_how_known'] ?? ''); ?>" placeholder="e.g. Neighbor, Colleague, Village elder" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('mobile_number', $lang); ?>:</label>
                                    <div class="input-group">
                                        <span class="input-group-text">91</span>
                                        <input type="tel" class="form-control" name="ref1_mobile" maxlength="10" value="<?php echo htmlspecialchars($ref1['mobile'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('address', $lang); ?>:</label>
                                    <textarea class="form-control" rows="2" name="ref1_address" <?php echo !$is_editable ? 'readonly' : ''; ?>><?php echo htmlspecialchars($ref1['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Ref 2 -->
                        <div class="col-md-6">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3"><?php echo lbl('reference_2', $lang); ?> <span class="badge bg-secondary fw-normal">Optional / ఐచ్ఛికం</span></h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('full_name', $lang); ?>:</label>
                                    <input type="text" class="form-control" name="ref2_name" value="<?php echo htmlspecialchars($ref2['full_name'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('relationship_how_known', $lang); ?>:</label>
                                    <input type="text" class="form-control" name="ref2_rel" value="<?php echo htmlspecialchars($ref2['relationship_how_known'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('mobile_number', $lang); ?>:</label>
                                    <div class="input-group">
                                        <span class="input-group-text">91</span>
                                        <input type="tel" class="form-control" name="ref2_mobile" maxlength="10" value="<?php echo htmlspecialchars($ref2['mobile'] ?? ''); ?>" <?php echo !$is_editable ? 'readonly' : ''; ?>>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label"><?php echo lbl('address', $lang); ?>:</label>
                                    <textarea class="form-control" rows="2" name="ref2_address" <?php echo !$is_editable ? 'readonly' : ''; ?>><?php echo htmlspecialchars($ref2['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3 Bottom Action Bar -->
            <div class="bel-bottom-bar">
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="saveDraft()">
                        <i class="fas fa-save me-1"></i> SAVE DRAFT
                    </button>
                    <button type="button" class="bel-btn-outline" onclick="openPdfPreviewModal()" title="Live Form PDF Preview">
                        <i class="fas fa-file-pdf text-danger me-1"></i> PDF PREVIEW
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="goToTab(2)">
                        <i class="fas fa-chevron-left me-1"></i> PREVIOUS
                    </button>
                    <button type="button" class="bel-btn-save-next" onclick="goToTab(4)">
                        SAVE & NEXT <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= TAB 4: TERMS & PICKUP POLICY ================= -->
        <div class="tab-pane-content" id="tabContent-4" style="display: none;">
            <!-- Section 4.1: General Employment Terms & Conditions -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secEmpTerms', 'btnToggleTerms')">
                    <span class="bel-section-title">Employment Terms & Conditions (ఉపాధి నిబంధనలు మరియు షరతులు)</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleTerms">[-]</button>
                </div>
                <div id="secEmpTerms" class="bel-section-body">
                    <div class="policy-clause-box">
                        <h6><?php echo lbl('terms_clause1_title', $lang); ?></h6>
                        <div class="text-secondary small mb-1"><?php echo JoiningI18n::t('terms_clause1_text1', 'en'); ?></div>
                        <div class="te-text text-dark small" style="background:#f1f5f9; padding:4px 8px; border-radius:3px;"><?php echo JoiningI18n::t('terms_clause1_text1', 'te'); ?></div>
                    </div>

                    <div class="policy-clause-box">
                        <h6><?php echo lbl('terms_clause2_title', $lang); ?></h6>
                        <div class="text-secondary small mb-1"><?php echo JoiningI18n::t('terms_clause2_text1', 'en'); ?></div>
                        <div class="te-text text-dark small" style="background:#f1f5f9; padding:4px 8px; border-radius:3px;"><?php echo JoiningI18n::t('terms_clause2_text1', 'te'); ?></div>
                    </div>

                    <div class="policy-clause-box">
                        <h6><?php echo lbl('terms_clause3_title', $lang); ?></h6>
                        <div class="text-secondary small mb-1"><?php echo JoiningI18n::t('terms_clause3_text1', 'en'); ?></div>
                        <div class="te-text text-dark small" style="background:#f1f5f9; padding:4px 8px; border-radius:3px;"><?php echo JoiningI18n::t('terms_clause3_text1', 'te'); ?></div>
                    </div>

                    <div class="policy-clause-box">
                        <h6><?php echo lbl('terms_clause4_title', $lang); ?></h6>
                        <div class="text-secondary small mb-1"><?php echo JoiningI18n::t('terms_clause4_text1', 'en'); ?></div>
                        <div class="te-text text-dark small" style="background:#f1f5f9; padding:4px 8px; border-radius:3px;"><?php echo JoiningI18n::t('terms_clause4_text1', 'te'); ?></div>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded border">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="terms_acknowledged" id="termsAck" value="1" 
                                <?php echo (!empty($declaration['terms_acknowledged']) || !empty($_POST['terms_acknowledged'])) ? 'checked' : ''; ?> required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                            <label class="form-check-label fw-bold text-dark" for="termsAck">
                                I have read, understood, and accept all the VK Logistics Hub Employment Terms & Conditions.<br>
                                <span class="text-muted fw-normal te-text">నేను పై ఉపాధి నిబంధనలు మరియు షరతులన్నింటినీ చదివాను, అర్థం చేసుకున్నాను మరియు అంగీకరిస్తున్నాను.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 4.2: Pickup Associate Leave & Incentive Policy -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secPickupPolicy', 'btnTogglePolicy')">
                    <span class="bel-section-title">Pickup Associate Leave & Incentive Policy (పికప్ అసోసియేట్ లీవ్ & ఇన్సెంటివ్ పాలసీ)</span>
                    <button type="button" class="bel-toggle-btn" id="btnTogglePolicy">[-]</button>
                </div>
                <div id="secPickupPolicy" class="bel-section-body">
                    <div class="bel-note-dashed mb-3">
                        <strong>Notice:</strong> This policy governs paid annual leave, 12 designated public festival holidays, daily pickup quotas, and verified pickup bonuses for associates.
                    </div>

                    <!-- 12 Public Holidays -->
                    <h6 class="fw-bold text-dark mb-2">12 Designated Paid Festival Holidays:</h6>
                    <div class="row g-2 mb-3">
                        <?php
                        $holidays_en = JoiningI18n::$translations['en']['holiday_list'];
                        $holidays_te = JoiningI18n::$translations['te']['holiday_list'];
                        for ($i = 0; $i < count($holidays_en); $i++):
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-2 border rounded bg-light d-flex align-items-center">
                                    <span class="badge bg-secondary rounded-pill me-2"><?php echo $i + 1; ?></span>
                                    <div style="font-size: 13.5px; line-height: 1.4;">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($holidays_en[$i]); ?></div>
                                        <div class="text-muted te-text"><?php echo htmlspecialchars($holidays_te[$i]); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Targets Table -->
                    <h6 class="fw-bold text-dark mb-2">Daily Pickup Targets and Bonus Examples:</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered bonus-table mb-0">
                            <thead>
                                <tr>
                                    <th>Daily Target</th>
                                    <th>Completed Pickups</th>
                                    <th>Bonus Pickups</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="fw-bold">3 Pickups</td>
                                    <td>3 Completed</td>
                                    <td><span class="badge bg-secondary">0 (Daily Target Met)</span></td>
                                </tr>
                                <tr class="table-success">
                                    <td class="fw-bold">3 Pickups</td>
                                    <td>4 Completed</td>
                                    <td><span class="badge bg-success">+1 Bonus Pickup</span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">4 Pickups</td>
                                    <td>4 Completed</td>
                                    <td><span class="badge bg-secondary">0 (Daily Target Met)</span></td>
                                </tr>
                                <tr class="table-success">
                                    <td class="fw-bold">4 Pickups</td>
                                    <td>6 Completed</td>
                                    <td><span class="badge bg-success">+2 Bonus Pickups</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Policy Checkbox -->
                    <div class="p-3 bg-light rounded border">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="pickup_policy_acknowledged" id="pickupPolicyAck" value="1" 
                                <?php echo (!empty($declaration['pickup_policy_acknowledged']) || !empty($_POST['pickup_policy_acknowledged'])) ? 'checked' : ''; ?> required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                            <label class="form-check-label fw-bold text-dark" for="pickupPolicyAck">
                                I acknowledge receipt of the Associate Leave & Pickup Incentive Policy and agree to abide by all its provisions. <span class="text-danger">*</span><br>
                                <span class="text-muted fw-normal te-text">నేను అసోసియేట్ సెలవు మరియు పికప్ ప్రోత్సాహక విధానాన్ని అందుకున్నానని మరియు దాని నిబంధనలన్నింటికీ కట్టుబడి ఉంటానని ధృవీకరిస్తున్నాను.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4 Bottom Action Bar -->
            <div class="bel-bottom-bar">
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="saveDraft()">
                        <i class="fas fa-save me-1"></i> SAVE DRAFT
                    </button>
                    <button type="button" class="bel-btn-outline" onclick="openPdfPreviewModal()" title="Live Form PDF Preview">
                        <i class="fas fa-file-pdf text-danger me-1"></i> PDF PREVIEW
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="goToTab(3)">
                        <i class="fas fa-chevron-left me-1"></i> PREVIOUS
                    </button>
                    <button type="button" class="bel-btn-save-next" onclick="goToTab(5)">
                        SAVE & NEXT <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= TAB 5: DOCUMENT AND DECLARATION ================= -->
        <div class="tab-pane-content" id="tabContent-5" style="display: none;">
            <!-- Section 5.1: Upload Documents -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secUploadDocs', 'btnToggleDocs')">
                    <span class="bel-section-title">Upload Identity Documents (పత్రాలను అప్‌లోడ్ చేయండి)</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleDocs">[-]</button>
                </div>
                <div id="secUploadDocs" class="bel-section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">1. Aadhaar Card (Front & Back / PDF): <span class="badge bg-secondary fw-normal">Optional</span></label>
                            <input type="file" class="form-control" name="aadhaar_doc" accept=".pdf,.jpg,.jpeg,.png" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">2. PAN Card:</label>
                            <input type="file" class="form-control" name="pan_doc" accept=".pdf,.jpg,.jpeg,.png" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">3. Driving License (Mandatory for Pickup / Delivery Associates):</label>
                            <input type="file" class="form-control" name="dl_doc" accept=".pdf,.jpg,.jpeg,.png" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">4. Bank Passbook / Cancelled Cheque:</label>
                            <input type="file" class="form-control" name="bank_doc" accept=".pdf,.jpg,.jpeg,.png" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                        </div>
                    </div>

                    <?php if (!empty($uploaded_docs)): ?>
                        <div class="mt-3 p-3 bg-light rounded border">
                            <div class="small fw-bold text-muted mb-2"><i class="fas fa-paperclip me-1"></i> Uploaded Documents:</div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($uploaded_docs as $ud): ?>
                                    <a href="<?php echo htmlspecialchars($ud['stored_file_path']); ?>" target="_blank" class="badge bg-white text-dark border p-2 text-decoration-none">
                                        <i class="fas fa-file-pdf text-danger me-1"></i> <?php echo htmlspecialchars($ud['document_type']); ?> (<?php echo htmlspecialchars($ud['original_file_name']); ?>)
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 5.2: Employee Declaration -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secDeclaration', 'btnToggleDecl')">
                    <span class="bel-section-title">Employee Declaration (ఉద్యోగి డిక్లరేషన్)</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleDecl">[-]</button>
                </div>
                <div id="secDeclaration" class="bel-section-body">
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="mb-2">
                            <div class="fw-bold text-dark small"><?php echo JoiningI18n::t('decl_point1', 'en'); ?></div>
                            <div class="text-muted small te-text"><?php echo JoiningI18n::t('decl_point1', 'te'); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-bold text-dark small"><?php echo JoiningI18n::t('decl_point2', 'en'); ?></div>
                            <div class="text-muted small te-text"><?php echo JoiningI18n::t('decl_point2', 'te'); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-bold text-dark small"><?php echo JoiningI18n::t('decl_point3', 'en'); ?></div>
                            <div class="text-muted small te-text"><?php echo JoiningI18n::t('decl_point3', 'te'); ?></div>
                        </div>
                        <div class="mb-2">
                            <div class="fw-bold text-dark small"><?php echo JoiningI18n::t('decl_point4', 'en'); ?></div>
                            <div class="text-muted small te-text"><?php echo JoiningI18n::t('decl_point4', 'te'); ?></div>
                        </div>
                        <div>
                            <div class="fw-bold text-dark small"><?php echo JoiningI18n::t('decl_point5', 'en'); ?></div>
                            <div class="text-muted small te-text"><?php echo JoiningI18n::t('decl_point5', 'te'); ?></div>
                        </div>
                    </div>

                    <!-- Mandatory Data Consent Checkbox -->
                    <div class="p-3 bg-light border rounded">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="data_consent_acknowledged" id="dataConsentAck" value="1" 
                                <?php echo (!empty($declaration['data_consent_acknowledged']) || !empty($_POST['data_consent_acknowledged'])) ? 'checked' : ''; ?> required <?php echo !$is_editable ? 'disabled' : ''; ?>>
                            <label class="form-check-label fw-bold text-dark" for="dataConsentAck">
                                I confirm that all information provided above is true and accurate. I have read, understood, and accept all the terms, policies and declarations. <span class="text-danger">*</span><br>
                                <span class="text-muted fw-normal te-text">పైన అందించిన సమాచారమంతా సత్యమైనదని మరియు ఖచ్చితమైనదని ధృవీకరిస్తున్నాను. నిబంధనలు మరియు పాలసీలను అంగీకరిస్తున్నాను.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 5.3: Digital Signature & Impression -->
            <div class="bel-section-card">
                <div class="bel-section-head" onclick="toggleCollapse('secSignature', 'btnToggleSig')">
                    <span class="bel-section-title">Digital Signature & Impression</span>
                    <button type="button" class="bel-toggle-btn" id="btnToggleSig">[-]</button>
                </div>
                <div id="secSignature" class="bel-section-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">
                                <?php echo lbl('signature_of_employee', $lang); ?>: <span class="text-danger">*</span>
                            </label>

                            <div class="p-3 border rounded bg-light text-center">
                                <?php if (!empty($signature['signature_image_path']) && file_exists(__DIR__ . '/' . $signature['signature_image_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($signature['signature_image_path']); ?>" alt="Recorded Signature" style="max-height: 90px;" class="rounded border bg-white p-1" id="existingSignatureImg">
                                        <div class="text-muted small mt-1" style="font-size:12px;">Recorded on <?php echo date('d-M-Y H:i', strtotime($signature['signed_at'])); ?></div>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-file-signature fs-1 text-primary mb-2 d-block" id="sigIconPlaceholder"></i>
                                <?php endif; ?>

                                <?php if ($is_editable): ?>
                                    <input type="file" class="form-control mt-2" name="signature_file" id="signatureFileInput" accept="image/*,.pdf" onchange="previewSignatureUpload(this)">
                                    <div class="text-muted mt-2" style="font-size: 13px;">Upload photo or scan of employee signature (JPG, PNG)</div>
                                    
                                    <div id="sigFilePreviewContainer" class="mt-2 text-center d-none">
                                        <img id="sigFilePreview" src="#" alt="Signature Preview" style="max-height: 80px;" class="border rounded bg-white p-1">
                                    </div>

                                    <div class="mt-2 text-start">
                                        <a href="javascript:void(0)" class="small text-decoration-none text-muted" onclick="toggleCanvasDraw()">
                                            <i class="fas fa-pen-nib me-1"></i> <span id="toggleDrawLabel">Or draw signature with mouse / finger</span>
                                        </a>
                                        <div id="canvasDrawContainer" style="display: none;" class="mt-2 position-relative">
                                            <canvas id="signaturePad" class="bel-sig-canvas"></canvas>
                                            <button type="button" class="btn btn-outline-danger btn-sm position-absolute top-0 end-0 m-2" onclick="clearSignature()">
                                                <i class="fas fa-eraser me-1"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted mt-2" style="font-size:12.5px;">Your signature is legally binding under the Information Technology Act.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><?php echo lbl('left_thumb_impression', $lang); ?>: <span class="badge bg-secondary">Optional</span></label>
                            <div class="p-3 border rounded bg-light text-center">
                                <?php if (!empty($signature['thumb_impression_path']) && file_exists(__DIR__ . '/' . $signature['thumb_impression_path'])): ?>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars($signature['thumb_impression_path']); ?>" alt="Thumb Impression" style="max-height: 90px;" class="rounded border bg-white p-1" id="existingThumbImg">
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-fingerprint fs-1 text-muted mb-2 d-block" id="thumbIconPlaceholder"></i>
                                <?php endif; ?>
                                
                                <?php if ($is_editable): ?>
                                    <input type="file" class="form-control mt-2" name="thumb_impression" id="thumbImpressionInput" accept="image/*" onchange="previewThumbUpload(this)">
                                    <div class="text-muted mt-2" style="font-size: 13px;">Upload photo or scan of left thumb ink impression</div>
                                    <div id="thumbFilePreviewContainer" class="mt-2 text-center d-none">
                                        <img id="thumbFilePreview" src="#" alt="Thumb Preview" style="max-height: 80px;" class="border rounded bg-white p-1">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 5 Bottom Action Bar -->
            <div class="bel-bottom-bar">
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="saveDraft()">
                        <i class="fas fa-save me-1"></i> SAVE DRAFT
                    </button>
                    <button type="button" class="bel-btn-outline" onclick="openPdfPreviewModal()" title="Live Form PDF Preview">
                        <i class="fas fa-file-pdf text-danger me-1"></i> PDF PREVIEW
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="bel-btn-outline" onclick="goToTab(4)">
                        <i class="fas fa-chevron-left me-1"></i> PREVIOUS
                    </button>
                    <?php if ($is_editable): ?>
                        <button type="button" class="bel-btn-submit" onclick="submitApplication()">
                            <i class="fas fa-check-circle me-1"></i> SUBMIT APPLICATION
                        </button>
                    <?php else: ?>
                        <a href="dashboard/management/joining_pdf.php?id=<?php echo $app_id; ?>&token=<?php echo urlencode($application['token']); ?>&auto_print=1" target="_blank" class="bel-btn-submit text-decoration-none">
                            <i class="fas fa-download me-1"></i> DOWNLOAD PDF
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </form>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentTab = 1;
    const totalTabs = 5;

    // Send dynamic height to parent window so iframe expands without inside scrollbar
    function notifyParentOfResize() {
        if (window.parent && window.parent !== window) {
            const body = document.body;
            const html = document.documentElement;
            const h = Math.max(
                body ? body.scrollHeight : 0,
                body ? body.offsetHeight : 0,
                html ? html.clientHeight : 0,
                html ? html.scrollHeight : 0,
                html ? html.offsetHeight : 0
            );
            if (h > 200) {
                window.parent.postMessage({ type: 'vk_resize_joining_iframe', height: h + 40 }, '*');
            }
        }
    }

    function clearValidationErrors() {
        document.querySelectorAll('.bel-invalid-field, .is-invalid').forEach(el => {
            el.classList.remove('is-invalid', 'bel-invalid-field');
            el.style.removeProperty('border-color');
            el.style.removeProperty('background-color');
            el.style.removeProperty('box-shadow');
        });
    }

    let toastTimeout = null;

    function hideValidationToast() {
        const toast = document.getElementById('vkValidationToast');
        if (toast) {
            toast.style.display = 'none';
        }
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }
    }

    function clearValidationBanner() {
        hideValidationToast();
    }

    function showValidationBanner(msg, el) {
        // Broadcast to parent dashboard if embedded in iframe so it is visible in the top viewport without scrolling
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({
                    type: 'vk_show_toast',
                    message: msg
                }, '*');
            } catch(e) {}
        }

        const toast = document.getElementById('vkValidationToast');
        const msgEl = document.getElementById('vkValidationToastMsg');
        if (toast && msgEl) {
            msgEl.innerHTML = msg;
            toast.style.display = 'block';

            if (toastTimeout) clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                hideValidationToast();
            }, 4500);
        }

        // Focus field without forcing page to scroll
        if (el && typeof el.focus === 'function') {
            try {
                el.focus({ preventScroll: true });
            } catch(e) {}
        }
    }

    function markFieldInvalid(el, message, errorsList, state) {
        if (!el) return;
        el.classList.add('is-invalid', 'bel-invalid-field');
        el.style.borderColor = '#dc2626';
        el.style.backgroundColor = '#fef2f2';
        el.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.2)';

        // Auto-remove red highlight on input / change
        const clearHandler = function() {
            el.classList.remove('is-invalid', 'bel-invalid-field');
            el.style.removeProperty('border-color');
            el.style.removeProperty('background-color');
            el.style.removeProperty('box-shadow');
            el.removeEventListener('input', clearHandler);
            el.removeEventListener('change', clearHandler);
        };
        el.addEventListener('input', clearHandler);
        el.addEventListener('change', clearHandler);

        // Auto expand collapsed section if inside one
        let cardBody = el.closest('.bel-section-body');
        if (cardBody && cardBody.style.display === 'none') {
            cardBody.style.display = 'block';
            let toggleBtn = cardBody.parentElement.querySelector('.bel-toggle-btn');
            if (toggleBtn) toggleBtn.innerText = '[-]';
        }

        if (!state.firstInvalidEl) {
            state.firstInvalidEl = el;
        }
        errorsList.push(message);
    }

    function validateTab(tabIndex) {
        clearValidationErrors();
        let errors = [];
        let state = { firstInvalidEl: null };

        if (tabIndex === 1) {
            // Personal Details (Mandatory)
            const desig = document.querySelector('[name="designation"]');
            if (!desig || !desig.value.trim()) markFieldInvalid(desig, '<strong>Designation / Job Role</strong> is mandatory (ఉద్యోగ హోదా తప్పనిసరి).', errors, state);

            const dept = document.querySelector('[name="department_id"]');
            if (!dept || !dept.value.trim()) markFieldInvalid(dept, '<strong>Department</strong> is mandatory (విభాగం ఎంచుకోవాలి).', errors, state);

            const joiningDate = document.querySelector('[name="joining_date"]');
            if (!joiningDate || !joiningDate.value.trim()) markFieldInvalid(joiningDate, '<strong>Date of Joining</strong> is mandatory (చేరిక తేదీ తప్పనిసరి).', errors, state);

            const fullName = document.querySelector('[name="full_name"]');
            if (!fullName || !fullName.value.trim()) markFieldInvalid(fullName, '<strong>Full Name</strong> is mandatory (పూర్తి పేరు తప్పనిసరి).', errors, state);

            const fatherName = document.querySelector('[name="father_name"]');
            if (!fatherName || !fatherName.value.trim()) markFieldInvalid(fatherName, "<strong>Father's Name</strong> is mandatory (తండ్రి పేరు తప్పనిసరి).", errors, state);

            const dob = document.querySelector('[name="dob"]');
            if (!dob || !dob.value.trim()) markFieldInvalid(dob, '<strong>Date of Birth</strong> is mandatory (పుట్టిన తేదీ తప్పనిసరి).', errors, state);

            const gender = document.querySelector('[name="gender"]');
            if (!gender || !gender.value.trim()) markFieldInvalid(gender, '<strong>Gender</strong> is mandatory (లింగం ఎంచుకోవాలి).', errors, state);

            const bloodGroup = document.querySelector('[name="blood_group"]');
            if (!bloodGroup || !bloodGroup.value.trim()) markFieldInvalid(bloodGroup, '<strong>Blood Group</strong> is mandatory (రక్త వర్గం తప్పనిసరి).', errors, state);

            const nationality = document.querySelector('[name="nationality"]');
            if (!nationality || !nationality.value.trim()) markFieldInvalid(nationality, '<strong>Nationality</strong> is mandatory (జాతీయత తప్పనిసరి).', errors, state);

            // Passport photo
            const photoPreview = document.getElementById('photo_preview');
            const photoFile = document.getElementById('passport_photo');
            const hasExistingPhoto = photoPreview && photoPreview.style.display !== 'none' && photoPreview.src && !photoPreview.src.includes('avatar-placeholder');
            const hasUploadedPhoto = photoFile && photoFile.files && photoFile.files.length > 0;
            if (!hasExistingPhoto && !hasUploadedPhoto) {
                const photoBox = document.querySelector('.bel-photo-box');
                markFieldInvalid(photoBox, '<strong>Candidate Passport Photo</strong> is mandatory (పాస్‌పోర్ట్ సైజు ఫోటో అప్‌లోడ్ చేయండి).', errors, state);
            }

            // Aadhaar is mandatory
            const aadhaar = document.querySelector('[name="aadhaar_number"]');
            const aVal = aadhaar ? aadhaar.value.replace(/\D/g, '') : '';
            if (!aadhaar || aVal.length !== 12) {
                markFieldInvalid(aadhaar, '<strong>Aadhaar Number</strong> is mandatory and must be exactly 12 digits (12 అంకెల ఆధార్ సంఖ్య తప్పనిసరి).', errors, state);
            }

            // Bank Details are all mandatory
            const bankHolder = document.querySelector('[name="bank_holder_name"]');
            if (!bankHolder || !bankHolder.value.trim()) markFieldInvalid(bankHolder, '<strong>Account Holder Name</strong> is mandatory (ఖాతాదారుని పేరు తప్పనిసరి).', errors, state);

            const bankName = document.querySelector('[name="bank_name"]');
            if (!bankName || !bankName.value.trim()) markFieldInvalid(bankName, '<strong>Bank Name</strong> is mandatory (బ్యాంకు పేరు తప్పనిసరి).', errors, state);

            const bankBranch = document.querySelector('[name="bank_branch"]');
            if (!bankBranch || !bankBranch.value.trim()) markFieldInvalid(bankBranch, '<strong>Bank Branch</strong> is mandatory (బ్యాంకు బ్రాంచ్ తప్పనిసరి).', errors, state);

            const bankAcc = document.querySelector('[name="bank_account_no"]');
            if (!bankAcc || bankAcc.value.trim().length < 8) markFieldInvalid(bankAcc, 'Valid <strong>Bank Account Number</strong> is mandatory (బ్యాంక్ ఖాతా సంఖ్య తప్పనిసరి).', errors, state);

            const bankIfsc = document.querySelector('[name="bank_ifsc"]');
            if (!bankIfsc || bankIfsc.value.trim().length < 8) markFieldInvalid(bankIfsc, 'Valid <strong>Bank IFSC Code</strong> is mandatory (బ్యాంక్ IFSC కోడ్ తప్పనిసరి).', errors, state);
        }

        if (tabIndex === 2) {
            // Contact Details (Mandatory)
            const mobile = document.querySelector('[name="mobile"]');
            const mVal = mobile ? mobile.value.replace(/\D/g, '') : '';
            if (!mobile || mVal.length !== 10) markFieldInvalid(mobile, 'Valid 10-digit <strong>Mobile Number</strong> is mandatory (10 అంకెల మొబైల్ నంబర్ తప్పనిసరి).', errors, state);

            const email = document.querySelector('[name="email"]');
            if (!email || !email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                markFieldInvalid(email, 'Valid <strong>Email Address</strong> is mandatory (సరైన ఈమెయిల్ తప్పనిసరి).', errors, state);
            }

            // Present Address (All Mandatory)
            const pHouse = document.querySelector('[name="present_house_flat_no"]');
            if (!pHouse || !pHouse.value.trim()) markFieldInvalid(pHouse, 'Present <strong>House / Flat No</strong> is mandatory (ఇంటి నంబరు తప్పనిసరి).', errors, state);

            const pStreet = document.querySelector('[name="present_street_area"]');
            if (!pStreet || !pStreet.value.trim()) markFieldInvalid(pStreet, 'Present <strong>Street / Area</strong> is mandatory (వీధి / ఏరియా తప్పనిసరి).', errors, state);

            const pVillage = document.querySelector('[name="present_village_town"]');
            if (!pVillage || !pVillage.value.trim()) markFieldInvalid(pVillage, 'Present <strong>Village / Town</strong> is mandatory (గ్రామం / పట్టణం తప్పనిసరి).', errors, state);

            const pMandal = document.querySelector('[name="present_mandal"]');
            if (!pMandal || !pMandal.value.trim()) markFieldInvalid(pMandal, 'Present <strong>Mandal</strong> is mandatory (మండలం తప్పనిసరి).', errors, state);

            const pDistrict = document.querySelector('[name="present_district"]');
            if (!pDistrict || !pDistrict.value.trim()) markFieldInvalid(pDistrict, 'Present <strong>District</strong> is mandatory (జిల్లా తప్పనిసరి).', errors, state);

            const pState = document.querySelector('[name="present_state"]');
            if (!pState || !pState.value.trim()) markFieldInvalid(pState, 'Present <strong>State</strong> is mandatory (రాష్ట్రం తప్పనిసరి).', errors, state);

            const pPincode = document.querySelector('[name="present_pincode"]');
            const pinVal = pPincode ? pPincode.value.replace(/\D/g, '') : '';
            if (!pPincode || pinVal.length !== 6) markFieldInvalid(pPincode, 'Valid 6-digit Present <strong>PIN Code</strong> is mandatory (6 అంకెల పిన్‌కోడ్ తప్పనిసరి).', errors, state);

            // Permanent Address (if not same as present)
            const sameAsPres = document.getElementById('sameAsPresentToggle');
            if (sameAsPres && !sameAsPres.checked) {
                const permHouse = document.querySelector('[name="perm_house_flat_no"]');
                if (!permHouse || !permHouse.value.trim()) markFieldInvalid(permHouse, 'Permanent <strong>House No</strong> is mandatory (శాశ్వత ఇంటి నంబరు తప్పనిసరి).', errors, state);

                const permStreet = document.querySelector('[name="perm_street_area"]');
                if (!permStreet || !permStreet.value.trim()) markFieldInvalid(permStreet, 'Permanent <strong>Street</strong> is mandatory (శాశ్వత వీధి తప్పనిసరి).', errors, state);

                const permVillage = document.querySelector('[name="perm_village_town"]');
                if (!permVillage || !permVillage.value.trim()) markFieldInvalid(permVillage, 'Permanent <strong>Village / Town</strong> is mandatory (శాశ్వత గ్రామం/పట్టణం తప్పనిసరి).', errors, state);

                const permMandal = document.querySelector('[name="perm_mandal"]');
                if (!permMandal || !permMandal.value.trim()) markFieldInvalid(permMandal, 'Permanent <strong>Mandal</strong> is mandatory (శాశ్వత మండలం తప్పనిసరి).', errors, state);

                const permDistrict = document.querySelector('[name="perm_district"]');
                if (!permDistrict || !permDistrict.value.trim()) markFieldInvalid(permDistrict, 'Permanent <strong>District</strong> is mandatory (శాశ్వత జిల్లా తప్పనిసరి).', errors, state);

                const permState = document.querySelector('[name="perm_state"]');
                if (!permState || !permState.value.trim()) markFieldInvalid(permState, 'Permanent <strong>State</strong> is mandatory (శాశ్వత రాష్ట్రం తప్పనిసరి).', errors, state);

                const permPincode = document.querySelector('[name="perm_pincode"]');
                const permPinVal = permPincode ? permPincode.value.replace(/\D/g, '') : '';
                if (!permPincode || permPinVal.length !== 6) markFieldInvalid(permPincode, 'Valid 6-digit Permanent <strong>PIN Code</strong> is mandatory (శాశ్వత పిన్‌కోడ్ తప్పనిసరి).', errors, state);
            }

            // Emergency Contact (All Mandatory)
            const emgName = document.querySelector('[name="emergency_name"]');
            if (!emgName || !emgName.value.trim()) markFieldInvalid(emgName, '<strong>Emergency Contact Person Name</strong> is mandatory (అత్యవసర సంప్రదింపు వ్యక్తి పేరు తప్పనిసరి).', errors, state);

            const emgRel = document.querySelector('[name="emergency_rel"]');
            if (!emgRel || !emgRel.value.trim()) markFieldInvalid(emgRel, '<strong>Emergency Relationship</strong> is mandatory (సంబంధం తప్పనిసరి).', errors, state);

            const emgMobile = document.querySelector('[name="emergency_mobile"]');
            const emgMVal = emgMobile ? emgMobile.value.replace(/\D/g, '') : '';
            if (!emgMobile || emgMVal.length !== 10) markFieldInvalid(emgMobile, 'Valid 10-digit <strong>Emergency Mobile</strong> is mandatory (అత్యవసర మొబైల్ నంబర్ తప్పనిసరి).', errors, state);

            const emgAddr = document.querySelector('[name="emergency_address"]');
            if (!emgAddr || !emgAddr.value.trim()) markFieldInvalid(emgAddr, '<strong>Emergency Address</strong> is mandatory (అత్యవసర చిరునామా తప్పనిసరి).', errors, state);
        }

        if (tabIndex === 3) {
            // Tab 3: Previous Employment & References can be skipped as requested
        }

        if (tabIndex === 4) {
            // All Ticks Mandatory
            const termsAck = document.getElementById('termsAck');
            if (termsAck && !termsAck.checked) {
                markFieldInvalid(termsAck, 'Please tick and accept the <strong>Employment Terms & Conditions</strong> (ఉపాధి నిబంధనలను అంగీకరించాలి).', errors, state);
            }

            const pickupPolicyAck = document.getElementById('pickupPolicyAck');
            if (pickupPolicyAck && !pickupPolicyAck.checked) {
                markFieldInvalid(pickupPolicyAck, 'Please tick and acknowledge the <strong>Associate Leave & Pickup Incentive Policy</strong> (పాలసీని అంగీకరించాలి).', errors, state);
            }
        }

        if (tabIndex === 5) {
            // Tab 5 All Ticks & Signature Mandatory
            const dataConsent = document.getElementById('dataConsentAck');
            if (dataConsent && !dataConsent.checked) {
                markFieldInvalid(dataConsent, 'Please tick the <strong>Employee Declaration & Data Authorization</strong> confirmation (డిక్లరేషన్‌ను ధృవీకరించాలి).', errors, state);
            }

            const sigFileInput = document.getElementById('signatureFileInput');
            const hasSigFile = sigFileInput && sigFileInput.files && sigFileInput.files.length > 0;
            const hasExistingSig = document.getElementById('existingSignatureImg') !== null;
            const hasCanvasSig = (hasSignature && document.getElementById('signatureData').value !== '');
            if (!hasSigFile && !hasExistingSig && !hasCanvasSig) {
                const sigTarget = document.querySelector('#canvasDrawContainer') || sigFileInput;
                markFieldInvalid(sigTarget, '<strong>Employee Signature</strong> is mandatory (ఉద్యోగి సంతకం తప్పనిసరి). Upload photo or draw signature.', errors, state);
            }
        }

        if (errors.length > 0) {
            showValidationBanner(errors[0], state.firstInvalidEl);
            return false;
        }

        clearValidationBanner();
        return true;
    }

    function goToTab(tabNum, force = false) {
        if (!force && tabNum > currentTab) {
            for (let t = currentTab; t < tabNum; t++) {
                if (!validateTab(t)) {
                    return false;
                }
            }
        }

        clearValidationBanner();

        for (let i = 1; i <= totalTabs; i++) {
            const pane = document.getElementById('tabContent-' + i);
            const nav = document.getElementById('tabNav-' + i);
            if (pane) pane.style.display = 'none';
            if (nav) nav.classList.remove('active');
        }

        const activePane = document.getElementById('tabContent-' + tabNum);
        const activeNav = document.getElementById('tabNav-' + tabNum);
        if (activePane) activePane.style.display = 'block';
        if (activeNav) activeNav.classList.add('active');

        currentTab = tabNum;

        // Scroll parent window smoothly
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'vk_scroll_parent_top' }, '*');
        } else {
            window.scrollTo({ top: 120, behavior: 'smooth' });
        }

        setTimeout(notifyParentOfResize, 50);
        setTimeout(notifyParentOfResize, 250);

        if (tabNum === 5) {
            setTimeout(initCanvasIfNeeded, 100);
        }
        return true;
    }

    function toggleCollapse(bodyId, btnId) {
        const body = document.getElementById(bodyId);
        const btn = document.getElementById(btnId);
        if (body) {
            if (body.style.display === 'none') {
                body.style.display = 'block';
                if (btn) btn.innerText = '[-]';
            } else {
                body.style.display = 'none';
                if (btn) btn.innerText = '[+]';
            }
            setTimeout(notifyParentOfResize, 50);
            setTimeout(notifyParentOfResize, 250);
        }
    }

    function toggleEmployeeIdField() {
        const isNew = document.getElementById('type_new').checked;
        const container = document.getElementById('empIdContainer');
        if (container) {
            container.style.display = isNew ? 'none' : 'block';
        }
    }

    function toggleSameAddress(isSame) {
        const container = document.getElementById('permanentAddressContainer');
        if (container) {
            container.style.display = isSame ? 'none' : 'flex';
        }
    }

    function toggleFresher(isFresher) {
        const container = document.getElementById('prevEmpContainer');
        if (container) {
            container.style.display = isFresher ? 'none' : 'flex';
            if (isFresher) {
                document.getElementById('prev_employer').value = '';
                document.getElementById('prev_desig').value = '';
                document.getElementById('prev_phone').value = '';
                document.getElementById('prev_reason').value = 'Fresher - No Prior Experience';
            }
        }
    }

    function checkRoleRequirements(role) {
        const isPickup = role.toLowerCase().includes('pickup') || role.toLowerCase().includes('delivery');
        const tab4 = document.getElementById('tabNav-4');
        if (tab4) {
            if (isPickup) {
                tab4.style.border = '1px solid #f59e0b';
            } else {
                tab4.style.border = '1px solid rgba(255,255,255,0.35)';
            }
        }
    }

    function previewPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('photo_preview');
                const ph = document.getElementById('photo_placeholder');
                if (img) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                if (ph) {
                    ph.style.display = 'none';
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function formatAadhaar(input) {
        let val = input.value.replace(/\D/g, '');
        if (val.length > 12) val = val.substring(0, 12);
        let parts = [];
        for (let i = 0; i < val.length; i += 4) {
            parts.push(val.substring(i, i + 4));
        }
        input.value = parts.join('-');
    }

    // Canvas Signature Logic
    let canvas = null;
    let ctx = null;
    let isDrawing = false;
    let hasSignature = false;

    function initCanvasIfNeeded() {
        canvas = document.getElementById('signaturePad');
        if (!canvas) return;
        
        const rect = canvas.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;

        canvas.width = rect.width * 2;
        canvas.height = rect.height * 2;
        ctx = canvas.getContext('2d');
        ctx.scale(2, 2);
        ctx.strokeStyle = '#1e1145';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        function getPos(e) {
            const r = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - r.left,
                y: clientY - r.top
            };
        }

        function startDraw(e) {
            e.preventDefault();
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            hasSignature = true;
        }

        function stopDraw(e) {
            if (isDrawing) {
                isDrawing = false;
                document.getElementById('signatureData').value = canvas.toDataURL('image/png');
            }
        }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseleave', stopDraw);

        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw, { passive: false });
    }

    function clearSignature() {
        if (canvas && ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            document.getElementById('signatureData').value = '';
            hasSignature = false;
        }
    }

    function toggleCanvasDraw() {
        const drawBox = document.getElementById('canvasDrawContainer');
        const label = document.getElementById('toggleDrawLabel');
        if (drawBox) {
            if (drawBox.style.display === 'none' || drawBox.style.display === '') {
                drawBox.style.display = 'block';
                if (label) label.textContent = 'Hide drawing canvas';
                setTimeout(initCanvasIfNeeded, 50);
            } else {
                drawBox.style.display = 'none';
                if (label) label.textContent = 'Or draw signature with mouse / finger';
            }
        }
    }

    function previewSignatureUpload(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const previewContainer = document.getElementById('sigFilePreviewContainer');
            const previewImg = document.getElementById('sigFilePreview');
            const iconPlaceholder = document.getElementById('sigIconPlaceholder');
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (previewContainer) previewContainer.classList.remove('d-none');
                    if (iconPlaceholder) iconPlaceholder.classList.add('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                if (previewContainer) {
                    previewContainer.innerHTML = '<span class="badge bg-secondary p-2"><i class="fas fa-file-pdf me-1 text-danger"></i> ' + file.name + '</span>';
                    previewContainer.classList.remove('d-none');
                }
            }
        }
    }

    function previewThumbUpload(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const previewContainer = document.getElementById('thumbFilePreviewContainer');
            const previewImg = document.getElementById('thumbFilePreview');
            const iconPlaceholder = document.getElementById('thumbIconPlaceholder');
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (previewContainer) previewContainer.classList.remove('d-none');
                    if (iconPlaceholder) iconPlaceholder.classList.add('d-none');
                };
                reader.readAsDataURL(file);
            }
        }
    }

    function saveDraft() {
        document.getElementById('formAction').value = 'save_draft';
        if (canvas && hasSignature) {
            document.getElementById('signatureData').value = canvas.toDataURL('image/png');
        }
        document.getElementById('joiningForm').submit();
    }

    function submitApplication() {
        if (canvas && hasSignature) {
            document.getElementById('signatureData').value = canvas.toDataURL('image/png');
        }

        // Validate all mandatory tabs before submission
        for (let t of [1, 2, 4, 5]) {
            if (!validateTab(t)) {
                goToTab(t, true);
                validateTab(t);
                return;
            }
        }

        if (confirm('Are you sure you want to submit your Joining Application to HR? You will not be able to edit once submitted.')) {
            document.getElementById('formAction').value = 'submit_form';
            document.getElementById('joiningForm').submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const desig = document.getElementById('designationSelect');
        if (desig && desig.value) {
            checkRoleRequirements(desig.value);
        }
        notifyParentOfResize();
        setTimeout(notifyParentOfResize, 200);
        setTimeout(notifyParentOfResize, 800);
    });

    window.addEventListener('load', function() {
        notifyParentOfResize();
        setTimeout(notifyParentOfResize, 300);
    });

    window.addEventListener('resize', notifyParentOfResize);

    window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'vk_request_height') {
            notifyParentOfResize();
        }
    });

    if (window.ResizeObserver && document.body) {
        new ResizeObserver(() => {
            notifyParentOfResize();
        }).observe(document.body);
    }
</script>

<!-- OCR UPLOAD MODAL -->
<div class="modal fade" id="ocrUploadModal" tabindex="-1" aria-labelledby="ocrUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-25 p-2 me-3">
                        <i class="fas fa-magic text-primary fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="ocrUploadModalLabel">Upload Written Form</h5>
                        <small class="text-white-50">Upload scanned PDF or photo of physical paper form</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="closeOcrModal()"></button>
            </div>
            <div class="modal-body p-4">
                <div id="ocrUploadFormArea">
                    <!-- Drag & Drop Zone -->
                    <div id="ocrDropZone" class="border border-2 border-dashed rounded-4 p-4 text-center cursor-pointer mb-3" style="border-color: #cbd5e1; background: #f8fafc; cursor: pointer;">
                        <div class="mb-3">
                            <i class="fas fa-cloud-upload-alt text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Drag and drop your written form here, or click to browse</h6>
                        <p class="text-muted small mb-2">Supports multi-page scanned PDF or photos (JPG, PNG, WebP up to 25MB)</p>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-folder-open me-1"></i> Choose File
                        </button>
                        <input type="file" id="ocrFileInput" accept=".pdf,.jpg,.jpeg,.png,.webp" style="display: none;">
                    </div>

                    <!-- File Selected Indicator -->
                    <div id="ocrFileInfo" class="alert alert-light border d-flex align-items-center justify-content-between p-2 px-3 rounded-3 mb-3" style="display: none;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-pdf text-danger fs-4 me-2"></i>
                            <div>
                                <strong id="ocrSelectedFileName" class="text-truncate d-inline-block" style="max-width: 380px;"></strong>
                                <small id="ocrSelectedFileSize" class="text-muted ms-2"></small>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm text-danger" onclick="document.getElementById('ocrFileInput').value = ''; document.getElementById('ocrFileInfo').style.display = 'none'; document.getElementById('btnStartOcrUpload').disabled = true;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Progress / Processing State -->
                <div id="ocrProgressContainer" style="display: none;" class="py-4 text-center">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                    <h6 class="fw-bold mb-2" id="ocrStatusText">Uploading document...</h6>
                    <div class="progress rounded-pill mb-2" style="height: 10px;">
                        <div id="ocrProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 20%;"></div>
                    </div>
                    <small class="text-muted">Analyzing handwriting and mapping fields. This typically takes 3-6 seconds.</small>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-3 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal" onclick="closeOcrModal()">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-5 fw-bold" id="btnStartOcrUpload" onclick="startOcrProcessing()" disabled>
                    <i class="fas fa-check-circle me-1"></i> Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Live PDF Preview Button -->
<div id="stickyPdfPreviewFab" style="position: fixed; bottom: 25px; right: 25px; z-index: 1040;">
    <button type="button" class="btn btn-danger shadow-lg rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2" onclick="openPdfPreviewModal()" style="border: 2px solid #ffffff; box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4) !important;">
        <i class="fas fa-file-pdf fs-5"></i> <span>PDF Preview</span>
    </button>
</div>

<!-- Real-Time PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered" style="max-width: 95vw; height: 92vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="height: 100%; display: flex; flex-direction: column;">
            <div class="modal-header bg-dark text-white border-0 py-2 px-4 d-flex align-items-center justify-content-between flex-shrink-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-25 p-2 me-2 text-danger">
                        <i class="fas fa-file-pdf fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-white" id="pdfPreviewModalLabel">Live PDF Preview - Employee Joining Document</h6>
                        <small class="text-white-50">Instant preview with your currently entered form data (Pages 1 - 9)</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="printPdfPreviewFrame()">
                        <i class="fas fa-download me-1"></i> Download / Print PDF
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="openPdfPreviewNewTab()">
                        <i class="fas fa-external-link-alt me-1"></i> New Tab
                    </button>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" onclick="closePdfPreviewModal()"></button>
                </div>
            </div>
            <div class="modal-body p-0 flex-grow-1" style="height: calc(100% - 60px); background: #cbd5e1;">
                <iframe id="pdfPreviewFrame" name="pdfPreviewFrame" style="width: 100%; height: 100%; border: none; background: #cbd5e1;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function openPdfPreviewModal() {
    const form = document.getElementById('joiningForm');
    if (!form) return;

    // Build payload dictionary from form
    const formData = new FormData(form);
    const formObj = {};
    for (let [k, v] of formData.entries()) {
        formObj[k] = v;
    }

    // Capture drawn canvas signature if present
    const sigCanvas = document.getElementById('signaturePad');
    if (sigCanvas && typeof sigCanvas.toDataURL === 'function') {
        try {
            const sData = sigCanvas.toDataURL('image/png');
            if (sData && sData.length > 500) {
                formObj['signature_data'] = sData;
            }
        } catch(e) {}
    }

    formObj['is_preview'] = '1';
    formObj['id'] = '<?php echo $app_id ?? 0; ?>';
    formObj['token'] = '<?php echo $application["token"] ?? ""; ?>';

    // If running in iframe inside parent dashboard, request parent to display in its viewport
    if (window.parent && window.parent !== window) {
        try {
            window.parent.postMessage({
                type: 'vk_open_pdf_preview',
                formData: formObj
            }, '*');
            return;
        } catch(e) {}
    }

    renderLocalPdfPreview(formObj);
}

let currentPdfPreviewHtml = '';
let currentPdfPreviewPayload = null;

function renderLocalPdfPreview(formObj) {
    const modalEl = document.getElementById('pdfPreviewModal');
    if (!modalEl) return;

    currentPdfPreviewPayload = formObj;

    // Show modal immediately
    if (window.bootstrap && window.bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    } else {
        modalEl.style.display = 'block';
        modalEl.classList.add('show');
    }

    const frame = document.getElementById('pdfPreviewFrame');
    if (frame) {
        // Sleek loading animation inside iframe so it is never blank
        frame.srcdoc = `
            <!DOCTYPE html>
            <html>
            <head>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        background: #f1f5f9;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        color: #334155;
                    }
                    .loader-card {
                        background: #ffffff;
                        padding: 32px 48px;
                        border-radius: 16px;
                        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        text-align: center;
                        max-width: 420px;
                    }
                    .spinner {
                        width: 48px;
                        height: 48px;
                        border: 4px solid #e2e8f0;
                        border-top-color: #dc2626;
                        border-radius: 50%;
                        animation: spin 0.8s linear infinite;
                        margin-bottom: 20px;
                    }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                </style>
            </head>
            <body>
                <div class="loader-card">
                    <div class="spinner"></div>
                    <div style="font-weight: 700; font-size: 17px; color: #0f172a;">Generating Live PDF Preview...</div>
                    <div style="font-size: 13px; color: #64748b; margin-top: 8px; line-height: 1.5;">
                        Assembling all 9 bilingual pages with your current form data.
                    </div>
                </div>
            </body>
            </html>
        `;
    }

    // Prepare FormData
    const fd = new FormData();
    for (let k in formObj) {
        if (formObj.hasOwnProperty(k) && formObj[k] !== undefined && formObj[k] !== null) {
            fd.append(k, formObj[k]);
        }
    }

    const endpoint = window.location.origin + '/dashboard/management/joining_pdf.php';

    fetch(endpoint, {
        method: 'POST',
        body: fd
    })
    .then(function(res) {
        if (!res.ok) {
            throw new Error('Server returned HTTP ' + res.status);
        }
        return res.text();
    })
    .then(function(html) {
        currentPdfPreviewHtml = html;
        if (frame) {
            frame.srcdoc = html;
        }
    })
    .catch(function(err) {
        console.error('PDF Preview Fetch Failed:', err);
        if (frame) {
            frame.srcdoc = `
                <div style="padding: 40px; text-align: center; font-family: sans-serif; color: #dc2626;">
                    <div style="font-size: 36px; margin-bottom: 12px;"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3 style="margin-bottom: 8px;">Unable to load PDF preview</h3>
                    <p style="color: #64748b; font-size: 14px;">${err.message}</p>
                </div>
            `;
        }
    });
}

function openPdfPreviewNewTab() {
    if (currentPdfPreviewHtml) {
        const blob = new Blob([currentPdfPreviewHtml], { type: 'text/html; charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const win = window.open(url, '_blank');
        if (win) return;
    }
    // Fallback: POST via hidden form targeting _blank
    let prevForm = document.getElementById('hiddenPdfPreviewForm');
    if (!prevForm) {
        prevForm = document.createElement('form');
        prevForm.id = 'hiddenPdfPreviewForm';
        prevForm.method = 'POST';
        prevForm.action = window.location.origin + '/dashboard/management/joining_pdf.php';
        prevForm.target = '_blank';
        prevForm.style.display = 'none';
        document.body.appendChild(prevForm);
    }
    prevForm.innerHTML = '';
    if (currentPdfPreviewPayload) {
        for (let k in currentPdfPreviewPayload) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = currentPdfPreviewPayload[k];
            prevForm.appendChild(inp);
        }
    }
    prevForm.submit();
}

function printPdfPreviewFrame() {
    const frame = document.getElementById('pdfPreviewFrame');

    // 1. Tell iframe to print itself via postMessage
    if (frame && frame.contentWindow) {
        try {
            frame.contentWindow.postMessage({ type: 'vk_trigger_print' }, '*');
        } catch(e) {}
    }

    // 2. Open printable window with the PDF content and auto-trigger print dialog
    if (currentPdfPreviewHtml) {
        const printWin = window.open('', '_blank');
        if (printWin) {
            printWin.document.open();
            let htmlWithPrint = currentPdfPreviewHtml;
            if (htmlWithPrint.indexOf('auto_print') === -1) {
                htmlWithPrint = htmlWithPrint.replace('</body>', '<script>window.addEventListener("load", function(){ setTimeout(function(){ window.focus(); window.print(); }, 400); });<\/script></body>');
            }
            printWin.document.write(htmlWithPrint);
            printWin.document.close();
            return;
        }
    }

    // 3. Fallback: Form submit to new tab with auto_print=1
    let prevForm = document.getElementById('hiddenPdfPrintForm');
    if (!prevForm) {
        prevForm = document.createElement('form');
        prevForm.id = 'hiddenPdfPrintForm';
        prevForm.method = 'POST';
        prevForm.action = window.location.origin + '/dashboard/management/joining_pdf.php';
        prevForm.target = '_blank';
        prevForm.style.display = 'none';
        document.body.appendChild(prevForm);
    }
    prevForm.innerHTML = '';
    if (currentPdfPreviewPayload) {
        for (let k in currentPdfPreviewPayload) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = currentPdfPreviewPayload[k];
            prevForm.appendChild(inp);
        }
    }
    const autoInp = document.createElement('input');
    autoInp.type = 'hidden';
    autoInp.name = 'auto_print';
    autoInp.value = '1';
    prevForm.appendChild(autoInp);
    prevForm.submit();
}

function closePdfPreviewModal() {
    const modalEl = document.getElementById('pdfPreviewModal');
    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    } else if (modalEl) {
        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
        const bdrop = document.querySelector('.modal-backdrop');
        if (bdrop) bdrop.remove();
    }
}
</script>

<script src="assets/js/form_autofill.js"></script>
</body>
</html>
