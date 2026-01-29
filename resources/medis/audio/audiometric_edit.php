<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';
require_once __DIR__ . '/../../../app/Services/company_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get test_id or test_date from URL for editing
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$test_date = isset($_GET['test_date']) ? $_GET['test_date'] : '';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

// Fetch existing test data
$existing_test = null;
$surveillance_id = 0;

if ($test_id > 0) {
    $stmt = $clinic_pdo->prepare("SELECT * FROM audiometric_tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $existing_test = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($test_date) && $patient_id > 0) {
    $stmt = $clinic_pdo->prepare("SELECT * FROM audiometric_tests WHERE patient_id = ? AND examination_date = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$patient_id, $test_date]);
    $existing_test = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$existing_test) {
    $_SESSION['error_message'] = 'Audiometric test not found.';
    header('Location: ' . app_url('audiometric_history'));
    exit();
}

// Get patient_id and surveillance_id from test record
$patient_id = $existing_test['patient_id'];
$surveillance_id = $existing_test['surveillance_id'] ?? 0;
$test_id = $existing_test['id'];

$prefilled_patient_name = '';
$prefilled_employer = '';

// Fetch comprehensive patient information (same as employee information)
$patient_data = null;
$existing_baseline = null;
$has_baseline = false;
$existing_test = $existing_test; // Use existing test data for pre-filling

if (!empty($patient_id)) {
    try {
        // Use the helper function to get complete patient information
        $patient_data = getClinicPatientById($patient_id);
        
        if ($patient_data && !isset($patient_data['error'])) {
            $prefilled_patient_name = $patient_data['first_name'] . ' ' . $patient_data['last_name'];
            $prefilled_employer = $patient_data['company_name'] ?? '';
            
            // Fetch complete company data if available
            if (!empty($patient_data['company_name'])) {
                $company_data = getCompanyByName($patient_data['company_name']);
                if ($company_data) {
                    // Merge all company fields into patient_data
                    $patient_data = array_merge($patient_data, [
                        'company_id' => $company_data['company_id'] ?? null,
                        'company_address' => $company_data['address'] ?? null,
                        'company_district' => $company_data['district'] ?? null,
                        'company_state' => $company_data['state'] ?? null,
                        'company_postcode' => $company_data['postcode'] ?? null,
                        'company_telephone' => $company_data['telephone'] ?? null,
                        'company_email' => $company_data['email'] ?? null,
                        'company_fax' => $company_data['fax'] ?? null,
                        'company_mykpp_registration_no' => $company_data['mykpp_registration_no'] ?? null,
                        'company_total_workers' => $company_data['total_workers'] ?? null
                    ]);
                    
                    // Fetch JKKP approval number from the most recent audiometric test for this patient
                    // JKKP is typically company-specific, so we get it from the latest test record
                    try {
                        $stmt = $clinic_pdo->prepare("
                            SELECT jkkp_approval_no 
                            FROM audiometric_tests 
                            WHERE patient_id = ? 
                            AND jkkp_approval_no IS NOT NULL 
                            AND jkkp_approval_no != ''
                            ORDER BY examination_date DESC, created_at DESC
                            LIMIT 1
                        ");
                        $stmt->execute([$patient_id]);
                        $jkkp_record = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($jkkp_record && !empty($jkkp_record['jkkp_approval_no'])) {
                            $patient_data['company_jkkp_approval_no'] = $jkkp_record['jkkp_approval_no'];
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching JKKP approval number: " . $e->getMessage());
                    }
                }
            }
            
            // Fetch medical staff information (doctor/examiner)
            // First try to get logged-in user's medical staff info
            $medical_staff_data = getLoggedInUserMedicalStaffInfo();
            
            // If not found, try to get by examiner_name from surveillance records
            if (!$medical_staff_data && !empty($surveillance_id)) {
                try {
                    $stmt = $clinic_pdo->prepare("
                        SELECT examiner_name 
                        FROM chemical_information 
                        WHERE surveillance_id = ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$surveillance_id]);
                    $surveillance_record = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($surveillance_record && !empty($surveillance_record['examiner_name'])) {
                        // Try to match examiner_name with medical_staff
                        // Examiner name might be in format "Dr. First Last" or "First Last"
                        $examiner_name = trim($surveillance_record['examiner_name']);
                        $examiner_name = preg_replace('/^Dr\.?\s*/i', '', $examiner_name); // Remove "Dr." prefix
                        $name_parts = explode(' ', $examiner_name, 2);
                        
                        if (count($name_parts) >= 2) {
                            $stmt = $clinic_pdo->prepare("
                                SELECT * FROM medical_staff 
                                WHERE first_name = ? AND last_name = ? 
                                LIMIT 1
                            ");
                            $stmt->execute([$name_parts[0], $name_parts[1]]);
                            $medical_staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error fetching medical staff from surveillance: " . $e->getMessage());
                }
            }
            
            // Merge medical staff data into patient_data if found
            if ($medical_staff_data) {
                $patient_data = array_merge($patient_data, [
                    'doctor_id' => $medical_staff_data['doctor_id'] ?? null,
                    'doctor_first_name' => $medical_staff_data['first_name'] ?? null,
                    'doctor_last_name' => $medical_staff_data['last_name'] ?? null,
                    'doctor_nric' => $medical_staff_data['NRIC'] ?? null,
                    'doctor_specialization' => $medical_staff_data['specialization'] ?? null,
                    'doctor_qualification' => $medical_staff_data['qualification'] ?? null,
                    'doctor_license_number' => $medical_staff_data['license_number'] ?? null,
                    'doctor_email' => $medical_staff_data['email'] ?? null,
                    'doctor_telephone' => $medical_staff_data['telephone_no'] ?? $medical_staff_data['phone'] ?? null,
                    'doctor_address' => $medical_staff_data['address'] ?? null,
                    'doctor_state' => $medical_staff_data['state'] ?? null,
                    'doctor_district' => $medical_staff_data['district'] ?? null,
                    'doctor_postcode' => $medical_staff_data['postcode'] ?? null,
                    'doctor_position' => $medical_staff_data['position'] ?? null,
                    'doctor_department' => $medical_staff_data['department'] ?? null
                ]);
            }
        } else {
            $error_msg = isset($patient_data['error']) ? $patient_data['error'] : 'Patient not found with ID: ' . $patient_id;
            error_log('Error fetching patient for audiometric test: ' . $error_msg);
            $patient_data = null;
        }
        
        // Check if this test is a baseline or has a baseline
        if ($existing_test['baseline_date']) {
            $baseline_date = $existing_test['baseline_date'];
            // Check if this is the baseline itself
            if ($existing_test['baseline_date'] == $existing_test['examination_date'] || 
                ($existing_test['id'] == $test_id && empty($existing_test['annual_date']))) {
                $has_baseline = true;
                $existing_baseline = $existing_test;
            } else {
                // This is an annual test, fetch the baseline
                $stmt = $clinic_pdo->prepare("
                    SELECT * FROM audiometric_tests 
                    WHERE patient_id = ? 
                    AND baseline_date = ?
                    ORDER BY examination_date ASC, created_at ASC
                    LIMIT 1
                ");
                $stmt->execute([$patient_id, $baseline_date]);
                $existing_baseline = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_baseline) {
                    $has_baseline = true;
                }
            }
        } else {
            // No baseline_date set, check if this is the oldest test
            $stmt = $clinic_pdo->prepare("
                SELECT * FROM audiometric_tests 
                WHERE patient_id = ? 
                ORDER BY examination_date ASC, created_at ASC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $oldest_test = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($oldest_test && $oldest_test['id'] == $test_id) {
                $has_baseline = true;
                $existing_baseline = $existing_test;
            } else {
                // Fetch baseline for annual test
                $stmt = $clinic_pdo->prepare("
                    SELECT * FROM audiometric_tests 
                    WHERE patient_id = ? 
                    ORDER BY examination_date ASC, created_at ASC
                    LIMIT 1
                ");
                $stmt->execute([$patient_id]);
                $existing_baseline = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($existing_baseline) {
                    $has_baseline = true;
                }
            }
        }
        
        // If we have a baseline, ensure it's properly set
        if ($existing_baseline && empty($existing_baseline['baseline_date']) && !empty($existing_baseline['examination_date'])) {
            $existing_baseline['baseline_date'] = $existing_baseline['examination_date'];
        }
        
        // For annual tests, we already have latest_annual_test set to existing_test
        // No need to fetch separately since we're editing the existing test
        if (!$has_baseline || ($existing_baseline && $existing_baseline['id'] != $test_id)) {
            // This is an annual test, latest_annual_test is already set to existing_test
        }
        
        // Additional check for baseline if needed
        if ($has_baseline && $existing_baseline) {
            // Fetch the most recent annual test if this is not it
            if ($existing_baseline['id'] != $test_id) {
                $stmt = $clinic_pdo->prepare("
                    SELECT * FROM audiometric_tests 
                    WHERE patient_id = ? 
                    AND id != ?
                    ORDER BY 
                        CASE WHEN annual_date IS NOT NULL THEN annual_date ELSE examination_date END DESC,
                        created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$patient_id, $baseline_id]);
                $existing_test = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_test) {
                    error_log("Latest annual test found for patient_id $patient_id: Test ID " . ($existing_test['id'] ?? 'N/A') . 
                             ", Examination Date: " . ($existing_test['examination_date'] ?? 'N/A') . 
                             ", Annual Date: " . ($existing_test['annual_date'] ?? 'N/A') .
                             ", Annual Right 250: " . ($existing_test['annual_right_250'] ?? 'NULL') .
                             ", Annual Left 250: " . ($existing_test['annual_left_250'] ?? 'NULL'));
                } else {
                    error_log("No annual test found for patient_id $patient_id (baseline_id: $baseline_id)");
                }
            }
            
            // Debug: Log that baseline was found with full details
            error_log("Baseline found for patient_id $patient_id: Test ID " . ($existing_baseline['id'] ?? 'N/A') . 
                     ", Examination Date: " . ($existing_baseline['examination_date'] ?? 'N/A') . 
                     ", Baseline Date: " . ($existing_baseline['baseline_date'] ?? 'N/A') .
                     ", Right 250: " . ($existing_baseline['right_250'] ?? 'N/A') .
                     ", Left 250: " . ($existing_baseline['left_250'] ?? 'N/A'));
        } else {
            error_log("No baseline found for patient_id $patient_id");
            // Additional debug: Check if any tests exist for this patient
            $checkStmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM audiometric_tests WHERE patient_id = ?");
            $checkStmt->execute([$patient_id]);
            $countResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Total tests for patient_id $patient_id: " . ($countResult['count'] ?? 0));
        }
    } catch (Exception $e) {
        error_log("Error fetching patient data: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['audiometric_form'])) {
    try {
        // Get patient_id from POST if not already set from GET
        if (empty($patient_id) && isset($_POST['patient_id'])) {
            $patient_id = (int)$_POST['patient_id'];
        } else {
            $patient_id = (int)$patient_id;
        }
        // Get surveillance_id from POST if not already set from GET
        if (empty($surveillance_id) && isset($_POST['surveillance_id'])) {
            $surveillance_id = isset($_POST['surveillance_id']) ? (int)$_POST['surveillance_id'] : 0;
        } else {
            $surveillance_id = (int)$surveillance_id;
        }
        
        // Get test_id from POST
        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
        
        // Validate required fields
        if (empty($patient_id) || $patient_id <= 0) {
            throw new Exception("Patient ID is required.");
        }
        if (empty($test_id) || $test_id <= 0) {
            throw new Exception("Test ID is required.");
        }
        if (empty($_POST['examination_date'])) {
            throw new Exception("Examination date is required.");
        }
        if (empty($_POST['jkkp_approval_no'])) {
            throw new Exception("JKKP Approval No is required.");
        }
        
        $clinic_pdo->beginTransaction();
        
        // Check if this is a baseline test
        $is_baseline = false;
        if (!empty($patient_id)) {
            // Check if ANY test exists for this patient (first one is always baseline)
            $checkStmt = $clinic_pdo->prepare("
                SELECT id FROM audiometric_tests 
                WHERE patient_id = ? 
                ORDER BY 
                    CASE WHEN baseline_date IS NOT NULL THEN 0 ELSE 1 END,
                    COALESCE(baseline_date, examination_date) ASC,
                    examination_date ASC,
                    created_at ASC
                LIMIT 1
            ");
            $checkStmt->execute([$patient_id]);
            $has_existing_baseline = $checkStmt->fetch() !== false;
        }
        
        // Create audiometric_tests table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS audiometric_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            surveillance_id INT,
            examination_date DATE,
            audiometer VARCHAR(100),
            calibration_date DATE,
            jkkp_approval_no VARCHAR(100),
            seg_value INT,
            otoscopy VARCHAR(50),
            rinne_right VARCHAR(50),
            rinne_left VARCHAR(50),
            weber_center VARCHAR(50),
            weber_right VARCHAR(50),
            weber_left VARCHAR(50),
            baseline_date DATE,
            annual_date DATE,
            right_250 INT,
            right_500 INT,
            right_1k INT,
            right_2k INT,
            right_3k INT,
            right_4k INT,
            right_6k INT,
            right_8k INT,
            left_250 INT,
            left_500 INT,
            left_1k INT,
            left_2k INT,
            left_3k INT,
            left_4k INT,
            left_6k INT,
            left_8k INT,
            right_bone_250 INT,
            right_bone_500 INT,
            right_bone_1k INT,
            right_bone_2k INT,
            right_bone_3k INT,
            right_bone_4k INT,
            right_bone_6k INT,
            right_bone_8k INT,
            left_bone_250 INT,
            left_bone_500 INT,
            left_bone_1k INT,
            left_bone_2k INT,
            left_bone_3k INT,
            left_bone_4k INT,
            left_bone_6k INT,
            left_bone_8k INT,
            annual_right_250 INT,
            annual_right_500 INT,
            annual_right_1k INT,
            annual_right_2k INT,
            annual_right_3k INT,
            annual_right_4k INT,
            annual_right_6k INT,
            annual_right_8k INT,
            annual_left_250 INT,
            annual_left_500 INT,
            annual_left_1k INT,
            annual_left_2k INT,
            annual_left_3k INT,
            annual_left_4k INT,
            annual_left_6k INT,
            annual_left_8k INT,
            annual_right_bone_250 INT,
            annual_right_bone_500 INT,
            annual_right_bone_1k INT,
            annual_right_bone_2k INT,
            annual_right_bone_3k INT,
            annual_right_bone_4k INT,
            annual_right_bone_6k INT,
            annual_right_bone_8k INT,
            annual_left_bone_250 INT,
            annual_left_bone_500 INT,
            annual_left_bone_1k INT,
            annual_left_bone_2k INT,
            annual_left_bone_3k INT,
            annual_left_bone_4k INT,
            annual_left_bone_6k INT,
            annual_left_bone_8k INT,
            ear_infections VARCHAR(10),
            head_injury VARCHAR(10),
            ototoxic_drugs VARCHAR(10),
            previous_ear_surgery VARCHAR(10),
            previous_noise_exposure VARCHAR(10),
            significant_hobbies TEXT,
            created_by VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $clinic_pdo->exec($createTable);
        
        // If baseline exists, insert baseline values (copied from baseline) + annual data
        // If no baseline, insert both baseline and annual data
        if ($has_existing_baseline) {
            // Fetch baseline values to copy them into the new annual test record
            $baselineStmt = $clinic_pdo->prepare("
                SELECT baseline_date, 
                       right_250, right_500, right_1k, right_2k, right_3k, right_4k, right_6k, right_8k,
                       left_250, left_500, left_1k, left_2k, left_3k, left_4k, left_6k, left_8k,
                       right_bone_250, right_bone_500, right_bone_1k, right_bone_2k, right_bone_3k, right_bone_4k, right_bone_6k, right_bone_8k,
                       left_bone_250, left_bone_500, left_bone_1k, left_bone_2k, left_bone_3k, left_bone_4k, left_bone_6k, left_bone_8k
                FROM audiometric_tests 
                WHERE patient_id = ? 
                AND baseline_date IS NOT NULL
                ORDER BY baseline_date ASC, examination_date ASC, created_at ASC
                LIMIT 1
            ");
            $baselineStmt->execute([$patient_id]);
            $baselineRow = $baselineStmt->fetch(PDO::FETCH_ASSOC);
            
            // If no explicit baseline found, use the oldest test as baseline
            if (!$baselineRow) {
                $baselineStmt = $clinic_pdo->prepare("
                    SELECT examination_date as baseline_date,
                           right_250, right_500, right_1k, right_2k, right_3k, right_4k, right_6k, right_8k,
                           left_250, left_500, left_1k, left_2k, left_3k, left_4k, left_6k, left_8k,
                           right_bone_250, right_bone_500, right_bone_1k, right_bone_2k, right_bone_3k, right_bone_4k, right_bone_6k, right_bone_8k,
                           left_bone_250, left_bone_500, left_bone_1k, left_bone_2k, left_bone_3k, left_bone_4k, left_bone_6k, left_bone_8k
                    FROM audiometric_tests 
                    WHERE patient_id = ? 
                    ORDER BY examination_date ASC, created_at ASC
                    LIMIT 1
                ");
                $baselineStmt->execute([$patient_id]);
                $baselineRow = $baselineStmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $baseline_date_value = $baselineRow ? $baselineRow['baseline_date'] : null;
            
            // Ensure baselineRow is not null before accessing its values
            if (!$baselineRow) {
                throw new Exception("Baseline data not found for patient. Cannot create annual test without baseline.");
            }
            
            // Update existing test record
            $stmt = $clinic_pdo->prepare("
                UPDATE audiometric_tests SET
                    patient_id = ?, surveillance_id = ?, examination_date = ?, audiometer = ?, calibration_date = ?, jkkp_approval_no = ?,
                    seg_value = ?, otoscopy = ?, rinne_right = ?, rinne_left = ?, weber_center = ?, weber_right = ?, weber_left = ?,
                    baseline_date = ?, annual_date = ?,
                    right_250 = ?, right_500 = ?, right_1k = ?, right_2k = ?, right_3k = ?, right_4k = ?, right_6k = ?, right_8k = ?,
                    left_250 = ?, left_500 = ?, left_1k = ?, left_2k = ?, left_3k = ?, left_4k = ?, left_6k = ?, left_8k = ?,
                    right_bone_250 = ?, right_bone_500 = ?, right_bone_1k = ?, right_bone_2k = ?, right_bone_3k = ?, right_bone_4k = ?, right_bone_6k = ?, right_bone_8k = ?,
                    left_bone_250 = ?, left_bone_500 = ?, left_bone_1k = ?, left_bone_2k = ?, left_bone_3k = ?, left_bone_4k = ?, left_bone_6k = ?, left_bone_8k = ?,
                    annual_right_250 = ?, annual_right_500 = ?, annual_right_1k = ?, annual_right_2k = ?, annual_right_3k = ?, annual_right_4k = ?, annual_right_6k = ?, annual_right_8k = ?,
                    annual_left_250 = ?, annual_left_500 = ?, annual_left_1k = ?, annual_left_2k = ?, annual_left_3k = ?, annual_left_4k = ?, annual_left_6k = ?, annual_left_8k = ?,
                    annual_right_bone_250 = ?, annual_right_bone_500 = ?, annual_right_bone_1k = ?, annual_right_bone_2k = ?, annual_right_bone_3k = ?, annual_right_bone_4k = ?, annual_right_bone_6k = ?, annual_right_bone_8k = ?,
                    annual_left_bone_250 = ?, annual_left_bone_500 = ?, annual_left_bone_1k = ?, annual_left_bone_2k = ?, annual_left_bone_3k = ?, annual_left_bone_4k = ?, annual_left_bone_6k = ?, annual_left_bone_8k = ?,
                    ear_infections = ?, head_injury = ?, ototoxic_drugs = ?, previous_ear_surgery = ?, previous_noise_exposure = ?, significant_hobbies = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            // Sanitize and prepare data
            $examination_date = $_POST['examination_date'];
            $audiometer = sanitizeInput($_POST['audiometer'] ?? '');
            $calibration_date = !empty($_POST['calibration_date']) ? $_POST['calibration_date'] : null;
            $jkkp_approval_no = sanitizeInput($_POST['jkkp_approval_no']);
            $seg_value = !empty($_POST['seg_value']) ? (int)$_POST['seg_value'] : null;
            $otoscopy = sanitizeInput($_POST['otoscopy'] ?? '');
            $rinne_right = sanitizeInput($_POST['rinne_right'] ?? '');
            $rinne_left = sanitizeInput($_POST['rinne_left'] ?? '');
            $weber_center = sanitizeInput($_POST['weber_center'] ?? '');
            $weber_right = sanitizeInput($_POST['weber_right'] ?? '');
            $weber_left = sanitizeInput($_POST['weber_left'] ?? '');
            $annual_date = !empty($_POST['annual_date']) ? $_POST['annual_date'] : null;
            $ear_infections = sanitizeInput($_POST['ear_infections'] ?? '');
            $head_injury = sanitizeInput($_POST['head_injury'] ?? '');
            $ototoxic_drugs = sanitizeInput($_POST['ototoxic_drugs'] ?? '');
            $previous_ear_surgery = sanitizeInput($_POST['previous_ear_surgery'] ?? '');
            $previous_noise_exposure = sanitizeInput($_POST['previous_noise_exposure'] ?? '');
            $significant_hobbies = sanitizeInput($_POST['significant_hobbies'] ?? '');
            
            // Convert numeric values for annual fields
            $annual_right_250 = !empty($_POST['annual_right_250']) ? (int)$_POST['annual_right_250'] : null;
            $annual_right_500 = !empty($_POST['annual_right_500']) ? (int)$_POST['annual_right_500'] : null;
            $annual_right_1k = !empty($_POST['annual_right_1k']) ? (int)$_POST['annual_right_1k'] : null;
            $annual_right_2k = !empty($_POST['annual_right_2k']) ? (int)$_POST['annual_right_2k'] : null;
            $annual_right_3k = !empty($_POST['annual_right_3k']) ? (int)$_POST['annual_right_3k'] : null;
            $annual_right_4k = !empty($_POST['annual_right_4k']) ? (int)$_POST['annual_right_4k'] : null;
            $annual_right_6k = !empty($_POST['annual_right_6k']) ? (int)$_POST['annual_right_6k'] : null;
            $annual_right_8k = !empty($_POST['annual_right_8k']) ? (int)$_POST['annual_right_8k'] : null;
            $annual_left_250 = !empty($_POST['annual_left_250']) ? (int)$_POST['annual_left_250'] : null;
            $annual_left_500 = !empty($_POST['annual_left_500']) ? (int)$_POST['annual_left_500'] : null;
            $annual_left_1k = !empty($_POST['annual_left_1k']) ? (int)$_POST['annual_left_1k'] : null;
            $annual_left_2k = !empty($_POST['annual_left_2k']) ? (int)$_POST['annual_left_2k'] : null;
            $annual_left_3k = !empty($_POST['annual_left_3k']) ? (int)$_POST['annual_left_3k'] : null;
            $annual_left_4k = !empty($_POST['annual_left_4k']) ? (int)$_POST['annual_left_4k'] : null;
            $annual_left_6k = !empty($_POST['annual_left_6k']) ? (int)$_POST['annual_left_6k'] : null;
            $annual_left_8k = !empty($_POST['annual_left_8k']) ? (int)$_POST['annual_left_8k'] : null;
            $annual_right_bone_250 = !empty($_POST['annual_right_bone_250']) ? (int)$_POST['annual_right_bone_250'] : null;
            $annual_right_bone_500 = !empty($_POST['annual_right_bone_500']) ? (int)$_POST['annual_right_bone_500'] : null;
            $annual_right_bone_1k = !empty($_POST['annual_right_bone_1k']) ? (int)$_POST['annual_right_bone_1k'] : null;
            $annual_right_bone_2k = !empty($_POST['annual_right_bone_2k']) ? (int)$_POST['annual_right_bone_2k'] : null;
            $annual_right_bone_3k = !empty($_POST['annual_right_bone_3k']) ? (int)$_POST['annual_right_bone_3k'] : null;
            $annual_right_bone_4k = !empty($_POST['annual_right_bone_4k']) ? (int)$_POST['annual_right_bone_4k'] : null;
            $annual_right_bone_6k = !empty($_POST['annual_right_bone_6k']) ? (int)$_POST['annual_right_bone_6k'] : null;
            $annual_right_bone_8k = !empty($_POST['annual_right_bone_8k']) ? (int)$_POST['annual_right_bone_8k'] : null;
            $annual_left_bone_250 = !empty($_POST['annual_left_bone_250']) ? (int)$_POST['annual_left_bone_250'] : null;
            $annual_left_bone_500 = !empty($_POST['annual_left_bone_500']) ? (int)$_POST['annual_left_bone_500'] : null;
            $annual_left_bone_1k = !empty($_POST['annual_left_bone_1k']) ? (int)$_POST['annual_left_bone_1k'] : null;
            $annual_left_bone_2k = !empty($_POST['annual_left_bone_2k']) ? (int)$_POST['annual_left_bone_2k'] : null;
            $annual_left_bone_3k = !empty($_POST['annual_left_bone_3k']) ? (int)$_POST['annual_left_bone_3k'] : null;
            $annual_left_bone_4k = !empty($_POST['annual_left_bone_4k']) ? (int)$_POST['annual_left_bone_4k'] : null;
            $annual_left_bone_6k = !empty($_POST['annual_left_bone_6k']) ? (int)$_POST['annual_left_bone_6k'] : null;
            $annual_left_bone_8k = !empty($_POST['annual_left_bone_8k']) ? (int)$_POST['annual_left_bone_8k'] : null;
            
            $stmt->execute([
                $patient_id, $surveillance_id, $examination_date, $audiometer, $calibration_date, $jkkp_approval_no,
                $seg_value, $otoscopy, $rinne_right, $rinne_left, $weber_center, $weber_right, $weber_left,
                $baseline_date_value, $annual_date,
                // Baseline values (ALWAYS copied from existing baseline record - IGNORES any POST data to prevent modification)
                $baselineRow['right_250'] ?? null, $baselineRow['right_500'] ?? null, $baselineRow['right_1k'] ?? null, $baselineRow['right_2k'] ?? null, $baselineRow['right_3k'] ?? null, $baselineRow['right_4k'] ?? null, $baselineRow['right_6k'] ?? null, $baselineRow['right_8k'] ?? null,
                $baselineRow['left_250'] ?? null, $baselineRow['left_500'] ?? null, $baselineRow['left_1k'] ?? null, $baselineRow['left_2k'] ?? null, $baselineRow['left_3k'] ?? null, $baselineRow['left_4k'] ?? null, $baselineRow['left_6k'] ?? null, $baselineRow['left_8k'] ?? null,
                $baselineRow['right_bone_250'] ?? null, $baselineRow['right_bone_500'] ?? null, $baselineRow['right_bone_1k'] ?? null, $baselineRow['right_bone_2k'] ?? null, $baselineRow['right_bone_3k'] ?? null, $baselineRow['right_bone_4k'] ?? null, $baselineRow['right_bone_6k'] ?? null, $baselineRow['right_bone_8k'] ?? null,
                $baselineRow['left_bone_250'] ?? null, $baselineRow['left_bone_500'] ?? null, $baselineRow['left_bone_1k'] ?? null, $baselineRow['left_bone_2k'] ?? null, $baselineRow['left_bone_3k'] ?? null, $baselineRow['left_bone_4k'] ?? null, $baselineRow['left_bone_6k'] ?? null, $baselineRow['left_bone_8k'] ?? null,
                // Annual values (from POST data - can be different each year)
                $annual_right_250, $annual_right_500, $annual_right_1k, $annual_right_2k, $annual_right_3k, $annual_right_4k, $annual_right_6k, $annual_right_8k,
                $annual_left_250, $annual_left_500, $annual_left_1k, $annual_left_2k, $annual_left_3k, $annual_left_4k, $annual_left_6k, $annual_left_8k,
                $annual_right_bone_250, $annual_right_bone_500, $annual_right_bone_1k, $annual_right_bone_2k, $annual_right_bone_3k, $annual_right_bone_4k, $annual_right_bone_6k, $annual_right_bone_8k,
                $annual_left_bone_250, $annual_left_bone_500, $annual_left_bone_1k, $annual_left_bone_2k, $annual_left_bone_3k, $annual_left_bone_4k, $annual_left_bone_6k, $annual_left_bone_8k,
                $ear_infections, $head_injury, $ototoxic_drugs, $previous_ear_surgery, $previous_noise_exposure, $significant_hobbies,
                $user_name
            ]);
        } else {
            // This should not happen in edit mode - we always have an existing test
            throw new Exception("Cannot update: Test record not found.");
        }
        
        // Remove the else block for INSERT - we only UPDATE in edit mode
        if (false) {
            // This code block is never executed - kept for reference only
            $stmt = $clinic_pdo->prepare("
                INSERT INTO audiometric_tests (
                    patient_id, surveillance_id, examination_date, audiometer, calibration_date, jkkp_approval_no,
                    seg_value, otoscopy, rinne_right, rinne_left, weber_center, weber_right, weber_left,
                    baseline_date, annual_date,
                    right_250, right_500, right_1k, right_2k, right_3k, right_4k, right_6k, right_8k,
                    left_250, left_500, left_1k, left_2k, left_3k, left_4k, left_6k, left_8k,
                    right_bone_250, right_bone_500, right_bone_1k, right_bone_2k, right_bone_3k, right_bone_4k, right_bone_6k, right_bone_8k,
                    left_bone_250, left_bone_500, left_bone_1k, left_bone_2k, left_bone_3k, left_bone_4k, left_bone_6k, left_bone_8k,
                    annual_right_250, annual_right_500, annual_right_1k, annual_right_2k, annual_right_3k, annual_right_4k, annual_right_6k, annual_right_8k,
                    annual_left_250, annual_left_500, annual_left_1k, annual_left_2k, annual_left_3k, annual_left_4k, annual_left_6k, annual_left_8k,
                    annual_right_bone_250, annual_right_bone_500, annual_right_bone_1k, annual_right_bone_2k, annual_right_bone_3k, annual_right_bone_4k, annual_right_bone_6k, annual_right_bone_8k,
                    annual_left_bone_250, annual_left_bone_500, annual_left_bone_1k, annual_left_bone_2k, annual_left_bone_3k, annual_left_bone_4k, annual_left_bone_6k, annual_left_bone_8k,
                    ear_infections, head_injury, ototoxic_drugs, previous_ear_surgery, previous_noise_exposure, significant_hobbies,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Sanitize and prepare data
            $examination_date = $_POST['examination_date'];
            $audiometer = sanitizeInput($_POST['audiometer'] ?? '');
            $calibration_date = !empty($_POST['calibration_date']) ? $_POST['calibration_date'] : null;
            $jkkp_approval_no = sanitizeInput($_POST['jkkp_approval_no']);
            $seg_value = !empty($_POST['seg_value']) ? (int)$_POST['seg_value'] : null;
            $otoscopy = sanitizeInput($_POST['otoscopy'] ?? '');
            $rinne_right = sanitizeInput($_POST['rinne_right'] ?? '');
            $rinne_left = sanitizeInput($_POST['rinne_left'] ?? '');
            $weber_center = sanitizeInput($_POST['weber_center'] ?? '');
            $weber_right = sanitizeInput($_POST['weber_right'] ?? '');
            $weber_left = sanitizeInput($_POST['weber_left'] ?? '');
            $baseline_date = !empty($_POST['baseline_date']) ? $_POST['baseline_date'] : null;
            $annual_date = !empty($_POST['annual_date']) ? $_POST['annual_date'] : null;
            $ear_infections = sanitizeInput($_POST['ear_infections'] ?? '');
            $head_injury = sanitizeInput($_POST['head_injury'] ?? '');
            $ototoxic_drugs = sanitizeInput($_POST['ototoxic_drugs'] ?? '');
            $previous_ear_surgery = sanitizeInput($_POST['previous_ear_surgery'] ?? '');
            $previous_noise_exposure = sanitizeInput($_POST['previous_noise_exposure'] ?? '');
            $significant_hobbies = sanitizeInput($_POST['significant_hobbies'] ?? '');
            
            // Convert numeric values for baseline fields
            $right_250 = !empty($_POST['right_250']) ? (int)$_POST['right_250'] : null;
            $right_500 = !empty($_POST['right_500']) ? (int)$_POST['right_500'] : null;
            $right_1k = !empty($_POST['right_1k']) ? (int)$_POST['right_1k'] : null;
            $right_2k = !empty($_POST['right_2k']) ? (int)$_POST['right_2k'] : null;
            $right_3k = !empty($_POST['right_3k']) ? (int)$_POST['right_3k'] : null;
            $right_4k = !empty($_POST['right_4k']) ? (int)$_POST['right_4k'] : null;
            $right_6k = !empty($_POST['right_6k']) ? (int)$_POST['right_6k'] : null;
            $right_8k = !empty($_POST['right_8k']) ? (int)$_POST['right_8k'] : null;
            $left_250 = !empty($_POST['left_250']) ? (int)$_POST['left_250'] : null;
            $left_500 = !empty($_POST['left_500']) ? (int)$_POST['left_500'] : null;
            $left_1k = !empty($_POST['left_1k']) ? (int)$_POST['left_1k'] : null;
            $left_2k = !empty($_POST['left_2k']) ? (int)$_POST['left_2k'] : null;
            $left_3k = !empty($_POST['left_3k']) ? (int)$_POST['left_3k'] : null;
            $left_4k = !empty($_POST['left_4k']) ? (int)$_POST['left_4k'] : null;
            $left_6k = !empty($_POST['left_6k']) ? (int)$_POST['left_6k'] : null;
            $left_8k = !empty($_POST['left_8k']) ? (int)$_POST['left_8k'] : null;
            $right_bone_250 = !empty($_POST['right_bone_250']) ? (int)$_POST['right_bone_250'] : null;
            $right_bone_500 = !empty($_POST['right_bone_500']) ? (int)$_POST['right_bone_500'] : null;
            $right_bone_1k = !empty($_POST['right_bone_1k']) ? (int)$_POST['right_bone_1k'] : null;
            $right_bone_2k = !empty($_POST['right_bone_2k']) ? (int)$_POST['right_bone_2k'] : null;
            $right_bone_3k = !empty($_POST['right_bone_3k']) ? (int)$_POST['right_bone_3k'] : null;
            $right_bone_4k = !empty($_POST['right_bone_4k']) ? (int)$_POST['right_bone_4k'] : null;
            $right_bone_6k = !empty($_POST['right_bone_6k']) ? (int)$_POST['right_bone_6k'] : null;
            $right_bone_8k = !empty($_POST['right_bone_8k']) ? (int)$_POST['right_bone_8k'] : null;
            $left_bone_250 = !empty($_POST['left_bone_250']) ? (int)$_POST['left_bone_250'] : null;
            $left_bone_500 = !empty($_POST['left_bone_500']) ? (int)$_POST['left_bone_500'] : null;
            $left_bone_1k = !empty($_POST['left_bone_1k']) ? (int)$_POST['left_bone_1k'] : null;
            $left_bone_2k = !empty($_POST['left_bone_2k']) ? (int)$_POST['left_bone_2k'] : null;
            $left_bone_3k = !empty($_POST['left_bone_3k']) ? (int)$_POST['left_bone_3k'] : null;
            $left_bone_4k = !empty($_POST['left_bone_4k']) ? (int)$_POST['left_bone_4k'] : null;
            $left_bone_6k = !empty($_POST['left_bone_6k']) ? (int)$_POST['left_bone_6k'] : null;
            $left_bone_8k = !empty($_POST['left_bone_8k']) ? (int)$_POST['left_bone_8k'] : null;
            $annual_right_250 = !empty($_POST['annual_right_250']) ? (int)$_POST['annual_right_250'] : null;
            $annual_right_500 = !empty($_POST['annual_right_500']) ? (int)$_POST['annual_right_500'] : null;
            $annual_right_1k = !empty($_POST['annual_right_1k']) ? (int)$_POST['annual_right_1k'] : null;
            $annual_right_2k = !empty($_POST['annual_right_2k']) ? (int)$_POST['annual_right_2k'] : null;
            $annual_right_3k = !empty($_POST['annual_right_3k']) ? (int)$_POST['annual_right_3k'] : null;
            $annual_right_4k = !empty($_POST['annual_right_4k']) ? (int)$_POST['annual_right_4k'] : null;
            $annual_right_6k = !empty($_POST['annual_right_6k']) ? (int)$_POST['annual_right_6k'] : null;
            $annual_right_8k = !empty($_POST['annual_right_8k']) ? (int)$_POST['annual_right_8k'] : null;
            $annual_left_250 = !empty($_POST['annual_left_250']) ? (int)$_POST['annual_left_250'] : null;
            $annual_left_500 = !empty($_POST['annual_left_500']) ? (int)$_POST['annual_left_500'] : null;
            $annual_left_1k = !empty($_POST['annual_left_1k']) ? (int)$_POST['annual_left_1k'] : null;
            $annual_left_2k = !empty($_POST['annual_left_2k']) ? (int)$_POST['annual_left_2k'] : null;
            $annual_left_3k = !empty($_POST['annual_left_3k']) ? (int)$_POST['annual_left_3k'] : null;
            $annual_left_4k = !empty($_POST['annual_left_4k']) ? (int)$_POST['annual_left_4k'] : null;
            $annual_left_6k = !empty($_POST['annual_left_6k']) ? (int)$_POST['annual_left_6k'] : null;
            $annual_left_8k = !empty($_POST['annual_left_8k']) ? (int)$_POST['annual_left_8k'] : null;
            $annual_right_bone_250 = !empty($_POST['annual_right_bone_250']) ? (int)$_POST['annual_right_bone_250'] : null;
            $annual_right_bone_500 = !empty($_POST['annual_right_bone_500']) ? (int)$_POST['annual_right_bone_500'] : null;
            $annual_right_bone_1k = !empty($_POST['annual_right_bone_1k']) ? (int)$_POST['annual_right_bone_1k'] : null;
            $annual_right_bone_2k = !empty($_POST['annual_right_bone_2k']) ? (int)$_POST['annual_right_bone_2k'] : null;
            $annual_right_bone_3k = !empty($_POST['annual_right_bone_3k']) ? (int)$_POST['annual_right_bone_3k'] : null;
            $annual_right_bone_4k = !empty($_POST['annual_right_bone_4k']) ? (int)$_POST['annual_right_bone_4k'] : null;
            $annual_right_bone_6k = !empty($_POST['annual_right_bone_6k']) ? (int)$_POST['annual_right_bone_6k'] : null;
            $annual_right_bone_8k = !empty($_POST['annual_right_bone_8k']) ? (int)$_POST['annual_right_bone_8k'] : null;
            $annual_left_bone_250 = !empty($_POST['annual_left_bone_250']) ? (int)$_POST['annual_left_bone_250'] : null;
            $annual_left_bone_500 = !empty($_POST['annual_left_bone_500']) ? (int)$_POST['annual_left_bone_500'] : null;
            $annual_left_bone_1k = !empty($_POST['annual_left_bone_1k']) ? (int)$_POST['annual_left_bone_1k'] : null;
            $annual_left_bone_2k = !empty($_POST['annual_left_bone_2k']) ? (int)$_POST['annual_left_bone_2k'] : null;
            $annual_left_bone_3k = !empty($_POST['annual_left_bone_3k']) ? (int)$_POST['annual_left_bone_3k'] : null;
            $annual_left_bone_4k = !empty($_POST['annual_left_bone_4k']) ? (int)$_POST['annual_left_bone_4k'] : null;
            $annual_left_bone_6k = !empty($_POST['annual_left_bone_6k']) ? (int)$_POST['annual_left_bone_6k'] : null;
            $annual_left_bone_8k = !empty($_POST['annual_left_bone_8k']) ? (int)$_POST['annual_left_bone_8k'] : null;
            
            $stmt->execute([
                $patient_id, $surveillance_id, $examination_date, $audiometer, $calibration_date, $jkkp_approval_no,
                $seg_value, $otoscopy, $rinne_right, $rinne_left, $weber_center, $weber_right, $weber_left,
                $baseline_date, $annual_date,
                $right_250, $right_500, $right_1k, $right_2k, $right_3k, $right_4k, $right_6k, $right_8k,
                $left_250, $left_500, $left_1k, $left_2k, $left_3k, $left_4k, $left_6k, $left_8k,
                $right_bone_250, $right_bone_500, $right_bone_1k, $right_bone_2k, $right_bone_3k, $right_bone_4k, $right_bone_6k, $right_bone_8k,
                $left_bone_250, $left_bone_500, $left_bone_1k, $left_bone_2k, $left_bone_3k, $left_bone_4k, $left_bone_6k, $left_bone_8k,
                $annual_right_250, $annual_right_500, $annual_right_1k, $annual_right_2k, $annual_right_3k, $annual_right_4k, $annual_right_6k, $annual_right_8k,
                $annual_left_250, $annual_left_500, $annual_left_1k, $annual_left_2k, $annual_left_3k, $annual_left_4k, $annual_left_6k, $annual_left_8k,
                $annual_right_bone_250, $annual_right_bone_500, $annual_right_bone_1k, $annual_right_bone_2k, $annual_right_bone_3k, $annual_right_bone_4k, $annual_right_bone_6k, $annual_right_bone_8k,
                $annual_left_bone_250, $annual_left_bone_500, $annual_left_bone_1k, $annual_left_bone_2k, $annual_left_bone_3k, $annual_left_bone_4k, $annual_left_bone_6k, $annual_left_bone_8k,
                $ear_infections, $head_injury, $ototoxic_drugs, $previous_ear_surgery, $previous_noise_exposure, $significant_hobbies,
                $user_name
            ]);
        }
        
        $test_id = $clinic_pdo->lastInsertId();
        
        // Commit transaction
        $clinic_pdo->commit();
        
        $_SESSION['success_message'] = 'Audiometric test saved successfully! Test ID: ' . $test_id;
        
        // Redirect to prevent form resubmission
        $redirect_url = 'audiometric_test.php?patient_id=' . urlencode($patient_id);
        if ($surveillance_id > 0) {
            $redirect_url .= '&surveillance_id=' . $surveillance_id;
        }
        $redirect_url .= '&saved=1';
        header('Location: ' . $redirect_url);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($clinic_pdo->inTransaction()) {
            $clinic_pdo->rollBack();
        }
        error_log("Error saving audiometric test: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = 'Error saving audiometric test: ' . $e->getMessage();
        // Don't redirect on error, let the form display the error message
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiometric Test - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .audiogram-chart-container {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
            min-height: 350px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }
        
        /* Clear and enhanced styling for audiogram input tables */
        .audiogram-table {
            margin-bottom: 1.5rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .audiogram-table table {
            margin-bottom: 0;
            width: 100%;
        }
        
        .audiogram-table input[type="number"] {
            width: 100%;
            min-width: 55px;
            height: 42px;
            font-size: 16px;
            font-weight: 400;
            text-align: center;
            border: 2px solid #ced4da;
            border-radius: 4px;
            padding: 8px 4px;
            background-color: #ffffff;
            color: #000000;
            line-height: 1.2;
            transition: all 0.2s ease;
        }
        
        .audiogram-table input[type="number"]:hover {
            border-color: #389B5B;
            background-color: #f8fff9;
        }
        
        .audiogram-table input[type="number"]:focus {
            border-color: #007bff;
            border-width: 3px;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            outline: none;
            background-color: #ffffff;
            z-index: 10;
            position: relative;
        }
        
        .audiogram-table input[type="number"][readonly] {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #ced4da;
        }
        
        .audiogram-table input[type="number"][readonly]:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }
        
        .audiogram-table input[type="number"]::-webkit-inner-spin-button,
        .audiogram-table input[type="number"]::-webkit-outer-spin-button {
            opacity: 1;
            height: 30px;
        }
        
        .audiogram-table th {
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            padding: 12px 8px;
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .audiogram-table th:first-child {
            background-color: #e9ecef;
            font-weight: 700;
            color: #212529;
        }
        
        .audiogram-table td {
            padding: 8px 6px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }
        
        .audiogram-table td:first-child {
            background-color: #e9ecef;
            font-weight: 700;
            font-size: 14px;
            color: #212529;
            text-align: center;
            min-width: 70px;
            border-right: 2px solid #ced4da;
        }
        
        .frequency-label {
            font-size: 10px;
            color: #6c757d;
            font-weight: 400;
            display: block;
            margin-top: 2px;
        }
        
        .audiogram-table small {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 400;
        }
        
        .audiogram-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .audiogram-table tbody tr:hover td:first-child {
            background-color: #dee2e6;
        }
        
        .report-header {
            background: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .legal-ref {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .certificate-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.3rem;
            letter-spacing: 1px;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #389B5B;
        }
        
        .form-section h5 {
            color: #389B5B;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #389B5B;
            margin-bottom: 1rem;
            margin-top: 0;
            letter-spacing: 0.5px;
        }
        
        .form-control {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 0.5rem 0.75rem;
            background: white;
            font-size: 1rem;
            color: #495057;
        }
        
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
            background: white;
        }
        
        .form-control-plaintext {
            padding: 0.5rem 0;
            font-size: 1rem;
            min-height: 2.5rem;
            display: flex;
            align-items: center;
            color: #495057;
        }
        
        /* Styling for read-only baseline fields */
        .form-control[readonly] {
            background-color: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #ced4da;
        }
        
        .form-control[readonly]:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }
        
        .baseline-readonly-note {
            font-size: 0.875rem;
            color: #6c757d;
            font-style: italic;
            margin-top: 0.5rem;
        }
        
        .btn {
            font-weight: 500;
            border-radius: 4px;
        }
        
        .btn-primary {
            background-color: #389B5B;
            border-color: #389B5B;
        }
        
        .btn-primary:hover {
            background-color: #319755;
            border-color: #2d8650;
        }
        
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 200px;
            margin-right: 1rem;
            font-size: 0.95rem;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 0.95rem;
            line-height: 1.5;
            flex: 1;
        }
        
        .table-responsive {
            margin-bottom: 1.5rem;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            padding: 0.75rem;
            text-align: center;
        }
        
        .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border-color: #dee2e6;
            text-align: center;
        }
        
        .table td:first-child {
            text-align: left;
            font-weight: 500;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .breadcrumb-nav {
            background: #f8fff9;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 1rem;
        }
        
        .breadcrumb-custom {
            background: none;
            padding: 0;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: #389B5B;
            text-decoration: none;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .navbar,
            .btn {
                display: none !important;
            }
            
            body {
                font-size: 10pt;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            
            .form-section {
                background: transparent !important;
                border: none !important;
                padding: 0.2rem 0 !important;
                margin: 0.2rem 0 !important;
            }
            
            .section-title {
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0.1rem 0 !important;
                margin-bottom: 0.2rem !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                text-align: left !important;
                color: #000 !important;
                font-size: 11pt !important;
            }
            
            .form-control {
                border: none !important;
                border-radius: 0 !important;
                background: transparent !important;
                font-weight: normal !important;
                padding: 0.1rem 0 !important;
                margin: 0.1rem 0 !important;
            }
            
            .form-label {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 10pt !important;
                margin-bottom: 0.25rem !important;
            }
        }
    </style>
</head>
<body>
    <?php // if (!isset($_GET['iframe']) || $_GET['iframe'] != '1') { include __DIR__ . '/../../views/includes/navigation.php'; } ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-3" style="background: #f8fff9; padding: 0.5rem 1rem; border-bottom: 1px solid #e9ecef;">
            <ol class="breadcrumb mb-0" style="background: none; padding: 0; margin: 0; font-size: 0.9rem;">
                <li class="breadcrumb-item"><a href="<?php echo app_url('medical_list'); ?>" style="color: #389B5B; text-decoration: none;">Company</a></li>
                <?php if ($patient_data && !empty($patient_id)): ?>
                    <li class="breadcrumb-item"><a href="<?php echo app_url('surveillance_list'); ?>?patient_id=<?php echo $patient_id; ?>" style="color: #389B5B; text-decoration: none;"><?php echo htmlspecialchars($prefilled_patient_name); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Test</li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Test</li>
                <?php endif; ?>
            </ol>
        </nav>

        <!-- Main Content Container -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 2rem; margin-bottom: 2rem;">
        
        <!-- Report Header -->
        <div class="report-header">
            <div class="text-center mb-2">
                <div class="legal-ref mb-2">Occupational Safety and Health Act 1994 (Act 514)</div>
                <div class="legal-ref mb-3">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
                <h3 class="certificate-title">AUDIOMETRIC TEST</h3>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="form-section">
            <h4 class="section-title">PATIENT INFORMATION</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="info-row">
                        <div class="info-label">NAME:</div>
                        <div class="info-value"><?php echo htmlspecialchars($prefilled_patient_name); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IC NO:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['NRIC'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">COMPANY:</div>
                        <div class="info-value"><?php echo htmlspecialchars($prefilled_employer); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">DEPARTMENT:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['department'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">JOB:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['job_title'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">STAFF NO:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">CONTACT:</div>
                        <div class="info-value">-</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-row">
                        <div class="info-label">SEX:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['gender'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">AGE:</div>
                        <div class="info-value">
                            <?php 
                            if ($patient_data && $patient_data['date_of_birth']) {
                                $birthDate = new DateTime($patient_data['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($birthDate)->y;
                                echo $age;
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">EMPLOYMENT DATE:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">DURATION OF EMPLOYMENT:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">AUDIOMETER:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">CALIBRATION DATE:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">JKKP APPROVAL NO:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['company_jkkp_approval_no'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-row">
                        <div class="info-label">SEG:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">OTOSCOPY:</div>
                        <div class="info-value">-</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RINNE TEST</div>
                        <div class="info-value">
                            <div>RIGHT: -</div>
                            <div>LEFT: -</div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">WEBER TEST LATERALIZATION</div>
                        <div class="info-value">
                            <div>CENTER: -</div>
                            <div>RIGHT: -</div>
                            <div>LEFT: -</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audiometric Test Form -->
        <form method="POST" id="audiometricForm">
            <input type="hidden" name="audiometric_form" value="1">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
            <input type="hidden" name="surveillance_id" value="<?php echo $surveillance_id; ?>">
            <?php if ($has_baseline && $existing_baseline): ?>
                <input type="hidden" name="existing_baseline_id" value="<?php echo $existing_baseline['id']; ?>">
            <?php endif; ?>
            
            <!-- Past Medical History -->
            <div class="form-section">
                <h4 class="section-title">PAST MEDICAL HISTORY</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Ear Infections:</label>
                            <select class="form-select" name="ear_infections">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['ear_infections']) && $existing_test['ear_infections'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['ear_infections']) && $existing_test['ear_infections'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Head Injury:</label>
                            <select class="form-select" name="head_injury">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['head_injury']) && $existing_test['head_injury'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['head_injury']) && $existing_test['head_injury'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Ototoxic Drugs:</label>
                            <select class="form-select" name="ototoxic_drugs">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['ototoxic_drugs']) && $existing_test['ototoxic_drugs'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['ototoxic_drugs']) && $existing_test['ototoxic_drugs'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Previous Ear Surgery:</label>
                            <select class="form-select" name="previous_ear_surgery">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['previous_ear_surgery']) && $existing_test['previous_ear_surgery'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['previous_ear_surgery']) && $existing_test['previous_ear_surgery'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Previous Noise Exposure:</label>
                            <select class="form-select" name="previous_noise_exposure">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['previous_noise_exposure']) && $existing_test['previous_noise_exposure'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['previous_noise_exposure']) && $existing_test['previous_noise_exposure'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Significant Hobbies:</label>
                            <textarea class="form-control" name="significant_hobbies" rows="2" placeholder="Enter significant hobbies"><?php echo htmlspecialchars($existing_test['significant_hobbies'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Examination Details -->
            <div class="form-section">
                <h4 class="section-title">EXAMINATION DETAILS</h4>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Date of Audiometry:</label>
                        <input type="date" class="form-control" name="examination_date" value="<?php 
                            if ($existing_test && !empty($existing_test['examination_date'])) {
                                echo htmlspecialchars($existing_test['examination_date']);
                            } else {
                                echo date('Y-m-d');
                            }
                        ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Audiometer:</label>
                        <input type="text" class="form-control" name="audiometer" value="<?php echo htmlspecialchars($existing_test['audiometer'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Calibration Date:</label>
                        <input type="date" class="form-control" name="calibration_date" value="<?php 
                            if ($existing_test && !empty($existing_test['calibration_date'])) {
                                echo htmlspecialchars($existing_test['calibration_date']);
                            } else {
                                echo '';
                            }
                        ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">JKKP Approval No:</label>
                        <input type="text" class="form-control" name="jkkp_approval_no" value="<?php 
                            if ($existing_test && !empty($existing_test['jkkp_approval_no'])) {
                                echo htmlspecialchars($existing_test['jkkp_approval_no']);
                            } else {
                                echo htmlspecialchars($patient_data['company_jkkp_approval_no'] ?? '');
                            }
                        ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SEG Value (dB):</label>
                        <input type="number" class="form-control" name="seg_value" placeholder="Enter SEG value" value="<?php echo htmlspecialchars($existing_test['seg_value'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Otoscopy:</label>
                            <select class="form-select" name="otoscopy" required>
                                <option value="">-- Select --</option>
                                <option value="NORMAL" <?php echo (isset($existing_test['otoscopy']) && $existing_test['otoscopy'] == 'NORMAL') ? 'selected' : ''; ?>>NORMAL</option>
                                <option value="ABNORMAL" <?php echo (isset($existing_test['otoscopy']) && $existing_test['otoscopy'] == 'ABNORMAL') ? 'selected' : ''; ?>>ABNORMAL</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Rinne Test - Right:</label>
                            <select class="form-select" name="rinne_right">
                                <option value="">-- Select --</option>
                                <option value="POSITIVE" <?php echo (isset($existing_test['rinne_right']) && $existing_test['rinne_right'] == 'POSITIVE') ? 'selected' : ''; ?>>POSITIVE</option>
                                <option value="NEGATIVE" <?php echo (isset($existing_test['rinne_right']) && $existing_test['rinne_right'] == 'NEGATIVE') ? 'selected' : ''; ?>>NEGATIVE</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Rinne Test - Left:</label>
                            <select class="form-select" name="rinne_left">
                                <option value="">-- Select --</option>
                                <option value="POSITIVE" <?php echo (isset($existing_test['rinne_left']) && $existing_test['rinne_left'] == 'POSITIVE') ? 'selected' : ''; ?>>POSITIVE</option>
                                <option value="NEGATIVE" <?php echo (isset($existing_test['rinne_left']) && $existing_test['rinne_left'] == 'NEGATIVE') ? 'selected' : ''; ?>>NEGATIVE</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Weber Test - Center:</label>
                            <select class="form-select" name="weber_center">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['weber_center']) && $existing_test['weber_center'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['weber_center']) && $existing_test['weber_center'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Weber Test - Right:</label>
                            <select class="form-select" name="weber_right">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['weber_right']) && $existing_test['weber_right'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['weber_right']) && $existing_test['weber_right'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Weber Test - Left:</label>
                            <select class="form-select" name="weber_left">
                                <option value="">-- Select --</option>
                                <option value="YES" <?php echo (isset($existing_test['weber_left']) && $existing_test['weber_left'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                                <option value="NO" <?php echo (isset($existing_test['weber_left']) && $existing_test['weber_left'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Baseline Audiograph -->
            <div class="form-section" id="baseline-section">
                <h4 class="section-title">BASELINE AUDIOGRAPH
                    <?php if ($has_baseline): ?>
                        <span class="badge bg-info ms-2"><i class="fas fa-lock"></i> Existing Record - Read Only</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2"><i class="fas fa-edit"></i> First Time - Please Enter Baseline Data</span>
                    <?php endif; ?>
                </h4>
                <?php if ($has_baseline): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Baseline audiograph found for this patient/employee.</strong><br>
                        The baseline data below is displayed from the existing first test record and is <strong>read-only</strong>. You cannot modify it.<br>
                        <strong>Please enter only the annual test data</strong> for this new surveillance/test in the "Annual Audiograph" section below.
                        <?php if (!empty($existing_baseline['baseline_date'])): ?>
                            <br><small><strong>Baseline Date:</strong> <?php echo date('d/m/Y', strtotime($existing_baseline['baseline_date'])); ?></small>
                        <?php elseif (!empty($existing_baseline['examination_date'])): ?>
                            <br><small><strong>Baseline Date:</strong> <?php echo date('d/m/Y', strtotime($existing_baseline['examination_date'])); ?> (First Test Record)</small>
                        <?php endif; ?>
                        <?php if (isset($existing_baseline['id'])): ?>
                            <br><small><strong>Baseline Test ID:</strong> <?php echo htmlspecialchars($existing_baseline['id']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <strong>No baseline audiograph found for this patient/employee.</strong><br>
                        This will be the <strong>first test record</strong> and will serve as the baseline for all future tests.<br>
                        <strong>Please enter the baseline audiograph data below</strong> (this is required for first-time patients/employees).
                        <?php if (!empty($patient_id)): ?>
                            <br><small>Patient ID: <?php echo htmlspecialchars($patient_id); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date:</label>
                        <input type="date" class="form-control" name="baseline_date" value="<?php 
                            if ($has_baseline && !empty($existing_baseline['baseline_date'])) {
                                echo htmlspecialchars($existing_baseline['baseline_date']);
                            } elseif ($has_baseline && !empty($existing_baseline['examination_date'])) {
                                echo htmlspecialchars($existing_baseline['examination_date']);
                            } else {
                                echo date('Y-m-d');
                            }
                        ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>>
                        <?php if ($has_baseline): ?>
                            <small class="baseline-readonly-note"><i class="fas fa-info-circle"></i> Baseline date from existing record (read-only)</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="audiogram-table">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>EAR</th>
                                        <th>250<br><small class="frequency-label">Hz</small></th>
                                        <th>500<br><small class="frequency-label">Hz</small></th>
                                        <th>1K<br><small class="frequency-label">Hz</small></th>
                                        <th>2K<br><small class="frequency-label">Hz</small></th>
                                        <th>3K<br><small class="frequency-label">Hz</small></th>
                                        <th>4K<br><small class="frequency-label">Hz</small></th>
                                        <th>6K<br><small class="frequency-label">Hz</small></th>
                                        <th>8K<br><small class="frequency-label">Hz</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RIGHT</td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="250" name="right_250" value="<?php 
                                            $is_baseline_record = ($has_baseline && $existing_test['id'] == $existing_baseline['id']);
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_250'] ?? '') : htmlspecialchars($existing_baseline['right_250'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="500" name="right_500" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_500'] ?? '') : htmlspecialchars($existing_baseline['right_500'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="1k" name="right_1k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_1k'] ?? '') : htmlspecialchars($existing_baseline['right_1k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="2k" name="right_2k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_2k'] ?? '') : htmlspecialchars($existing_baseline['right_2k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="3k" name="right_3k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_3k'] ?? '') : htmlspecialchars($existing_baseline['right_3k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="4k" name="right_4k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_4k'] ?? '') : htmlspecialchars($existing_baseline['right_4k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="6k" name="right_6k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_6k'] ?? '') : htmlspecialchars($existing_baseline['right_6k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="air" data-freq="8k" name="right_8k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['right_8k'] ?? '') : htmlspecialchars($existing_baseline['right_8k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                    </tr>
                                    <tr>
                                        <td>LEFT</td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="250" name="left_250" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_250'] ?? '') : htmlspecialchars($existing_baseline['left_250'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="500" name="left_500" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_500'] ?? '') : htmlspecialchars($existing_baseline['left_500'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="1k" name="left_1k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_1k'] ?? '') : htmlspecialchars($existing_baseline['left_1k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="2k" name="left_2k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_2k'] ?? '') : htmlspecialchars($existing_baseline['left_2k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="3k" name="left_3k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_3k'] ?? '') : htmlspecialchars($existing_baseline['left_3k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="4k" name="left_4k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_4k'] ?? '') : htmlspecialchars($existing_baseline['left_4k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="6k" name="left_6k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_6k'] ?? '') : htmlspecialchars($existing_baseline['left_6k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="air" data-freq="8k" name="left_8k" value="<?php 
                                            echo $is_baseline_record ? htmlspecialchars($existing_test['left_8k'] ?? '') : htmlspecialchars($existing_baseline['left_8k'] ?? '');
                                        ?>" <?php echo ($has_baseline && !$is_baseline_record) ? 'readonly' : ''; ?>></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="section-title mt-4">Diagnostic Bone Conduction</h5>
                        <div class="audiogram-table">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>EAR</th>
                                        <th>250<br><small class="frequency-label">Hz</small></th>
                                        <th>500<br><small class="frequency-label">Hz</small></th>
                                        <th>1K<br><small class="frequency-label">Hz</small></th>
                                        <th>2K<br><small class="frequency-label">Hz</small></th>
                                        <th>3K<br><small class="frequency-label">Hz</small></th>
                                        <th>4K<br><small class="frequency-label">Hz</small></th>
                                        <th>6K<br><small class="frequency-label">Hz</small></th>
                                        <th>8K<br><small class="frequency-label">Hz</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RIGHT</td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="250" name="right_bone_250" value="<?php echo htmlspecialchars($existing_baseline['right_bone_250'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="500" name="right_bone_500" value="<?php echo htmlspecialchars($existing_baseline['right_bone_500'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="1k" name="right_bone_1k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_1k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="2k" name="right_bone_2k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_2k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="3k" name="right_bone_3k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_3k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="4k" name="right_bone_4k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_4k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="6k" name="right_bone_6k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_6k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="right" data-type="bone" data-freq="8k" name="right_bone_8k" value="<?php echo htmlspecialchars($existing_baseline['right_bone_8k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                    </tr>
                                    <tr>
                                        <td>LEFT</td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="250" name="left_bone_250" value="<?php echo htmlspecialchars($existing_baseline['left_bone_250'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="500" name="left_bone_500" value="<?php echo htmlspecialchars($existing_baseline['left_bone_500'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="1k" name="left_bone_1k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_1k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="2k" name="left_bone_2k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_2k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="3k" name="left_bone_3k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_3k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="4k" name="left_bone_4k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_4k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="6k" name="left_bone_6k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_6k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                        <td><input type="number" class="form-control form-control-sm baseline-input" data-ear="left" data-type="bone" data-freq="8k" name="left_bone_8k" value="<?php echo htmlspecialchars($existing_baseline['left_bone_8k'] ?? ''); ?>" <?php echo $has_baseline ? 'readonly' : ''; ?>></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Annual Audiograph -->
            <div class="form-section">
                <h4 class="section-title">ANNUAL AUDIOGRAPH
                    <?php if ($existing_test): ?>
                        <span class="badge bg-success ms-2"><i class="fas fa-check-circle"></i> Latest Annual Test Found</span>
                    <?php endif; ?>
                </h4>
                <?php if ($existing_test && !empty($existing_test)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i> <strong>Latest annual test found.</strong> The annual values below are displayed from the most recent annual test record (Test ID: <?php echo htmlspecialchars($existing_test['id'] ?? 'N/A'); ?>, Date: <?php echo !empty($existing_test['annual_date']) ? date('d/m/Y', strtotime($existing_test['annual_date'])) : (!empty($existing_test['examination_date']) ? date('d/m/Y', strtotime($existing_test['examination_date'])) : 'N/A'); ?>).<br>
                        <small>You can modify these values to create a new annual test, or leave them as-is to update the existing record.</small>
                    </div>
                <?php elseif ($has_baseline): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>No annual test found.</strong> Baseline test exists. Please enter annual test data below, or run the SQL script to insert existing annual test data.
                    </div>
                <?php endif; ?>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date:</label>
                        <input type="date" class="form-control" name="annual_date" value="<?php 
                            if ($existing_test && !empty($existing_test['annual_date'])) {
                                echo htmlspecialchars($existing_test['annual_date']);
                            } elseif ($existing_test && !empty($existing_test['examination_date'])) {
                                echo htmlspecialchars($existing_test['examination_date']);
                            } else {
                                echo date('Y-m-d');
                            }
                        ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="audiogram-table">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>EAR</th>
                                        <th>250<br><small class="frequency-label">Hz</small></th>
                                        <th>500<br><small class="frequency-label">Hz</small></th>
                                        <th>1K<br><small class="frequency-label">Hz</small></th>
                                        <th>2K<br><small class="frequency-label">Hz</small></th>
                                        <th>3K<br><small class="frequency-label">Hz</small></th>
                                        <th>4K<br><small class="frequency-label">Hz</small></th>
                                        <th>6K<br><small class="frequency-label">Hz</small></th>
                                        <th>8K<br><small class="frequency-label">Hz</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RIGHT</td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="250" name="annual_right_250" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_250']) ? htmlspecialchars($existing_test['annual_right_250']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="500" name="annual_right_500" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_500']) ? htmlspecialchars($existing_test['annual_right_500']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="1k" name="annual_right_1k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_1k']) ? htmlspecialchars($existing_test['annual_right_1k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="2k" name="annual_right_2k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_2k']) ? htmlspecialchars($existing_test['annual_right_2k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="3k" name="annual_right_3k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_3k']) ? htmlspecialchars($existing_test['annual_right_3k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="4k" name="annual_right_4k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_4k']) ? htmlspecialchars($existing_test['annual_right_4k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="6k" name="annual_right_6k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_6k']) ? htmlspecialchars($existing_test['annual_right_6k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="air" data-freq="8k" name="annual_right_8k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_8k']) ? htmlspecialchars($existing_test['annual_right_8k']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>LEFT</td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="250" name="annual_left_250" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_250']) ? htmlspecialchars($existing_test['annual_left_250']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="500" name="annual_left_500" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_500']) ? htmlspecialchars($existing_test['annual_left_500']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="1k" name="annual_left_1k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_1k']) ? htmlspecialchars($existing_test['annual_left_1k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="2k" name="annual_left_2k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_2k']) ? htmlspecialchars($existing_test['annual_left_2k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="3k" name="annual_left_3k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_3k']) ? htmlspecialchars($existing_test['annual_left_3k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="4k" name="annual_left_4k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_4k']) ? htmlspecialchars($existing_test['annual_left_4k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="6k" name="annual_left_6k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_6k']) ? htmlspecialchars($existing_test['annual_left_6k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="air" data-freq="8k" name="annual_left_8k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_8k']) ? htmlspecialchars($existing_test['annual_left_8k']) : ''; ?>"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="section-title mt-4">Diagnostic Bone Conduction</h5>
                        <div class="audiogram-table">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>EAR</th>
                                        <th>250<br><small class="frequency-label">Hz</small></th>
                                        <th>500<br><small class="frequency-label">Hz</small></th>
                                        <th>1K<br><small class="frequency-label">Hz</small></th>
                                        <th>2K<br><small class="frequency-label">Hz</small></th>
                                        <th>3K<br><small class="frequency-label">Hz</small></th>
                                        <th>4K<br><small class="frequency-label">Hz</small></th>
                                        <th>6K<br><small class="frequency-label">Hz</small></th>
                                        <th>8K<br><small class="frequency-label">Hz</small></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RIGHT</td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="250" name="annual_right_bone_250" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_250']) ? htmlspecialchars($existing_test['annual_right_bone_250']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="500" name="annual_right_bone_500" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_500']) ? htmlspecialchars($existing_test['annual_right_bone_500']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="1k" name="annual_right_bone_1k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_1k']) ? htmlspecialchars($existing_test['annual_right_bone_1k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="2k" name="annual_right_bone_2k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_2k']) ? htmlspecialchars($existing_test['annual_right_bone_2k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="3k" name="annual_right_bone_3k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_3k']) ? htmlspecialchars($existing_test['annual_right_bone_3k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="4k" name="annual_right_bone_4k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_4k']) ? htmlspecialchars($existing_test['annual_right_bone_4k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="6k" name="annual_right_bone_6k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_6k']) ? htmlspecialchars($existing_test['annual_right_bone_6k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="right" data-type="bone" data-freq="8k" name="annual_right_bone_8k" value="<?php echo isset($existing_test) && isset($existing_test['annual_right_bone_8k']) ? htmlspecialchars($existing_test['annual_right_bone_8k']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>LEFT</td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="250" name="annual_left_bone_250" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_250']) ? htmlspecialchars($existing_test['annual_left_bone_250']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="500" name="annual_left_bone_500" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_500']) ? htmlspecialchars($existing_test['annual_left_bone_500']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="1k" name="annual_left_bone_1k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_1k']) ? htmlspecialchars($existing_test['annual_left_bone_1k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="2k" name="annual_left_bone_2k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_2k']) ? htmlspecialchars($existing_test['annual_left_bone_2k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="3k" name="annual_left_bone_3k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_3k']) ? htmlspecialchars($existing_test['annual_left_bone_3k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="4k" name="annual_left_bone_4k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_4k']) ? htmlspecialchars($existing_test['annual_left_bone_4k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="6k" name="annual_left_bone_6k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_6k']) ? htmlspecialchars($existing_test['annual_left_bone_6k']) : ''; ?>"></td>
                                        <td><input type="number" class="form-control form-control-sm annual-input" data-ear="left" data-type="bone" data-freq="8k" name="annual_left_bone_8k" value="<?php echo isset($existing_test) && isset($existing_test['annual_left_bone_8k']) ? htmlspecialchars($existing_test['annual_left_bone_8k']) : ''; ?>"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audiogram Graphs Side by Side -->
            <div class="form-section">
                <h4 class="section-title">AUDIOGRAM GRAPHS</h4>
                
                <!-- Symbol Customization Controls -->
                <div class="mb-4 p-3" style="background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;">
                    <h6 class="mb-3"><i class="fas fa-palette"></i> Symbol Customization</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Air Conduction - Baseline</h6>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Right Ear:</label>
                                    <select class="form-select form-select-sm" id="baseline_air_right_symbol">
                                        <option value="circle_baseline">○ (Red - Baseline)</option>
                                        <option value="circle_unmasked">○ (Maroon - Unmasked)</option>
                                        <option value="circle_masked">⬤ (Red - Masked)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Left Ear:</label>
                                    <select class="form-select form-select-sm" id="baseline_air_left_symbol">
                                        <option value="cross_baseline">X (Blue - Baseline)</option>
                                        <option value="cross_unmasked">X (Black - Unmasked)</option>
                                        <option value="cross_masked">⧗ (Blue - Masked)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Air Conduction - Annual</h6>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Right Ear:</label>
                                    <select class="form-select form-select-sm" id="annual_air_right_symbol">
                                        <option value="circle_unmasked" selected>○ (Maroon - Unmasked)</option>
                                        <option value="circle_baseline">○ (Red - Baseline)</option>
                                        <option value="circle_masked">⬤ (Red - Masked)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Left Ear:</label>
                                    <select class="form-select form-select-sm" id="annual_air_left_symbol">
                                        <option value="cross_unmasked" selected>X (Black - Unmasked)</option>
                                        <option value="cross_baseline">X (Blue - Baseline)</option>
                                        <option value="cross_masked">⧗ (Blue - Masked)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Bone Conduction - Baseline</h6>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Right Ear:</label>
                                    <select class="form-select form-select-sm" id="baseline_bone_right_symbol">
                                        <option value="triangle_unmasked_right" selected>&lt; (Dark Red - Unmasked)</option>
                                        <option value="bracket_masked_right">[ (Dark Red - Masked)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Left Ear:</label>
                                    <select class="form-select form-select-sm" id="baseline_bone_left_symbol">
                                        <option value="triangle_unmasked_left" selected>&gt; (Black - Unmasked)</option>
                                        <option value="bracket_masked_left">] (Black - Masked)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Bone Conduction - Annual</h6>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <label class="form-label small">Right Ear:</label>
                                    <select class="form-select form-select-sm" id="annual_bone_right_symbol">
                                        <option value="triangle_unmasked_right" selected>&lt; (Dark Red - Unmasked)</option>
                                        <option value="bracket_masked_right">[ (Dark Red - Masked)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Left Ear:</label>
                                    <select class="form-select form-select-sm" id="annual_bone_left_symbol">
                                        <option value="triangle_unmasked_left" selected>&gt; (Black - Unmasked)</option>
                                        <option value="bracket_masked_left">] (Black - Masked)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="applySymbolChanges()">
                        <i class="fas fa-sync"></i> Apply Symbol Changes
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="section-title mb-3">BASELINE AUDIOGRAM GRAPH</h5>
                        <div class="audiogram-chart-container">
                            <div class="chart-wrapper" style="height: 500px;">
                                <canvas id="baselineChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="section-title mb-3">ANNUAL AUDIOGRAM GRAPH</h5>
                        <div class="audiogram-chart-container">
                            <div class="chart-wrapper" style="height: 500px;">
                                <canvas id="annualChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-4">
                <button type="button" class="btn btn-outline-secondary btn-lg me-3" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Save Test
                </button>
                <button type="button" class="btn btn-success btn-lg" onclick="window.location.href='audiometric_summary.php?patient_id=<?php echo $patient_id; ?>&surveillance_id=<?php echo $surveillance_id; ?>'">
                    <i class="fas fa-arrow-right"></i> Next: Summary
                </button>
            </div>
        </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const frequencies = [250, 500, 1000, 2000, 3000, 4000, 6000, 8000];
        const frequencyLabels = ['250', '500', '1K', '2K', '3K', '4K', '6K', '8K'];
        
        let baselineChart = null;
        let annualChart = null;
        
        // Symbol configuration storage
        let symbolConfig = {
            baseline: {
                air: { right: 'circle_baseline', left: 'cross_baseline' },
                bone: { right: 'triangle_unmasked_right', left: 'triangle_unmasked_left' }
            },
            annual: {
                air: { right: 'circle_unmasked', left: 'cross_unmasked' },
                bone: { right: 'triangle_unmasked_right', left: 'triangle_unmasked_left' }
            }
        };
        
        // Symbol definitions with colors
        const symbolDefinitions = {
            // Air Conduction
            'circle_baseline': { style: 'circle', color: '#dc3545', fill: false }, // Red circle
            'circle_unmasked': { style: 'circle', color: '#800000', fill: false }, // Maroon circle
            'circle_masked': { style: '⬤', color: '#dc3545', fill: false }, // Red ⬤
            'cross_baseline': { style: '×', color: '#007bff', fill: false }, // Blue ×
            'cross_unmasked': { style: '×', color: '#000000', fill: false }, // Black ×
            'cross_masked': { style: '⧗', color: '#007bff', fill: false }, // Blue ⧗
            // Bone Conduction
            'triangle_unmasked_right': { style: '<', color: '#c40404', rotation: 0 }, // Dark Red <
            'triangle_unmasked_left': { style: '>', color: '#000000', rotation: 0 }, // Black >
            'bracket_masked_right': { style: '[', color: '#c40404', rotation: 0 }, // Dark Red [
            'bracket_masked_left': { style: ']', color: '#000000', rotation: 0 } // Black ]
        };
        
        // Custom point renderer plugin for Chart.js
        const customPointRenderer = {
            id: 'customPointRenderer',
            afterDatasetsDraw: function(chart) {
                const ctx = chart.ctx;
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    if (!meta.hidden && dataset.symbolKey) {
                        const symbolDef = symbolDefinitions[dataset.symbolKey];
                        // Draw Unicode symbols as text (excluding circles which use Chart.js native style)
                        if (symbolDef && symbolDef.style !== 'circle') {
                            meta.data.forEach((point, index) => {
                                // Only draw symbol if there's actual data (not null, not undefined)
                                const dataValue = dataset.data[index];
                                if (point && 
                                    point.y !== null && 
                                    point.y !== undefined && 
                                    !isNaN(point.x) && 
                                    !isNaN(point.y) && 
                                    dataValue !== null && 
                                    dataValue !== undefined && 
                                    dataValue !== '') {
                                    // Draw Unicode symbol as text
                                    ctx.save();
                                    ctx.fillStyle = symbolDef.color;
                                    // Ensure consistent symbol size - use fixed size for all symbols
                                    const symbolSize = 15; // Fixed size for all symbols
                                    ctx.font = 'bold ' + symbolSize + 'px Arial';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'middle';
                                    ctx.fillText(symbolDef.style, point.x, point.y);
                                    ctx.restore();
                                }
                            });
                        }
                    }
                });
            }
        };
        
        // Register the custom plugin
        Chart.register(customPointRenderer);
        
        // Function to get symbol configuration for a dataset
        function getSymbolConfig(chartType, ear, type) {
            const config = symbolConfig[chartType][type][ear];
            return symbolDefinitions[config] || symbolDefinitions['circle_unmasked'];
        }
        
        // Function to apply symbol changes
        function applySymbolChanges() {
            // Update symbol configuration from UI
            symbolConfig.baseline.air.right = document.getElementById('baseline_air_right_symbol').value;
            symbolConfig.baseline.air.left = document.getElementById('baseline_air_left_symbol').value;
            symbolConfig.baseline.bone.right = document.getElementById('baseline_bone_right_symbol').value;
            symbolConfig.baseline.bone.left = document.getElementById('baseline_bone_left_symbol').value;
            symbolConfig.annual.air.right = document.getElementById('annual_air_right_symbol').value;
            symbolConfig.annual.air.left = document.getElementById('annual_air_left_symbol').value;
            symbolConfig.annual.bone.right = document.getElementById('annual_bone_right_symbol').value;
            symbolConfig.annual.bone.left = document.getElementById('annual_bone_left_symbol').value;
            
            // Recreate charts with new symbols
            if (baselineChart) {
                baselineChart.destroy();
                baselineChart = createBaselineChart('baselineChart');
            }
            if (annualChart) {
                annualChart.destroy();
                annualChart = createAnnualChart('annualChart');
            }
        }
        
        function getValue(input) {
            if (!input) return null;
            const val = input.value;
            // Handle readonly fields and empty values - return null for empty strings
            if (val === '' || val === null || val === undefined || val.trim() === '') return null;
            const numVal = parseFloat(val);
            // Return null if NaN or if the original value was empty/whitespace
            return (!isNaN(numVal) && val.trim() !== '') ? numVal : null;
        }
        
        function getBaselineData(ear, type) {
            const data = [];
            frequencies.forEach(freq => {
                let key;
                if (freq === 250) key = ear + (type === 'bone' ? '_bone_250' : '_250');
                else if (freq === 500) key = ear + (type === 'bone' ? '_bone_500' : '_500');
                else if (freq === 1000) key = ear + (type === 'bone' ? '_bone_1k' : '_1k');
                else if (freq === 2000) key = ear + (type === 'bone' ? '_bone_2k' : '_2k');
                else if (freq === 3000) key = ear + (type === 'bone' ? '_bone_3k' : '_3k');
                else if (freq === 4000) key = ear + (type === 'bone' ? '_bone_4k' : '_4k');
                else if (freq === 6000) key = ear + (type === 'bone' ? '_bone_6k' : '_6k');
                else if (freq === 8000) key = ear + (type === 'bone' ? '_bone_8k' : '_8k');
                
                const input = document.querySelector(`input[name="${key}"]`);
                const value = getValue(input);
                
                // Debug: Log if input not found or value is null for bone conduction
                if (type === 'bone' && !input) {
                    console.warn(`Baseline bone conduction input not found: ${key}`);
                } else if (type === 'bone') {
                    if (value === null && input) {
                        console.log(`Baseline bone conduction value is null for ${key}, input value: "${input.value}", input exists: ${!!input}`);
                    } else if (value !== null) {
                        console.log(`Baseline bone conduction value found for ${key}: ${value}`);
                    }
                }
                
                data.push(value);
            });
            
            // Debug: Log summary for bone conduction
            if (type === 'bone') {
                const validValues = data.filter(v => v !== null && v !== undefined);
                console.log(`Baseline ${ear} bone conduction: ${validValues.length} valid values out of ${data.length}`, data);
            }
            
            return data;
        }
        
        function getAnnualData(ear, type) {
            const data = [];
            frequencies.forEach(freq => {
                let key;
                if (freq === 250) key = 'annual_' + ear + (type === 'bone' ? '_bone_250' : '_250');
                else if (freq === 500) key = 'annual_' + ear + (type === 'bone' ? '_bone_500' : '_500');
                else if (freq === 1000) key = 'annual_' + ear + (type === 'bone' ? '_bone_1k' : '_1k');
                else if (freq === 2000) key = 'annual_' + ear + (type === 'bone' ? '_bone_2k' : '_2k');
                else if (freq === 3000) key = 'annual_' + ear + (type === 'bone' ? '_bone_3k' : '_3k');
                else if (freq === 4000) key = 'annual_' + ear + (type === 'bone' ? '_bone_4k' : '_4k');
                else if (freq === 6000) key = 'annual_' + ear + (type === 'bone' ? '_bone_6k' : '_6k');
                else if (freq === 8000) key = 'annual_' + ear + (type === 'bone' ? '_bone_8k' : '_8k');
                
                const input = document.querySelector(`input[name="${key}"]`);
                const value = getValue(input);
                
                // Debug: Log if input not found or value is null for bone conduction
                if (type === 'bone' && !input) {
                    console.warn(`Annual bone conduction input not found: ${key}`);
                } else if (type === 'bone') {
                    if (value === null && input) {
                        console.log(`Annual bone conduction value is null for ${key}, input value: "${input.value}", input exists: ${!!input}`);
                    } else if (value !== null) {
                        console.log(`Annual bone conduction value found for ${key}: ${value}`);
                    }
                }
                
                data.push(value);
            });
            
            // Debug: Log summary for bone conduction
            if (type === 'bone') {
                const validValues = data.filter(v => v !== null && v !== undefined);
                console.log(`Annual ${ear} bone conduction: ${validValues.length} valid values out of ${data.length}`, data);
            }
            
            return data;
        }
        
        function updateBaselineChart(chart) {
            if (!chart) return;
            
            const rightAirData = getBaselineData('right', 'air');
            const rightBoneData = getBaselineData('right', 'bone');
            const leftAirData = getBaselineData('left', 'air');
            const leftBoneData = getBaselineData('left', 'bone');
            
            // Debug: Log the data being read
            console.log('Update Baseline Chart - Data:', {
                rightAir: rightAirData,
                rightBone: rightBoneData,
                leftAir: leftAirData,
                leftBone: leftBoneData
            });
            
            chart.data.datasets[0].data = rightAirData;  // Right Air
            chart.data.datasets[1].data = rightBoneData; // Right Bone
            chart.data.datasets[2].data = leftAirData;    // Left Air
            chart.data.datasets[3].data = leftBoneData;   // Left Bone
            
            // Update symbol configurations if they changed
            const baselineAirRightSymbol = symbolDefinitions[symbolConfig.baseline.air.right];
            const baselineAirLeftSymbol = symbolDefinitions[symbolConfig.baseline.air.left];
            const baselineBoneRightSymbol = symbolDefinitions[symbolConfig.baseline.bone.right];
            const baselineBoneLeftSymbol = symbolDefinitions[symbolConfig.baseline.bone.left];
            
            // Update Right Air
            chart.data.datasets[0].borderColor = baselineAirRightSymbol.color;
            chart.data.datasets[0].pointBorderColor = baselineAirRightSymbol.color;
            chart.data.datasets[0].pointBackgroundColor = baselineAirRightSymbol.fill ? baselineAirRightSymbol.color : 'transparent';
            chart.data.datasets[0].pointStyle = baselineAirRightSymbol.style === 'circle' ? 'circle' : false;
            chart.data.datasets[0].pointBorderWidth = baselineAirRightSymbol.style === 'circle' ? 2.5 : 1;
            chart.data.datasets[0].symbolKey = symbolConfig.baseline.air.right;
            
            // Update Right Bone
            chart.data.datasets[1].borderColor = baselineBoneRightSymbol.color;
            chart.data.datasets[1].pointBorderColor = baselineBoneRightSymbol.color;
            chart.data.datasets[1].pointStyle = false;
            chart.data.datasets[1].pointRotation = 0;
            chart.data.datasets[1].symbolKey = symbolConfig.baseline.bone.right;
            
            // Update Left Air
            chart.data.datasets[2].borderColor = baselineAirLeftSymbol.color;
            chart.data.datasets[2].pointBorderColor = baselineAirLeftSymbol.color;
            chart.data.datasets[2].pointBackgroundColor = baselineAirLeftSymbol.fill ? baselineAirLeftSymbol.color : 'transparent';
            chart.data.datasets[2].pointStyle = baselineAirLeftSymbol.style === 'circle' ? 'circle' : false;
            chart.data.datasets[2].symbolKey = symbolConfig.baseline.air.left;
            
            // Update Left Bone
            chart.data.datasets[3].borderColor = baselineBoneLeftSymbol.color;
            chart.data.datasets[3].pointBorderColor = baselineBoneLeftSymbol.color;
            chart.data.datasets[3].pointStyle = false;
            chart.data.datasets[3].pointRotation = 0;
            chart.data.datasets[3].symbolKey = symbolConfig.baseline.bone.left;
            
            // Ensure all datasets are visible and configured correctly
            chart.data.datasets.forEach((dataset, index) => {
                dataset.hidden = false;
                // Ensure spanGaps is true so null values don't break the line
                dataset.spanGaps = true;
                // Ensure showLine is true for bone conduction
                if (index === 1 || index === 3) { // Right Bone (index 1) or Left Bone (index 3)
                    dataset.showLine = true;
                }
            });
            
            chart.update('none');
        }
        
        function updateAnnualChart(chart) {
            if (!chart) return;
            
            const rightAirData = getAnnualData('right', 'air');
            const rightBoneData = getAnnualData('right', 'bone');
            const leftAirData = getAnnualData('left', 'air');
            const leftBoneData = getAnnualData('left', 'bone');
            
            // Debug: Log the data being read
            console.log('Update Annual Chart - Data:', {
                rightAir: rightAirData,
                rightBone: rightBoneData,
                leftAir: leftAirData,
                leftBone: leftBoneData
            });
            
            chart.data.datasets[0].data = rightAirData;  // Right Air
            chart.data.datasets[1].data = rightBoneData; // Right Bone
            chart.data.datasets[2].data = leftAirData;    // Left Air
            chart.data.datasets[3].data = leftBoneData;   // Left Bone
            
            // Update symbol configurations if they changed
            const annualAirRightSymbol = symbolDefinitions[symbolConfig.annual.air.right];
            const annualAirLeftSymbol = symbolDefinitions[symbolConfig.annual.air.left];
            const annualBoneRightSymbol = symbolDefinitions[symbolConfig.annual.bone.right];
            const annualBoneLeftSymbol = symbolDefinitions[symbolConfig.annual.bone.left];
            
            // Update Right Air
            chart.data.datasets[0].borderColor = annualAirRightSymbol.color;
            chart.data.datasets[0].pointBorderColor = annualAirRightSymbol.color;
            chart.data.datasets[0].pointBackgroundColor = annualAirRightSymbol.fill ? annualAirRightSymbol.color : 'transparent';
            chart.data.datasets[0].pointStyle = annualAirRightSymbol.style === 'circle' ? 'circle' : false;
            chart.data.datasets[0].pointBorderWidth = annualAirRightSymbol.style === 'circle' ? 2.5 : 1;
            chart.data.datasets[0].symbolKey = symbolConfig.annual.air.right;
            
            // Update Right Bone
            chart.data.datasets[1].borderColor = annualBoneRightSymbol.color;
            chart.data.datasets[1].pointBorderColor = annualBoneRightSymbol.color;
            chart.data.datasets[1].pointStyle = false;
            chart.data.datasets[1].pointRotation = 0;
            chart.data.datasets[1].symbolKey = symbolConfig.annual.bone.right;
            
            // Update Left Air
            chart.data.datasets[2].borderColor = annualAirLeftSymbol.color;
            chart.data.datasets[2].pointBorderColor = annualAirLeftSymbol.color;
            chart.data.datasets[2].pointBackgroundColor = annualAirLeftSymbol.fill ? annualAirLeftSymbol.color : 'transparent';
            chart.data.datasets[2].pointStyle = annualAirLeftSymbol.style === 'circle' ? 'circle' : false;
            chart.data.datasets[2].symbolKey = symbolConfig.annual.air.left;
            
            // Update Left Bone
            chart.data.datasets[3].borderColor = annualBoneLeftSymbol.color;
            chart.data.datasets[3].pointBorderColor = annualBoneLeftSymbol.color;
            chart.data.datasets[3].pointStyle = false;
            chart.data.datasets[3].pointRotation = 0;
            chart.data.datasets[3].symbolKey = symbolConfig.annual.bone.left;
            
            // Ensure all datasets are visible and configured correctly
            chart.data.datasets.forEach((dataset, index) => {
                dataset.hidden = false;
                // Ensure spanGaps is true so null values don't break the line
                dataset.spanGaps = true;
            });
            
            chart.update('none');
        }
        
        function createBaselineChart(canvasId) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            
            const rightAirData = getBaselineData('right', 'air');
            const rightBoneData = getBaselineData('right', 'bone');
            const leftAirData = getBaselineData('left', 'air');
            const leftBoneData = getBaselineData('left', 'bone');
            
            // Get baseline date for chart title
            let baselineTitle = 'Baseline Audiogram';
            const baselineDateInput = document.querySelector('input[name="baseline_date"]');
            if (baselineDateInput && baselineDateInput.value && baselineDateInput.value !== '') {
                try {
                    const date = new Date(baselineDateInput.value);
                    const formattedDate = date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
                    baselineTitle = 'Baseline Audiogram (' + formattedDate + ')';
                } catch (e) {
                    // Keep default title if date parsing fails
                }
            }
            
            // Get symbol configurations for baseline
            const baselineAirRightSymbol = symbolDefinitions[symbolConfig.baseline.air.right];
            const baselineAirLeftSymbol = symbolDefinitions[symbolConfig.baseline.air.left];
            const baselineBoneRightSymbol = symbolDefinitions[symbolConfig.baseline.bone.right];
            const baselineBoneLeftSymbol = symbolDefinitions[symbolConfig.baseline.bone.left];
            
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: frequencyLabels,
                    datasets: [
                        {
                            label: 'Right Ear - Air Conduction',
                            data: rightAirData,
                            borderColor: baselineAirRightSymbol.color,
                            backgroundColor: baselineAirRightSymbol.color + '1A',
                            pointBackgroundColor: baselineAirRightSymbol.fill ? baselineAirRightSymbol.color : 'transparent',
                            pointBorderColor: baselineAirRightSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointHitRadius: 12,
                            pointBorderWidth: baselineAirRightSymbol.style === 'circle' ? 2.5 : 1,
                            pointStyle: baselineAirRightSymbol.style === 'circle' ? 'circle' : false,
                            pointRotation: baselineAirRightSymbol.rotation || 0,
                            symbolKey: symbolConfig.baseline.air.right,
                            tension: 0.3,
                            fill: false,
                            spanGaps: true
                        },
                        {
                            label: 'Right Ear - Bone Conduction',
                            data: rightBoneData,
                            borderColor: baselineBoneRightSymbol.color,
                            backgroundColor: baselineBoneRightSymbol.color + '1A',
                            pointBackgroundColor: 'transparent',
                            pointBorderColor: baselineBoneRightSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointHitRadius: 12,
                            pointStyle: false,
                            pointRotation: 0,
                            symbolKey: symbolConfig.baseline.bone.right,
                            borderDash: [5, 5],
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                            hidden: false,
                            showLine: true
                        },
                        {
                            label: 'Left Ear - Air Conduction',
                            data: leftAirData,
                            borderColor: baselineAirLeftSymbol.color,
                            backgroundColor: baselineAirLeftSymbol.color + '1A',
                            pointBackgroundColor: baselineAirLeftSymbol.fill ? baselineAirLeftSymbol.color : 'transparent',
                            pointBorderColor: baselineAirLeftSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointHitRadius: 12,
                            pointStyle: baselineAirLeftSymbol.style === 'circle' ? 'circle' : false,
                            pointRotation: baselineAirLeftSymbol.rotation || 0,
                            symbolKey: symbolConfig.baseline.air.left,
                            tension: 0.3,
                            fill: false,
                            spanGaps: true
                        },
                        {
                            label: 'Left Ear - Bone Conduction',
                            data: leftBoneData,
                            borderColor: baselineBoneLeftSymbol.color,
                            backgroundColor: baselineBoneLeftSymbol.color + '1A',
                            pointBackgroundColor: 'transparent',
                            pointBorderColor: baselineBoneLeftSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointHitRadius: 12,
                            pointStyle: false,
                            pointRotation: 0,
                            symbolKey: symbolConfig.baseline.bone.left,
                            borderDash: [5, 5],
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                            hidden: false,
                            showLine: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: true,
                        mode: 'point'
                    },
                    onClick: function(event, elements, chart) {
                        console.log('Baseline chart clicked, elements:', elements);
                        if (elements && elements.length > 0) {
                            const element = elements[0];
                            const datasetIndex = element.datasetIndex;
                            const index = element.index;
                            console.log('Clicking baseline point:', { datasetIndex, index });
                            cycleSymbol('baseline', datasetIndex, index, event);
                        } else {
                            // If no element clicked, try to find nearest point
                            const chartInstance = chart || this;
                            const canvasPosition = Chart.helpers.getRelativePosition(event, chartInstance);
                            const dataX = chartInstance.scales.x.getValueForPixel(canvasPosition.x);
                            const dataY = chartInstance.scales.y.getValueForPixel(canvasPosition.y);
                            
                            console.log('No direct element clicked, finding nearest point at:', { dataX, dataY });
                            
                            // Find the closest point
                            let closestDistance = Infinity;
                            let closestElement = null;
                            
                            chartInstance.data.datasets.forEach((dataset, datasetIdx) => {
                                dataset.data.forEach((value, dataIdx) => {
                                    if (value !== null && value !== undefined) {
                                        const x = dataIdx;
                                        const y = value;
                                        const distance = Math.sqrt(Math.pow(x - dataX, 2) + Math.pow(y - dataY, 2));
                                        if (distance < closestDistance) {
                                            closestDistance = distance;
                                            closestElement = { datasetIndex: datasetIdx, index: dataIdx };
                                        }
                                    }
                                });
                            });
                            
                            console.log('Closest element:', closestElement, 'distance:', closestDistance);
                            if (closestElement && closestDistance < 2) {
                                cycleSymbol('baseline', closestElement.datasetIndex, closestElement.index, event);
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: baselineTitle,
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y !== null ? context.parsed.y + ' dB HL' : 'No data';
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Frequency (Hz)',
                                font: { size: 12, weight: 'bold' }
                            },
                            grid: { color: '#e9ecef' }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Hearing Level (dB HL)',
                                font: { size: 12, weight: 'bold' }
                            },
                            reverse: true,
                            min: -10,
                            max: 120,
                            ticks: { stepSize: 10 },
                            grid: { color: '#e9ecef' }
                        }
                    },
                    elements: {
                        line: {
                            spanGaps: true
                        },
                        point: {
                            radius: function(context) {
                                // Don't show points if value is null or undefined
                                return (context.parsed.y !== null && context.parsed.y !== undefined) ? (context.datasetIndex === 0 || context.datasetIndex === 2 ? 6 : 5) : 0;
                            }
                        }
                    }
                }
            });
        }
        
        function createAnnualChart(canvasId) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            
            const rightAirData = getAnnualData('right', 'air');
            const rightBoneData = getAnnualData('right', 'bone');
            const leftAirData = getAnnualData('left', 'air');
            const leftBoneData = getAnnualData('left', 'bone');
            
            // Debug: Log data when creating chart
            console.log('Creating Annual Chart with data:', {
                rightAir: rightAirData,
                rightBone: rightBoneData,
                leftAir: leftAirData,
                leftBone: leftBoneData
            });
            
            // Get symbol configurations for annual
            const annualAirRightSymbol = symbolDefinitions[symbolConfig.annual.air.right];
            const annualAirLeftSymbol = symbolDefinitions[symbolConfig.annual.air.left];
            const annualBoneRightSymbol = symbolDefinitions[symbolConfig.annual.bone.right];
            const annualBoneLeftSymbol = symbolDefinitions[symbolConfig.annual.bone.left];
            
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: frequencyLabels,
                    datasets: [
                        {
                            label: 'Right Ear - Air Conduction',
                            data: rightAirData,
                            borderColor: annualAirRightSymbol.color,
                            backgroundColor: annualAirRightSymbol.color + '1A',
                            pointBackgroundColor: annualAirRightSymbol.fill ? annualAirRightSymbol.color : 'transparent',
                            pointBorderColor: annualAirRightSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointHitRadius: 12,
                            pointBorderWidth: annualAirRightSymbol.style === 'circle' ? 2.5 : 1,
                            pointStyle: annualAirRightSymbol.style === 'circle' ? 'circle' : false,
                            pointRotation: annualAirRightSymbol.rotation || 0,
                            symbolKey: symbolConfig.annual.air.right,
                            tension: 0.3,
                            fill: false,
                            spanGaps: true
                        },
                        {
                            label: 'Right Ear - Bone Conduction',
                            data: rightBoneData,
                            borderColor: annualBoneRightSymbol.color,
                            backgroundColor: annualBoneRightSymbol.color + '1A',
                            pointBackgroundColor: 'transparent',
                            pointBorderColor: annualBoneRightSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointHitRadius: 12,
                            pointStyle: false,
                            pointRotation: annualBoneRightSymbol.rotation !== undefined ? annualBoneRightSymbol.rotation : 180,
                            symbolKey: symbolConfig.annual.bone.right,
                            borderDash: [5, 5],
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                            hidden: false,
                            showLine: true
                        },
                        {
                            label: 'Left Ear - Air Conduction',
                            data: leftAirData,
                            borderColor: annualAirLeftSymbol.color,
                            backgroundColor: annualAirLeftSymbol.color + '1A',
                            pointBackgroundColor: annualAirLeftSymbol.fill ? annualAirLeftSymbol.color : 'transparent',
                            pointBorderColor: annualAirLeftSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 10,
                            pointHitRadius: 12,
                            pointStyle: annualAirLeftSymbol.style === 'circle' ? 'circle' : false,
                            pointRotation: annualAirLeftSymbol.rotation || 0,
                            symbolKey: symbolConfig.annual.air.left,
                            tension: 0.3,
                            fill: false,
                            spanGaps: true
                        },
                        {
                            label: 'Left Ear - Bone Conduction',
                            data: leftBoneData,
                            borderColor: annualBoneLeftSymbol.color,
                            backgroundColor: annualBoneLeftSymbol.color + '1A',
                            pointBackgroundColor: 'transparent',
                            pointBorderColor: annualBoneLeftSymbol.color,
                            pointRadius: 6,
                            pointHoverRadius: 9,
                            pointHitRadius: 12,
                            pointStyle: false,
                            pointRotation: annualBoneLeftSymbol.rotation !== undefined ? annualBoneLeftSymbol.rotation : 0,
                            symbolKey: symbolConfig.annual.bone.left,
                            borderDash: [5, 5],
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                            hidden: false,
                            showLine: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: true,
                        mode: 'point'
                    },
                    onClick: function(event, elements, chart) {
                        console.log('Annual chart clicked, elements:', elements);
                        if (elements && elements.length > 0) {
                            const element = elements[0];
                            const datasetIndex = element.datasetIndex;
                            const index = element.index;
                            console.log('Clicking annual point:', { datasetIndex, index });
                            cycleSymbol('annual', datasetIndex, index, event);
                        } else {
                            // If no element clicked, try to find nearest point
                            const chartInstance = chart || this;
                            const canvasPosition = Chart.helpers.getRelativePosition(event, chartInstance);
                            const dataX = chartInstance.scales.x.getValueForPixel(canvasPosition.x);
                            const dataY = chartInstance.scales.y.getValueForPixel(canvasPosition.y);
                            
                            console.log('No direct element clicked, finding nearest point at:', { dataX, dataY });
                            
                            // Find the closest point
                            let closestDistance = Infinity;
                            let closestElement = null;
                            
                            chartInstance.data.datasets.forEach((dataset, datasetIdx) => {
                                dataset.data.forEach((value, dataIdx) => {
                                    if (value !== null && value !== undefined) {
                                        const x = dataIdx;
                                        const y = value;
                                        const distance = Math.sqrt(Math.pow(x - dataX, 2) + Math.pow(y - dataY, 2));
                                        if (distance < closestDistance) {
                                            closestDistance = distance;
                                            closestElement = { datasetIndex: datasetIdx, index: dataIdx };
                                        }
                                    }
                                });
                            });
                            
                            console.log('Closest element:', closestElement, 'distance:', closestDistance);
                            if (closestElement && closestDistance < 2) {
                                cycleSymbol('annual', closestElement.datasetIndex, closestElement.index, event);
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Annual Audiogram',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y !== null ? context.parsed.y + ' dB HL' : 'No data';
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Frequency (Hz)',
                                font: { size: 12, weight: 'bold' }
                            },
                            grid: { color: '#e9ecef' }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Hearing Level (dB HL)',
                                font: { size: 12, weight: 'bold' }
                            },
                            reverse: true,
                            min: -10,
                            max: 120,
                            ticks: { stepSize: 10 },
                            grid: { color: '#e9ecef' }
                        }
                    },
                    elements: {
                        line: {
                            spanGaps: true
                        },
                        point: {
                            radius: function(context) {
                                // Don't show points if value is null or undefined
                                return (context.parsed.y !== null && context.parsed.y !== undefined) ? (context.datasetIndex === 0 || context.datasetIndex === 2 ? 6 : 5) : 0;
                            }
                        }
                    }
                }
            });
        }
        
        // Function to get field name from chart point click
        function getFieldNameFromChartPoint(chartType, datasetIndex, frequencyIndex) {
            const frequencyMap = {
                0: '250',
                1: '500',
                2: '1k',
                3: '2k',
                4: '3k',
                5: '4k',
                6: '6k',
                7: '8k'
            };
            
            const freq = frequencyMap[frequencyIndex];
            if (!freq) return null;
            
            // Dataset mapping: 0=Right Air, 1=Right Bone, 2=Left Air, 3=Left Bone
            let fieldName = '';
            const prefix = chartType === 'annual' ? 'annual_' : '';
            
            if (datasetIndex === 0) {
                // Right Ear - Air Conduction
                fieldName = prefix + 'right_' + freq;
            } else if (datasetIndex === 1) {
                // Right Ear - Bone Conduction
                fieldName = prefix + 'right_bone_' + freq;
            } else if (datasetIndex === 2) {
                // Left Ear - Air Conduction
                fieldName = prefix + 'left_' + freq;
            } else if (datasetIndex === 3) {
                // Left Ear - Bone Conduction
                fieldName = prefix + 'left_bone_' + freq;
            }
            
            return fieldName;
        }
        
        // Function to cycle through symbols automatically
        function cycleSymbol(chartType, datasetIndex, frequencyIndex, event) {
            // Determine ear and type from dataset index
            const ear = datasetIndex === 0 || datasetIndex === 1 ? 'right' : 'left';
            const type = datasetIndex === 0 || datasetIndex === 2 ? 'air' : 'bone';
            
            // Get available symbols for this type
            let availableSymbols = [];
            if (type === 'air') {
                if (ear === 'right') {
                    availableSymbols = ['circle_baseline', 'circle_unmasked', 'circle_masked'];
                } else {
                    availableSymbols = ['cross_baseline', 'cross_unmasked', 'cross_masked'];
                }
            } else {
                if (ear === 'right') {
                    availableSymbols = ['triangle_unmasked_right', 'bracket_masked_right'];
                } else {
                    availableSymbols = ['triangle_unmasked_left', 'bracket_masked_left'];
                }
            }
            
            // Get current symbol index
            const currentSymbolKey = symbolConfig[chartType][type][ear];
            const currentIndex = availableSymbols.indexOf(currentSymbolKey);
            const nextIndex = (currentIndex + 1) % availableSymbols.length;
            const nextSymbolKey = availableSymbols[nextIndex];
            
            // Update symbol configuration
            symbolConfig[chartType][type][ear] = nextSymbolKey;
            
            // Update dropdown if it exists
            const dropdownId = `${chartType}_${type}_${ear}_symbol`;
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.value = nextSymbolKey;
            }
            
            // Update chart
            if (chartType === 'baseline' && baselineChart) {
                updateBaselineChart(baselineChart);
            } else if (chartType === 'annual' && annualChart) {
                updateAnnualChart(annualChart);
            }
        }
        
        // Function to handle chart point click - now with symbol/value selection
        function handleChartPointClick(chartType, datasetIndex, frequencyIndex, event) {
            console.log('Chart point clicked:', { chartType, datasetIndex, frequencyIndex });
            
            // Check if user wants to change symbol (right-click or Ctrl+click) or value (regular click)
            const isSymbolChange = event.ctrlKey || event.metaKey || event.button === 2 || event.which === 3;
            
            if (isSymbolChange) {
                // Show symbol selector
                event.preventDefault();
                showSymbolSelector(chartType, datasetIndex, frequencyIndex, event);
                return;
            }
            
            // Otherwise, edit the value (existing functionality)
            const fieldName = getFieldNameFromChartPoint(chartType, datasetIndex, frequencyIndex);
            if (!fieldName) {
                console.warn('Could not determine field name from chart point');
                return;
            }
            
            console.log('Field name:', fieldName);
            
            const input = document.querySelector(`input[name="${fieldName}"]`);
            if (!input) {
                console.warn(`Input field not found: ${fieldName}`);
                alert(`Input field not found: ${fieldName}`);
                return;
            }
            
            // Check if input is readonly (baseline fields are readonly if baseline exists)
            if (input.readOnly && chartType === 'baseline') {
                alert('Baseline values cannot be edited. Please edit the annual test values instead.');
                return;
            }
            
            // Get current value
            const currentValue = input.value || '';
            
            // Build a user-friendly label from data attributes
            const ear = input.getAttribute('data-ear') || '';
            const type = input.getAttribute('data-type') || '';
            const freq = input.getAttribute('data-freq') || '';
            const earLabel = ear === 'right' ? 'Right Ear' : ear === 'left' ? 'Left Ear' : '';
            const typeLabel = type === 'air' ? 'Air Conduction' : type === 'bone' ? 'Bone Conduction' : '';
            const freqLabel = freq ? `${freq} Hz` : '';
            const friendlyLabel = [earLabel, typeLabel, freqLabel].filter(Boolean).join(' - ') || fieldName;
            
            // Prompt user for new value
            const newValue = prompt(
                `Enter new value (dB HL):`,
                currentValue
            );
            
            // If user cancelled, return
            if (newValue === null) return;
            
            // Validate and set new value
            const numValue = parseFloat(newValue);
            if (!isNaN(numValue)) {
                // Validate range (-10 to 120 dB HL)
                if (numValue < -10 || numValue > 120) {
                    alert('Value must be between -10 and 120 dB HL');
                    return;
                }
                
                input.value = numValue;
                
                // Trigger input event to update chart
                input.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Update the appropriate chart
                if (chartType === 'baseline' && baselineChart) {
                    updateBaselineChart(baselineChart);
                } else if (chartType === 'annual' && annualChart) {
                    updateAnnualChart(annualChart);
                }
            } else if (newValue.trim() === '') {
                // Allow clearing the value
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                
                if (chartType === 'baseline' && baselineChart) {
                    updateBaselineChart(baselineChart);
                } else if (chartType === 'annual' && annualChart) {
                    updateAnnualChart(annualChart);
                }
            } else {
                alert('Please enter a valid number');
            }
        }
        
        // Function to clear form for new entry
        function clearFormForNewEntry() {
            // Clear examination details (except patient info which is readonly/display)
            const form = document.getElementById('audiometricForm');
            if (!form) return;
            
            // Clear examination date (set to today)
            const examDateInput = form.querySelector('input[name="examination_date"]');
            if (examDateInput) {
                const today = new Date().toISOString().split('T')[0];
                examDateInput.value = today;
            }
            
            // Clear audiometer, calibration date, JKKP approval
            const audiometerInput = form.querySelector('input[name="audiometer"]');
            if (audiometerInput) audiometerInput.value = '';
            
            const calibrationInput = form.querySelector('input[name="calibration_date"]');
            if (calibrationInput) calibrationInput.value = '';
            
            const jkkpInput = form.querySelector('input[name="jkkp_approval_no"]');
            if (jkkpInput) jkkpInput.value = '';
            
            // Clear SEG value
            const segInput = form.querySelector('input[name="seg_value"]');
            if (segInput) segInput.value = '';
            
            // Reset otoscopy to default
            const otoscopySelect = form.querySelector('select[name="otoscopy"]');
            if (otoscopySelect) otoscopySelect.value = '';
            
            // Reset Rinne tests
            const rinneRight = form.querySelector('select[name="rinne_right"]');
            if (rinneRight) rinneRight.value = '';
            
            const rinneLeft = form.querySelector('select[name="rinne_left"]');
            if (rinneLeft) rinneLeft.value = '';
            
            // Reset Weber tests
            const weberCenter = form.querySelector('select[name="weber_center"]');
            if (weberCenter) weberCenter.value = '';
            
            const weberRight = form.querySelector('select[name="weber_right"]');
            if (weberRight) weberRight.value = '';
            
            const weberLeft = form.querySelector('select[name="weber_left"]');
            if (weberLeft) weberLeft.value = '';
            
            // Clear annual date (set to today)
            const annualDateInput = form.querySelector('input[name="annual_date"]');
            if (annualDateInput) {
                const today = new Date().toISOString().split('T')[0];
                annualDateInput.value = today;
            }
            
            // Clear all annual audiogram inputs (but NOT baseline if readonly)
            document.querySelectorAll('.annual-input').forEach(input => {
                if (!input.readOnly) {
                    input.value = '';
                }
            });
            
            // Clear baseline date only if it's not readonly (new patient, no baseline exists)
            const baselineDateInput = form.querySelector('input[name="baseline_date"]');
            if (baselineDateInput && !baselineDateInput.readOnly) {
                const today = new Date().toISOString().split('T')[0];
                baselineDateInput.value = today;
            }
            
            // Clear baseline inputs only if they're not readonly (new patient)
            document.querySelectorAll('.baseline-input').forEach(input => {
                if (!input.readOnly) {
                    input.value = '';
                }
            });
            
            // Update charts after clearing
            setTimeout(function() {
                if (baselineChart) {
                    updateBaselineChart(baselineChart);
                }
                if (annualChart) {
                    updateAnnualChart(annualChart);
                }
            }, 100);
        }
        
        // Check if we're in an iframe
        const isInIframe = window.self !== window.top;
        if (isInIframe) {
            console.log('Page loaded in iframe - will use extended delays');
        }
        
        // Global function to force chart updates (can be called from parent window)
        window.forceUpdateCharts = function() {
            console.log('forceUpdateCharts called externally');
            if (typeof forceUpdateBaselineChart === 'function') {
                forceUpdateBaselineChart();
            }
            if (typeof forceUpdateAnnualChart === 'function') {
                forceUpdateAnnualChart();
            }
        };
        
        // Initialize charts
        function initializeCharts() {
            // Create charts first
            baselineChart = createBaselineChart('baselineChart');
            annualChart = createAnnualChart('annualChart');
            
            // Check if this is a new entry and clear form if needed
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('new') === '1') {
                // Clear the form for new entry after a short delay to ensure form is fully loaded
                setTimeout(function() {
                    clearFormForNewEntry();
                }, 300);
            }
        }
        
        // Initialize on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            
            // Function to update baseline chart (handles readonly fields)
            function forceUpdateBaselineChart() {
                if (baselineChart) {
                    // Read baseline data from form fields (including readonly ones)
                    const rightAirData = getBaselineData('right', 'air');
                    const rightBoneData = getBaselineData('right', 'bone');
                    const leftAirData = getBaselineData('left', 'air');
                    const leftBoneData = getBaselineData('left', 'bone');
                    
                    // Debug: Log the data being read
                    console.log('Force Update Baseline Chart - Data:', {
                        rightAir: rightAirData,
                        rightBone: rightBoneData,
                        leftAir: leftAirData,
                        leftBone: leftBoneData
                    });
                    
                    // Check if bone conduction inputs exist
                    const rightBoneInputs = document.querySelectorAll('input[name^="right_bone"]');
                    const leftBoneInputs = document.querySelectorAll('input[name^="left_bone"]');
                    console.log(`Found ${rightBoneInputs.length} baseline right bone inputs, ${leftBoneInputs.length} baseline left bone inputs`);
                    rightBoneInputs.forEach(input => {
                        console.log(`Baseline right bone input ${input.name}: value="${input.value}", readonly=${input.readOnly}`);
                    });
                    leftBoneInputs.forEach(input => {
                        console.log(`Baseline left bone input ${input.name}: value="${input.value}", readonly=${input.readOnly}`);
                    });
                    
                    // Update chart data
                    baselineChart.data.datasets[0].data = rightAirData;  // Right Air
                    baselineChart.data.datasets[1].data = rightBoneData; // Right Bone
                    baselineChart.data.datasets[2].data = leftAirData;    // Left Air
                    baselineChart.data.datasets[3].data = leftBoneData;   // Left Bone
                    
                    // Check if bone conduction data has any valid values
                    const rightBoneHasData = rightBoneData.some(v => v !== null && v !== undefined);
                    const leftBoneHasData = leftBoneData.some(v => v !== null && v !== undefined);
                    
                    console.log(`Baseline - Right bone has data: ${rightBoneHasData}, Left bone has data: ${leftBoneHasData}`);
                    
                    // Ensure all datasets are visible and configured correctly
                    baselineChart.data.datasets.forEach((dataset, index) => {
                        dataset.hidden = false;
                        dataset.spanGaps = true;
                        // For bone conduction datasets (index 1 and 3), ensure showLine is true
                        if (index === 1 || index === 3) {
                            dataset.showLine = true;
                            // If no data, at least ensure the dataset is visible in legend
                            if (index === 1 && !rightBoneHasData) {
                                console.warn('Baseline right bone conduction has no data - line may not display');
                            }
                            if (index === 3 && !leftBoneHasData) {
                                console.warn('Baseline left bone conduction has no data - line may not display');
                            }
                        }
                    });
                    
                    // Force chart update with animation disabled
                    baselineChart.update('none');
                    
                    // Log chart state after update
                    console.log('Baseline chart datasets after update:', baselineChart.data.datasets.map((d, i) => ({
                        index: i,
                        label: d.label,
                        hidden: d.hidden,
                        showLine: d.showLine,
                        dataLength: d.data.length,
                        hasData: d.data.some(v => v !== null && v !== undefined)
                    })));
                } else {
                    console.warn('Baseline chart is null, cannot update');
                }
            }
            
            // Function to update annual chart with existing data
            function forceUpdateAnnualChart() {
                if (annualChart) {
                    // Read annual data from form fields
                    const rightAirData = getAnnualData('right', 'air');
                    const rightBoneData = getAnnualData('right', 'bone');
                    const leftAirData = getAnnualData('left', 'air');
                    const leftBoneData = getAnnualData('left', 'bone');
                    
                    // Debug: Log the data being read
                    console.log('Force Update Annual Chart - Data:', {
                        rightAir: rightAirData,
                        rightBone: rightBoneData,
                        leftAir: leftAirData,
                        leftBone: leftBoneData
                    });
                    
                    // Check if bone conduction inputs exist and log their values
                    const rightBoneInputs = document.querySelectorAll('input[name^="annual_right_bone"]');
                    const leftBoneInputs = document.querySelectorAll('input[name^="annual_left_bone"]');
                    console.log(`Found ${rightBoneInputs.length} annual right bone inputs, ${leftBoneInputs.length} annual left bone inputs`);
                    
                    if (rightBoneInputs.length === 0) {
                        console.error('ERROR: No annual right bone conduction inputs found!');
                    } else {
                        rightBoneInputs.forEach(input => {
                            console.log(`Annual right bone input ${input.name}: value="${input.value}", exists=${!!input}`);
                        });
                    }
                    
                    if (leftBoneInputs.length === 0) {
                        console.error('ERROR: No annual left bone conduction inputs found!');
                    } else {
                        leftBoneInputs.forEach(input => {
                            console.log(`Annual left bone input ${input.name}: value="${input.value}", exists=${!!input}`);
                        });
                    }
                    
                    // Check if bone conduction data has any valid values
                    const rightBoneHasData = rightBoneData.some(v => v !== null && v !== undefined);
                    const leftBoneHasData = leftBoneData.some(v => v !== null && v !== undefined);
                    
                    console.log(`Right bone has data: ${rightBoneHasData}, Left bone has data: ${leftBoneHasData}`);
                    
                    // Update chart data
                    annualChart.data.datasets[0].data = rightAirData;  // Right Air
                    annualChart.data.datasets[1].data = rightBoneData; // Right Bone
                    annualChart.data.datasets[2].data = leftAirData;    // Left Air
                    annualChart.data.datasets[3].data = leftBoneData;   // Left Bone
                    
                    // Ensure all datasets are visible and configured correctly
                    annualChart.data.datasets.forEach((dataset, index) => {
                        dataset.hidden = false;
                        dataset.spanGaps = true;
                        // For bone conduction datasets (index 1 and 3), ensure showLine is true
                        if (index === 1 || index === 3) {
                            dataset.showLine = true;
                            // If no data, at least ensure the dataset is visible in legend
                            if (index === 1 && !rightBoneHasData) {
                                console.warn('Right bone conduction has no data - line may not display');
                            }
                            if (index === 3 && !leftBoneHasData) {
                                console.warn('Left bone conduction has no data - line may not display');
                            }
                        }
                    });
                    
                    // Force chart update with animation disabled
                    annualChart.update('none');
                    
                    // Log chart state after update
                    console.log('Annual chart datasets after update:', annualChart.data.datasets.map((d, i) => ({
                        index: i,
                        label: d.label,
                        hidden: d.hidden,
                        showLine: d.showLine,
                        dataLength: d.data.length,
                        hasData: d.data.some(v => v !== null && v !== undefined)
                    })));
                }
            }
            
            // Immediately update baseline chart with existing data (even from readonly fields)
            // This ensures baseline data displays in the graph when opening a new form
            forceUpdateBaselineChart();
            
            // Immediately update annual chart with existing data
            // This ensures annual data displays in the graph when opening a form with existing annual test
            forceUpdateAnnualChart();
            
            // Add event listeners for real-time updates (skip readonly inputs)
            document.querySelectorAll('.baseline-input').forEach(input => {
                if (!input.readOnly) {
                    input.addEventListener('input', function() {
                        if (baselineChart) {
                            updateBaselineChart(baselineChart);
                        }
                    });
                }
            });
            
            document.querySelectorAll('.annual-input').forEach(input => {
                input.addEventListener('input', function() {
                    if (annualChart) {
                        updateAnnualChart(annualChart);
                    }
                });
            });
            
            // Additional update after a short delay to ensure all form fields are fully loaded
            // This is especially important for readonly fields that may load asynchronously
            // Use longer delays if in iframe
            const delay1 = isInIframe ? 500 : 200;
            const delay2 = isInIframe ? 1000 : 500;
            const delay3 = isInIframe ? 1500 : 100;
            
            setTimeout(function() {
                console.log('First chart update (delay: ' + delay1 + 'ms)');
                forceUpdateBaselineChart();
                forceUpdateAnnualChart();
            }, delay1);
            
            // Another update after a longer delay to catch any late-loading data
            setTimeout(function() {
                console.log('Second chart update (delay: ' + delay2 + 'ms)');
                forceUpdateBaselineChart();
                forceUpdateAnnualChart();
            }, delay2);
            
            // Also update when window finishes loading
            window.addEventListener('load', function() {
                setTimeout(function() {
                    console.log('Window load chart update (delay: ' + delay3 + 'ms)');
                    forceUpdateBaselineChart();
                    forceUpdateAnnualChart();
                }, delay3);
            });
            
            // If in iframe, also listen for iframe load event and add extra delays
            if (isInIframe) {
                console.log('Page loaded in iframe - using extended delays for chart updates');
                
                // Wait for iframe to be fully ready
                setTimeout(function() {
                    console.log('Iframe-specific chart update (2000ms delay)');
                    forceUpdateBaselineChart();
                    forceUpdateAnnualChart();
                }, 2000);
                
                setTimeout(function() {
                    console.log('Iframe-specific chart update (3000ms delay)');
                    forceUpdateBaselineChart();
                    forceUpdateAnnualChart();
                }, 3000);
            }
            
            // Use MutationObserver to watch for value changes in form fields
            // This ensures charts update when data is loaded dynamically
            const form = document.getElementById('audiometricForm');
            if (form) {
                const observer = new MutationObserver(function(mutations) {
                    let shouldUpdate = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                            shouldUpdate = true;
                        }
                        if (mutation.type === 'childList') {
                            shouldUpdate = true;
                        }
                    });
                    if (shouldUpdate) {
                        setTimeout(function() {
                            console.log('MutationObserver triggered chart update');
                            forceUpdateBaselineChart();
                            forceUpdateAnnualChart();
                        }, 100);
                    }
                });
                
                // Observe all input fields for value changes
                const allInputs = form.querySelectorAll('input[type="number"]');
                allInputs.forEach(function(input) {
                    observer.observe(input, {
                        attributes: true,
                        attributeFilter: ['value'],
                        childList: false,
                        subtree: false
                    });
                });
            }
        });
    </script>
</body>
</html>
