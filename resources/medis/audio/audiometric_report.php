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

// Fetch logged-in user's full name and signature for OHD field
$ohd_full_name = '';
$ohd_signature_path = '';
$ohd_signature_name = '';

try {
    // Get user details from users table
    $stmt = $clinic_pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_details) {
        $ohd_full_name = trim(($user_details['first_name'] ?? '') . ' ' . ($user_details['last_name'] ?? ''));
    }
    
    // Get user signature from user_signatures table
    $stmt = $clinic_pdo->prepare("SELECT file_path, signature_name FROM user_signatures WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $signature_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($signature_data) {
        $ohd_signature_path = $signature_data['file_path'] ?? '';
        $ohd_signature_name = $signature_data['signature_name'] ?? '';
    }
} catch (Exception $e) {
    error_log("Error fetching OHD user info: " . $e->getMessage());
}

// Get pre-filled patient data from URL
$prefilled_patient_name = $_GET['patient_name'] ?? '';
$prefilled_employer = $_GET['employer'] ?? '';
$patient_id = $_GET['patient_id'] ?? '';
$surveillance_id = isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0;

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_form'])) {
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
        
        // Create audiometric_reports table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS audiometric_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT,
            surveillance_id INT,
            personal_exposure_monitoring_dba INT,
            personal_exposure_monitoring_date DATE,
            current_illness_symptoms TEXT,
            smoking_status VARCHAR(10),
            smoking_packs_per_day INT,
            past_ear_disease VARCHAR(10),
            past_ear_disease_specify TEXT,
            past_head_injury VARCHAR(10),
            past_head_injury_specify TEXT,
            past_medical_history VARCHAR(10),
            past_medical_history_specify TEXT,
            ototoxic_medications VARCHAR(10),
            ototoxic_medications_specify TEXT,
            hobbies_diving TINYINT(1),
            hobbies_loud_music TINYINT(1),
            hobbies_musical_instrument TINYINT(1),
            hobbies_karaoke TINYINT(1),
            hobbies_shooting TINYINT(1),
            hobbies_others TINYINT(1),
            php_ear_plug TINYINT(1),
            php_earmuff TINYINT(1),
            php_combination TINYINT(1),
            php_none TINYINT(1),
            external_ear_normal TINYINT(1),
            external_ear_abnormal TINYINT(1),
            external_ear_specify TEXT,
            middle_ear_normal TINYINT(1),
            middle_ear_abnormal TINYINT(1),
            middle_ear_specify TEXT,
            weber_centralization TINYINT(1),
            weber_lateralization_left TINYINT(1),
            weber_lateralization_right TINYINT(1),
            rinne_right_positive TINYINT(1),
            rinne_right_negative TINYINT(1),
            rinne_left_positive TINYINT(1),
            rinne_left_negative TINYINT(1),
            impression_conductive TINYINT(1),
            impression_sensorineural TINYINT(1),
            impression_mixed TINYINT(1),
            conclusion_occupational_hearing_impairment TINYINT(1),
            conclusion_occupational_permanent_standard_threshold_shift TINYINT(1),
            conclusion_occupational_noise_induced_hearing_loss TINYINT(1),
            conclusion_age_related_hearing_loss TINYINT(1),
            conclusion_others TINYINT(1),
            conclusion_others_specify TEXT,
            recommendation_repeat_audiometry TINYINT(1),
            recommendation_continue_annual_audiometry TINYINT(1),
            recommendation_provision_php TINYINT(1),
            recommendation_referral_specialist TINYINT(1),
            recommendation_notification_dosh TINYINT(1),
            recommendation_others TINYINT(1),
            recommendation_others_specify TEXT,
            remarks TEXT,
            ohd_name_signature_stamp TEXT,
            employee_acknowledgment TEXT,
            employee_signature TEXT,
            employee_name TEXT,
            employee_ic_passport TEXT,
            employee_date DATE,
            created_by VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $clinic_pdo->exec($createTable);
        
        // Check if report already exists for this patient
        $checkStmt = $clinic_pdo->prepare("SELECT id FROM audiometric_reports WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $checkStmt->execute([$patient_id]);
        $existing_report_id = $checkStmt->fetchColumn();
        
        // Sanitize text inputs
        $personal_exposure_dba = !empty($_POST['personal_exposure_monitoring_dba']) ? (int)$_POST['personal_exposure_monitoring_dba'] : null;
        $personal_exposure_date = !empty($_POST['personal_exposure_monitoring_date']) ? $_POST['personal_exposure_monitoring_date'] : null;
        $current_illness_symptoms = sanitizeInput($_POST['current_illness_symptoms'] ?? '');
        $smoking_status = sanitizeInput($_POST['smoking_status'] ?? 'NO');
        $smoking_packs_per_day = !empty($_POST['smoking_packs_per_day']) ? (int)$_POST['smoking_packs_per_day'] : null;
        $past_ear_disease = sanitizeInput($_POST['past_ear_disease'] ?? 'NO');
        $past_ear_disease_specify = sanitizeInput($_POST['past_ear_disease_specify'] ?? '');
        $past_head_injury = sanitizeInput($_POST['past_head_injury'] ?? 'NO');
        $past_head_injury_specify = sanitizeInput($_POST['past_head_injury_specify'] ?? '');
        $past_medical_history = sanitizeInput($_POST['past_medical_history'] ?? 'NO');
        $past_medical_history_specify = sanitizeInput($_POST['past_medical_history_specify'] ?? '');
        $ototoxic_medications = sanitizeInput($_POST['ototoxic_medications'] ?? 'NO');
        $ototoxic_medications_specify = sanitizeInput($_POST['ototoxic_medications_specify'] ?? '');
        $external_ear_specify = sanitizeInput($_POST['external_ear_specify'] ?? '');
        $middle_ear_specify = sanitizeInput($_POST['middle_ear_specify'] ?? '');
        $conclusion_others_specify = sanitizeInput($_POST['conclusion_others_specify'] ?? '');
        $recommendation_others_specify = sanitizeInput($_POST['recommendation_others_specify'] ?? '');
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        $ohd_name_signature_stamp = sanitizeInput($_POST['ohd_name_signature_stamp'] ?? '');
        $employee_acknowledgment = sanitizeInput($_POST['employee_acknowledgment'] ?? '');
        // Employee signature is now base64 image data, not text
        $employee_signature = $_POST['employee_signature'] ?? '';
        $employee_name = sanitizeInput($_POST['employee_name'] ?? '');
        $employee_ic_passport = sanitizeInput($_POST['employee_ic_passport'] ?? '');
        $employee_date = !empty($_POST['employee_date']) ? $_POST['employee_date'] : null;
        
        // Prepare checkbox values
        $hobbies_diving = isset($_POST['hobbies_diving']) ? 1 : 0;
        $hobbies_loud_music = isset($_POST['hobbies_loud_music']) ? 1 : 0;
        $hobbies_musical_instrument = isset($_POST['hobbies_musical_instrument']) ? 1 : 0;
        $hobbies_karaoke = isset($_POST['hobbies_karaoke']) ? 1 : 0;
        $hobbies_shooting = isset($_POST['hobbies_shooting']) ? 1 : 0;
        $hobbies_others = isset($_POST['hobbies_others']) ? 1 : 0;
        $php_ear_plug = isset($_POST['php_ear_plug']) ? 1 : 0;
        $php_earmuff = isset($_POST['php_earmuff']) ? 1 : 0;
        $php_combination = isset($_POST['php_combination']) ? 1 : 0;
        $php_none = isset($_POST['php_none']) ? 1 : 0;
        $external_ear_normal = isset($_POST['external_ear_normal']) ? 1 : 0;
        $external_ear_abnormal = isset($_POST['external_ear_abnormal']) ? 1 : 0;
        $middle_ear_normal = isset($_POST['middle_ear_normal']) ? 1 : 0;
        $middle_ear_abnormal = isset($_POST['middle_ear_abnormal']) ? 1 : 0;
        $weber_centralization = isset($_POST['weber_centralization']) ? 1 : 0;
        $weber_lateralization_left = isset($_POST['weber_lateralization_left']) ? 1 : 0;
        $weber_lateralization_right = isset($_POST['weber_lateralization_right']) ? 1 : 0;
        $rinne_right_positive = isset($_POST['rinne_right_positive']) ? 1 : 0;
        $rinne_right_negative = isset($_POST['rinne_right_negative']) ? 1 : 0;
        $rinne_left_positive = isset($_POST['rinne_left_positive']) ? 1 : 0;
        $rinne_left_negative = isset($_POST['rinne_left_negative']) ? 1 : 0;
        $impression_conductive = isset($_POST['impression_conductive']) ? 1 : 0;
        $impression_sensorineural = isset($_POST['impression_sensorineural']) ? 1 : 0;
        $impression_mixed = isset($_POST['impression_mixed']) ? 1 : 0;
        $conclusion_occupational_hearing_impairment = isset($_POST['conclusion_occupational_hearing_impairment']) ? 1 : 0;
        $conclusion_occupational_permanent_standard_threshold_shift = isset($_POST['conclusion_occupational_permanent_standard_threshold_shift']) ? 1 : 0;
        $conclusion_occupational_noise_induced_hearing_loss = isset($_POST['conclusion_occupational_noise_induced_hearing_loss']) ? 1 : 0;
        $conclusion_age_related_hearing_loss = isset($_POST['conclusion_age_related_hearing_loss']) ? 1 : 0;
        $conclusion_others = isset($_POST['conclusion_others']) ? 1 : 0;
        $recommendation_repeat_audiometry = isset($_POST['recommendation_repeat_audiometry']) ? 1 : 0;
        $recommendation_continue_annual_audiometry = isset($_POST['recommendation_continue_annual_audiometry']) ? 1 : 0;
        $recommendation_provision_php = isset($_POST['recommendation_provision_php']) ? 1 : 0;
        $recommendation_referral_specialist = isset($_POST['recommendation_referral_specialist']) ? 1 : 0;
        $recommendation_notification_dosh = isset($_POST['recommendation_notification_dosh']) ? 1 : 0;
        $recommendation_others = isset($_POST['recommendation_others']) ? 1 : 0;
        
        if ($existing_report_id) {
            // Update existing report
            $stmt = $clinic_pdo->prepare("
                UPDATE audiometric_reports SET
                    surveillance_id = ?,
                    personal_exposure_monitoring_dba = ?,
                    personal_exposure_monitoring_date = ?,
                    current_illness_symptoms = ?,
                    smoking_status = ?,
                    smoking_packs_per_day = ?,
                    past_ear_disease = ?,
                    past_ear_disease_specify = ?,
                    past_head_injury = ?,
                    past_head_injury_specify = ?,
                    past_medical_history = ?,
                    past_medical_history_specify = ?,
                    ototoxic_medications = ?,
                    ototoxic_medications_specify = ?,
                    hobbies_diving = ?,
                    hobbies_loud_music = ?,
                    hobbies_musical_instrument = ?,
                    hobbies_karaoke = ?,
                    hobbies_shooting = ?,
                    hobbies_others = ?,
                    php_ear_plug = ?,
                    php_earmuff = ?,
                    php_combination = ?,
                    php_none = ?,
                    external_ear_normal = ?,
                    external_ear_abnormal = ?,
                    external_ear_specify = ?,
                    middle_ear_normal = ?,
                    middle_ear_abnormal = ?,
                    middle_ear_specify = ?,
                    weber_centralization = ?,
                    weber_lateralization_left = ?,
                    weber_lateralization_right = ?,
                    rinne_right_positive = ?,
                    rinne_right_negative = ?,
                    rinne_left_positive = ?,
                    rinne_left_negative = ?,
                    impression_conductive = ?,
                    impression_sensorineural = ?,
                    impression_mixed = ?,
                    conclusion_occupational_hearing_impairment = ?,
                    conclusion_occupational_permanent_standard_threshold_shift = ?,
                    conclusion_occupational_noise_induced_hearing_loss = ?,
                    conclusion_age_related_hearing_loss = ?,
                    conclusion_others = ?,
                    conclusion_others_specify = ?,
                    recommendation_repeat_audiometry = ?,
                    recommendation_continue_annual_audiometry = ?,
                    recommendation_provision_php = ?,
                    recommendation_referral_specialist = ?,
                    recommendation_notification_dosh = ?,
                    recommendation_others = ?,
                    recommendation_others_specify = ?,
                    remarks = ?,
                    ohd_name_signature_stamp = ?,
                    employee_acknowledgment = ?,
                    employee_signature = ?,
                    employee_name = ?,
                    employee_ic_passport = ?,
                    employee_date = ?,
                    created_by = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $surveillance_id, $personal_exposure_dba, $personal_exposure_date,
                $current_illness_symptoms, $smoking_status, $smoking_packs_per_day, $past_ear_disease, $past_ear_disease_specify,
                $past_head_injury, $past_head_injury_specify, $past_medical_history, $past_medical_history_specify,
                $ototoxic_medications, $ototoxic_medications_specify, $hobbies_diving, $hobbies_loud_music, $hobbies_musical_instrument,
                $hobbies_karaoke, $hobbies_shooting, $hobbies_others, $php_ear_plug, $php_earmuff, $php_combination, $php_none,
                $external_ear_normal, $external_ear_abnormal, $external_ear_specify, $middle_ear_normal, $middle_ear_abnormal, $middle_ear_specify,
                $weber_centralization, $weber_lateralization_left, $weber_lateralization_right, $rinne_right_positive, $rinne_right_negative,
                $rinne_left_positive, $rinne_left_negative, $impression_conductive, $impression_sensorineural, $impression_mixed,
                $conclusion_occupational_hearing_impairment, $conclusion_occupational_permanent_standard_threshold_shift,
                $conclusion_occupational_noise_induced_hearing_loss, $conclusion_age_related_hearing_loss, $conclusion_others, $conclusion_others_specify,
                $recommendation_repeat_audiometry, $recommendation_continue_annual_audiometry, $recommendation_provision_php,
                $recommendation_referral_specialist, $recommendation_notification_dosh, $recommendation_others, $recommendation_others_specify,
                $remarks, $ohd_name_signature_stamp, $employee_acknowledgment, $employee_signature, $employee_name, $employee_ic_passport, $employee_date, $user_name,
                $existing_report_id
            ]);
            
            $report_id = $existing_report_id;
        } else {
            // Insert new report data
            $stmt = $clinic_pdo->prepare("
                INSERT INTO audiometric_reports (
                    patient_id, surveillance_id, personal_exposure_monitoring_dba, personal_exposure_monitoring_date,
                    current_illness_symptoms, smoking_status, smoking_packs_per_day, past_ear_disease, past_ear_disease_specify,
                    past_head_injury, past_head_injury_specify, past_medical_history, past_medical_history_specify,
                    ototoxic_medications, ototoxic_medications_specify, hobbies_diving, hobbies_loud_music, hobbies_musical_instrument,
                    hobbies_karaoke, hobbies_shooting, hobbies_others, php_ear_plug, php_earmuff, php_combination, php_none,
                    external_ear_normal, external_ear_abnormal, external_ear_specify, middle_ear_normal, middle_ear_abnormal, middle_ear_specify,
                    weber_centralization, weber_lateralization_left, weber_lateralization_right, rinne_right_positive, rinne_right_negative,
                    rinne_left_positive, rinne_left_negative, impression_conductive, impression_sensorineural, impression_mixed,
                    conclusion_occupational_hearing_impairment, conclusion_occupational_permanent_standard_threshold_shift,
                    conclusion_occupational_noise_induced_hearing_loss, conclusion_age_related_hearing_loss, conclusion_others, conclusion_others_specify,
                    recommendation_repeat_audiometry, recommendation_continue_annual_audiometry, recommendation_provision_php,
                    recommendation_referral_specialist, recommendation_notification_dosh, recommendation_others, recommendation_others_specify,
                    remarks, ohd_name_signature_stamp, employee_acknowledgment, employee_signature, employee_name, employee_ic_passport, employee_date, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $patient_id, $surveillance_id, $personal_exposure_dba, $personal_exposure_date,
                $current_illness_symptoms, $smoking_status, $smoking_packs_per_day, $past_ear_disease, $past_ear_disease_specify,
                $past_head_injury, $past_head_injury_specify, $past_medical_history, $past_medical_history_specify,
                $ototoxic_medications, $ototoxic_medications_specify, $hobbies_diving, $hobbies_loud_music, $hobbies_musical_instrument,
                $hobbies_karaoke, $hobbies_shooting, $hobbies_others, $php_ear_plug, $php_earmuff, $php_combination, $php_none,
                $external_ear_normal, $external_ear_abnormal, $external_ear_specify, $middle_ear_normal, $middle_ear_abnormal, $middle_ear_specify,
                $weber_centralization, $weber_lateralization_left, $weber_lateralization_right, $rinne_right_positive, $rinne_right_negative,
                $rinne_left_positive, $rinne_left_negative, $impression_conductive, $impression_sensorineural, $impression_mixed,
                $conclusion_occupational_hearing_impairment, $conclusion_occupational_permanent_standard_threshold_shift,
                $conclusion_occupational_noise_induced_hearing_loss, $conclusion_age_related_hearing_loss, $conclusion_others, $conclusion_others_specify,
                $recommendation_repeat_audiometry, $recommendation_continue_annual_audiometry, $recommendation_provision_php,
                $recommendation_referral_specialist, $recommendation_notification_dosh, $recommendation_others, $recommendation_others_specify,
                $remarks, $ohd_name_signature_stamp, $employee_acknowledgment, $employee_signature, $employee_name, $employee_ic_passport, $employee_date, $user_name
            ]);
            
            $report_id = $clinic_pdo->lastInsertId();
        }
        
        // Commit transaction
        $clinic_pdo->commit();
        
        $_SESSION['success_message'] = 'Audiometric report saved successfully! Report ID: ' . $report_id;
        
        // Redirect to prevent form resubmission
        header('Location: ' . app_url('audiometric_report') . '?patient_id=' . $patient_id . '&surveillance_id=' . $surveillance_id . '&saved=1');
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($clinic_pdo->inTransaction()) {
            $clinic_pdo->rollBack();
        }
        error_log("Error saving audiometric report: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        error_log("POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = 'Error saving audiometric report: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiometric Report - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
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
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        /* Bootstrap form-check styling for checkboxes and radio buttons */
        .form-check {
            margin-bottom: 0.75rem;
        }
        
        .form-check-input {
            margin-top: 0.25rem;
            margin-right: 0.5rem;
        }
        
        .form-check-label {
            font-weight: 500;
            color: #495057;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .form-check-input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
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
        
        .form-control-plaintext {
            padding: 0.5rem 0;
            font-size: 1rem;
            min-height: 2.5rem;
            display: flex;
            align-items: center;
            color: #495057;
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
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .three-column {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        
        .acknowledgment-section {
            background: white;
            border: 1px solid #dee2e6;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            padding: 2rem;
            margin-top: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .acknowledgment-text {
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            text-align: justify;
        }
        
        @media (max-width: 768px) {
            .two-column,
            .three-column {
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
                border-bottom: 2px solid #28a745 !important;
                padding-bottom: 0.5rem !important;
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
            
            .form-check {
                display: block !important;
                margin-bottom: 0.5rem !important;
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
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Report</li>
                <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Audiometric Report</li>
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
                <h3 class="certificate-title">REPORT OF OCCUPATIONAL DISEASE/POISONINGS</h3>
            </div>
        </div>

        <form method="POST" id="reportForm">
            <input type="hidden" name="report_form" value="1">
            <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
            <input type="hidden" name="surveillance_id" value="<?php echo $surveillance_id; ?>">
            
            <!-- PART A: EMPLOYEE DETAILS -->
            <div class="form-section">
                <h4 class="section-title">PART A: EMPLOYEE DETAILS</h4>
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

            <!-- PART B: MEDICAL HISTORY -->
            <div class="form-section">
                <h4 class="section-title">PART B: MEDICAL HISTORY</h4>
              
                
                <!-- Personal exposure monitoring -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Personal exposure monitoring:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="personal_exposure_monitoring_dba" placeholder="dB(A)">
                                </div>
                                <div class="col-md-6">
                                    <input type="date" class="form-control" name="personal_exposure_monitoring_date">
                                </div>
                            </div>
                            <small class="form-text text-muted">[please refer to personal exposure monitoring of individual/similar exposure group (SEG) in the Noise Risk Assessment Report]</small>
                        </div>
                    </div>
                </div>

                <!-- Current illness -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Current illness (Symptoms):</label>
                            <textarea class="form-control" name="current_illness_symptoms" rows="3" placeholder="Enter symptoms..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Smoking -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Smoking:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="smoking_status" value="YES" id="smoking_yes">
                                    <label class="form-check-label" for="smoking_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="smoking_status" value="NO" id="smoking_no">
                                    <label class="form-check-label" for="smoking_no">NO</label>
                                </div>
                            </div>
                            <div class="row" id="smoking_specify_container" style="display: none;">
                                <div class="col-md-6">
                                    <input type="number" class="form-control" name="smoking_packs_per_day" placeholder="pack per days">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Past ear disease -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Past ear disease / Ear infection, discharge:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_ear_disease" value="YES" id="ear_disease_yes">
                                    <label class="form-check-label" for="ear_disease_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_ear_disease" value="NO" id="ear_disease_no">
                                    <label class="form-check-label" for="ear_disease_no">NO</label>
                                </div>
                            </div>
                            <div class="row" id="past_ear_disease_specify_container" style="display: none;">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="past_ear_disease_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Past head injury -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Past head injury/accident/surgery:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_head_injury" value="YES" id="head_injury_yes">
                                    <label class="form-check-label" for="head_injury_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_head_injury" value="NO" id="head_injury_no">
                                    <label class="form-check-label" for="head_injury_no">NO</label>
                                </div>
                            </div>
                            <div class="row" id="past_head_injury_specify_container" style="display: none;">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="past_head_injury_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Past medical history -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Past medical history:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_medical_history" value="YES" id="medical_history_yes">
                                    <label class="form-check-label" for="medical_history_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="past_medical_history" value="NO" id="medical_history_no">
                                    <label class="form-check-label" for="medical_history_no">NO</label>
                                </div>
                            </div>
                            <div class="row" id="past_medical_history_specify_container" style="display: none;">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="past_medical_history_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ototoxic medications -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Ototoxic medications/chemical exposure:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ototoxic_medications" value="YES" id="ototoxic_yes">
                                    <label class="form-check-label" for="ototoxic_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ototoxic_medications" value="NO" id="ototoxic_no">
                                    <label class="form-check-label" for="ototoxic_no">NO</label>
                                </div>
                            </div>
                            <div class="row" id="ototoxic_medications_specify_container" style="display: none;">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="ototoxic_medications_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hobbies -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label">Hobbies with noise exposure and significance:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_diving" id="hobbies_diving">
                                        <label class="form-check-label" for="hobbies_diving">Diving</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_loud_music" id="hobbies_loud_music">
                                        <label class="form-check-label" for="hobbies_loud_music">Loud music</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_musical_instrument" id="hobbies_musical_instrument">
                                        <label class="form-check-label" for="hobbies_musical_instrument">Musical instrument</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_karaoke" id="hobbies_karaoke">
                                        <label class="form-check-label" for="hobbies_karaoke">Karaoke</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_shooting" id="hobbies_shooting">
                                        <label class="form-check-label" for="hobbies_shooting">Shooting</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hobbies_others" id="hobbies_others">
                                        <label class="form-check-label" for="hobbies_others">Others</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal hearing protectors -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label">Use of personal hearing protectors (PHP):</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="php_ear_plug" id="php_ear_plug">
                                        <label class="form-check-label" for="php_ear_plug">Ear plug</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="php_earmuff" id="php_earmuff">
                                        <label class="form-check-label" for="php_earmuff">Earmuff</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="php_combination" id="php_combination">
                                        <label class="form-check-label" for="php_combination">Combination</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="php_none" id="php_none">
                                        <label class="form-check-label" for="php_none">None</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PART C: PHYSICAL EXAMINATION -->
            <div class="form-section">
                <h4 class="section-title">PART C: PHYSICAL EXAMINATION</h4>
                
                <!-- External ear -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">External ear:</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="external_ear_normal" value="1" id="external_ear_normal">
                                    <label class="form-check-label" for="external_ear_normal">Normal</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="external_ear_abnormal" value="1" id="external_ear_abnormal">
                                    <label class="form-check-label" for="external_ear_abnormal">Abnormal</label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="external_ear_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle ear -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Middle ear (otoscopy):</label>
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="middle_ear_normal" value="1" id="middle_ear_normal">
                                    <label class="form-check-label" for="middle_ear_normal">Normal</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="middle_ear_abnormal" value="1" id="middle_ear_abnormal">
                                    <label class="form-check-label" for="middle_ear_abnormal">Abnormal</label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="text" class="form-control" name="middle_ear_specify" placeholder="Please specify">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tuning fork test -->
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label">Tuning fork test:</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>WEBER:</strong>
                                    <div class="mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="weber_centralization" id="weber_centralization">
                                            <label class="form-check-label" for="weber_centralization">Centralization: (Midline)</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="weber_lateralization_left" id="weber_lateralization_left">
                                            <label class="form-check-label" for="weber_lateralization_left">Lateralization: To Left Ear</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="weber_lateralization_right" id="weber_lateralization_right">
                                            <label class="form-check-label" for="weber_lateralization_right">To Right Ear</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <strong>RINNER:</strong>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Right ear:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rinne_right_positive" value="1" id="rinne_right_positive">
                                                <label class="form-check-label" for="rinne_right_positive">Positive</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rinne_right_negative" value="1" id="rinne_right_negative">
                                                <label class="form-check-label" for="rinne_right_negative">Negative</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Left ear:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rinne_left_positive" value="1" id="rinne_left_positive">
                                                <label class="form-check-label" for="rinne_left_positive">Positive</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rinne_left_negative" value="1" id="rinne_left_negative">
                                                <label class="form-check-label" for="rinne_left_negative">Negative</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Impression -->
                <div class="mb-4">
                    <label class="form-label">Impression:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="impression_conductive" id="impression_conductive">
                        <label class="form-check-label" for="impression_conductive">Conductive Hearing Loss</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="impression_sensorineural" id="impression_sensorineural">
                        <label class="form-check-label" for="impression_sensorineural">Sensorineural Hearing loss.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="impression_mixed" id="impression_mixed">
                        <label class="form-check-label" for="impression_mixed">Mixed Hearing loss.</label>
                    </div>
                </div>
            </div>

            <!-- PART D: CONCLUSION -->
            <div class="form-section">
                <h4 class="section-title">PART D: CONCLUSION</h4>
                <p class="mb-3"><strong>Conclusion:</strong> [Please refer to current and baseline Audiometric Report and Questionnaire Form for Audiometric Testing]</p>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="conclusion_occupational_hearing_impairment" id="conclusion_occupational_hearing_impairment">
                    <label class="form-check-label" for="conclusion_occupational_hearing_impairment">Occupational Hearing Impairment</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="conclusion_occupational_permanent_standard_threshold_shift" id="conclusion_occupational_permanent_standard_threshold_shift">
                    <label class="form-check-label" for="conclusion_occupational_permanent_standard_threshold_shift">Occupational Permanent Standard Threshold Shift</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="conclusion_occupational_noise_induced_hearing_loss" id="conclusion_occupational_noise_induced_hearing_loss">
                    <label class="form-check-label" for="conclusion_occupational_noise_induced_hearing_loss">Occupational Noise-Induced Hearing Loss</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="conclusion_age_related_hearing_loss" id="conclusion_age_related_hearing_loss">
                    <label class="form-check-label" for="conclusion_age_related_hearing_loss">Age-related Hearing Loss (Presbycusis)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="conclusion_others" id="conclusion_others">
                    <label class="form-check-label" for="conclusion_others">Others:</label>
                </div>
                <div class="mt-2 mb-3">
                    <input type="text" class="form-control" name="conclusion_others_specify" placeholder="Specify">
                </div>
            </div>

            <!-- PART E: RECOMMENDATION & REMARKS -->
            <div class="form-section">
                <h4 class="section-title">PART E: RECOMMENDATION & REMARKS</h4>
                
                <!-- Recommendation Section -->
                <div class="mb-4">
                    <label class="form-label"><strong>Recommendation:</strong></label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_repeat_audiometry" id="recommendation_repeat_audiometry">
                                <label class="form-check-label" for="recommendation_repeat_audiometry">Repeat audiometry after treatment.</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_continue_annual_audiometry" id="recommendation_continue_annual_audiometry">
                                <label class="form-check-label" for="recommendation_continue_annual_audiometry">Continue annual audiometry Education & training.</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_provision_php" id="recommendation_provision_php">
                                <label class="form-check-label" for="recommendation_provision_php">Provision of PHP</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_referral_specialist" id="recommendation_referral_specialist">
                                <label class="form-check-label" for="recommendation_referral_specialist">Referral to specialist for further management</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_notification_dosh" id="recommendation_notification_dosh">
                                <label class="form-check-label" for="recommendation_notification_dosh">Notification of DOSH</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recommendation_others" id="recommendation_others">
                                <label class="form-check-label" for="recommendation_others">Others:</label>
                            </div>
                            <div class="mt-2">
                                <input type="text" class="form-control" name="recommendation_others_specify" placeholder="Specify">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Remarks Section -->
                <div class="mb-3">
                    <label class="form-label"><strong>Remarks:</strong></label>
                    <textarea class="form-control" name="remarks" rows="4" placeholder="Enter remarks..."></textarea>
                </div>
            </div>

            <!-- ACKNOWLEDGMENT -->
            <div class="acknowledgment-section">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Name, signature & stamp OHD:</label>
                            <?php if (!empty($ohd_full_name) || !empty($ohd_signature_path)): ?>
                                <div class="ohd-display-section" style="margin-top: 15px;">
                                    <?php if (!empty($ohd_signature_path) && file_exists($ohd_signature_path)): ?>
                                        <div class="mb-3" style="text-align: center;">
                                            <img src="<?php echo htmlspecialchars($ohd_signature_path); ?>" alt="OHD Signature" style="max-height: 80px; max-width: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($ohd_full_name)): ?>
                                        <?php 
                                        // Remove "Dr." or "Dr" prefix from name and convert to uppercase
                                        $display_name = preg_replace('/^Dr\.?\s*/i', '', $ohd_full_name);
                                        $display_name = strtoupper(trim($display_name));
                                        ?>
                                        <div style="text-align: center; font-weight: 500; font-size: 16px; letter-spacing: 1px;">
                                            <?php echo htmlspecialchars($display_name); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($ohd_full_name) && empty($ohd_signature_path)): ?>
                                        <div class="text-muted">No OHD information found for current user.</div>
                                    <?php endif; ?>
                                </div>
                                <input type="hidden" name="ohd_name_signature_stamp" value="<?php echo htmlspecialchars($ohd_full_name . (!empty($ohd_signature_path) ? ' | Signature: ' . basename($ohd_signature_path) : '')); ?>">
                            <?php else: ?>
                                <input type="text" class="form-control" name="ohd_name_signature_stamp" placeholder="Enter OHD details">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="acknowledgment-text">
                            <p>I acknowledge that the doctor attending me has explained the results of above examination and their implication & hereby authorize the doctor to disclose the information in third form to my employer/representative & DOSH if necessary.</p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><strong>Employee Signature (Tandatangan Pekerja):</strong></label>
                            <div class="signature-pad" style="border: 2px solid #ddd; border-radius: 8px; background: white; margin: 10px 0;">
                                <canvas id="employee-signature-pad" width="400" height="150" style="border:1px solid #ccc; width: 100%; height: 150px; border-radius: 6px;"></canvas>
                            </div>
                            <div class="signature-controls" style="margin-top: 10px;">
                                <button type="button" id="clear-employee" class="btn btn-outline-secondary btn-sm">Clear</button>
                                <button type="button" id="save-employee" class="btn btn-outline-primary btn-sm">Save Signature</button>
                            </div>
                            <input type="hidden" id="employee_signature_data" name="employee_signature" value="">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name:</label>
                                    <div class="form-control-plaintext" style="padding: 0.5rem 0; font-size: 1rem; min-height: 2.5rem; display: flex; align-items: center; color: #495057;">
                                        <?php echo htmlspecialchars($prefilled_patient_name); ?>
                                    </div>
                                    <input type="hidden" name="employee_name" value="<?php echo htmlspecialchars($prefilled_patient_name); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">IC/passport no:</label>
                                    <div class="form-control-plaintext" style="padding: 0.5rem 0; font-size: 1rem; min-height: 2.5rem; display: flex; align-items: center; color: #495057;">
                                        <?php echo htmlspecialchars($patient_data['NRIC'] ?? ($patient_data['passport_no'] ?? '-')); ?>
                                    </div>
                                    <input type="hidden" name="employee_ic_passport" value="<?php echo htmlspecialchars($patient_data['NRIC'] ?? ($patient_data['passport_no'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date:</label>
                                    <div class="form-control-plaintext" style="padding: 0.5rem 0; font-size: 1rem; min-height: 2.5rem; display: flex; align-items: center; color: #495057;">
                                        <?php echo date('d/m/Y'); ?>
                                    </div>
                                    <input type="hidden" name="employee_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center mt-4">
               
                <button type="button" class="btn btn-outline-primary btn-lg me-3" onclick="window.location.href='audiometric_summary.php?patient_id=<?php echo $patient_id; ?>&surveillance_id=<?php echo $surveillance_id; ?>'">
                    <i class="fas fa-arrow-left"></i> Back: Summary
                </button>
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Save Report
                </button>
                
            </div>
        </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
        // Initialize employee signature pad
        const employeeCanvas = document.getElementById('employee-signature-pad');
        const employeeSignaturePad = new SignaturePad(employeeCanvas);
        
        // Configure signature pad
        employeeSignaturePad.penColor = '#000000';
        
        // Employee signature controls
        document.getElementById('clear-employee').addEventListener('click', () => {
            employeeSignaturePad.clear();
            document.getElementById('employee_signature_data').value = '';
        });
        
        document.getElementById('save-employee').addEventListener('click', () => {
            if (employeeSignaturePad.isEmpty()) {
                alert('Please sign before saving');
                return;
            }
            const signatureData = employeeSignaturePad.toDataURL();
            document.getElementById('employee_signature_data').value = signatureData;
            alert('Employee signature saved!');
        });
        
        // Form submission validation - check if employee signature is provided
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const employeeSignature = document.getElementById('employee_signature_data').value;
            
            if (!employeeSignature) {
                e.preventDefault();
                alert('Please provide employee signature before saving');
                return false;
            }
        });
        
        // Show/hide specify fields based on YES/NO radio button selection
        function toggleSpecifyField(yesRadioId, noRadioId, containerId) {
            const yesRadio = document.getElementById(yesRadioId);
            const noRadio = document.getElementById(noRadioId);
            const container = document.getElementById(containerId);
            
            if (yesRadio && noRadio && container) {
                const updateVisibility = () => {
                    if (yesRadio.checked) {
                        container.style.display = 'block';
                    } else if (noRadio.checked) {
                        container.style.display = 'none';
                        // Clear the input when hidden
                        const input = container.querySelector('input');
                        if (input) input.value = '';
                    }
                };
                
                yesRadio.addEventListener('change', updateVisibility);
                noRadio.addEventListener('change', updateVisibility);
                
                // Check initial state
                updateVisibility();
            }
        }
        
        // Initialize all specify field toggles
        document.addEventListener('DOMContentLoaded', function() {
            toggleSpecifyField('smoking_yes', 'smoking_no', 'smoking_specify_container');
            toggleSpecifyField('ear_disease_yes', 'ear_disease_no', 'past_ear_disease_specify_container');
            toggleSpecifyField('head_injury_yes', 'head_injury_no', 'past_head_injury_specify_container');
            toggleSpecifyField('medical_history_yes', 'medical_history_no', 'past_medical_history_specify_container');
            toggleSpecifyField('ototoxic_yes', 'ototoxic_no', 'ototoxic_medications_specify_container');
        });
    </script>
</body>
</html>
