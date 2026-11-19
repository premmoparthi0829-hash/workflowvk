<?php
/**
 * VK Logistics Hub - Handwritten Joining Form OCR & Auto-Fill Endpoint
 * Handles uploaded physical form scans (PDF/Images), archives documents,
 * invokes Gemini Multimodal Vision API for handwriting extraction,
 * and returns structured JSON for instant frontend auto-fill.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$response = [
    'success' => false,
    'message' => '',
    'mode' => 'none',
    'api_key_configured' => false,
    'scan_file_path' => '',
    'extracted_data' => null
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method Not Allowed. Use POST multipart/form-data.';
    echo json_encode($response);
    exit;
}

// Check for uploaded file
if (empty($_FILES['form_document']) || $_FILES['form_document']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $error_code = $_FILES['form_document']['error'] ?? 'NO_FILE';
    $response['message'] = 'No valid form document was uploaded (Error code: ' . $error_code . ').';
    echo json_encode($response);
    exit;
}

$file = $_FILES['form_document'];
$file_tmp = $file['tmp_name'];
$file_orig_name = $file['name'];
$file_size = $file['size'];
$file_ext = strtolower(pathinfo($file_orig_name, PATHINFO_EXTENSION));

// Allowed extensions
$allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
if (!in_array($file_ext, $allowed_exts)) {
    http_response_code(400);
    $response['message'] = 'Invalid file format. Please upload a PDF document or JPG/PNG/WebP image.';
    echo json_encode($response);
    exit;
}

// Allowed MIME types
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

$allowed_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/webp'
];

if (!in_array($mime_type, $allowed_mimes)) {
    // If mime detection gives octet-stream for valid extensions, map gracefully
    if ($file_ext === 'pdf') $mime_type = 'application/pdf';
    elseif (in_array($file_ext, ['jpg', 'jpeg'])) $mime_type = 'image/jpeg';
    elseif ($file_ext === 'png') $mime_type = 'image/png';
    elseif ($file_ext === 'webp') $mime_type = 'image/webp';
    else {
        http_response_code(400);
        $response['message'] = 'Unsupported document MIME type: ' . htmlspecialchars($mime_type);
        echo json_encode($response);
        exit;
    }
}

// Max 25MB
if ($file_size > 25 * 1024 * 1024) {
    http_response_code(400);
    $response['message'] = 'File size exceeds maximum permitted limit of 25MB.';
    echo json_encode($response);
    exit;
}

// Application ID & Token lookup
$db = getDB();
$app_id = !empty($_POST['application_id']) ? intval($_POST['application_id']) : null;
$app_token = trim($_POST['token'] ?? '');

if (!$app_id && !empty($app_token)) {
    $stmt = $db->prepare("SELECT id FROM employee_joining_applications WHERE token = ? LIMIT 1");
    $stmt->execute([$app_token]);
    $found = $stmt->fetch();
    if ($found) {
        $app_id = intval($found['id']);
    }
}

// Prepare archive directory
$upload_dir = __DIR__ . '/../uploads/scanned_forms/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$safe_filename = 'scan_' . ($app_id ?: 'temp') . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
$dest_path = $upload_dir . $safe_filename;
$relative_path = 'uploads/scanned_forms/' . $safe_filename;

$saved = false;
if (is_uploaded_file($file_tmp)) {
    $saved = move_uploaded_file($file_tmp, $dest_path);
} else {
    $saved = copy($file_tmp, $dest_path);
}

if (!$saved) {
    http_response_code(500);
    $response['message'] = 'Failed to save uploaded document to server storage.';
    echo json_encode($response);
    exit;
}

// Record in employee_uploaded_documents if application ID is established
if ($app_id) {
    try {
        $ins = $db->prepare("INSERT INTO employee_uploaded_documents 
            (application_id, document_type, original_file_name, stored_file_path, file_size_bytes, mime_type, uploaded_at)
            VALUES (?, 'physical_joining_form', ?, ?, ?, ?, NOW())");
        $ins->execute([$app_id, $file_orig_name, $relative_path, $file_size, $mime_type]);
    } catch (Exception $e) {
        error_log("Failed to insert scanned document record: " . $e->getMessage());
    }
}

$response['scan_file_path'] = $relative_path;

// Check Gemini API Key (from POST, config.php, or environment)
$api_key = trim($_POST['gemini_api_key'] ?? '');
if (empty($api_key)) {
    $api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
}
if (empty($api_key)) {
    $api_key = getenv('GEMINI_API_KEY') ?: '';
}

// If Gemini API Key is present, invoke Google Gemini Multimodal Vision API
if (!empty($api_key)) {
    $response['api_key_configured'] = true;
    
    // Read file data for inline base64
    $file_bytes = file_get_contents($dest_path);
    $base64_data = base64_encode($file_bytes);
    
    $prompt = <<<EOT
You are an expert AI document parser specializing in VK Logistics Hub bilingual employee joining forms (English + Telugu).
The uploaded document is a handwritten/completed physical joining form.

TASK:
Accurately read and extract all handwritten ink and printed fields into the requested structured JSON.
Handle Indian names, Telugu script transliterations, messy or cursive handwriting, dates, and phone numbers carefully.

RULES:
1. Standardize all dates to YYYY-MM-DD format (e.g. "15/06/1998" -> "1998-06-15").
2. Standardize phone and mobile numbers to 10 digits (digits only, no +91 or spaces).
3. Standardize gender to one of: "male", "female", "other".
4. Standardize designation/role to closest match: "Pickup Associate", "Delivery Associate", "Warehouse Executive", "Hub Incharge", "Operations Associate", "Branch Manager", "Accountant", "Driver", "Security Guard", "Other".
5. For Aadhaar, extract 12 digits or formatted number.
6. For other ID, identify if PAN Card, Driving License, Voter ID, or Passport.
7. If a field is empty, blank, or completely illegible in the document, return an empty string "" or null (do not invent fake data).
8. Return ONLY valid JSON adhering strictly to the schema below without markdown backticks or commentary.

SCHEMA:
{
  "personal": {
    "full_name": "",
    "father_name": "",
    "dob": "YYYY-MM-DD",
    "gender": "male",
    "mobile": "",
    "alternate_mobile": "",
    "email": "",
    "designation": "",
    "joining_date": "YYYY-MM-DD",
    "nationality": "Indian",
    "blood_group": "",
    "previous_work_experience": "",
    "employee_type": "new_joiner",
    "employee_id": ""
  },
  "present_address": {
    "house_flat_no": "",
    "street_area": "",
    "village_town": "",
    "mandal": "",
    "district": "",
    "state": "",
    "pincode": ""
  },
  "permanent_address": {
    "same_as_present": true,
    "house_flat_no": "",
    "street_area": "",
    "village_town": "",
    "mandal": "",
    "district": "",
    "state": "",
    "pincode": ""
  },
  "identity": {
    "aadhaar_number": "",
    "other_id_type": "PAN Card",
    "other_id_number": "",
    "docs_submitted": ["Aadhaar Card", "PAN Card"]
  },
  "emergency_contact": {
    "name": "",
    "relationship": "",
    "mobile": "",
    "alternate_mobile": "",
    "address": ""
  },
  "references": [
    {
      "name": "",
      "relationship": "",
      "mobile": "",
      "address": ""
    },
    {
      "name": "",
      "relationship": "",
      "mobile": "",
      "address": ""
    }
  ],
  "previous_employment": {
    "employer_name": "",
    "designation": "",
    "employer_phone": "",
    "reason_for_leaving": ""
  },
  "declarations": {
    "terms_acknowledged": true,
    "data_consent_acknowledged": true,
    "pickup_policy_acknowledged": true
  }
}
EOT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mime_type,
                            'data' => $base64_data
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.1
        ]
    ];

    // Try Gemini 2.5 Flash first, with fallback to gemini-1.5-flash
    $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];
    $parsed_data = null;
    $api_error = '';

    foreach ($models as $model_name) {
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . urlencode($api_key);
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            $api_error = "cURL error: " . $curl_err;
            continue;
        }

        if ($http_code === 200 && !empty($result)) {
            $json_resp = json_decode($result, true);
            $candidate_text = $json_resp['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            // Clean markdown wrapping if present
            $candidate_text = preg_replace('/^```(?:json)?\s*/i', '', trim($candidate_text));
            $candidate_text = preg_replace('/\s*```$/', '', $candidate_text);
            
            $extracted_json = json_decode($candidate_text, true);
            if (is_array($extracted_json) && (isset($extracted_json['personal']) || isset($extracted_json['present_address']))) {
                $parsed_data = $extracted_json;
                $response['mode'] = 'gemini_live';
                $response['model_used'] = $model_name;
                break;
            }
        } else {
            $api_error = "Gemini API returned HTTP {$http_code}: " . substr($result, 0, 200);
        }
    }

    if ($parsed_data) {
        $response['success'] = true;
        $response['message'] = 'Handwritten document successfully analyzed via Google Gemini Vision AI.';
        $response['extracted_data'] = $parsed_data;
        echo json_encode($response);
        exit;
    } else {
        // If API key was invalid or quota exceeded, record warning
        $response['api_warning'] = $api_error;
    }
}

// -------------------------------------------------------------------------
// REAL DOCUMENT EXTRACTION (NO HARDCODED / FIXED MOCK DATA)
// -------------------------------------------------------------------------

// If uploaded document is a PDF, invoke our Python PDF text & form field parser
if ($file_ext === 'pdf') {
    $py_script = __DIR__ . '/pdf_extractor.py';
    $cmd = 'python3 ' . escapeshellarg($py_script) . ' ' . escapeshellarg($dest_path);
    $output = shell_exec($cmd);
    
    if (!empty($output)) {
        $py_data = json_decode($output, true);
        if ($py_data && isset($py_data['success']) && $py_data['success']) {
            $response['success'] = true;
            $response['mode'] = ($py_data['filled_count'] > 0) ? 'pdf_extracted' : 'pdf_template';
            $response['message'] = $py_data['message'];
            $response['extracted_data'] = $py_data['extracted_data'];
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Default clean empty structure (never use hardcoded fake data)
$response['success'] = true;
$response['mode'] = 'document_archived';
$response['message'] = 'Document successfully uploaded and archived. No pre-filled text entries were detected. (To enable handwriting AI recognition for physical paper scans, provide a Gemini API Key).';

$response['extracted_data'] = [
    'personal' => [
        'full_name' => '',
        'father_name' => '',
        'dob' => '',
        'gender' => '',
        'mobile' => '',
        'alternate_mobile' => '',
        'email' => '',
        'designation' => '',
        'joining_date' => '',
        'blood_group' => '',
        'previous_work_experience' => '',
        'employee_type' => '',
        'employee_id' => ''
    ],
    'present_address' => [
        'house_flat_no' => '',
        'street_area' => '',
        'village_town' => '',
        'mandal' => '',
        'district' => '',
        'state' => '',
        'pincode' => ''
    ],
    'permanent_address' => [
        'same_as_present' => true,
        'house_flat_no' => '',
        'street_area' => '',
        'village_town' => '',
        'mandal' => '',
        'district' => '',
        'state' => '',
        'pincode' => ''
    ],
    'identity' => [
        'aadhaar_number' => '',
        'other_id_type' => '',
        'other_id_number' => '',
        'docs_submitted' => []
    ],
    'emergency_contact' => [
        'name' => '',
        'relationship' => '',
        'mobile' => '',
        'alternate_mobile' => '',
        'address' => ''
    ],
    'references' => [
        ['name' => '', 'relationship' => '', 'mobile' => '', 'address' => ''],
        ['name' => '', 'relationship' => '', 'mobile' => '', 'address' => '']
    ],
    'previous_employment' => [
        'employer_name' => '',
        'designation' => '',
        'employer_phone' => '',
        'reason_for_leaving' => ''
    ],
    'declarations' => [
        'terms_acknowledged' => false,
        'data_consent_acknowledged' => false,
        'pickup_policy_acknowledged' => false
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
