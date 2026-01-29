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

// Get pre-filled patient data from URL
$prefilled_patient_name = $_GET['patient_name'] ?? '';
$prefilled_employer = $_GET['employer'] ?? '';
$patient_id = $_GET['patient_id'] ?? '';
$surveillance_id = isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0;
$is_new_entry = isset($_GET['new']) && $_GET['new'] == '1';

// Fetch comprehensive patient information (same as employee information)
$patient_data = null;
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
            error_log("Error fetching patient data: " . $error_msg);
            $patient_data = null;
        }
    } catch (Exception $e) {
        error_log("Error fetching patient data: " . $e->getMessage());
        $patient_data = null;
    }
}

// Helper functions for audiometric calculations
function calculateAverage($values) {
    $values = array_filter($values, function($v) { return $v !== null && $v !== ''; });
    return !empty($values) ? round(array_sum($values) / count($values), 2) : null;
}

// Helper function to get value from annual fields if available, otherwise from baseline fields
function getAnnualValue($test, $field) {
    // Check if annual field exists and has value (for annual tests)
    $annualField = 'annual_' . $field;
    if (isset($test[$annualField]) && $test[$annualField] !== null && $test[$annualField] !== '') {
        return $test[$annualField];
    }
    // Fall back to baseline field (for baseline test or backwards compatibility)
    return $test[$field] ?? null;
}

// Calculate average from 2K, 3K, 4K (for STS calculation)
// Formula: (2K + 3K + 4K) / 3
// For baseline: uses baseline fields
// For annual: uses annual fields if available
function calculateAverage2K3K4K($test, $ear = 'right', $use_baseline_fields = true) {
    if (!$test) return null;
    
    $prefix = $ear . '_';
    $frequencies = ['2k', '3k', '4k'];
    $values = [];
    
    foreach ($frequencies as $freq) {
        if ($use_baseline_fields) {
            // For baseline: always use baseline fields
            $val = $test[$prefix . $freq] ?? null;
        } else {
            // For annual: use annual fields if available
            $val = getAnnualValue($test, $prefix . $freq);
        }
        
        if ($val !== null && $val !== '') {
            $values[] = $val;
        }
    }
    
    if (empty($values)) return null;
    
    // Divide by 3 (2K + 3K + 4K = 3 frequencies)
    return round(array_sum($values) / count($values), 2);
}

// Calculate average of 0.5K, 1K, 2K, 3K (baseline PTA or annual PTA)
// Formula: (0.5K + 1K + 2K + 3K) / 4
// For baseline: uses baseline fields
// For annual: uses annual fields if available
function calculateAverage05_1K_2K_3K($test, $ear = 'right', $use_baseline_fields = true) {
    if (!$test) return null;
    
    $prefix = $ear . '_';
    $frequencies = ['500', '1k', '2k', '3k'];
    $values = [];
    
    foreach ($frequencies as $freq) {
        if ($use_baseline_fields) {
            // For baseline: always use baseline fields
            $val = $test[$prefix . $freq] ?? null;
        } else {
            // For annual: use annual fields if available
            $val = getAnnualValue($test, $prefix . $freq);
        }
        
        if ($val !== null && $val !== '') {
            $values[] = $val;
        }
    }
    
    if (empty($values)) return null;
    
    // Divide by 4 (0.5K + 1K + 2K + 3K = 4 frequencies)
    return round(array_sum($values) / count($values), 2);
}

// Calculate STS (Standard Threshold Shift) using 2K, 3K, 4K average
// Formula: Average(2K, 3K, 4K) for baseline and annual, then difference
function calculateSTS($baseline_test, $current_test, $ear = 'right') {
    if (!$baseline_test || !$current_test) return null;
    
    // Calculate average of 2K, 3K, 4K for baseline (baseline fields)
    $baseline_avg_2k_3k_4k = calculateAverage2K3K4K($baseline_test, $ear, true);
    
    // Calculate average of 2K, 3K, 4K for annual (annual fields)
    $annual_avg_2k_3k_4k = calculateAverage2K3K4K($current_test, $ear, false);
    
    if ($baseline_avg_2k_3k_4k === null || $annual_avg_2k_3k_4k === null) return null;
    
    // STS = annual average - baseline average
    $sts_value = $annual_avg_2k_3k_4k - $baseline_avg_2k_3k_4k;
    
    // If result >= 10, STS = YES
    $has_sts = ($sts_value >= 10);
    
    return [
        'value' => round($sts_value, 2),
        'has_sts' => $has_sts,
        'baseline_avg' => $baseline_avg_2k_3k_4k,
        'annual_avg' => $annual_avg_2k_3k_4k
    ];
}

// Fetch existing audiometric tests for this patient
$audiometric_tests = [];
$latest_test = null;
$baseline_test = null;
$calculated_values = [
    'right' => [
        'pta_baseline' => null,      // PTA baseline: Average(0.5K, 1K, 2K, 3K)
        'pta_annual' => null,        // PTA annual: Average(0.5K, 1K, 2K, 3K)
        'sts' => null,               // STS: Difference of Average(2K, 3K, 4K) between annual and baseline
        'has_sts' => false,          // Whether STS >= 10
        'sts_calculable' => false    // Whether STS can be calculated (has baseline)
    ],
    'left' => [
        'pta_baseline' => null,
        'pta_annual' => null,
        'sts' => null,
        'has_sts' => false,
        'sts_calculable' => false
    ]
];

// Force clear all data if this is a new entry
if ($is_new_entry) {
    $audiometric_tests = [];
    $latest_test = null;
    $baseline_test = null;
    $calculated_values = [
        'right' => [
            'pta_baseline' => null,
            'pta_annual' => null,
            'sts' => null,
            'has_sts' => false,
            'sts_calculable' => false
        ],
        'left' => [
            'pta_baseline' => null,
            'pta_annual' => null,
            'sts' => null,
            'has_sts' => false,
            'sts_calculable' => false
        ]
    ];
}

// Only fetch data if NOT a new entry
if (!empty($patient_id) && !$is_new_entry) {
    try {
        // First, get the baseline test (oldest test with baseline_date set, or oldest test overall)
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM audiometric_tests 
            WHERE patient_id = ? 
            AND baseline_date IS NOT NULL
            ORDER BY baseline_date ASC, examination_date ASC, created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $baseline_test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no explicit baseline found, use the oldest test as baseline
        if (!$baseline_test) {
            $stmt = $clinic_pdo->prepare("
                SELECT * FROM audiometric_tests 
                WHERE patient_id = ? 
                ORDER BY examination_date ASC, created_at ASC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $baseline_test = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get the 4 latest annual tests (excluding baseline)
        // These are tests that are NOT the baseline (examination_date != baseline_date or different test)
        $baseline_date = $baseline_test ? $baseline_test['examination_date'] : null;
        $baseline_id = $baseline_test ? $baseline_test['id'] : null;
        
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM audiometric_tests 
            WHERE patient_id = ? 
            AND id != ?
            ORDER BY examination_date DESC
            LIMIT 4
        ");
        $stmt->execute([$patient_id, $baseline_id ?: 0]);
        $annual_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sort annual tests by date descending to ensure latest is first
        usort($annual_tests, function($a, $b) {
            return strtotime($b['examination_date']) - strtotime($a['examination_date']);
        });
        
        // Combine baseline and annual tests for display
        // Baseline should appear first (oldest), then annual tests (newest first)
        $audiometric_tests = [];
        if ($baseline_test) {
            // Add baseline test with a flag to identify it
            $baseline_test['is_baseline'] = true;
            $audiometric_tests[] = $baseline_test;
        }
        // Add annual tests
        foreach ($annual_tests as $test) {
            $test['is_baseline'] = false;
            $audiometric_tests[] = $test;
        }
        
        // Sort all tests by date (baseline first, then annual tests by date descending)
        usort($audiometric_tests, function($a, $b) {
            // If one is baseline and the other is not, baseline comes first
            if ($a['is_baseline'] && !$b['is_baseline']) return -1;
            if (!$a['is_baseline'] && $b['is_baseline']) return 1;
            // If both are annual tests, sort by date descending (newest first)
            if (!$a['is_baseline'] && !$b['is_baseline']) {
                return strtotime($b['examination_date']) - strtotime($a['examination_date']);
            }
            // If both are baseline (shouldn't happen), sort by date ascending
            return strtotime($a['examination_date']) - strtotime($b['examination_date']);
        });
        
        // Calculate PTA for baseline test (if available)
        if ($baseline_test) {
            // Calculate baseline PTA: Average(0.5K, 1K, 2K, 3K) / 4
            $calculated_values['right']['pta_baseline'] = calculateAverage05_1K_2K_3K($baseline_test, 'right', true);
            $calculated_values['left']['pta_baseline'] = calculateAverage05_1K_2K_3K($baseline_test, 'left', true);
        }
        
        // Get latest test (most recent annual test, not baseline)
        if (!empty($annual_tests)) {
            $latest_test = $annual_tests[0];
            
            // Calculate annual PTA: Average(0.5K, 1K, 2K, 3K) / 4 (from annual fields)
            $calculated_values['right']['pta_annual'] = calculateAverage05_1K_2K_3K($latest_test, 'right', false);
            $calculated_values['left']['pta_annual'] = calculateAverage05_1K_2K_3K($latest_test, 'left', false);
            
            // Calculate STS if we have both baseline and current test
            if ($baseline_test && $latest_test && $baseline_test['id'] != $latest_test['id']) {
                // Mark STS as calculable
                $calculated_values['right']['sts_calculable'] = true;
                $calculated_values['left']['sts_calculable'] = true;
                
                // Calculate STS: Difference of Average(2K, 3K, 4K) between annual and baseline
                $right_sts = calculateSTS($baseline_test, $latest_test, 'right');
                $left_sts = calculateSTS($baseline_test, $latest_test, 'left');
                
                if ($right_sts) {
                    $calculated_values['right']['sts'] = $right_sts['value'];
                    $calculated_values['right']['has_sts'] = $right_sts['has_sts'];
                }
                if ($left_sts) {
                    $calculated_values['left']['sts'] = $left_sts['value'];
                    $calculated_values['left']['has_sts'] = $left_sts['has_sts'];
                }
            } else {
                // STS not calculable (no baseline available or same test)
                $calculated_values['right']['sts_calculable'] = false;
                $calculated_values['left']['sts_calculable'] = false;
            }
        } elseif ($baseline_test) {
            // If only baseline exists (no annual test yet), only show baseline PTA
            // Annual PTA remains null
            $calculated_values['right']['sts_calculable'] = false;
            $calculated_values['left']['sts_calculable'] = false;
        }
    } catch (Exception $e) {
        error_log("Error fetching audiometric tests: " . $e->getMessage());
    }
}

// Fetch existing summary if available (only if NOT a new entry)
$existing_summary = null;
if (!empty($patient_id) && !$is_new_entry) {
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM audiometric_summaries 
            WHERE patient_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_id]);
        $existing_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching existing summary: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['summary_form'])) {
    try {
        // Get and validate patient_id
        $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : (int)$patient_id;
        $surveillance_id = isset($_POST['surveillance_id']) ? (int)$_POST['surveillance_id'] : (int)$surveillance_id;
        
        // Validate required fields
        if (empty($patient_id) || $patient_id <= 0) {
            throw new Exception("Patient ID is required. Please select a patient.");
        }
        
        // Start transaction
        $clinic_pdo->beginTransaction();
        
        // Create audiometric_summaries table if it doesn't exist
        // Add new columns if they don't exist (for migration)
        $alterTable = "
            CREATE TABLE IF NOT EXISTS audiometric_summaries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT,
                surveillance_id INT,
                right_ear_standard_threshold_shift VARCHAR(10),
                right_ear_pta_baseline DECIMAL(5,2),
                right_ear_pta_annual DECIMAL(5,2),
                right_ear_sts DECIMAL(5,2),
                left_ear_standard_threshold_shift VARCHAR(10),
                left_ear_pta_baseline DECIMAL(5,2),
                left_ear_pta_annual DECIMAL(5,2),
                left_ear_sts DECIMAL(5,2),
                standard_analysis TEXT,
                recommendation TEXT,
                reviewed_by VARCHAR(255),
                done_by VARCHAR(255),
                report_date DATE,
                remark TEXT,
                created_by VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $clinic_pdo->exec($alterTable);
        
        // Add new columns if table already exists (migration)
        $new_columns = [
            'right_ear_pta_baseline' => 'DECIMAL(5,2)',
            'right_ear_pta_annual' => 'DECIMAL(5,2)',
            'right_ear_sts' => 'DECIMAL(5,2)',
            'left_ear_pta_baseline' => 'DECIMAL(5,2)',
            'left_ear_pta_annual' => 'DECIMAL(5,2)',
            'left_ear_sts' => 'DECIMAL(5,2)'
        ];
        
        foreach ($new_columns as $column => $type) {
            try {
                // Check if column exists
                $check = $clinic_pdo->query("SHOW COLUMNS FROM audiometric_summaries LIKE '$column'");
                if ($check->rowCount() == 0) {
                    $clinic_pdo->exec("ALTER TABLE audiometric_summaries ADD COLUMN $column $type");
                }
            } catch (Exception $e) {
                // Column might already exist or table doesn't exist, ignore error
            }
        }
        
        // Check if summary already exists for this patient
        $checkStmt = $clinic_pdo->prepare("SELECT id FROM audiometric_summaries WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $checkStmt->execute([$patient_id]);
        $existing_summary_id = $checkStmt->fetchColumn();
        
        // Sanitize inputs
        $right_ear_sts = sanitizeInput($_POST['right_ear_standard_threshold_shift'] ?? 'N/A');
        $left_ear_sts = sanitizeInput($_POST['left_ear_standard_threshold_shift'] ?? 'N/A');
        $standard_analysis = sanitizeInput($_POST['standard_analysis'] ?? '');
        $recommendation = sanitizeInput($_POST['recommendation'] ?? '');
        $reviewed_by = sanitizeInput($_POST['reviewed_by'] ?? '');
        $done_by = sanitizeInput($_POST['done_by'] ?? '');
        $remark = sanitizeInput($_POST['remark'] ?? '');
        
        // Convert numeric values
        $right_ear_pta_baseline = !empty($_POST['right_ear_pta_baseline']) ? (float)$_POST['right_ear_pta_baseline'] : null;
        $right_ear_pta_annual = !empty($_POST['right_ear_pta_annual']) ? (float)$_POST['right_ear_pta_annual'] : null;
        $right_ear_sts_value = !empty($_POST['right_ear_sts']) ? (float)$_POST['right_ear_sts'] : null;
        $left_ear_pta_baseline = !empty($_POST['left_ear_pta_baseline']) ? (float)$_POST['left_ear_pta_baseline'] : null;
        $left_ear_pta_annual = !empty($_POST['left_ear_pta_annual']) ? (float)$_POST['left_ear_pta_annual'] : null;
        $left_ear_sts_value = !empty($_POST['left_ear_sts']) ? (float)$_POST['left_ear_sts'] : null;
        
        if ($existing_summary_id) {
            // Update existing summary
            $stmt = $clinic_pdo->prepare("
                UPDATE audiometric_summaries SET
                    surveillance_id = ?,
                    right_ear_standard_threshold_shift = ?,
                    right_ear_pta_baseline = ?,
                    right_ear_pta_annual = ?,
                    right_ear_sts = ?,
                    left_ear_standard_threshold_shift = ?,
                    left_ear_pta_baseline = ?,
                    left_ear_pta_annual = ?,
                    left_ear_sts = ?,
                    standard_analysis = ?,
                    recommendation = ?,
                    reviewed_by = ?,
                    done_by = ?,
                    report_date = ?,
                    remark = ?,
                    created_by = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $surveillance_id,
                $right_ear_sts,
                $right_ear_pta_baseline,
                $right_ear_pta_annual,
                $right_ear_sts_value,
                $left_ear_sts,
                $left_ear_pta_baseline,
                $left_ear_pta_annual,
                $left_ear_sts_value,
                $standard_analysis,
                $recommendation,
                $reviewed_by,
                $done_by,
                $_POST['report_date'] ?? null,
                $remark,
                $user_name,
                $existing_summary_id
            ]);
            
            $summary_id = $existing_summary_id;
        } else {
            // Insert new summary data
            $stmt = $clinic_pdo->prepare("
                INSERT INTO audiometric_summaries (
                    patient_id, surveillance_id, right_ear_standard_threshold_shift, 
                    right_ear_pta_baseline, right_ear_pta_annual, right_ear_sts,
                    left_ear_standard_threshold_shift, 
                    left_ear_pta_baseline, left_ear_pta_annual, left_ear_sts,
                    standard_analysis, recommendation, reviewed_by, done_by, report_date, remark, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $patient_id, $surveillance_id, 
                $right_ear_sts,
                $right_ear_pta_baseline, 
                $right_ear_pta_annual, 
                $right_ear_sts_value,
                $left_ear_sts, 
                $left_ear_pta_baseline, 
                $left_ear_pta_annual, 
                $left_ear_sts_value,
                $standard_analysis, $recommendation, $reviewed_by, $done_by, $_POST['report_date'] ?? null, $remark, $user_name
            ]);
            
            $summary_id = $clinic_pdo->lastInsertId();
        }
        
        // Commit transaction
        $clinic_pdo->commit();
        
        $_SESSION['success_message'] = 'Audiometric summary saved successfully! Summary ID: ' . $summary_id;
        
        // Redirect to prevent form resubmission
        header('Location: ' . app_url('audiometric_summary') . '?patient_id=' . $patient_id . '&surveillance_id=' . $surveillance_id . '&saved=1');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($clinic_pdo->inTransaction()) {
            $clinic_pdo->rollBack();
        }
        error_log("Error saving audiometric summary: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = 'Error saving audiometric summary: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiometric Summary - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
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
            background: white;
            border: 1px solid #dee2e6;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            margin-top: 0;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
        }
        
        .audiograph-section {
            padding: 0;
        }
        
        .audiograph-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            text-align: center;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
        }
        
        .audiograph-table {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .audiograph-table table {
            margin-bottom: 0;
        }
        
        .audiograph-table th {
            background-color: #f8f9fa;
            color: #212529;
            font-weight: 700;
            text-align: center;
            padding: 12px 8px;
            border: 1px solid #dee2e6;
            font-size: 13px;
        }
        
        .audiograph-table td {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .audiograph-table td:first-child {
            background-color: #e9ecef;
            font-weight: 700;
            color: #212529;
            text-align: center;
        }
        
        .date-cell {
            background-color: #e9ecef !important;
            font-weight: 700;
            color: #495057;
        }
        
        .audiograph-chart {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            min-height: 400px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        .comments-section {
            padding: 2rem;
            background: white;
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
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .btn {
            font-weight: 500;
            border-radius: 4px;
        }
        
        .btn-primary {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
        
        .threshold-shift-table {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .threshold-shift-table th {
            background-color: #f8f9fa;
            color: #212529;
            font-weight: 700;
            text-align: center;
            padding: 12px 8px;
            border: 1px solid #dee2e6;
            font-size: 13px;
        }
        
        .threshold-shift-table td {
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .threshold-shift-table td:first-child {
            background-color: #e9ecef;
            font-weight: 700;
            color: #212529;
            text-align: center;
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
        
        /* Add spacing between info columns */
        .form-section .row > .col-md-4:not(:first-child) {
            margin-left: 3rem;
        }
        
        @media (max-width: 768px) {
            .form-section .row > .col-md-4:not(:first-child) {
                margin-left: 0;
                margin-top: 1.5rem;
            }
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
            
            .summary-container {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
            
            .audiograph-section {
                padding: 0.5rem !important;
            }
            
            .comments-section {
                padding: 0.5rem !important;
                background: transparent !important;
            }
            
            .section-title {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 11pt !important;
            }
            
            .form-control {
                border: none !important;
                background: transparent !important;
                padding: 0.1rem 0 !important;
            }
            
            .form-label {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 10pt !important;
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
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Summary</li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Summary</li>
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
                <h3 class="certificate-title">AUDIOMETRIC SUMMARY</h3>
            </div>
        </div>

        <!-- Patient Information -->
        <?php if ($patient_data): ?>
        <div class="form-section">
            <h4 class="section-title">PATIENT INFORMATION</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="info-row">
                        <div class="info-label">NAME:</div>
                        <div class="info-value"><?php echo htmlspecialchars($prefilled_patient_name); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IC/PASSPORT:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['NRIC'] ?? ''); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">NATIONALITY:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['citizenship'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">COMPANY:</div>
                        <div class="info-value"><?php echo htmlspecialchars($prefilled_employer); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-row">
                        <div class="info-label">AGE:</div>
                        <div class="info-value">
                            <?php 
                            if ($patient_data && $patient_data['date_of_birth']) {
                                $birthDate = new DateTime($patient_data['date_of_birth']);
                                $today = new DateTime();
                                echo $today->diff($birthDate)->y;
                            } else {
                                echo '-';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">SEX:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['gender'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">JOB TITLE:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['job_title'] ?? '-'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">WORK UNIT:</div>
                        <div class="info-value"><?php echo htmlspecialchars($patient_data['department'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Audiograph Sections -->
        <div class="form-section">
            <h4 class="section-title">AUDIOGRAPH SUMMARY</h4>
            <div class="grid-container">
                <!-- Right Audiograph -->
                <div class="audiograph-section">
                    <h4 class="audiograph-title">RIGHT AUDIOGRAPH</h4>
                    
                    <div class="audiograph-table">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>250</th>
                                    <th>500</th>
                                    <th>1K</th>
                                    <th>2K</th>
                                    <th>3K</th>
                                    <th>4K</th>
                                    <th>6K</th>
                                    <th>8K</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audiometric_tests) || $is_new_entry): ?>
                                <tr>
                                    <td class="date-cell" style="background-color: #ffc107;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #28a745;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #6f4e37;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #007bff;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <?php else: ?>
                                <?php 
                                // Display baseline first, then up to 4 annual tests
                                // Baseline color: #dc3545 (red), Annual colors: ['#ffc107', '#28a745', '#6f4e37', '#007bff']
                                $baseline_color = '#dc3545';
                                $annual_colors = ['#ffc107', '#28a745', '#6f4e37', '#007bff'];
                                $annual_index = 0;
                                
                                foreach ($audiometric_tests as $test): 
                                    $is_baseline = isset($test['is_baseline']) && $test['is_baseline'];
                                    $cell_color = $is_baseline ? $baseline_color : $annual_colors[$annual_index % count($annual_colors)];
                                    if (!$is_baseline) $annual_index++;
                                ?>
                                <tr>
                                    <td class="date-cell" style="background-color: <?php echo $cell_color; ?>;">
                                        <?php 
                                        $date_str = date('d/m/Y', strtotime($test['examination_date']));
                                        if ($is_baseline) {
                                            echo $date_str . ' (Baseline)';
                                        } else {
                                            echo $date_str;
                                        }
                                        ?>
                                    </td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_250'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_250') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_500'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_500') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_1k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_1k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_2k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_2k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_3k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_3k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_4k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_4k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_6k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_6k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['right_8k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'right_8k') ?: '-';
                                        }
                                    ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Right Ear Chart -->
                    <div class="audiograph-chart">
                        <div class="chart-container">
                            <canvas id="rightAudiogramChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Left Audiograph -->
                <div class="audiograph-section">
                    <h4 class="audiograph-title">LEFT AUDIOGRAPH</h4>
                    
                    <div class="audiograph-table">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>250</th>
                                    <th>500</th>
                                    <th>1K</th>
                                    <th>2K</th>
                                    <th>3K</th>
                                    <th>4K</th>
                                    <th>6K</th>
                                    <th>8K</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($audiometric_tests) || $is_new_entry): ?>
                                <tr>
                                    <td class="date-cell" style="background-color: #007bff;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #17a2b8;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #6f42c1;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="date-cell" style="background-color: #007bff;">-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                </tr>
                                <?php else: ?>
                                <?php 
                                // Display baseline first, then up to 4 annual tests
                                // Baseline color: #dc3545 (red), Annual colors: ['#007bff', '#17a2b8', '#6f42c1', '#ffc107']
                                $baseline_color = '#dc3545';
                                $annual_colors = ['#007bff', '#17a2b8', '#6f42c1', '#ffc107'];
                                $annual_index = 0;
                                
                                foreach ($audiometric_tests as $test): 
                                    $is_baseline = isset($test['is_baseline']) && $test['is_baseline'];
                                    $cell_color = $is_baseline ? $baseline_color : $annual_colors[$annual_index % count($annual_colors)];
                                    if (!$is_baseline) $annual_index++;
                                ?>
                                <tr>
                                    <td class="date-cell" style="background-color: <?php echo $cell_color; ?>;">
                                        <?php 
                                        $date_str = date('d/m/Y', strtotime($test['examination_date']));
                                        if ($is_baseline) {
                                            echo $date_str . ' (Baseline)';
                                        } else {
                                            echo $date_str;
                                        }
                                        ?>
                                    </td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_250'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_250') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_500'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_500') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_1k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_1k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_2k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_2k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_3k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_3k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_4k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_4k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_6k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_6k') ?: '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        if ($is_baseline) {
                                            echo $test['left_8k'] ?? '-';
                                        } else {
                                            echo getAnnualValue($test, 'left_8k') ?: '-';
                                        }
                                    ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Left Ear Chart -->
                    <div class="audiograph-chart">
                        <div class="chart-container">
                            <canvas id="leftAudiogramChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="form-section">
                <h4 class="section-title">COMMENTS</h4>
                
                <form method="POST" id="summaryForm">
                    <input type="hidden" name="summary_form" value="1">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
                    <input type="hidden" name="surveillance_id" value="<?php echo $surveillance_id; ?>">
                    
                    <!-- Standard Threshold Shift -->
                    <div class="threshold-shift-table">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Standard Threshold Shift</th>
                                    <th>Result</th>
                                    <th>Pure Tone Average (Baseline & Annual)</th>
                                    <th>Standard Threshold Shift (Difference between Baseline & Annual)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Right Ear:</td>
                                    <td>
                                        <?php 
                                        $right_result = 'N/A';
                                        $right_result_color = '#6c757d';
                                        $right_result_value = 'N/A';
                                        if ($calculated_values['right']['sts_calculable']) {
                                            $right_result = $calculated_values['right']['has_sts'] ? 'YES' : 'NO';
                                            $right_result_color = $calculated_values['right']['has_sts'] ? '#dc3545' : '#28a745';
                                            $right_result_value = $right_result;
                                        }
                                        ?>
                                        <div class="form-control form-control-sm" style="background-color: #f8f9fa; border: 1px solid #ced4da; text-align: center; font-weight: 600; color: <?php echo $right_result_color; ?>;">
                                            <?php echo $right_result; ?>
                                        </div>
                                        <input type="hidden" name="right_ear_standard_threshold_shift" value="<?php echo $right_result_value; ?>">
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <div style="flex: 1;">
                                                <label style="font-size: 0.75rem; color: #6c757d; margin-bottom: 2px; display: block;">Baseline:</label>
                                                <input type="number" class="form-control form-control-sm" name="right_ear_pta_baseline" id="right_ear_pta_baseline" value="<?php echo $is_new_entry ? '' : ($calculated_values['right']['pta_baseline'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                            </div>
                                            <div style="flex: 1;">
                                                <label style="font-size: 0.75rem; color: #6c757d; margin-bottom: 2px; display: block;">Annual:</label>
                                                <input type="number" class="form-control form-control-sm" name="right_ear_pta_annual" id="right_ear_pta_annual" value="<?php echo $is_new_entry ? '' : ($calculated_values['right']['pta_annual'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" name="right_ear_sts" id="right_ear_sts" value="<?php echo $is_new_entry ? '' : ($calculated_values['right']['sts'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Left Ear:</td>
                                    <td>
                                        <?php 
                                        $left_result = 'N/A';
                                        $left_result_color = '#6c757d';
                                        $left_result_value = 'N/A';
                                        if ($calculated_values['left']['sts_calculable']) {
                                            $left_result = $calculated_values['left']['has_sts'] ? 'YES' : 'NO';
                                            $left_result_color = $calculated_values['left']['has_sts'] ? '#dc3545' : '#28a745';
                                            $left_result_value = $left_result;
                                        }
                                        ?>
                                        <div class="form-control form-control-sm" style="background-color: #f8f9fa; border: 1px solid #ced4da; text-align: center; font-weight: 600; color: <?php echo $left_result_color; ?>;">
                                            <?php echo $left_result; ?>
                                        </div>
                                        <input type="hidden" name="left_ear_standard_threshold_shift" value="<?php echo $left_result_value; ?>">
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            <div style="flex: 1;">
                                                <label style="font-size: 0.75rem; color: #6c757d; margin-bottom: 2px; display: block;">Baseline:</label>
                                                <input type="number" class="form-control form-control-sm" name="left_ear_pta_baseline" id="left_ear_pta_baseline" value="<?php echo $is_new_entry ? '' : ($calculated_values['left']['pta_baseline'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                            </div>
                                            <div style="flex: 1;">
                                                <label style="font-size: 0.75rem; color: #6c757d; margin-bottom: 2px; display: block;">Annual:</label>
                                                <input type="number" class="form-control form-control-sm" name="left_ear_pta_annual" id="left_ear_pta_annual" value="<?php echo $is_new_entry ? '' : ($calculated_values['left']['pta_annual'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" name="left_ear_sts" id="left_ear_sts" value="<?php echo $is_new_entry ? '' : ($calculated_values['left']['sts'] ?? ''); ?>" step="0.01" readonly style="background-color: #f8f9fa;">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Standard Analysis -->
                    <div class="mb-3">
                        <label class="form-label">Standard Analysis:</label>
                        <textarea class="form-control" name="standard_analysis" rows="4" placeholder="Enter standard analysis..."><?php echo $is_new_entry ? '' : htmlspecialchars($existing_summary['standard_analysis'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Recommendation -->
                    <div class="mb-3">
                        <label class="form-label">Recommendation:</label>
                        <textarea class="form-control" name="recommendation" rows="4" placeholder="Enter recommendations..."><?php echo $is_new_entry ? '' : htmlspecialchars($existing_summary['recommendation'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Administrative Details -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Reviewed by:</label>
                                <input type="text" class="form-control" name="reviewed_by" value="<?php echo htmlspecialchars($existing_summary['reviewed_by'] ?? ''); ?>" readonly>
                                <!--<small class="form-text text-muted">HQ/23/DOC/00/01035</small>-->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Done by:</label>
                                <input type="text" class="form-control" name="done_by" placeholder="Enter name..." value="<?php echo $is_new_entry ? '' : htmlspecialchars($existing_summary['done_by'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date:</label>
                                <input type="date" class="form-control" name="report_date" value="<?php echo $is_new_entry ? date('Y-m-d') : ($existing_summary['report_date'] ?? date('Y-m-d')); ?>">
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <a href="<?php echo app_url('generate_audiometric_complete_pdf'); ?>?patient_id=<?php echo $patient_id; ?>&surveillance_id=<?php echo $surveillance_id; ?>" 
                           class="btn btn-info btn-lg me-3" target="_blank">
                            <i class="fas fa-file-pdf"></i> Generate Complete Report
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-lg me-3" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-lg me-3" onclick="window.location.href='audiometric_test.php?patient_id=<?php echo $patient_id; ?>&surveillance_id=<?php echo $surveillance_id; ?>'">
                            <i class="fas fa-arrow-left"></i> Back: Test
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-save"></i> Save Summary
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="window.location.href='audiometric_report.php?patient_id=<?php echo $patient_id; ?>&surveillance_id=<?php echo $surveillance_id; ?>'">
                            <i class="fas fa-arrow-right"></i> Next: Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Audiometric test data from PHP
        const audiometricTests = <?php echo json_encode($audiometric_tests); ?>;
        
        // Frequency labels (logarithmic scale positions)
        const frequencies = [250, 500, 1000, 2000, 3000, 4000, 6000, 8000];
        const frequencyLabels = ['250', '500', '1K', '2K', '3K', '4K', '6K', '8K'];
        
        // Symbol definitions with colors (matching audiometric_test.php)
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
        
        // Convert frequency to log scale position (for better visualization)
        function getFrequencyPosition(freq) {
            const logFreq = Math.log10(freq);
            const minLog = Math.log10(250);
            const maxLog = Math.log10(8000);
            return ((logFreq - minLog) / (maxLog - minLog)) * 100;
        }
        
        // Create audiogram chart
        function createAudiogramChart(canvasId, ear, tests) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;
            
            // Baseline color: red, Annual colors vary by ear
            const baselineColor = '#dc3545';
            const annualColors = ear === 'right' 
                ? ['#ffc107', '#28a745', '#6f4e37', '#007bff']
                : ['#007bff', '#17a2b8', '#6f42c1', '#ffc107'];
            const datasets = [];
            
            // Display baseline first, then up to 4 annual tests
            const allTests = tests && tests.length > 0 ? tests : [];
            let annualIndex = 0;
            
            if (allTests.length > 0) {
                allTests.forEach((test) => {
                    // Check if baseline (handle both boolean and integer from JSON)
                    const isBaseline = test.is_baseline === true || test.is_baseline === 1 || test.is_baseline === '1';
                    const dateLabel = new Date(test.examination_date).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
                    
                    // AIR CONDUCTION DATA
                    const airData = frequencies.map(freq => {
                        let key;
                        if (freq === 250) key = ear + '_250';
                        else if (freq === 500) key = ear + '_500';
                        else if (freq === 1000) key = ear + '_1k';
                        else if (freq === 2000) key = ear + '_2k';
                        else if (freq === 3000) key = ear + '_3k';
                        else if (freq === 4000) key = ear + '_4k';
                        else if (freq === 6000) key = ear + '_6k';
                        else if (freq === 8000) key = ear + '_8k';
                        else key = ear + '_' + freq.toString();
                        
                        let value = null;
                        if (isBaseline) {
                            // For baseline, use baseline fields directly
                            value = test[key] !== null && test[key] !== undefined && test[key] !== '' 
                                ? parseFloat(test[key]) 
                                : null;
                        } else {
                            // For annual tests, use annual fields if available, otherwise fall back to baseline fields
                            const annualKey = 'annual_' + key;
                            if (test[annualKey] !== null && test[annualKey] !== undefined && test[annualKey] !== '') {
                                value = parseFloat(test[annualKey]);
                            } else if (test[key] !== null && test[key] !== undefined && test[key] !== '') {
                                value = parseFloat(test[key]);
                            }
                        }
                        return value;
                    });
                    
                    // BONE CONDUCTION DATA
                    const boneData = frequencies.map(freq => {
                        let key;
                        if (freq === 250) key = ear + '_bone_250';
                        else if (freq === 500) key = ear + '_bone_500';
                        else if (freq === 1000) key = ear + '_bone_1k';
                        else if (freq === 2000) key = ear + '_bone_2k';
                        else if (freq === 3000) key = ear + '_bone_3k';
                        else if (freq === 4000) key = ear + '_bone_4k';
                        else if (freq === 6000) key = ear + '_bone_6k';
                        else if (freq === 8000) key = ear + '_bone_8k';
                        else key = ear + '_bone_' + freq.toString();
                        
                        let value = null;
                        if (isBaseline) {
                            // For baseline, use baseline bone fields directly
                            value = test[key] !== null && test[key] !== undefined && test[key] !== '' 
                                ? parseFloat(test[key]) 
                                : null;
                        } else {
                            // For annual tests, use annual bone fields if available
                            const annualKey = 'annual_' + key;
                            if (test[annualKey] !== null && test[annualKey] !== undefined && test[annualKey] !== '') {
                                value = parseFloat(test[annualKey]);
                            } else if (test[key] !== null && test[key] !== undefined && test[key] !== '') {
                                value = parseFloat(test[key]);
                            }
                        }
                        return value;
                    });
                    
                    // Determine symbol for air conduction based on ear and test type
                    let airSymbolKey;
                    if (ear === 'right') {
                        // Right ear uses circles
                        airSymbolKey = isBaseline ? 'circle_baseline' : 'circle_unmasked';
                    } else {
                        // Left ear uses crosses
                        airSymbolKey = isBaseline ? 'cross_baseline' : 'cross_unmasked';
                    }
                    
                    // Determine symbol for bone conduction
                    let boneSymbolKey;
                    if (ear === 'right') {
                        boneSymbolKey = isBaseline ? 'triangle_unmasked_right' : 'triangle_unmasked_right';
                    } else {
                        boneSymbolKey = isBaseline ? 'triangle_unmasked_left' : 'triangle_unmasked_left';
                    }
                    
                    const airSymbolDef = symbolDefinitions[airSymbolKey];
                    const boneSymbolDef = symbolDefinitions[boneSymbolKey];
                    const airColor = airSymbolDef ? airSymbolDef.color : (isBaseline ? baselineColor : annualColors[annualIndex % annualColors.length]);
                    const boneColor = boneSymbolDef ? boneSymbolDef.color : '#c40404';
                    
                    // Add AIR CONDUCTION dataset (only if has data)
                    const hasAirData = airData.some(v => v !== null && v !== undefined && v !== '');
                    if (hasAirData) {
                        datasets.push({
                            label: (isBaseline ? dateLabel + ' (Baseline)' : dateLabel) + ' - Air',
                            data: airData,
                            borderColor: airColor,
                            backgroundColor: airColor + '1A',
                            pointBackgroundColor: airSymbolDef && airSymbolDef.fill ? airSymbolDef.color : 'transparent',
                            pointBorderColor: airColor,
                            pointRadius: 7,
                            pointHoverRadius: 12,
                            pointHitRadius: 15,
                            pointBorderWidth: (airSymbolDef && airSymbolDef.style === 'circle' && ear === 'right' && !isBaseline) ? 2.5 : 2,
                            pointStyle: airSymbolDef && airSymbolDef.style === 'circle' ? 'circle' : false,
                            pointRotation: airSymbolDef ? (airSymbolDef.rotation || 0) : 0,
                            symbolKey: airSymbolKey,
                            tension: 0.4,
                            fill: false,
                            spanGaps: true,
                            borderWidth: isBaseline ? 3 : 2.5,
                            borderDash: isBaseline ? [8, 5] : []
                        });
                    }
                    
                    // Add BONE CONDUCTION dataset (only if has data)
                    const hasBoneData = boneData.some(v => v !== null && v !== undefined && v !== '');
                    if (hasBoneData) {
                        datasets.push({
                            label: (isBaseline ? dateLabel + ' (Baseline)' : dateLabel) + ' - Bone',
                            data: boneData,
                            borderColor: boneColor,
                            backgroundColor: boneColor + '1A',
                            pointBackgroundColor: 'transparent',
                            pointBorderColor: boneColor,
                            pointRadius: 7,
                            pointHoverRadius: 12,
                            pointHitRadius: 15,
                            pointBorderWidth: 2,
                            pointStyle: false,
                            pointRotation: boneSymbolDef ? (boneSymbolDef.rotation || 0) : 0,
                            symbolKey: boneSymbolKey,
                            tension: 0.4,
                            fill: false,
                            spanGaps: true,
                            showLine: true,
                            borderWidth: isBaseline ? 3 : 2.5,
                            borderDash: isBaseline ? [8, 5] : []
                        });
                    }
                    
                    if (!isBaseline) annualIndex++;
                });
            } else {
                // Create empty dataset to show graph structure
                datasets.push({
                    label: 'No data',
                    data: frequencies.map(() => null),
                    borderColor: '#e9ecef',
                    backgroundColor: 'transparent',
                    pointBackgroundColor: '#e9ecef',
                    pointBorderColor: '#e9ecef',
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    tension: 0.3,
                    fill: false,
                    spanGaps: true,
                    borderWidth: 0
                });
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: frequencyLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: (ear === 'right' ? 'Right' : 'Left') + ' Ear Audiogram',
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
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: '#e9ecef'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Hearing Level (dB HL)',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            reverse: true, // dB HL is reversed (higher numbers = worse hearing)
                            min: -10,
                            max: 120,
                            ticks: {
                                stepSize: 10
                            },
                            grid: {
                                color: '#e9ecef'
                            }
                        }
                    }
                }
            });
        }
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Always create charts (even if no data) - so user can see graph structure for UI arrangement
            createAudiogramChart('rightAudiogramChart', 'right', audiometricTests || []);
            createAudiogramChart('leftAudiogramChart', 'left', audiometricTests || []);
        });
    </script>
</body>
</html>
