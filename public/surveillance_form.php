<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('login.php'));
    exit();
}

$message = '';
$messageType = '';
$editMode = false;
$editData = null;

// Check if loaded in iframe
$is_iframe = isset($_GET['iframe']) && $_GET['iframe'] == '1';

// Check if surveillance_id is provided for viewing existing data
$surveillance_id = isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0;
$existing_surveillance_data = null;

// Check for preserved patient data from declaration tab
$preserved_patient_name = isset($_GET['preserve_patient_name']) ? $_GET['preserve_patient_name'] : '';
$preserved_employer = isset($_GET['preserve_employer']) ? $_GET['preserve_employer'] : '';

// Legacy edit mode removed; all editing now handled via dedicated views
// Determine examiner name based on logged-in user
$session_first = trim($_SESSION['first_name'] ?? '');
$session_last = trim($_SESSION['last_name'] ?? '');
$examiner_name = trim($session_first . ' ' . $session_last);
if ($examiner_name === '') {
    $examiner_name = $_SESSION['username'] ?? 'System User';
}

if ($_SESSION['role'] === 'Doctor') {
    try {
        $examiner_data = getLoggedInUserMedicalStaffInfo();
        if ($examiner_data) {
            $examiner_candidate = trim(($examiner_data['first_name'] ?? '') . ' ' . ($examiner_data['last_name'] ?? ''));
            if ($examiner_candidate !== '') {
                $examiner_name = $examiner_candidate;
            }
        }
    } catch (Exception $e) {
        // Keep session-based fallback if lookup fails
    }
}

$display_examiner_name = $examiner_name;

if ($editMode && $editData) {
    if (isset($editData['examiner_name']) && $editData['examiner_name'] !== '') {
        $display_examiner_name = $editData['examiner_name'];
    } elseif (isset($editData['surveillance']['examiner_name']) && $editData['surveillance']['examiner_name'] !== '') {
        $display_examiner_name = $editData['surveillance']['examiner_name'];
    } elseif (isset($existing_surveillance_data['examiner_name']) && $existing_surveillance_data['examiner_name'] !== '') {
        $display_examiner_name = $existing_surveillance_data['examiner_name'];
    }
}

// Handle pre-population when patient_id is provided
$prePopulateData = null;
if (isset($_GET['patient_id']) && !$editMode) {
    $patientId = $_GET['patient_id'];
    try {
        // Get patient information and company details
        $stmt = $clinic_pdo->prepare("
            SELECT pi.id, pi.patient_id, pi.first_name, pi.last_name, 
                   oh.company_name, oh.job_title
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE pi.id = ?
        ");
        $stmt->execute([$patientId]);
        $prePopulateData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If there's an error, just continue without pre-population
        $prePopulateData = null;
    }
} elseif (isset($_GET['preserve_patient_name']) && !empty($_GET['preserve_patient_name']) && !$editMode) {
    // Fallback: try to find patient by name if patient_id is not provided
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT pi.id, pi.patient_id, pi.first_name, pi.last_name, 
                   oh.company_name, oh.job_title
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE CONCAT(pi.first_name, ' ', pi.last_name) = ?
            LIMIT 1
        ");
        $stmt->execute([$_GET['preserve_patient_name']]);
        $prePopulateData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If there's an error, just continue without pre-population
        $prePopulateData = null;
    }
}

// Get all companies for dropdown (only when not in edit mode and no pre-populated data)
$all_companies = [];
if (!$editMode && !$prePopulateData) {
    try {
        $stmt = $clinic_pdo->query("
            SELECT id, company_name 
            FROM company 
            ORDER BY company_name
        ");
        $all_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Handle messages from URL parameters (after redirect)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && ($_POST['action'] == 'save_surveillance' || $_POST['action'] == 'submit_surveillance')) {
        // Debug: Log form submission
        error_log("Surveillance form submitted for patient_id: " . ($_POST['patient_id'] ?? 'unknown'));
        
        try {
            // Prepare recommendations type first
            $recommendations_type = '';
            if (isset($_POST['recommendations_type']) && is_array($_POST['recommendations_type'])) {
                $recommendations_type = implode(', ', $_POST['recommendations_type']);
            }
            
            // Get patient_id from form or prePopulateData
            $patient_id_for_save = $_POST['patient_id'] ?? '';
            if (empty($patient_id_for_save) && $prePopulateData && isset($prePopulateData['id'])) {
                $patient_id_for_save = $prePopulateData['id'];
            }
            
            // Cast to integer to ensure proper type
            $patient_id_for_save = (int)$patient_id_for_save;
            
            // Validate patient_id
            if (empty($patient_id_for_save) || $patient_id_for_save <= 0) {
                throw new Exception('Invalid patient ID. Please ensure patient is properly selected.');
            }
            
            // Prepare health surveillance data for new clinic database
            // Handle chemical selection - if "Other" is selected, use the other field value
            $chemical = sanitizeInput($_POST['chemical'] ?? '');
            if ($chemical === 'Other' && !empty($_POST['chemical_other'])) {
                $chemical = sanitizeInput($_POST['chemical_other']);
            }
            
            $healthData = [
                'patient_id' => $patient_id_for_save, // Don't sanitize integers
                'workplace' => sanitizeInput($_POST['workplace']),
                'chemical' => $chemical,
                'examination_type' => sanitizeInput($_POST['examination_type']),
                'examination_date' => $_POST['examination_date'],
                'examiner_name' => sanitizeInput($_POST['examiner_name']),
                'final_assessment' => $_POST['fitness_status'] ?? null,
                // Additional fields required by addHealthSurveillance function
                'history_of_health' => $_POST['history_of_health'] ?? 'No',
                'clinical_findings' => $_POST['has_clinical_findings'] ?? 'No',
                'target_organ' => $_POST['target_organ'] ?? 'No',
                'biological_monitoring' => $_POST['biological_monitoring'] ?? 'No',
                'pregnancy_breast_feeding' => $_POST['pregnancy_breast_feeding'] ?? 'No',
                'clinical_work_related' => $_POST['clinical_work_related'] ?? 'No',
                'organ_work_related' => $_POST['organ_work_related'] ?? 'No',
                'biological_work_related' => $_POST['biological_work_related'] ?? 'No',
                'recommendations_type' => $recommendations_type,
                'date_of_MRP' => $_POST['date_of_MRP'] ?? null,
                'next_review_date' => $_POST['next_review_date'] ?? null,
                'recommendations_notes' => sanitizeInput($_POST['recommendations_notes'] ?? ''),
                // Handle biological exposure selection - if "Other" is selected, use the other field value
                'biological_exposure' => (isset($_POST['biological_exposure']) && $_POST['biological_exposure'] === 'Other' && !empty($_POST['biological_exposure_other'])) 
                    ? sanitizeInput($_POST['biological_exposure_other']) 
                    : sanitizeInput($_POST['biological_exposure'] ?? ''),
                'result_baseline' => sanitizeInput($_POST['result_baseline'] ?? ''),
                'result_annual' => sanitizeInput($_POST['result_annual'] ?? ''),
                'respirator_result' => $_POST['respirator_result'] ?? 'Fit',
                'respirator_justification' => sanitizeInput($_POST['respirator_justification'] ?? ''),
                // History of health symptoms
                'breathing_difficulty' => $_POST['breathing_difficulty'] ?? 'No',
                'cough' => $_POST['cough'] ?? 'No',
                'sore_throat' => $_POST['sore_throat'] ?? 'No',
                'sneezing' => $_POST['sneezing'] ?? 'No',
                'chest_pain' => $_POST['chest_pain'] ?? 'No',
                'palpitation' => $_POST['palpitation'] ?? 'No',
                'limb_oedema' => $_POST['limb_oedema'] ?? 'No',
                'drowsiness' => $_POST['drowsiness'] ?? 'No',
                'dizziness' => $_POST['dizziness'] ?? 'No',
                'headache' => $_POST['headache'] ?? 'No',
                'confusion' => $_POST['confusion'] ?? 'No',
                'lethargy' => $_POST['lethargy'] ?? 'No',
                'nausea' => $_POST['nausea'] ?? 'No',
                'vomiting' => $_POST['vomiting'] ?? 'No',
                'eye_irritations' => $_POST['eye_irritations'] ?? 'No',
                'blurred_vision' => $_POST['blurred_vision'] ?? 'No',
                'blisters' => $_POST['blisters'] ?? 'No',
                'burns' => $_POST['burns'] ?? 'No',
                'itching' => $_POST['itching'] ?? 'No',
                'rash' => $_POST['rash'] ?? 'No',
                'redness' => $_POST['redness'] ?? 'No',
                'abdominal_pain' => $_POST['abdominal_pain'] ?? 'No',
                'abdominal_mass' => $_POST['abdominal_mass'] ?? 'No',
                'jaundice' => $_POST['jaundice'] ?? 'No',
                'diarrhoea' => $_POST['diarrhoea'] ?? 'No',
                'loss_of_weight' => $_POST['loss_of_weight'] ?? 'No',
                'loss_of_appetite' => $_POST['loss_of_appetite'] ?? 'No',
                'dysuria' => $_POST['dysuria'] ?? 'No',
                'haematuria' => $_POST['haematuria'] ?? 'No',
                'others_symptoms' => sanitizeInput($_POST['others_symptoms'] ?? ''),
                // Clinical findings
                'has_clinical_findings' => $_POST['has_clinical_findings'] ?? 'No',
                'clinical_elaboration' => sanitizeInput($_POST['clinical_elaboration'] ?? ''),
                // Physical examination
                'weight' => $_POST['weight'] ?? null,
                'height' => $_POST['height'] ?? null,
                'BMI' => $_POST['bmi'] ?? null,
                'blood_pressure_systolic' => sanitizeInput($_POST['blood_pressure_systolic'] ?? ''),
                'blood_pressure_diastolic' => sanitizeInput($_POST['blood_pressure_diastolic'] ?? ''),
                'pulse_rate' => $_POST['pulse_rate'] ?? null,
                'respiratory_rate' => $_POST['respiratory_rate'] ?? null,
                'general_appearance' => $_POST['general_appearance'] ?? 'Normal',
                's1_s2' => $_POST['s1_s2'] ?? 'No',
                'murmur' => $_POST['murmur'] ?? 'No',
                'ear_nose_throat' => $_POST['ear_nose_throat'] ?? 'Normal',
                'visual_acuity_left' => sanitizeInput($_POST['visual_acuity_left'] ?? ''),
                'visual_acuity_right' => sanitizeInput($_POST['visual_acuity_right'] ?? ''),
                'colour_blindness' => $_POST['colour_blindness'] ?? 'No',
                'tenderness' => $_POST['tenderness'] ?? 'No',
                'abdominal_mass' => $_POST['abdominal_mass'] ?? 'No',
                'lymph_nodes' => $_POST['lymph_nodes'] ?? 'Non-palpable',
                'splenomegaly' => $_POST['splenomegaly'] ?? 'No',
                'ballottable' => $_POST['ballottable'] ?? 'No',
                'jaundice' => $_POST['jaundice'] ?? 'No',
                'hepatomegaly' => $_POST['hepatomegaly'] ?? 'No',
                'muscle_tone' => $_POST['muscle_tone'] ?? '3',
                'muscle_tenderness' => $_POST['muscle_tenderness'] ?? 'No',
                'power' => $_POST['power'] ?? '3',
                'sensation' => $_POST['sensation'] ?? 'Normal',
                'sound' => $_POST['sound'] ?? 'Clear',
                'air_entry' => $_POST['air_entry'] ?? 'Normal',
                'reproductive' => $_POST['reproductive'] ?? 'Normal',
                'skin' => $_POST['skin'] ?? 'Normal',
                'respiratory_findings' => sanitizeInput($_POST['respiratory_findings'] ?? ''),
                'ent' => $_POST['ent'] ?? 'Normal',
                'gi_tenderness' => $_POST['gi_tenderness'] ?? 'No',
                'abdominal_mass_exam' => $_POST['abdominal_mass_exam'] ?? 'No',
                'kidney_tenderness' => $_POST['kidney_tenderness'] ?? 'No',
                'ballotable' => $_POST['ballotable'] ?? 'No',
                'liver_jaundice' => $_POST['liver_jaundice'] ?? 'No',
                'others_exam' => sanitizeInput($_POST['others_exam'] ?? ''),
                // Conclusion of MS Findings
                'history_of_health' => $_POST['history_of_health'] ?? 'No',
                'clinical_findings' => $_POST['has_clinical_findings'] ?? 'No',
                'target_organ' => $_POST['target_organ'] ?? 'No',
                'biological_monitoring' => $_POST['biological_monitoring'] ?? 'No',
                'pregnancy_breast_feeding' => $_POST['pregnancy_breast_feeding'] ?? 'No',
                'clinical_work_related' => $_POST['clinical_work_related'] ?? 'No',
                'organ_work_related' => $_POST['organ_work_related'] ?? 'No',
                'biological_work_related' => $_POST['biological_work_related'] ?? 'No',
                'fitness_status' => $_POST['fitness_status'] ?? 'Not specified'
            ];
            
            // Save health surveillance data using new function
            error_log("surveillance_form.php: Attempting to save surveillance data for patient_id: " . $healthData['patient_id']);
            error_log("surveillance_form.php: Health data keys: " . implode(', ', array_keys($healthData)));
            error_log("surveillance_form.php: Workplace: " . ($healthData['workplace'] ?? 'NULL'));
            error_log("surveillance_form.php: Chemical: " . ($healthData['chemical'] ?? 'NULL'));
            error_log("surveillance_form.php: Examination date: " . ($healthData['examination_date'] ?? 'NULL'));
            
            $result = addHealthSurveillance($healthData);
            
            error_log("surveillance_form.php: Save result - success: " . ($result['success'] ? 'YES' : 'NO') . ", surveillance_id: " . ($result['surveillance_id'] ?? 'N/A') . ", message: " . ($result['message'] ?? 'N/A'));
            
            // Verify the data was actually saved
            if ($result['success'] && isset($result['surveillance_id'])) {
                $verify_stmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM chemical_information WHERE surveillance_id = ? AND patient_id = ?");
                $verify_stmt->execute([$result['surveillance_id'], $healthData['patient_id']]);
                $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                error_log("surveillance_form.php: Verification - Found " . $verify_result['count'] . " record(s) in database for surveillance_id: " . $result['surveillance_id']);
            }
            
            if ($result['success']) {
                // Get the patient ID for additional tables
                $patient_id = $healthData['patient_id'];
                $new_surveillance_id = $result['surveillance_id'];
                
                error_log("surveillance_form.php: Main surveillance saved successfully. surveillance_id: " . $new_surveillance_id . ", patient_id: " . $patient_id);
                
                // Save Target Organ data
                $targetOrganData = [
                    'patient_id' => $patient_id,
                    'surveillance_id' => $new_surveillance_id,
                    'blood_count' => $_POST['blood_count'] ?? 'Normal',
                    'renal_function' => $_POST['renal_function'] ?? 'Normal',
                    'liver_function' => $_POST['liver_function'] ?? 'Normal',
                    'chest_xray' => $_POST['chest_xray'] ?? 'Normal',
                    'spirometry_fev1' => !empty($_POST['spirometry_fev1']) ? (float)$_POST['spirometry_fev1'] : null,
                    'spirometry_fvc2' => !empty($_POST['spirometry_fvc2']) ? (float)$_POST['spirometry_fvc2'] : null,
                    'spirometry_fev_fvc' => !empty($_POST['spirometry_fev_fvc']) ? (float)$_POST['spirometry_fev_fvc'] : null,
                    'blood_comment' => sanitizeInput($_POST['blood_comment'] ?? ''),
                    'renal_comment' => sanitizeInput($_POST['renal_comment'] ?? ''),
                    'liver_comment' => sanitizeInput($_POST['liver_comment'] ?? ''),
                    'xray_comment' => sanitizeInput($_POST['xray_comment'] ?? ''),
                    'spirometry_comment' => sanitizeInput($_POST['spirometry_comment'] ?? '')
                ];
                
                // Save Biological Monitoring data
                // Handle biological exposure selection - if "Other" is selected, use the other field value
                $biological_exposure = sanitizeInput($_POST['biological_exposure'] ?? '');
                if ($biological_exposure === 'Other' && !empty($_POST['biological_exposure_other'])) {
                    $biological_exposure = sanitizeInput($_POST['biological_exposure_other']);
                }
                
                $biologicalData = [
                    'patient_id' => $patient_id,
                    'surveillance_id' => $new_surveillance_id,
                    'biological_exposure' => $biological_exposure,
                    'result_baseline' => sanitizeInput($_POST['result_baseline'] ?? ''),
                    'result_annual' => sanitizeInput($_POST['result_annual'] ?? '')
                ];
                
                // Save Fitness Respirator data
                $fitnessData = [
                    'patient_id' => $patient_id,
                    'result' => $_POST['respirator_result'] ?? 'Fit',
                    'justification' => sanitizeInput($_POST['respirator_justification'] ?? '')
                ];
                
                // Save Conclusion MS Finding data
                $conclusionData = [
                    'patient_id' => $patient_id,
                    'surveillance_id' => $new_surveillance_id,
                    'history_of_health' => $_POST['history_of_health'] ?? 'No',
                    'clinical_findings' => $_POST['has_clinical_findings'] ?? 'No',
                    'target_organ' => $_POST['target_organ'] ?? 'No',
                    'biological_monitoring' => $_POST['biological_monitoring'] ?? 'No',
                    'pregnancy_breast_feeding' => $_POST['pregnancy_breast_feeding'] ?? 'No',
                    'clinical_work_related' => $_POST['clinical_work_related'] ?? 'No',
                    'organ_work_related' => $_POST['organ_work_related'] ?? 'No',
                    'biological_work_related' => $_POST['biological_work_related'] ?? 'No'
                ];
                
                // Save Recommendations data
                $recommendationsData = [
                    'patient_id' => $patient_id,
                    'surveillance_id' => $new_surveillance_id,
                    'recommendations_type' => sanitizeInput($recommendations_type),
                    'date_of_MRP' => $_POST['date_of_MRP'] ?? null,
                    'next_review_date' => $_POST['next_review_date'] ?? null,
                    'notes' => sanitizeInput($_POST['recommendations_notes'] ?? '')
                ];
                
                // Debug: Log save result
                error_log("Save result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . " - " . ($result['message'] ?? 'No message'));
                error_log("Surveillance ID: " . $new_surveillance_id . ", Patient ID: " . $patient_id);
                
                if ($result['success']) {
                    // Save all additional data
                    $additionalDataErrors = [];
                    try {
                        $targetResult = saveTargetOrganData($targetOrganData);
                        if (!$targetResult['success']) {
                            $additionalDataErrors[] = "Target Organ: " . ($targetResult['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $additionalDataErrors[] = "Target Organ: " . $e->getMessage();
                        error_log("Error saving Target Organ data: " . $e->getMessage());
                    }
                    
                    try {
                        $bioResult = saveBiologicalMonitoringData($biologicalData);
                        if (!$bioResult['success']) {
                            $additionalDataErrors[] = "Biological Monitoring: " . ($bioResult['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $additionalDataErrors[] = "Biological Monitoring: " . $e->getMessage();
                        error_log("Error saving Biological Monitoring data: " . $e->getMessage());
                    }
                    
                    try {
                        $fitnessResult = saveFitnessRespiratorData($fitnessData);
                        if (!$fitnessResult['success']) {
                            $additionalDataErrors[] = "Fitness Respirator: " . ($fitnessResult['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $additionalDataErrors[] = "Fitness Respirator: " . $e->getMessage();
                        error_log("Error saving Fitness Respirator data: " . $e->getMessage());
                    }
                    
                    try {
                        $conclusionResult = saveConclusionMSFindingData($conclusionData);
                        if (!$conclusionResult['success']) {
                            $additionalDataErrors[] = "Conclusion MS Finding: " . ($conclusionResult['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $additionalDataErrors[] = "Conclusion MS Finding: " . $e->getMessage();
                        error_log("Error saving Conclusion MS Finding data: " . $e->getMessage());
                    }
                    
                    try {
                        $recResult = saveRecommendationsData($recommendationsData);
                        if (!$recResult['success']) {
                            $additionalDataErrors[] = "Recommendations: " . ($recResult['message'] ?? 'Unknown error');
                        }
                    } catch (Exception $e) {
                        $additionalDataErrors[] = "Recommendations: " . $e->getMessage();
                        error_log("Error saving Recommendations data: " . $e->getMessage());
                    }
                    
                    if (!empty($additionalDataErrors)) {
                        error_log("Warning: Some additional data failed to save: " . implode("; ", $additionalDataErrors));
                    } else {
                        error_log("All additional data saved successfully");
                    }
                    
                    // Redirect to surveillance_list.php with success message
                    $patient_id = $healthData['patient_id'];
                    
                    error_log("Starting redirect process for patient_id: " . $patient_id);
                    error_log("Patient ID validation passed: " . ($patient_id > 0 ? 'YES' : 'NO'));
                    
                    // Get company_id from patient's occupational history
                    try {
                        $stmt = $clinic_pdo->prepare("
                            SELECT c.id as company_id 
                            FROM company c
                            INNER JOIN occupational_history oh ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(oh.company_name))
                            WHERE oh.patient_id = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$patient_id]);
                        $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        $company_id = $company_data ? $company_data['company_id'] : 1;
                        
                        error_log("Company ID found: " . $company_id);
                        
                    } catch (Exception $e) {
                        $company_id = 1; // Default fallback
                        error_log("Error getting company_id: " . $e->getMessage());
                    }
                    
                    // Additional fallback: try to get company_id from POST or URL if available
                    if ($company_id <= 0 && isset($_POST['company_id'])) {
                        $company_id = (int)$_POST['company_id'];
                        error_log("Using company_id from POST: " . $company_id);
                    }
                    if ($company_id <= 0 && isset($_GET['company_id'])) {
                        $company_id = (int)$_GET['company_id'];
                        error_log("Using company_id from URL: " . $company_id);
                    }
                    
                    // Ensure we have valid IDs for redirect - don't use default 1, try harder to find it
                    if ($company_id <= 0) {
                        // Try one more time to get company_id from patient's occupational history
                        try {
                            $stmt = $clinic_pdo->prepare("
                                SELECT c.id as company_id 
                                FROM company c
                                INNER JOIN occupational_history oh ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(oh.company_name))
                                WHERE oh.patient_id = ?
                                LIMIT 1
                            ");
                            $stmt->execute([$patient_id]);
                            $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($company_data && $company_data['company_id'] > 0) {
                                $company_id = $company_data['company_id'];
                                error_log("Found company_id from second attempt: " . $company_id);
                            }
                        } catch (Exception $e) {
                            error_log("Error in second attempt to get company_id: " . $e->getMessage());
                        }
                    }
                    if ($patient_id <= 0) {
                        error_log("ERROR: Invalid patient_id for redirect: " . $patient_id);
                        $message = 'Error: Invalid patient ID. Cannot redirect to surveillance list.';
                        $messageType = 'danger';
                    } else {
                        // Check if "Not Fit" was selected in conclusion or recommendation
                        $fitness_status = $_POST['fitness_status'] ?? '';
                        $respirator_result = $_POST['respirator_result'] ?? '';
                        $is_not_fit = false;
                        
                        // Check if fitness status is "Not Fit for Work"
                        if (stripos($fitness_status, 'Not Fit') !== false || $fitness_status === 'Not Fit for Work') {
                            $is_not_fit = true;
                            error_log("Not Fit detected in fitness_status: " . $fitness_status);
                        }
                        
                        // Check if respirator result is "Not Fit"
                        if (stripos($respirator_result, 'Not Fit') !== false || $respirator_result === 'Not Fit') {
                            $is_not_fit = true;
                            error_log("Not Fit detected in respirator_result: " . $respirator_result);
                        }
                        
                        // Redirect to medical removal protection if "Not Fit" was selected
                        if ($is_not_fit && $company_id > 0) {
                            // Get patient name for pre-filling
                            $patient_name = '';
                            try {
                                $patient_stmt = $clinic_pdo->prepare("SELECT first_name, last_name FROM patient_information WHERE id = ?");
                                $patient_stmt->execute([$patient_id]);
                                $patient_info = $patient_stmt->fetch(PDO::FETCH_ASSOC);
                                if ($patient_info) {
                                    $patient_name = $patient_info['first_name'] . ' ' . $patient_info['last_name'];
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching patient name: " . $e->getMessage());
                            }
                            
                            // Get company name for pre-filling
                            $company_name = '';
                            try {
                                $company_stmt = $clinic_pdo->prepare("SELECT company_name FROM company WHERE id = ?");
                                $company_stmt->execute([$company_id]);
                                $company_info = $company_stmt->fetch(PDO::FETCH_ASSOC);
                                if ($company_info) {
                                    $company_name = $company_info['company_name'];
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching company name: " . $e->getMessage());
                            }
                            
                            $redirect_url = "medical_removal_protection.php?patient_id=" . $patient_id . "&patient_name=" . urlencode($patient_name) . "&employer=" . urlencode($company_name) . "&message=" . urlencode("Health surveillance data saved successfully! Please complete the medical removal protection form.") . "&type=success";
                            error_log("Redirecting to medical removal protection: " . $redirect_url);
                        } else {
                            $redirect_url = "surveillance_list.php?company_id=" . $company_id . "&patient_id=" . $patient_id . "&message=" . urlencode("Health surveillance data saved successfully!") . "&type=success";
                            error_log("About to redirect to: " . $redirect_url);
                        }
                        
                        // If this is loaded in an iframe, redirect the parent window directly
                        if ($is_iframe) {
                            // Output redirect HTML for iframe
                            echo "<!DOCTYPE html>
                            <html>
                            <head>
                                <title>Redirecting...</title>
                                <script>
                                    console.log('Starting redirect process...');
                                    console.log('Redirect URL: " . $redirect_url . "');
                                    console.log('Is iframe: true');
                                    
                                    // Use setTimeout to ensure page is loaded before redirect
                                    setTimeout(function() {
                                        try {
                                            if (window.parent && window.parent !== window) {
                                                console.log('Redirecting parent window to: " . $redirect_url . "');
                                                window.parent.location.href = '" . $redirect_url . "';
                                            } else {
                                                console.log('Redirecting current window to: " . $redirect_url . "');
                                                window.location.href = '" . $redirect_url . "';
                                            }
                                        } catch (e) {
                                            console.error('Redirect error: ', e);
                                            // Fallback: try top window
                                            if (window.top) {
                                                window.top.location.href = '" . $redirect_url . "';
                                            }
                                        }
                                    }, 100);
                                </script>
                            </head>
                            <body>
                                <p style='text-align: center; padding: 20px;'>Saving data and redirecting to surveillance list...</p>
                                <p style='text-align: center;'><a href='" . $redirect_url . "'>Click here if not redirected automatically</a></p>
                            </body>
                            </html>";
                            exit();
                        } else {
                            // Direct redirect for non-iframe
                            header("Location: " . url($redirect_url));
                            exit();
                        }
                    }
                } else {
                    // Save failed - don't redirect, show error
                    $message = 'Error saving surveillance record: ' . ($result['message'] ?? 'Unknown error');
                    $messageType = 'danger';
                    error_log("surveillance_form.php: Save failed - " . $message);
                }
            } else {
                // addHealthSurveillance returned false - don't redirect
                $message = 'Error: Failed to save surveillance data. ' . ($result['message'] ?? 'Unknown error');
                $messageType = 'danger';
                error_log("surveillance_form.php: addHealthSurveillance failed - " . ($result['message'] ?? 'Unknown error'));
            }
            
        } catch (Exception $e) {
            // Exception caught - don't redirect, show error
            $message = 'Error saving surveillance record: ' . $e->getMessage();
            $messageType = 'danger';
            error_log("surveillance_form.php: Exception caught - " . $e->getMessage());
            error_log("surveillance_form.php: Exception trace - " . $e->getTraceAsString());
        }
    }
}

// Get patients for dropdown
$patients = getAllClinicPatients();

// Fetch OHD (Occupational Health Doctor) information from database
$ohd_data = null;
$ohd_full_name = '';
$ohd_email = '';
$mmc_no = '';
$dosh_registration_no = '';

// Get logged-in user's information
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id && $_SESSION['role'] === 'Doctor') {
    try {
        // Get user details
        $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_details) {
            // Try to get medical_staff record by matching first_name and last_name
            $stmt = $clinic_pdo->prepare("SELECT * FROM medical_staff WHERE first_name = ? AND last_name = ? LIMIT 1");
            $stmt->execute([$user_details['first_name'], $user_details['last_name']]);
            $ohd_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ohd_data) {
                // Build OHD full name
                $ohd_full_name = trim(($ohd_data['first_name'] ?? '') . ' ' . ($ohd_data['last_name'] ?? ''));
                if (!empty($ohd_data['first_name']) && !empty($ohd_data['last_name'])) {
                    $ohd_full_name = 'DR ' . strtoupper($ohd_data['first_name'] . ' ' . $ohd_data['last_name']);
                }
                $ohd_email = $ohd_data['email'] ?? '';
                $mmc_no = $ohd_data['license_number'] ?? '';
            } else {
                // Fallback: use user details if medical_staff not found
                $ohd_full_name = 'DR ' . strtoupper(trim(($user_details['first_name'] ?? '') . ' ' . ($user_details['last_name'] ?? '')));
                $ohd_email = $user_details['email'] ?? '';
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching OHD data: " . $e->getMessage());
    }
}

// Fetch clinic information from database (clinic_info table)
$clinic_info = null;
$clinic_name = '';
$clinic_phone = '';
$clinic_email = '';
$clinic_fax = '';
$dosh_registration_no = '';

try {
    // Get clinic info - fetch first clinic record
    $stmt = $clinic_pdo->query("SELECT * FROM clinic_info ORDER BY id ASC LIMIT 1");
    $clinic_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($clinic_info) {
        $clinic_name = $clinic_info['clinic_name'] ?? 'KLINIK HAYDAR & KAMAL';
        $clinic_phone = $clinic_info['clinic_phone'] ?? '09-7444451 / 017-7003822';
        $clinic_email = $clinic_info['clinic_email'] ?? 'info@warisankamal.my';
        // Check if clinic_fax column exists (may need to add column via SQL)
        $clinic_fax = array_key_exists('clinic_fax', $clinic_info) ? ($clinic_info['clinic_fax'] ?: '') : '';
        // Check if DOSH registration is stored in clinic_info (may need to add column via SQL)
        if (array_key_exists('dosh_registration_no', $clinic_info)) {
            $dosh_registration_no = $clinic_info['dosh_registration_no'] ?: 'HQ/23/DOC/00/01035';
        } elseif (array_key_exists('dosh_no', $clinic_info)) {
            $dosh_registration_no = $clinic_info['dosh_no'] ?: 'HQ/23/DOC/00/01035';
        } else {
            $dosh_registration_no = 'HQ/23/DOC/00/01035';
        }
    } else {
        // Defaults if clinic info not found
        $clinic_name = 'KLINIK HAYDAR & KAMAL';
        $clinic_phone = '09-7444451 / 017-7003822';
        $clinic_email = 'info@warisankamal.my';
        $clinic_fax = '';
        $dosh_registration_no = 'HQ/23/DOC/00/01035';
    }
} catch (Exception $e) {
    error_log("Error fetching clinic info: " . $e->getMessage());
    // Use defaults
    $clinic_name = 'KLINIK HAYDAR & KAMAL';
    $clinic_phone = '09-7444451 / 017-7003822';
    $clinic_email = 'info@warisankamal.my';
    $clinic_fax = '';
    $dosh_registration_no = 'HQ/23/DOC/00/01035';
}

// Default MMC No. if not found
if (empty($mmc_no)) {
    $mmc_no = '53733';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit Health Surveillance' : 'New Health Surveillance'; ?> - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        .required {
            color: #dc3545;
        }
        .form-section {
            background: #f8fff9;
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
        
        /* Custom dropdown styles - scoped to avoid navigation conflicts */
        .form-section .dropdown {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .form-section .dropbtn {
            background-color: #fff;
            color: #6c757d;
            padding: 12px 15px;
            font-size: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px 12px;
            appearance: none;
        }
        
        .form-section .dropbtn:not(:disabled) {
            color: #212529;
        }
        
        .form-section .dropdown-arrow {
            display: none;
        }
        
        .form-section .dropbtn:hover {
            border-color: #389B5B;
        }
        
        .form-section .dropbtn:focus {
            border-color: #389B5B;
            box-shadow: 0 0 0 0.2rem rgba(56, 155, 91, 0.25);
            outline: none;
        }
        
        .form-section .dropbtn:disabled {
            background-color: #e9ecef;
            opacity: 0.65;
            cursor: not-allowed;
        }
        
        .form-section .dropdown-arrow {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }
        
        .form-section .dropdown-content {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 100%;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .form-section .dropdown-content input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            outline: none;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.3s ease;
        }
        
        .form-section .dropdown-content input:focus {
            border-color: #389B5B;
            box-shadow: 0 0 0 0.2rem rgba(56, 155, 91, 0.25);
            transform: translateY(-2px);
        }
        
        /* Style placeholder to match other form elements */
        .form-section .dropdown-content input::placeholder {
            color: #6c757d;
            opacity: 1;
            font-size: 1rem;
            font-style: normal;
            text-transform: uppercase;
        }
        
        .form-section .dropdown-content input::-webkit-input-placeholder {
            color: #6c757d;
            opacity: 1;
            font-size: 1rem;
            font-style: normal;
            text-transform: uppercase;
        }
        
        .form-section .dropdown-content input::-moz-placeholder {
            color: #6c757d;
            opacity: 1;
            font-size: 1rem;
            font-style: normal;
            text-transform: uppercase;
        }
        
        .form-section .dropdown-content input:-ms-input-placeholder {
            color: #6c757d;
            opacity: 1;
            font-size: 1rem;
            font-style: normal;
            text-transform: uppercase;
        }
        
        .form-section .dropdown-content a {
            color: #212529;
            padding: 0.5rem 1rem;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s ease;
        }
        
        .form-section .dropdown-content a:hover {
            background-color: #e8f5e8;
            color: #389B5B;
        }
        
        .form-section .dropdown-content a.selected {
            background-color: #389B5B;
            color: white;
        }
        
        .form-section .dropdown-content.show {
            display: block;
        }
        
        .form-section .dropdown.show .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        /* Tab Navigation Styles */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
        
        .nav-tabs::-webkit-scrollbar {
            height: 6px;
        }
        
        .nav-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .nav-tabs::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .nav-tabs::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .nav-tabs .nav-item {
            flex-shrink: 0;
            white-space: nowrap;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1rem;
            margin-right: 0.25rem;
            border-radius: 0;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            border-bottom-color: #389B5B;
            color: #389B5B;
            background-color: #f8fff9;
        }
        
        .nav-tabs .nav-link.active {
            color: #389B5B;
            background-color: #f8fff9;
            border-color: transparent;
            border-bottom-color: #389B5B;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.has-error {
            position: relative;
        }
        
        .nav-tabs .nav-link.has-error::after {
            content: '!';
            position: relative;
            display: inline-block;
            margin-left: 6px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            font-weight: 600;
            font-size: 12px;
            vertical-align: middle;
        }
        
        .nav-tabs .nav-link.has-error:hover {
            border-bottom-color: #389B5B;
        }
        
        .nav-tabs .nav-link.has-error.active {
            border-bottom-color: #389B5B;
        }
        
        .fixed-save-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .tab-content {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        /* MS Findings Table Styling */
        .ms-findings-table {
            margin-top: 1rem;
            border-collapse: collapse;
        }
        
        .ms-findings-table th,
        .ms-findings-table td {
            border: 1px solid #dee2e6;
            padding: 12px 8px;
            vertical-align: middle;
        }
        
        .ms-findings-table th {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .ms-findings-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .ms-findings-table .form-check-input {
            margin: 0;
            transform: scale(1.2);
        }
        
        .ms-findings-table .form-check-input:checked {
            background-color: #389B5B;
            border-color: #389B5B;
        }
        
        /* Ensure all select elements match form-control styling */
        #workplace, #patient_id {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        #workplace:focus, #patient_id:focus {
            border-color: #389B5B;
            box-shadow: 0 0 0 0.2rem rgba(56, 155, 91, 0.25);
            transform: translateY(-2px);
        }
        
        /* Auto-capitalization styling */
        input[type="text"]:not([name="email"]), 
        textarea:not([name="email"]) {
            text-transform: uppercase;
        }
        
        /* Keep placeholders in proper case */
        input[type="text"]:not([name="email"])::placeholder,
        textarea:not([name="email"])::placeholder {
            text-transform: none;
        }
        
        /* Ensure email field is not affected */
        input[type="email"] {
            text-transform: none;
        }
        
        /* Info display styling - clean information display without borders */
        .info-display {
            padding: 0.5rem 0;
            font-weight: 600;
            color: #495057;
            background: transparent;
            border: none;
            font-size: 1.25rem;
        }
        
    </style>
</head>
<body>
    <?php if (!$is_iframe): ?>
        <?php include __DIR__ . '/includes/navigation.php'; ?>
    <?php endif; ?>

    <?php if ($is_iframe): ?>
        <!-- Full width form for iframe -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Normal layout with navigation and cards -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-end align-items-center mb-4">
                        <div>
                            <a href="surveillance_list.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> View Surveillance Records
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php if ($editMode): ?>
                                    <i class="fas fa-edit"></i> Edit Medical Surveillance
                                <?php else: ?>
                                    <i class="fas fa-plus-circle"></i> New Medical Surveillance
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
    <?php endif; ?>
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="surveillance_form.php<?php 
                            // Preserve URL parameters for redirect
                            $urlParams = [];
                            if (isset($_GET['patient_id'])) $urlParams[] = 'patient_id=' . urlencode($_GET['patient_id']);
                            if (isset($_GET['company_id'])) $urlParams[] = 'company_id=' . urlencode($_GET['company_id']);
                            if (isset($_GET['iframe'])) $urlParams[] = 'iframe=' . urlencode($_GET['iframe']);
                            if (isset($_GET['surveillance_id'])) $urlParams[] = 'surveillance_id=' . urlencode($_GET['surveillance_id']);
                            if (!empty($urlParams)) echo '?' . implode('&', $urlParams);
                        ?>" id="surveillanceForm">
                            <input type="hidden" name="action" value="save_surveillance" id="formAction">
                            <!-- Ensure patient_id is passed -->
                            <?php if ($prePopulateData && isset($prePopulateData['id'])): ?>
                                <input type="hidden" name="patient_id" value="<?php echo $prePopulateData['id']; ?>">
                            <?php elseif (isset($_GET['patient_id'])): ?>
                                <input type="hidden" name="patient_id" value="<?php echo (int)$_GET['patient_id']; ?>">
                            <?php endif; ?>
                            <!-- Preserve company_id for redirect -->
                            <?php if (isset($_GET['company_id'])): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$_GET['company_id']; ?>">
                            <?php endif; ?>
                            <!-- Hidden field for patient's date of birth for age calculation -->
                            <input type="hidden" id="patient_dob" value="<?php echo $editMode && isset($editData['patient']['date_of_birth']) ? $editData['patient']['date_of_birth'] : ''; ?>">
                            
                            <!-- Tab Navigation -->
                            <ul class="nav nav-tabs" id="surveillanceTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                        <i class="fas fa-user"></i> General Information
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                                        <i class="fas fa-history"></i> History of Health
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="clinical-tab" data-bs-toggle="tab" data-bs-target="#clinical" type="button" role="tab" aria-controls="clinical" aria-selected="false">
                                        <i class="fas fa-search"></i> Clinical Findings
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="physical-tab" data-bs-toggle="tab" data-bs-target="#physical" type="button" role="tab" aria-controls="physical" aria-selected="false">
                                        <i class="fas fa-user-md"></i> Physical Examination
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="target-tab" data-bs-toggle="tab" data-bs-target="#target" type="button" role="tab" aria-controls="target" aria-selected="false">
                                        <i class="fas fa-heartbeat"></i> Target Organ Function
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="biological-tab" data-bs-toggle="tab" data-bs-target="#biological" type="button" role="tab" aria-controls="biological" aria-selected="false">
                                        <i class="fas fa-flask"></i> Biological Monitoring
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="conclusion-tab" data-bs-toggle="tab" data-bs-target="#conclusion" type="button" role="tab" aria-controls="conclusion" aria-selected="false">
                                        <i class="fas fa-clipboard-check"></i> Conclusion
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="recommendations-tab" data-bs-toggle="tab" data-bs-target="#recommendations" type="button" role="tab" aria-controls="recommendations" aria-selected="false">
                                        <i class="fas fa-lightbulb"></i> Recommendations
                                    </button>
                                </li>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content" id="surveillanceTabContent">
                            
                                <!-- General Information Tab -->
                                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                    <!-- Examination Details -->
                                    <div class="mb-4">
                                        <h5><i class="fas fa-stethoscope"></i> Examination Details</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Examiner</label>
                                            <div class="info-display">
                                                <?php echo htmlspecialchars($display_examiner_name); ?>
                                            </div>
                                            <input type="hidden" name="examiner_name" value="<?php echo htmlspecialchars($display_examiner_name); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Examination Date</label>
                                            <div class="info-display">
                                                <?php echo date('d/m/Y'); ?>
                                            </div>
                                            <input type="hidden" name="examination_date" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patient Information -->
                            <div class="mb-4">
                                <h5><i class="fas fa-user"></i> Patient Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                            <?php if ($prePopulateData || $preserved_employer): ?>
                                                <!-- Show pre-populated company -->
                                                <div class="info-display">
                                                    <?php echo htmlspecialchars($preserved_employer ?: ($prePopulateData['company_name'] ?: 'CONSIST')); ?>
                                                </div>
                                                <input type="hidden" name="workplace" value="<?php echo htmlspecialchars($preserved_employer ?: ($prePopulateData['company_name'] ?: 'CONSIST')); ?>">
                                            <?php else: ?>
                                                <!-- Show company dropdown -->
                                                <select class="form-select" id="company_select" name="workplace" required onchange="updateEmployeeDropdown()">
                                                    <option value="">-- Select Company --</option>
                                                    <?php foreach ($all_companies as $company): ?>
                                                        <option value="<?php echo htmlspecialchars($company['company_name']); ?>">
                                                            <?php echo htmlspecialchars(ucwords(strtolower($company['company_name']))); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Employee Name <span class="text-danger">*</span></label>
                                            <?php if ($prePopulateData || $preserved_patient_name): ?>
                                                <!-- Show pre-populated employee -->
                                                <div class="info-display">
                                                    <?php echo htmlspecialchars($preserved_patient_name ?: ($prePopulateData['first_name'] . ' ' . $prePopulateData['last_name'])); ?>
                                                </div>
                                                <input type="hidden" name="patient_id" value="<?php echo $prePopulateData['id'] ?? ''; ?>">
                                            <?php else: ?>
                                                <!-- Show employee dropdown -->
                                                <select class="form-select" id="employee_select" name="patient_id" required disabled>
                                                    <option value="">-- Select Company First --</option>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Chemical and Medical Type -->
                            <div class="mb-4">
                                <h5><i class="fas fa-flask"></i> Chemical and Medical Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="chemical" class="form-label">Chemicals <span class="required">*</span></label>
                                            <select class="form-select" id="chemical" name="chemical" required onchange="handleChemicalSelection()">
                                                <option value="">-- Select Chemical --</option>
                                                <option value="Lead (Inorganic & Organic)" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Lead (Inorganic & Organic)') ? 'selected' : ''; ?>>Lead (Inorganic & Organic)</option>
                                                <option value="Organophosphate pesticides" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Organophosphate pesticides') ? 'selected' : ''; ?>>Organophosphate pesticides</option>
                                                <option value="Benzene" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Benzene') ? 'selected' : ''; ?>>Benzene</option>
                                                <option value="Carbon Disulphide" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Carbon Disulphide') ? 'selected' : ''; ?>>Carbon Disulphide</option>
                                                <option value="n-Hexane" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'n-Hexane') ? 'selected' : ''; ?>>n-Hexane</option>
                                                <option value="Trichloroethylene" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Trichloroethylene') ? 'selected' : ''; ?>>Trichloroethylene</option>
                                                <option value="Arsenic (inorganic)" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Arsenic (inorganic)') ? 'selected' : ''; ?>>Arsenic (inorganic)</option>
                                                <option value="Cadmium" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Cadmium') ? 'selected' : ''; ?>>Cadmium</option>
                                                <option value="Chromium VI" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Chromium VI') ? 'selected' : ''; ?>>Chromium VI</option>
                                                <option value="Mercury" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Mercury') ? 'selected' : ''; ?>>Mercury</option>
                                                <option value="Nickel" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Nickel') ? 'selected' : ''; ?>>Nickel</option>
                                                <option value="Manganese" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Manganese') ? 'selected' : ''; ?>>Manganese</option>
                                                <option value="Toluene" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Toluene') ? 'selected' : ''; ?>>Toluene</option>
                                                <option value="Xylene" <?php echo ($editMode && isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Xylene') ? 'selected' : ''; ?>>Xylene</option>
                                                <option value="Other">Other (Specify)</option>
                                            </select>
                                            <input type="text" class="form-control mt-2" id="chemical_other" name="chemical_other" placeholder="Specify other chemical" style="display: none;" 
                                                   value="<?php echo ($editMode && isset($editData['surveillance']['chemical']) && !in_array($editData['surveillance']['chemical'], ['Lead (Inorganic & Organic)', 'Organophosphate pesticides', 'Benzene', 'Carbon Disulphide', 'n-Hexane', 'Trichloroethylene', 'Arsenic (inorganic)', 'Cadmium', 'Chromium VI', 'Mercury', 'Nickel', 'Manganese', 'Toluene', 'Xylene'])) ? htmlspecialchars($editData['surveillance']['chemical']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="examination_type" class="form-label">Type of Medical Examination <span class="required">*</span></label>
                                            <select class="form-select" id="examination_type" name="examination_type" required>
                                                <option value="">Select examination type</option>
                                                <option value="Pre-employment" <?php echo ($editMode && isset($editData['surveillance']['examination_type']) && $editData['surveillance']['examination_type'] == 'Pre-employment') ? 'selected' : ''; ?>>Pre-employment</option>
                                                <option value="Periodic" <?php echo ($editMode && isset($editData['surveillance']['examination_type']) && $editData['surveillance']['examination_type'] == 'Periodic') ? 'selected' : ''; ?>>Periodic</option>
                                                <option value="Return to work" <?php echo ($editMode && isset($editData['surveillance']['examination_type']) && $editData['surveillance']['examination_type'] == 'Return to work') ? 'selected' : ''; ?>>Return to work</option>
                                                <option value="Exit" <?php echo ($editMode && isset($editData['surveillance']['examination_type']) && $editData['surveillance']['examination_type'] == 'Exit') ? 'selected' : ''; ?>>Exit</option>
                                               
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="history-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- History of Health Tab -->
                                <div class="tab-pane fade" id="history" role="tabpanel" aria-labelledby="history-tab">
                                    <!-- History of Health Effects Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-history"></i> History of Health Effects During Chemical Exposure</h5>
                                
                                <div class="row">
                                    <!-- Respiratory & Cardiovascular Symptoms -->
                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-primary"><i class="fas fa-lungs"></i> Respiratory & Cardiovascular</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 50%; font-size: 0.8rem;">Symptoms</th>
                                                        <th style="width: 25%;" class="text-center">Yes</th>
                                                        <th style="width: 25%;" class="text-center">No</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Breathing difficulty</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="breathing_difficulty" value="Yes" required <?php echo ($editMode && isset($editData['health_history']['breathing_difficulty']) && $editData['health_history']['breathing_difficulty'] == 'Yes') ? 'checked' : ''; ?>>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="breathing_difficulty" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Cough</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="cough" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="cough" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Sore throat</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="sore_throat" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="sore_throat" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Sneezing</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="sneezing" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="sneezing" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Chest Pain</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="chest_pain" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="chest_pain" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Palpitation</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="palpitation" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="palpitation" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Limb oedema</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="limb_oedema" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="limb_oedema" value="No" required>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Nervous System Symptoms -->
                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-primary"><i class="fas fa-brain"></i> Nervous System</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 50%; font-size: 0.8rem;">Symptoms</th>
                                                        <th style="width: 25%;" class="text-center">Yes</th>
                                                        <th style="width: 25%;" class="text-center">No</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Drowsiness</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="drowsiness" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="drowsiness" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Dizziness</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="dizziness" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="dizziness" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Headache</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="headache" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="headache" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Confusion</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="confusion" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="confusion" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Lethargy</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="lethargy" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="lethargy" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Nausea</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="nausea" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="nausea" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Vomiting</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="vomiting" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="vomiting" value="No" required>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Skin & Eye Symptoms -->
                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-primary"><i class="fas fa-eye"></i> Skin & Eye</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 50%; font-size: 0.8rem;">Symptoms</th>
                                                        <th style="width: 25%;" class="text-center">Yes</th>
                                                        <th style="width: 25%;" class="text-center">No</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Eye irritations</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="eye_irritations" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="eye_irritations" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Blurred vision</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="blurred_vision" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="blurred_vision" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Blisters</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="blisters" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="blisters" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Burns</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="burns" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="burns" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Itching</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="itching" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="itching" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Rash</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="rash" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="rash" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Redness</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="redness" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="redness" value="No" required>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Gastrointestinal & Genitourinary Symptoms -->
                                    <div class="col-md-6 mb-4">
                                        <h6 class="text-primary"><i class="fas fa-stomach"></i> Gastrointestinal & Genitourinary</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 50%; font-size: 0.8rem;">Symptoms</th>
                                                        <th style="width: 25%;" class="text-center">Yes</th>
                                                        <th style="width: 25%;" class="text-center">No</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Abdominal pain</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="abdominal_pain" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="abdominal_pain" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Abdominal mass</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="abdominal_mass" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="abdominal_mass" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Jaundice</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="jaundice" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="jaundice" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Diarrhoea</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="diarrhoea" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="diarrhoea" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Loss of weight</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="loss_of_weight" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="loss_of_weight" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Loss of appetite</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="loss_of_appetite" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="loss_of_appetite" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Dysuria</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="dysuria" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="dysuria" value="No" required>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="font-size: 0.8rem;">Haematuria</td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="haematuria" value="Yes" required>
                                                        </td>
                                                        <td class="text-center">
                                                            <input class="form-check-input" type="radio" name="haematuria" value="No" required>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Symptoms -->
                                <div class="mb-3">
                                    <label for="others_symptoms" class="form-label">Other Symptoms</label>
                                    <textarea class="form-control" id="others_symptoms" name="others_symptoms" rows="3" 
                                              placeholder="Elaborate the frequency & severity..."></textarea>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="clinical-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Clinical Findings Tab -->
                                <div class="tab-pane fade" id="clinical" role="tabpanel" aria-labelledby="clinical-tab">
                                    <!-- Clinical Findings Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-search"></i> Clinical Findings</h5>
                                <div class="mb-3">
                                    <h6 class="mb-2"><strong> CURRENT HEALTH EFFECT DUE TO CHTH EXPOSURE</strong></h6>
                                    <p class="mb-3 text-muted small">Describe the health effects currently experienced by the employees (refer to the Health Effects Monitoring of the relevant chemical in the Specific Guidelines or other relevant references)</p>
                                    
                                    <!-- Yes/No Radio Buttons -->
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Are there any clinical findings? <span class="required">*</span></strong></label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="has_clinical_findings" value="Yes" id="has_clinical_yes" required
                                                   <?php echo ($editMode && isset($editData['clinical_findings']['result_clinical_findings']) && $editData['clinical_findings']['result_clinical_findings'] == 'Yes') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_clinical_yes">Yes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="has_clinical_findings" value="No" id="has_clinical_no" required
                                                   <?php echo ($editMode && isset($editData['clinical_findings']['result_clinical_findings']) && $editData['clinical_findings']['result_clinical_findings'] == 'No') ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_clinical_no">No</label>
                                        </div>
                                    </div>
                                    
                                    <!-- Elaboration Textarea (shown for both Yes and No) -->
                                    <div class="mb-3" id="clinical_elaboration_div">
                                        <label class="form-label"><strong>Please provide details: <span class="required">*</span></strong></label>
                                        <textarea class="form-control" id="clinical_elaboration" name="clinical_elaboration" rows="6" required
                                                  placeholder="If Yes: Describe the health effects currently experienced by the employees...&#10;If No: Explain why there are no clinical findings or provide additional context..."><?php echo $editMode && isset($editData['clinical_findings']['elaboration']) ? htmlspecialchars($editData['clinical_findings']['elaboration']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="physical-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Physical Examination Tab -->
                                <div class="tab-pane fade" id="physical" role="tabpanel" aria-labelledby="physical-tab">
                                    <!-- Physical Examination Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-user-md"></i> Physical Examination</h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50%;">Examination</th>
                                                <th style="width: 50%;">Clinical Findings</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Anthropometry -->
                                            <tr class="table-secondary">
                                                <td colspan="2"><strong>a) Anthropometry</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Weight (kg) <span class="required">*</span></td>
                                                <td><input type="number" class="form-control form-control-sm" name="weight" id="weight" step="0.1" required></td>
                                            </tr>
                                            <tr>
                                                <td>Height (cm) <span class="required">*</span></td>
                                                <td><input type="number" class="form-control form-control-sm" name="height" id="height" required></td>
                                            </tr>
                                            <tr>
                                                <td>BMI</td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm" name="bmi" id="bmi" step="0.1" readonly>
                                                    <small class="text-muted" id="bmi-category"></small>
                                                </td>
                                            </tr>

                                            <!-- Vital Signs -->
                                            <tr class="table-secondary">
                                                <td colspan="2"><strong>b) Vital Signs</strong></td>
                                            </tr>
                                            <tr>
                                                <td>Blood Pressure (mm/Hg) <span class="required">*</span></td>
                                                <td>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <input type="number" class="form-control form-control-sm" name="blood_pressure_systolic" id="bp_systolic" placeholder="Systolic" min="40" max="200" required value="<?php echo $editMode && isset($editData['physical_examination']['bp_systolic']) ? htmlspecialchars($editData['physical_examination']['bp_systolic']) : ''; ?>">
                                                           
                                                        </div>
                                                        <div class="col-6">
                                                            <input type="number" class="form-control form-control-sm" name="blood_pressure_diastolic" id="bp_diastolic" placeholder="Diastolic" min="20" max="120" required value="<?php echo $editMode && isset($editData['physical_examination']['bp_distolic']) ? htmlspecialchars($editData['physical_examination']['bp_distolic']) : ''; ?>">
                                                            
                                                        </div>
                                                    </div>
                                                    <div id="bp_status" class="mt-1"></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Pulse Rate (beats/min) <span class="required">*</span></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm" name="pulse_rate" id="pulse_rate" min="40" max="220" required value="<?php echo $editMode && isset($editData['physical_examination']['pulse_rate']) ? htmlspecialchars($editData['physical_examination']['pulse_rate']) : ''; ?>">
                                                    <div id="pulse_status" class="mt-1"></div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Respiratory Rate (breaths/min) <span class="required">*</span></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm" name="respiratory_rate" id="respiratory_rate" min="8" max="60" required value="<?php echo $editMode && isset($editData['physical_examination']['respiratory_rate']) ? htmlspecialchars($editData['physical_examination']['respiratory_rate']) : ''; ?>">
                                                    <div id="respiratory_status" class="mt-1"></div>
                                                </td>
                                            </tr>

                                            <!-- General Appearance -->
                                            <tr class="table-secondary">
                                                <td colspan="2"><strong>c) General Appearance</strong></td>
                                            </tr>
                                            <tr>
                                                <td>General Appearance <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="general_appearance" value="Normal" id="general_appearance_normal" required>
                                                        <label class="form-check-label" for="general_appearance_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="general_appearance" value="Abnormal" id="general_appearance_abnormal" required>
                                                        <label class="form-check-label" for="general_appearance_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Organ/System -->
                                            <tr class="table-secondary">
                                                <td colspan="2"><strong>d) Organ/System</strong></td>
                                            </tr>
                                            
                                            <!-- Cardiovascular System -->
                                            <tr>
                                                <td><strong>(i) Cardiovascular system:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">S1 & S2 <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="s1_s2" value="Yes" id="s1_s2_yes" required>
                                                        <label class="form-check-label" for="s1_s2_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="s1_s2" value="No" id="s1_s2_no" required>
                                                        <label class="form-check-label" for="s1_s2_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Murmur <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="murmur" value="Yes" id="murmur_yes" required>
                                                        <label class="form-check-label" for="murmur_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="murmur" value="No" id="murmur_no" required>
                                                        <label class="form-check-label" for="murmur_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Ear, Nose and Throat -->
                                            <tr>
                                                <td><strong>(ii) Ear, nose and throat: <span class="required">*</span></strong></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="ent" value="Normal" id="ent_normal" required>
                                                        <label class="form-check-label" for="ent_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="ent" value="Abnormal" id="ent_abnormal" required>
                                                        <label class="form-check-label" for="ent_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Eyes -->
                                            <tr>
                                                <td><strong>(iii) Eyes:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Visual Acuity <span class="required">*</span></td>
                                                <td>
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <label class="form-label small">R:</label>
                                                            <input type="text" class="form-control form-control-sm" name="visual_acuity_right" required>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label small">L:</label>
                                                            <input type="text" class="form-control form-control-sm" name="visual_acuity_left" required>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Colour Blindness <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="colour_blindness" value="Yes" id="colour_blindness_yes" required>
                                                        <label class="form-check-label" for="colour_blindness_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="colour_blindness" value="No" id="colour_blindness_no" required>
                                                        <label class="form-check-label" for="colour_blindness_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Gastrointestinal -->
                                            <tr>
                                                <td><strong>(iv) Gastrointestinal:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Tenderness <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="gi_tenderness" value="Yes" id="gi_tenderness_yes" required>
                                                        <label class="form-check-label" for="gi_tenderness_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="gi_tenderness" value="No" id="gi_tenderness_no" required>
                                                        <label class="form-check-label" for="gi_tenderness_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Abdominal Mass <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="abdominal_mass_exam" value="Yes" id="abdominal_mass_exam_yes" required>
                                                        <label class="form-check-label" for="abdominal_mass_exam_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="abdominal_mass_exam" value="No" id="abdominal_mass_exam_no" required>
                                                        <label class="form-check-label" for="abdominal_mass_exam_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Haematology -->
                                            <tr>
                                                <td><strong>(v) Haematology:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Lymph nodes <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="lymph_nodes" value="Palpable" id="lymph_nodes_palpable" required>
                                                        <label class="form-check-label" for="lymph_nodes_palpable">Palpable</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="lymph_nodes" value="Non-palpable" id="lymph_nodes_non_palpable" required>
                                                        <label class="form-check-label" for="lymph_nodes_non_palpable">Non-palpable</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Splenomegaly <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="splenomegaly" value="Yes" id="splenomegaly_yes" required>
                                                        <label class="form-check-label" for="splenomegaly_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="splenomegaly" value="No" id="splenomegaly_no" required>
                                                        <label class="form-check-label" for="splenomegaly_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Kidney -->
                                            <tr>
                                                <td><strong>(vi) Kidney:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Tenderness <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="kidney_tenderness" value="Yes" id="kidney_tenderness_yes" required>
                                                        <label class="form-check-label" for="kidney_tenderness_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="kidney_tenderness" value="No" id="kidney_tenderness_no" required>
                                                        <label class="form-check-label" for="kidney_tenderness_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Ballotable <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="ballotable" value="Yes" id="ballotable_yes" required>
                                                        <label class="form-check-label" for="ballotable_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="ballotable" value="No" id="ballotable_no" required>
                                                        <label class="form-check-label" for="ballotable_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Liver -->
                                            <tr>
                                                <td><strong>(vii) Liver:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Jaundice <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="liver_jaundice" value="Yes" id="liver_jaundice_yes" required>
                                                        <label class="form-check-label" for="liver_jaundice_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="liver_jaundice" value="No" id="liver_jaundice_no" required>
                                                        <label class="form-check-label" for="liver_jaundice_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Hepatomegaly <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="hepatomegaly" value="Yes" id="hepatomegaly_yes" required>
                                                        <label class="form-check-label" for="hepatomegaly_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="hepatomegaly" value="No" id="hepatomegaly_no" required>
                                                        <label class="form-check-label" for="hepatomegaly_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Musculoskeletal -->
                                            <tr>
                                                <td><strong>(viii) Musculoskeletal:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Muscle Tone <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tone" value="1" id="muscle_tone_1" required>
                                                        <label class="form-check-label" for="muscle_tone_1">1</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tone" value="2" id="muscle_tone_2" required>
                                                        <label class="form-check-label" for="muscle_tone_2">2</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tone" value="3" id="muscle_tone_3" required>
                                                        <label class="form-check-label" for="muscle_tone_3">3</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tone" value="4" id="muscle_tone_4" required>
                                                        <label class="form-check-label" for="muscle_tone_4">4</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tone" value="5" id="muscle_tone_5" required>
                                                        <label class="form-check-label" for="muscle_tone_5">5</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Muscle Tenderness <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tenderness" value="Yes" id="muscle_tenderness_yes" required>
                                                        <label class="form-check-label" for="muscle_tenderness_yes">Yes</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="muscle_tenderness" value="No" id="muscle_tenderness_no" required>
                                                        <label class="form-check-label" for="muscle_tenderness_no">No</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Nervous System -->
                                            <tr>
                                                <td><strong>(ix) Nervous System:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Power <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="power" value="1" id="power_1" required>
                                                        <label class="form-check-label" for="power_1">1</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="power" value="2" id="power_2" required>
                                                        <label class="form-check-label" for="power_2">2</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="power" value="3" id="power_3" required>
                                                        <label class="form-check-label" for="power_3">3</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="power" value="4" id="power_4" required>
                                                        <label class="form-check-label" for="power_4">4</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="power" value="5" id="power_5" required>
                                                        <label class="form-check-label" for="power_5">5</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Sensation <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="sensation" value="Normal" id="sensation_normal" required>
                                                        <label class="form-check-label" for="sensation_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="sensation" value="Abnormal" id="sensation_abnormal" required>
                                                        <label class="form-check-label" for="sensation_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Respiratory -->
                                            <tr>
                                                <td><strong>(x) Respiratory:</strong></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Sound<span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="respiratory_findings" value="Clear" id="respiratory_clear" required <?php echo ($editMode && isset($editData['physical_examination']['respiratory_findings']) && $editData['physical_examination']['respiratory_findings'] == 'Clear') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="respiratory_clear">Clear</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="respiratory_findings" value="Rhonchi" id="respiratory_rhonchi" required <?php echo ($editMode && isset($editData['physical_examination']['respiratory_findings']) && $editData['physical_examination']['respiratory_findings'] == 'Rhonchi') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="respiratory_rhonchi">Rhonchi</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="respiratory_findings" value="Crepitus" id="respiratory_crepitus" required <?php echo ($editMode && isset($editData['physical_examination']['respiratory_findings']) && $editData['physical_examination']['respiratory_findings'] == 'Crepitus') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="respiratory_crepitus">Crepitus</label>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-left: 30px;">Air entry <span class="required">*</span></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="air_entry" value="Normal" id="air_entry_normal" required>
                                                        <label class="form-check-label" for="air_entry_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="air_entry" value="Abnormal" id="air_entry_abnormal" required>
                                                        <label class="form-check-label" for="air_entry_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Reproductive -->
                                            <tr>
                                                <td><strong>(xi) Reproductive: <span class="required">*</span></strong></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="reproductive" value="Normal" id="reproductive_normal" required>
                                                        <label class="form-check-label" for="reproductive_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="reproductive" value="Abnormal" id="reproductive_abnormal" required>
                                                        <label class="form-check-label" for="reproductive_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Skin -->
                                            <tr>
                                                <td><strong>(xii) Skin: <span class="required">*</span></strong></td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="skin" value="Normal" id="skin_normal" required>
                                                        <label class="form-check-label" for="skin_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="skin" value="Abnormal" id="skin_abnormal" required>
                                                        <label class="form-check-label" for="skin_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Others -->
                                            <tr>
                                                <td><strong>(xiii) Others: <span class="required">*</span></strong></td>
                                                <td><input type="text" class="form-control form-control-sm" name="others_exam" required></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="target-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Target Organ Function Tab -->
                                <div class="tab-pane fade" id="target" role="tabpanel" aria-labelledby="target-tab">
                                    <!-- Target Organ Function Test -->
                            <div class="form-section">
                                <h5><i class="fas fa-heartbeat"></i> Target Organ Function Test</h5>
                                <p class="text-muted mb-3"><strong>Please refer to Specific MS Guidelines.</strong></p>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-light" style="background-color: #e8f5e8;">
                                            <tr>
                                                <th style="width: 30%; background-color: #e8f5e8;">TEST</th>
                                                <th style="width: 35%; background-color: #e8f5e8;">RESULTS/FINDINGS <span class="required">*</span></th>
                                                <th style="width: 35%; background-color: #e8f5e8;">COMMENTS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Full Blood Count -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Full Blood Count</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="blood_count" value="Normal" id="blood_count_normal" required>
                                                        <label class="form-check-label" for="blood_count_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="blood_count" value="Abnormal" id="blood_count_abnormal" required>
                                                        <label class="form-check-label" for="blood_count_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="blood_comment" name="blood_comment" placeholder="Enter comments">
                                                </td>
                                            </tr>
                                            
                                            <!-- Renal Function Test -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Renal Function Test</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="renal_function" value="Normal" id="renal_function_normal" required>
                                                        <label class="form-check-label" for="renal_function_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="renal_function" value="Abnormal" id="renal_function_abnormal" required>
                                                        <label class="form-check-label" for="renal_function_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="renal_comment" name="renal_comment" placeholder="Enter comments">
                                                </td>
                                            </tr>
                                            
                                            <!-- Liver Function Test -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Liver Function Test</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="liver_function" value="Normal" id="liver_function_normal" required>
                                                        <label class="form-check-label" for="liver_function_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="liver_function" value="Abnormal" id="liver_function_abnormal" required>
                                                        <label class="form-check-label" for="liver_function_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="liver_comment" name="liver_comment" placeholder="Enter comments">
                                                </td>
                                            </tr>
                                            
                                            <!-- Chest x-ray -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Chest x-ray</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="chest_xray" value="Normal" id="chest_xray_normal" required>
                                                        <label class="form-check-label" for="chest_xray_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="chest_xray" value="Abnormal" id="chest_xray_abnormal" required>
                                                        <label class="form-check-label" for="chest_xray_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="xray_comment" name="xray_comment" placeholder="Enter comments">
                                                </td>
                                            </tr>
                                            
                                            <!-- Spirometry -->
                                            <tr>
                                                <td rowspan="3" style="background-color: #e8f5e8; font-weight: 500; vertical-align: middle;">Spirometry</td>
                                                <td style="background-color: #e8f5e8;">FEV 1</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="spirometry_fev1" name="spirometry_fev1" placeholder="Enter FEV 1 value">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="background-color: #e8f5e8;">FVC</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="spirometry_fvc2" name="spirometry_fvc2" placeholder="Enter FVC value">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="background-color: #e8f5e8;">FEV/FVC</td>
                                                <td>
                                                    <input type="number" step="0.01" class="form-control form-control-sm" id="spirometry_fev_fvc" name="spirometry_fev_fvc" placeholder="Enter FEV/FVC value">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="background-color: #e8f5e8;"></td>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Spirometry Comments</td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="spirometry_comment" name="spirometry_comment" placeholder="Enter comments">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="biological-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Biological Monitoring Tab -->
                                <div class="tab-pane fade" id="biological" role="tabpanel" aria-labelledby="biological-tab">
                                    <!-- Biological Monitoring -->
                            <div class="form-section">
                                <h5><i class="fas fa-flask"></i> Biological Monitoring</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50%;">Biological Exposure Indices Determinants (BM/BEM)</th>
                                                <th style="width: 50%;">Results</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td rowspan="2" style="vertical-align: middle;">
                                                    <select class="form-control form-control-sm" id="biological_exposure" name="biological_exposure" required onchange="handleBiologicalExposureSelection()">
                                                        <option value="">-- Select Biological Exposure Indices --</option>
                                                        <option value="Blood Lead Level (BLL)">Blood Lead Level (BLL)</option>
                                                        <option value="RBC Cholinesterase">RBC Cholinesterase</option>
                                                        <option value="Plasma Cholinesterase">Plasma Cholinesterase</option>
                                                        <option value="S-PMA (urine)">S-PMA (urine)</option>
                                                        <option value="TTCA (urine)">TTCA (urine)</option>
                                                        <option value="2,5-Hexanedione (urine)">2,5-Hexanedione (urine)</option>
                                                        <option value="TCA (urine)">TCA (urine)</option>
                                                        <option value="Urinary As + metabolites">Urinary As + metabolites</option>
                                                        <option value="Urinary Cd">Urinary Cd</option>
                                                        <option value="Blood Cd">Blood Cd</option>
                                                        <option value="Urinary total chromium">Urinary total chromium</option>
                                                        <option value="Urinary mercury">Urinary mercury</option>
                                                        <option value="Blood mercury">Blood mercury</option>
                                                        <option value="Urinary nickel (Elemental Nickel)">Urinary nickel (Elemental Nickel)</option>
                                                        <option value="Urinary nickel (Soluble compounds)">Urinary nickel (Soluble compounds)</option>
                                                        <option value="Blood manganese">Blood manganese</option>
                                                        <option value="Toluene (urine)">Toluene (urine)</option>
                                                        <option value="Methylhippuric acids (urine)">Methylhippuric acids (urine)</option>
                                                        <option value="Other">Other (Specify)</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2" id="biological_exposure_other" name="biological_exposure_other" placeholder="Please specify" style="display: none;">
                                                </td>
                                                <td>
                                                    <label class="form-label small mb-1">Baseline:</label>
                                                    <input type="text" class="form-control form-control-sm" id="result_baseline" name="result_baseline" placeholder="Enter baseline results" required>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label class="form-label small mb-1">Annual:</label>
                                                    <input type="text" class="form-control form-control-sm" id="result_annual" name="result_annual" placeholder="Enter annual results" required>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="conclusion-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Conclusion Tab -->
                                <div class="tab-pane fade" id="conclusion" role="tabpanel" aria-labelledby="conclusion-tab">
                                    <!-- Fitness to Wear Respirator -->
                            <div class="form-section">
                                <h5><i class="fas fa-mask"></i> Assessment on the Fitness to Wear Respirator</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Conclusion on fitness to wear respirator <span class="required">*</span></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="respirator_result" value="Fit" id="respirator_fit" required>
                                                <label class="form-check-label" for="respirator_fit">Fit</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="respirator_result" value="Not Fit" id="respirator_not_fit" required>
                                                <label class="form-check-label" for="respirator_not_fit">Not Fit</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="respirator_justification" class="form-label">Justification <span class="required">*</span></label>
                                            <textarea class="form-control" id="respirator_justification" name="respirator_justification" rows="3" placeholder="Please justify the decision" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Conclusion of MS Findings -->
                            <div class="form-section">
                                <h5><i class="fas fa-clipboard-check"></i> Conclusion of MS Findings</h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered ms-findings-table">
                                        <thead>
                                            <tr>
                                                <th rowspan="2" style="background-color: #e8f5e8; vertical-align: middle; width: 40%;">MS Finding</th>
                                                <th colspan="2" style="text-align: center; background-color: #f8f9fa;">MS Finding</th>
                                                <th colspan="2" style="text-align: center; background-color: #f8f9fa;">if yes, is it work related?</th>
                                            </tr>
                                            <tr>
                                                <th style="text-align: center; background-color: #f8f9fa;">Yes</th>
                                                <th style="text-align: center; background-color: #f8f9fa;">No</th>
                                                <th style="text-align: center; background-color: #f8f9fa;">Yes</th>
                                                <th style="text-align: center; background-color: #f8f9fa;">No</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- History of health effects due to chemical(s) exposure [H] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">History of health effects due to chemical(s) exposure [H]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="history_of_health" value="Yes" id="history_yes" required <?php echo ($editMode && isset($editData['surveillance']['history_of_health']) && $editData['surveillance']['history_of_health'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="history_of_health" value="No" id="history_no" required>
                                                </td>
                                                <td colspan="2" style="text-align: center; background-color: #6c757d; color: white; font-weight: 500;">Not applicable</td>
                                            </tr>
                                            
                                            <!-- Clinical findings [I] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Clinical findings [I]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_findings" value="Yes" id="clinical_yes" required <?php echo ($editMode && isset($editData['surveillance']['clinical_findings']) && $editData['surveillance']['clinical_findings'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_findings" value="No" id="clinical_no" required>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_work_related" value="Yes" id="clinical_work_yes" required <?php echo ($editMode && isset($editData['surveillance']['clinical_work_related']) && $editData['surveillance']['clinical_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_work_related" value="No" id="clinical_work_no" required>
                                                </td>
                                            </tr>
                                            
                                            <!-- Target organ function test results (please specify) [J] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Target organ function test results (please specify) [J]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="target_organ" value="Yes" id="target_organ_yes" required <?php echo ($editMode && isset($editData['surveillance']['target_organ']) && $editData['surveillance']['target_organ'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="target_organ" value="No" id="target_organ_no" required>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="organ_work_related" value="Yes" id="organ_work_yes" required <?php echo ($editMode && isset($editData['surveillance']['organ_work_related']) && $editData['surveillance']['organ_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="organ_work_related" value="No" id="organ_work_no" required>
                                                </td>
                                            </tr>
                                            
                                            <!-- BEI determinant (BM/BEM) (please specify) [K] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">BEI determinant (BM/BEM) (please specify) [K]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_monitoring" value="Yes" id="biological_yes" required <?php echo ($editMode && isset($editData['surveillance']['biological_monitoring']) && $editData['surveillance']['biological_monitoring'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_monitoring" value="No" id="biological_no" required>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_work_related" value="Yes" id="biological_work_yes" required <?php echo ($editMode && isset($editData['surveillance']['biological_work_related']) && $editData['surveillance']['biological_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_work_related" value="No" id="biological_work_no" required>
                                                </td>
                                            </tr>
                                            
                                            <!-- Pregnancy/Breast feeding -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Pregnancy/Breast feeding</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="pregnancy_breast_feeding" value="Yes" id="pregnancy_yes" required <?php echo ($editMode && isset($editData['surveillance']['pregnancy_breast_feeding']) && $editData['surveillance']['pregnancy_breast_feeding'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="pregnancy_breast_feeding" value="No" id="pregnancy_no" required>
                                                </td>
                                                <td colspan="2" style="text-align: center; background-color: #6c757d; color: white; font-weight: 500;">Not applicable</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Conclusion of fitness to work -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">*Information can be obtained from the relevant section in USECHH 1</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Conclusion of fitness to work <span class="required">*</span></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fitness_status" value="Fit for Work" id="fitness_fit" required>
                                                <label class="form-check-label" for="fitness_fit">Fit</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fitness_status" value="Not Fit for Work" id="fitness_not_fit" required <?php echo ($editMode && isset($editData['surveillance']['final_assessment']) && $editData['surveillance']['final_assessment'] == 'Not Fit for Work') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="fitness_not_fit">Not Fit</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Next Button -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="button" class="btn btn-primary btn-next-tab" data-next-tab="recommendations-tab">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                                </div>
                                
                                <!-- Recommendations Tab -->
                                <div class="tab-pane fade" id="recommendations" role="tabpanel" aria-labelledby="recommendations-tab">
                                    <!-- Recommendations -->
                            <div class="form-section">
                                <h5><i class="fas fa-lightbulb"></i> Recommendations</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Recommendations Type <span class="required">*</span></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Fit to work with no restriction" id="rec_fit_no_restriction" required>
                                                <label class="form-check-label" for="rec_fit_no_restriction">Fit to work with no restriction</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Fit for work with restriction" id="rec_fit_with_restriction">
                                                <label class="form-check-label" for="rec_fit_with_restriction">Fit for work with restriction</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Annual medical surveillance" id="rec_annual_surveillance">
                                                <label class="form-check-label" for="rec_annual_surveillance">Annual medical surveillance</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Temporary Medical Removal Protection" id="rec_temp_mrp">
                                                <label class="form-check-label" for="rec_temp_mrp">Temporary Medical Removal Protection</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Permanent Medical Removal Protection" id="rec_perm_mrp">
                                                <label class="form-check-label" for="rec_perm_mrp">Permanent Medical Removal Protection</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Follow up and review" id="rec_follow_up">
                                                <label class="form-check-label" for="rec_follow_up">Follow up and review</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Reinforce PPE and hygiene practices like stop smoking, job rotation and training" id="rec_ppe_hygiene">
                                                <label class="form-check-label" for="rec_ppe_hygiene">Reinforce PPE and hygiene practices like stop smoking, job rotation and training</label>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_of_MRP" class="form-label">Date of MRP <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="date_of_MRP" name="date_of_MRP" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="next_review_date" class="form-label">Next Review Date <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="next_review_date" name="next_review_date" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="recommendations_notes" class="form-label">Notes <span class="required">*</span></label>
                                            <textarea class="form-control" id="recommendations_notes" name="recommendations_notes" rows="3" placeholder="Enter additional notes" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OHD Information Card -->
                            <div class="form-section mt-4" style="border: 2px solid #000; padding: 1.5rem;">
                                <h5><i class="fas fa-user-md"></i> Occupational Health Doctor Information</h5>
                                
                                <!-- OHD and Clinic Information (Two Columns) -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Name of Occupational Health Doctor:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($ohd_full_name ?: 'DR WAN HAZIQ BIN WAN KAMARUL ZAMAN'); ?>
                                        </div>
                                        <input type="hidden" name="ohd_name" value="<?php echo htmlspecialchars($ohd_full_name ?: 'DR WAN HAZIQ BIN WAN KAMARUL ZAMAN'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Name of clinic:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($clinic_name ?: 'KLINIK HAYDAR & KAMAL'); ?>
                                        </div>
                                        <input type="hidden" name="ohd_clinic_name" value="<?php echo htmlspecialchars($clinic_name ?: 'KLINIK HAYDAR & KAMAL'); ?>">
                                    </div>
                                </div>
                                
                                <!-- Contact and Registration Details (Two Columns) -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">MMC No.:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($mmc_no ?: '53733'); ?>
                                        </div>
                                        <input type="hidden" name="mmc_no" value="<?php echo htmlspecialchars($mmc_no ?: '53733'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">DOSH Registration No.:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($dosh_registration_no); ?>
                                        </div>
                                        <input type="hidden" name="dosh_registration_no" value="<?php echo htmlspecialchars($dosh_registration_no); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Clinic Tel. No.:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($clinic_phone); ?>
                                        </div>
                                        <input type="hidden" name="clinic_tel_no" value="<?php echo htmlspecialchars($clinic_phone); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Fax No.:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($clinic_fax ?: '-'); ?>
                                        </div>
                                        <input type="hidden" name="clinic_fax_no" value="<?php echo htmlspecialchars($clinic_fax); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Email:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo htmlspecialchars($clinic_email ?: ($ohd_email ?: 'info@warisankamal.my')); ?>
                                        </div>
                                        <input type="hidden" name="clinic_email" value="<?php echo htmlspecialchars($clinic_email ?: ($ohd_email ?: 'info@warisankamal.my')); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;">Date:</label>
                                        <div style="padding: 0.5rem 0; min-height: 2rem;">
                                            <?php echo date('d/m/Y'); ?>
                                        </div>
                                        <input type="hidden" name="ohd_date" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                
                                <!-- Employee Signature Section -->
                                <div class="row mt-4">
                                    <div class="col-md-6 offset-md-6">
                                        <div style="border: 1px solid #000; padding: 1rem; min-height: 150px;">
                                            <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;"><strong>Signature of the employee:</strong></label>
                                            <div class="signature-pad" style="border: 1px solid #ddd; border-radius: 4px; background: white; margin: 10px 0;">
                                                <canvas id="employee-signature-pad" width="400" height="120" style="border:1px solid #ccc; width: 100%; height: 120px; border-radius: 4px;"></canvas>
                                            </div>
                                            <div class="signature-controls" style="margin-top: 10px;">
                                                <button type="button" id="clear-employee" class="btn btn-outline-secondary btn-sm">Clear</button>
                                                <button type="button" id="save-employee" class="btn btn-outline-primary btn-sm">Save Signature</button>
                                            </div>
                                            <input type="hidden" id="employee_signature_data" name="employee_signature" value="">
                                            <div class="mt-3">
                                                <label class="form-label" style="font-weight: 600; margin-bottom: 0.5rem;"><strong>Date:</strong></label>
                                                <div style="padding: 0.5rem 0; min-height: 2rem;">
                                                    <?php echo date('d/m/Y'); ?>
                                                </div>
                                                <input type="hidden" name="employee_signature_date" value="<?php echo date('Y-m-d'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button (only in recommendations tab) -->
                            <div class="d-flex justify-content-end mt-4 mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                                    <i class="fas fa-check-circle"></i> Submit (Complete Form)
                                </button>
                            </div>
                                </div>
                                
                            </div>
                            <!-- End Tab Content -->
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between mt-4">
                                <a href="surveillance.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Surveillance
                                </a>
                            </div>
                            
                            <!-- Fixed Save Button (available on all tabs) -->
                            <button type="button" class="btn btn-success fixed-save-button" id="saveBtn">
                                <i class="fas fa-save"></i> Save (Incomplete)
                            </button>
                        </form>
    <?php if (!$is_iframe): ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
    // Tab validation and form handling
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('surveillanceForm');
        const saveBtn = document.getElementById('saveBtn');
        const submitBtn = document.getElementById('submitBtn');
        const formAction = document.getElementById('formAction');
        
        // Define tab mapping
        const tabs = {
            'general': { tabId: 'general-tab', paneId: 'general' },
            'history': { tabId: 'history-tab', paneId: 'history' },
            'clinical': { tabId: 'clinical-tab', paneId: 'clinical' },
            'physical': { tabId: 'physical-tab', paneId: 'physical' },
            'target': { tabId: 'target-tab', paneId: 'target' },
            'biological': { tabId: 'biological-tab', paneId: 'biological' },
            'conclusion': { tabId: 'conclusion-tab', paneId: 'conclusion' },
            'recommendations': { tabId: 'recommendations-tab', paneId: 'recommendations' }
        };
        
        // Function to validate a single tab
        function validateTab(tabKey) {
            const tab = tabs[tabKey];
            if (!tab) return true;
            
            const tabPane = document.getElementById(tab.paneId);
            if (!tabPane) return true;
            
            let isValid = true;
            
            // Get all required fields within this tab pane
            const requiredFields = tabPane.querySelectorAll('[required]');
            const checkedRadioGroups = new Set();
            
            requiredFields.forEach(field => {
                if (field.type === 'radio') {
                    // For radio buttons, check if at least one in the group is checked
                    const name = field.name;
                    if (!checkedRadioGroups.has(name)) {
                        checkedRadioGroups.add(name);
                        const radioGroup = tabPane.querySelectorAll(`input[type="radio"][name="${name}"]`);
                        if (radioGroup.length > 0) {
                            const hasChecked = Array.from(radioGroup).some(r => r.checked);
                            if (!hasChecked) {
                                isValid = false;
                            }
                        }
                    }
                } else if (field.type === 'checkbox') {
                    // For checkboxes, check if at least one in the group is checked (if multiple checkboxes with same name)
                    const name = field.name;
                    const checkboxGroup = tabPane.querySelectorAll(`input[type="checkbox"][name="${name}"]`);
                    if (checkboxGroup.length > 1) {
                        // Multiple checkboxes with same name - at least one must be checked
                        if (!checkedRadioGroups.has(name)) {
                            checkedRadioGroups.add(name);
                            const hasChecked = Array.from(checkboxGroup).some(c => c.checked);
                            if (!hasChecked) {
                                isValid = false;
                            }
                        }
                    } else {
                        // Single checkbox - must be checked
                        if (!field.checked) {
                            isValid = false;
                        }
                    }
                } else {
                    // Regular input, select, textarea
                    // Skip if field is disabled or hidden
                    if (field.disabled || field.style.display === 'none') {
                        const parent = field.closest('[style*="display: none"]');
                        if (parent) return;
                    }
                    
                    const value = field.value ? field.value.trim() : '';
                    // Check for empty or placeholder values
                    if (value === '' || value === '-- Select --' || value === '-- Select Company --' || value === '-- Select Company First --' || value === 'Select examination type') {
                        isValid = false;
                    }
                }
            });
            
            return isValid;
        }
        
        // Function to validate all tabs
        function validateAllTabs() {
            let allValid = true;
            for (const tabKey in tabs) {
                if (!validateTab(tabKey)) {
                    allValid = false;
                    updateTabIndicator(tabKey, false);
                } else {
                    updateTabIndicator(tabKey, true);
                }
            }
            return allValid;
        }
        
        // Function to update tab indicator
        function updateTabIndicator(tabKey, isValid) {
            const tab = tabs[tabKey];
            if (!tab) return;
            
            const tabElement = document.getElementById(tab.tabId);
            if (tabElement) {
                if (isValid) {
                    tabElement.classList.remove('has-error');
                } else {
                    tabElement.classList.add('has-error');
                }
            }
        }
        
        // Function to validate current tab only
        function validateCurrentTab() {
            const activeTab = document.querySelector('.tab-pane.active');
            if (!activeTab) return true;
            
            const tabId = activeTab.id;
            const isValid = validateTab(tabId);
            updateTabIndicator(tabId, isValid);
            return isValid;
        }
        
        // Validate tabs on input change
        function setupTabValidation() {
            // Add event listeners to all form fields
            const allFields = form.querySelectorAll('input, select, textarea');
            allFields.forEach(field => {
                // Use multiple event types for better coverage
                ['change', 'input', 'blur'].forEach(eventType => {
                    field.addEventListener(eventType, function() {
                        // Small delay to ensure value is updated
                        setTimeout(function() {
                            // Validate current tab
                            validateCurrentTab();
                            // Re-validate all tabs for submit button
                            const allValid = validateAllTabs();
                            if (submitBtn) {
                                submitBtn.disabled = !allValid;
                            }
                            // Update save button text based on completion status
                            if (saveBtn) {
                                const newText = allValid ? 'Save (Complete)' : 'Save (Incomplete)';
                                saveBtn.innerHTML = '<i class="fas fa-save"></i> ' + newText;
                            }
                        }, 100);
                    });
                });
            });
            
            // Validate on tab change
            const tabButtons = document.querySelectorAll('#surveillanceTabs button');
            tabButtons.forEach(btn => {
                btn.addEventListener('shown.bs.tab', function(e) {
                    setTimeout(function() {
                        validateCurrentTab();
                        const allValid = validateAllTabs();
                        if (submitBtn) {
                            submitBtn.disabled = !allValid;
                        }
                        // Update save button text based on completion status
                        if (saveBtn) {
                            const newText = allValid ? 'Save (Complete)' : 'Save (Incomplete)';
                            saveBtn.innerHTML = '<i class="fas fa-save"></i> ' + newText;
                        }
                    }, 100);
                });
            });
        }
        
        // Initialize validation on load
        setTimeout(function() {
            const allValid = validateAllTabs();
            if (submitBtn) {
                submitBtn.disabled = !allValid;
            }
            // Update save button text based on completion status
            if (saveBtn) {
                const newText = allValid ? 'Save (Complete)' : 'Save (Incomplete)';
                saveBtn.innerHTML = '<i class="fas fa-save"></i> ' + newText;
            }
        }, 500);
        setupTabValidation();
        
        // Save button handler (allows partial save)
        if (saveBtn) {
            saveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Save button clicked');
                console.log('Form action:', formAction.value);
                
                // Set form action
                formAction.value = 'save_surveillance';
                
                // Log form data before submission
                const formData = new FormData(form);
                console.log('Form data being submitted:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Temporarily remove required attributes for save
                const requiredFields = form.querySelectorAll('[required]');
                const requiredBackup = [];
                requiredFields.forEach(field => {
                    requiredBackup.push({
                        element: field,
                        wasRequired: field.hasAttribute('required')
                    });
                    field.removeAttribute('required');
                });
                
                // Show loading state
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveBtn.disabled = true;
                
                // Disable form to prevent double submission
                form.style.pointerEvents = 'none';
                
                // Submit form
                console.log('Submitting form...');
                form.submit();
            });
        }
        
        // Submit button handler (full validation)
        if (submitBtn && form) {
            form.addEventListener('submit', function(e) {
                if (formAction.value === 'submit_surveillance') {
                    // Validate all tabs
                    const allValid = validateAllTabs();
                    if (!allValid) {
                        e.preventDefault();
                        alert('Please complete all required fields in all tabs before submitting.');
                        // Highlight first incomplete tab
                        for (const tabKey in tabs) {
                            if (!validateTab(tabKey)) {
                                const tab = tabs[tabKey];
                                const tabElement = document.getElementById(tab.tabId);
                                if (tabElement) {
                                    const tabTrigger = new bootstrap.Tab(tabElement);
                                    tabTrigger.show();
                                }
                                break;
                            }
                        }
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    submitBtn.disabled = true;
                }
                return true;
            });
        }
        
        // Update submit button state when clicked
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                formAction.value = 'submit_surveillance';
                const allValid = validateAllTabs();
                if (!allValid) {
                    e.preventDefault();
                    alert('Please complete all required fields in all tabs before submitting.');
                    return false;
                }
            });
        }
    });
    
    // Conditional field handling
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tabs
        var triggerTabList = [].slice.call(document.querySelectorAll('#surveillanceTabs button'))
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl)
            
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault()
                tabTrigger.show()
            })
        })
        
        // Next button handler for tab navigation
        var nextButtons = document.querySelectorAll('.btn-next-tab');
        nextButtons.forEach(function (nextBtn) {
            nextBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var nextTabId = this.getAttribute('data-next-tab');
                if (nextTabId) {
                    var nextTabElement = document.getElementById(nextTabId);
                    if (nextTabElement) {
                        var nextTabTrigger = new bootstrap.Tab(nextTabElement);
                        nextTabTrigger.show();
                    }
                }
            });
        });
        
        // MS Findings conditional logic
        function handleMSFindingsLogic() {
            // Clinical findings work-related logic
            const clinicalYes = document.getElementById('clinical_yes');
            const clinicalWorkYes = document.getElementById('clinical_work_yes');
            const clinicalWorkNo = document.getElementById('clinical_work_no');
            
            function toggleClinicalWorkRelated() {
                if (clinicalYes.checked) {
                    clinicalWorkYes.disabled = false;
                    clinicalWorkNo.disabled = false;
                } else {
                    clinicalWorkYes.disabled = true;
                    clinicalWorkNo.disabled = true;
                    clinicalWorkYes.checked = false;
                    clinicalWorkNo.checked = false;
                }
            }
            
            clinicalYes.addEventListener('change', toggleClinicalWorkRelated);
            document.getElementById('clinical_no').addEventListener('change', toggleClinicalWorkRelated);
            
            // Target organ work-related logic
            const targetOrganYes = document.getElementById('target_organ_yes');
            const organWorkYes = document.getElementById('organ_work_yes');
            const organWorkNo = document.getElementById('organ_work_no');
            
            function toggleOrganWorkRelated() {
                if (targetOrganYes.checked) {
                    organWorkYes.disabled = false;
                    organWorkNo.disabled = false;
                } else {
                    organWorkYes.disabled = true;
                    organWorkNo.disabled = true;
                    organWorkYes.checked = false;
                    organWorkNo.checked = false;
                }
            }
            
            targetOrganYes.addEventListener('change', toggleOrganWorkRelated);
            document.getElementById('target_organ_no').addEventListener('change', toggleOrganWorkRelated);
            
            // Biological monitoring work-related logic
            const biologicalYes = document.getElementById('biological_yes');
            const biologicalWorkYes = document.getElementById('biological_work_yes');
            const biologicalWorkNo = document.getElementById('biological_work_no');
            
            function toggleBiologicalWorkRelated() {
                if (biologicalYes.checked) {
                    biologicalWorkYes.disabled = false;
                    biologicalWorkNo.disabled = false;
                } else {
                    biologicalWorkYes.disabled = true;
                    biologicalWorkNo.disabled = true;
                    biologicalWorkYes.checked = false;
                    biologicalWorkNo.checked = false;
                }
            }
            
            biologicalYes.addEventListener('change', toggleBiologicalWorkRelated);
            document.getElementById('biological_no').addEventListener('change', toggleBiologicalWorkRelated);
            
            // Initialize on page load
            toggleClinicalWorkRelated();
            toggleOrganWorkRelated();
            toggleBiologicalWorkRelated();
        }
        
        // Call the MS findings logic function
        handleMSFindingsLogic();
        
        // Vital Signs Validation based on Vital Signs Chart
        function validateVitalSigns() {
            const age = calculateAge();
            const systolic = parseInt(document.getElementById('bp_systolic').value) || 0;
            const diastolic = parseInt(document.getElementById('bp_diastolic').value) || 0;
            const pulse = parseInt(document.getElementById('pulse_rate').value) || 0;
            const respiratory = parseInt(document.getElementById('respiratory_rate').value) || 0;
            
            // Blood Pressure Validation
            validateBloodPressure(systolic, diastolic, age);
            
            // Pulse Rate Validation
            validatePulseRate(pulse, age);
            
            // Respiratory Rate Validation
            validateRespiratoryRate(respiratory, age);
        }
        
        function calculateAge() {
            // Get patient's date of birth from hidden field
            const dobField = document.getElementById('patient_dob');
            if (!dobField || !dobField.value) {
                return 25; // Default adult age if no DOB available
            }
            
            const today = new Date();
            const birthDate = new Date(dobField.value);
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }
        
        function validateBloodPressure(systolic, diastolic, age) {
            const bpStatus = document.getElementById('bp_status');
            let status = '';
            let className = '';
            
            if (systolic === 0 && diastolic === 0) {
                status = '';
                className = '';
            } else {
                // Blood Pressure ranges based on Vital Signs Chart
                let systolicRange = [90, 120]; // Adult default
                let diastolicRange = [60, 80]; // Adult default
                
                if (age >= 0 && age <= 1) {
                    // Infant (0-1 year)
                    systolicRange = [72, 104];
                    diastolicRange = [37, 56];
                } else if (age >= 2 && age <= 2) {
                    // Toddler (1-2 years)
                    systolicRange = [86, 106];
                    diastolicRange = [42, 63];
                } else if (age >= 3 && age <= 5) {
                    // Preschooler (3-5 years)
                    systolicRange = [89, 112];
                    diastolicRange = [46, 72];
                } else if (age >= 6 && age <= 12) {
                    // School-age (6-12 years)
                    systolicRange = [97, 115];
                    diastolicRange = [57, 76];
                } else if (age >= 13 && age <= 18) {
                    // Adolescent (13-18 years)
                    systolicRange = [110, 131];
                    diastolicRange = [64, 83];
                }
                // Adult (>18 years) uses default ranges
                
                const systolicNormal = systolic >= systolicRange[0] && systolic <= systolicRange[1];
                const diastolicNormal = diastolic >= diastolicRange[0] && diastolic <= diastolicRange[1];
                
                if (systolicNormal && diastolicNormal) {
                    status = 'Normotensive';
                    className = 'text-success';
                } else if (systolic < systolicRange[0] || diastolic < diastolicRange[0]) {
                    status = 'Hypotension';
                    className = 'text-warning';
                } else if (systolic > systolicRange[1] || diastolic > diastolicRange[1]) {
                    status = 'Hypertension';
                    className = 'text-danger';
                } else {
                    status = 'Abnormal';
                    className = 'text-warning';
                }
            }
            
            bpStatus.innerHTML = status;
            bpStatus.className = className;
        }
        
        function validatePulseRate(pulse, age) {
            const pulseStatus = document.getElementById('pulse_status');
            let status = '';
            let className = '';
            
            if (pulse === 0) {
                status = '';
                className = '';
            } else {
                // Pulse rate ranges based on Vital Signs Chart
                let range = [60, 100]; // Adult default
                
                if (age >= 0 && age <= 1) {
                    // Infant (0-1 year)
                    range = [100, 160];
                } else if (age >= 2 && age <= 2) {
                    // Toddler (1-2 years)
                    range = [90, 150];
                } else if (age >= 3 && age <= 5) {
                    // Preschooler (3-5 years)
                    range = [80, 140];
                } else if (age >= 6 && age <= 12) {
                    // School-age (6-12 years)
                    range = [70, 120];
                } else if (age >= 13 && age <= 18) {
                    // Adolescent (13-18 years)
                    range = [60, 100];
                }
                // Adult (>18 years) uses default range
                
                if (pulse >= range[0] && pulse <= range[1]) {
                    status = 'Normal';
                    className = 'text-success';
                } else if (pulse < range[0]) {
                    status = 'Bradycardia';
                    className = 'text-warning';
                } else {
                    status = 'Tachycardia';
                    className = 'text-danger';
                }
            }
            
            pulseStatus.innerHTML = status;
            pulseStatus.className = className;
        }
        
        function validateRespiratoryRate(respiratory, age) {
            const respiratoryStatus = document.getElementById('respiratory_status');
            let status = '';
            let className = '';
            
            if (respiratory === 0) {
                status = '';
                className = '';
            } else {
                // Respiratory rate ranges based on Vital Signs Chart
                let range = [12, 20]; // Adult default
                
                if (age >= 0 && age <= 1) {
                    // Infant (0-1 year)
                    range = [30, 60];
                } else if (age >= 2 && age <= 2) {
                    // Toddler (1-2 years)
                    range = [24, 40];
                } else if (age >= 3 && age <= 5) {
                    // Preschooler (3-5 years)
                    range = [22, 34];
                } else if (age >= 6 && age <= 12) {
                    // School-age (6-12 years)
                    range = [18, 30];
                } else if (age >= 13 && age <= 18) {
                    // Adolescent (13-18 years)
                    range = [12, 20];
                }
                // Adult (>18 years) uses default range
                
                if (respiratory >= range[0] && respiratory <= range[1]) {
                    status = 'Normal';
                    className = 'text-success';
                } else if (respiratory < range[0]) {
                    status = 'Bradypnea';
                    className = 'text-warning';
                } else {
                    status = 'Tachypnea';
                    className = 'text-danger';
                }
            }
            
            respiratoryStatus.innerHTML = status;
            respiratoryStatus.className = className;
        }
        
        // Add event listeners for vital signs validation
        document.getElementById('bp_systolic').addEventListener('input', validateVitalSigns);
        document.getElementById('bp_diastolic').addEventListener('input', validateVitalSigns);
        document.getElementById('pulse_rate').addEventListener('input', validateVitalSigns);
        document.getElementById('respiratory_rate').addEventListener('input', validateVitalSigns);
        
        // Handle chemical selection - show/hide "Other" input field
        window.handleChemicalSelection = function() {
            const chemicalSelect = document.getElementById('chemical');
            const chemicalOther = document.getElementById('chemical_other');
            
            if (!chemicalSelect || !chemicalOther) {
                return;
            }
            
            if (chemicalSelect.value === 'Other') {
                chemicalOther.style.display = 'block';
                chemicalOther.required = true;
            } else {
                chemicalOther.style.display = 'none';
                chemicalOther.required = false;
                chemicalOther.value = '';
            }
        };
        
        // Initialize chemical selection on page load
        if (document.getElementById('chemical')) {
            handleChemicalSelection();
        }
        
        window.handleBiologicalExposureSelection = function() {
            const biologicalSelect = document.getElementById('biological_exposure');
            const biologicalOther = document.getElementById('biological_exposure_other');
            
            if (!biologicalSelect || !biologicalOther) {
                return;
            }
            
            if (biologicalSelect.value === 'Other') {
                biologicalOther.style.display = 'block';
                biologicalOther.required = true;
            } else {
                biologicalOther.style.display = 'none';
                biologicalOther.required = false;
                biologicalOther.value = '';
            }
        };
        
        // Initialize biological exposure selection on page load
        if (document.getElementById('biological_exposure')) {
            handleBiologicalExposureSelection();
        }
        
        // Clinical findings radio button handling
        const hasClinicalYes = document.getElementById('has_clinical_yes');
        const hasClinicalNo = document.getElementById('has_clinical_no');
        const clinicalElaborationDiv = document.getElementById('clinical_elaboration_div');
        const clinicalElaborationTextarea = document.getElementById('clinical_elaboration');
        
        function updateClinicalElaborationPlaceholder() {
            if (hasClinicalYes.checked) {
                clinicalElaborationTextarea.placeholder = "Describe the health effects currently experienced by the employees...";
            } else if (hasClinicalNo.checked) {
                clinicalElaborationTextarea.placeholder = "Explain why there are no clinical findings or provide additional context...";
            }
        }
        
        // Set initial state - always show elaboration field
        clinicalElaborationDiv.style.display = 'block';
        updateClinicalElaborationPlaceholder();
        
        // Add event listeners
        hasClinicalYes.addEventListener('change', updateClinicalElaborationPlaceholder);
        hasClinicalNo.addEventListener('change', updateClinicalElaborationPlaceholder);
        
        // Custom dropdown functionality
        const workplaceBtn = document.getElementById('workplace_btn');
        const employeeBtn = document.getElementById('employee_btn');
        const workplaceSelect = document.getElementById('workplace_select');
        const patientSelect = document.getElementById('patient_id');
        
        // Load workplaces on page load
        loadWorkplaces();
        
        // Global variables for dropdown state
        let selectedWorkplace = '';
        let selectedEmployee = '';
        
        // Make toggle functions global so they can be called from onclick
        window.toggleWorkplaceDropdown = function() {
            console.log('toggleWorkplaceDropdown called');
            const dropdown = document.getElementById('workplace_dropdown');
            const isOpen = dropdown.classList.contains('show');
            
            // Close all dropdowns first
            closeAllDropdowns();
            
            if (!isOpen) {
                dropdown.classList.add('show');
                workplaceBtn.parentElement.classList.add('show');
                document.getElementById('workplace_search').focus();
                console.log('Workplace dropdown opened');
            } else {
                console.log('Workplace dropdown closed');
            }
        };
        
        window.toggleEmployeeDropdown = function() {
            if (employeeBtn.disabled) return;
            
            const dropdown = document.getElementById('employee_dropdown');
            const isOpen = dropdown.classList.contains('show');
            
            // Close all dropdowns first
            closeAllDropdowns();
            
            if (!isOpen) {
                dropdown.classList.add('show');
                employeeBtn.parentElement.classList.add('show');
                document.getElementById('employee_search').focus();
            }
        };
        
        // Close dropdown functions
        function closeWorkplaceDropdown() {
            document.getElementById('workplace_dropdown').classList.remove('show');
            workplaceBtn.parentElement.classList.remove('show');
        }
        
        function closeEmployeeDropdown() {
            document.getElementById('employee_dropdown').classList.remove('show');
            employeeBtn.parentElement.classList.remove('show');
        }
        
        function closeAllDropdowns() {
            closeWorkplaceDropdown();
            closeEmployeeDropdown();
        }
        
        // Make filter functions global so they can be called from onkeyup
        window.filterWorkplace = function() {
            const input = document.getElementById('workplace_search');
            const filter = input.value.toLowerCase();
            const options = document.querySelectorAll('#workplace_options a');
            
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(filter) ? 'block' : 'none';
            });
        };
        
        window.filterEmployee = function() {
            const input = document.getElementById('employee_search');
            const filter = input.value.toLowerCase();
            const options = document.querySelectorAll('#employee_options a');
            
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(filter) ? 'block' : 'none';
            });
        };
        
        
        // Close dropdowns when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropbtn') && !event.target.matches('.dropdown-content') && !event.target.matches('.dropdown-content *')) {
                closeAllDropdowns();
            }
        }
        
        // Function to load workplaces
        async function loadWorkplaces() {
            try {
                const response = await fetch('api/get_workplaces.php');
                const data = await response.json();
                
                if (data.success) {
                    const optionsContainer = document.getElementById('workplace_options');
                    optionsContainer.innerHTML = '';
                    data.workplaces.forEach(workplace => {
                        const option = document.createElement('a');
                        option.href = '#';
                        option.textContent = workplace;
                        option.onclick = function(e) {
                            e.preventDefault();
                            selectWorkplace(workplace);
                        };
                        optionsContainer.appendChild(option);
                    });
            } else {
                    console.error('Error loading workplaces:', data.error);
                }
            } catch (error) {
                console.error('Error fetching workplaces:', error);
            }
        }
        
        // Function to load employees by workplace
        async function loadEmployeesByWorkplace(workplace) {
            try {
                const response = await fetch(`api/get_employees_by_workplace.php?workplace=${encodeURIComponent(workplace)}`);
                const data = await response.json();
                
                if (data.success) {
                    const optionsContainer = document.getElementById('employee_options');
                    optionsContainer.innerHTML = '';
                    data.employees.forEach(employee => {
                        const option = document.createElement('a');
                        option.href = '#';
                        option.textContent = `${employee.first_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')} ${employee.last_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')}`;
                        option.dataset.employeeId = employee.id;
                        option.onclick = function(e) {
                            e.preventDefault();
                            selectEmployee(employee.id, this.textContent);
                        };
                        optionsContainer.appendChild(option);
                    });
                } else {
                    console.error('Error loading employees:', data.error);
                    const optionsContainer = document.getElementById('employee_options');
                    optionsContainer.innerHTML = '<a href="#" style="color: #dc3545;">No employees found</a>';
                }
            } catch (error) {
                console.error('Error fetching employees:', error);
                const optionsContainer = document.getElementById('employee_options');
                optionsContainer.innerHTML = '<a href="#" style="color: #dc3545;">Error loading employees</a>';
            }
        }
        
        // Function to select workplace
        function selectWorkplace(workplace) {
            selectedWorkplace = workplace;
            workplaceBtn.innerHTML = workplace;
            workplaceSelect.value = workplace;
            closeWorkplaceDropdown();
            
            // Enable employee dropdown and load employees
            employeeBtn.disabled = false;
            loadEmployeesByWorkplace(workplace);
            
            // Clear employee selection
            selectedEmployee = '';
            employeeBtn.innerHTML = `Select Employee`;
            patientSelect.value = '';
        }
        
        // Function to select employee
        function selectEmployee(employeeId, employeeText) {
            selectedEmployee = employeeId;
            employeeBtn.innerHTML = employeeText;
            patientSelect.value = employeeId;
            closeEmployeeDropdown();
        }
        
        
        // Auto-capitalization for text inputs (except email)
        const textInputs = document.querySelectorAll('input[type="text"], textarea');
        textInputs.forEach(input => {
            // Skip email field
            if (input.type === 'email' || input.name === 'email') {
                return;
            }
            
            // Add event listener for auto-capitalization
            input.addEventListener('input', function() {
                const cursorPosition = this.selectionStart;
                const originalValue = this.value;
                
                // Convert to uppercase
                const capitalizedValue = originalValue.toUpperCase();
                
                // Only update if the value actually changed
                if (originalValue !== capitalizedValue) {
                    this.value = capitalizedValue;
                    // Restore cursor position
                    this.setSelectionRange(cursorPosition, cursorPosition);
                }
            });
        });
        
        // BMI Auto-calculation
        const weightInput = document.getElementById('weight');
        const heightInput = document.getElementById('height');
        const bmiInput = document.getElementById('bmi');
        const bmiCategory = document.getElementById('bmi-category');
        
        function calculateBMI() {
            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value);
            
            if (weight && height && height > 0) {
                // Convert height from cm to meters
                const heightInMeters = height / 100;
                // Calculate BMI: weight (kg) / height (m)²
                const bmi = weight / (heightInMeters * heightInMeters);
                
                // Round to 1 decimal place
                const bmiRounded = Math.round(bmi * 10) / 10;
                bmiInput.value = bmiRounded;
                
                // Determine BMI category
                let category = '';
                let categoryClass = '';
                if (bmiRounded < 18.5) {
                    category = 'Underweight';
                    categoryClass = 'text-info';
                } else if (bmiRounded >= 18.5 && bmiRounded < 25) {
                    category = 'Normal weight';
                    categoryClass = 'text-success';
                } else if (bmiRounded >= 25 && bmiRounded < 30) {
                    category = 'Overweight';
                    categoryClass = 'text-warning';
                } else if (bmiRounded >= 30) {
                    category = 'Obese';
                    categoryClass = 'text-danger';
                }
                
                bmiCategory.textContent = category;
                bmiCategory.className = `text-muted ${categoryClass}`;
            } else {
                bmiInput.value = '';
                bmiCategory.textContent = '';
                bmiCategory.className = 'text-muted';
            }
        }
        
        // Add event listeners for weight and height inputs
        weightInput.addEventListener('input', calculateBMI);
        heightInput.addEventListener('input', calculateBMI);
        
        // Function to update employee dropdown based on company selection
        window.updateEmployeeDropdown = function() {
            const companySelect = document.getElementById('company_select');
            const employeeSelect = document.getElementById('employee_select');
            
            if (!companySelect || !employeeSelect) {
                return;
            }
            
            const companyName = companySelect.value;
            
            if (!companyName) {
                // Reset employee dropdown
                employeeSelect.innerHTML = '<option value="">-- Select Company First --</option>';
                employeeSelect.disabled = true;
                return;
            }
            
            // Show loading state
            employeeSelect.innerHTML = '<option value="">Loading employees...</option>';
            employeeSelect.disabled = true;
            
            // Fetch employees for the selected company
            fetch(`get_company_employees.php?company_name=${encodeURIComponent(companyName)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear and populate employee dropdown
                        employeeSelect.innerHTML = '<option value="">-- Select Employee --</option>';
                        
                        data.employees.forEach(employee => {
                            const option = document.createElement('option');
                            option.value = employee.id;
                            option.textContent = `${employee.first_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')} ${employee.last_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')}`;
                            employeeSelect.appendChild(option);
                        });
                        
                        employeeSelect.disabled = false;
                    } else {
                        // Show error
                        employeeSelect.innerHTML = '<option value="">Error loading employees</option>';
                        employeeSelect.disabled = true;
                        console.error('Error fetching employees:', data.message);
                    }
                })
                .catch(error => {
                    // Show error
                    employeeSelect.innerHTML = '<option value="">Error loading employees</option>';
                    employeeSelect.disabled = true;
                    console.error('Error:', error);
                });
        }
        
        // Form initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener for company selection as backup
            const companySelect = document.getElementById('company_select');
            if (companySelect) {
                companySelect.addEventListener('change', function() {
                    updateEmployeeDropdown();
                });
            }
        });
        
        
        // Handle form submission with loading state
        const form = document.querySelector('form');
        const saveBtn = document.getElementById('saveBtn');
        
        // Add custom validation before form submission
        form.addEventListener('submit', function(e) {
            const companySelect = document.getElementById('company_select');
            const employeeSelect = document.getElementById('employee_select');
            
            // Only validate if these elements exist (not in pre-populated mode)
            if (companySelect && employeeSelect) {
                const companyValue = companySelect.value;
                const employeeValue = employeeSelect.value;
                
                // Validate company selection
                if (!companyValue) {
                    e.preventDefault();
                    alert('Please select a company first.');
                    companySelect.focus();
                    return false;
                }
                
                // Validate employee selection
                if (!employeeValue) {
                    e.preventDefault();
                    alert('Please select an employee.');
                    employeeSelect.focus();
                    return false;
                }
            }
            
            // If validation passes, continue with form submission
        });
        
        form.addEventListener('submit', function() {
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            saveBtn.disabled = true;
        });
        
        // Initialize Employee Signature Pad
        const employeeCanvas = document.getElementById('employee-signature-pad');
        if (employeeCanvas) {
            const employeeSignaturePad = new SignaturePad(employeeCanvas);
            
            // Configure signature pad
            employeeSignaturePad.penColor = '#000000';
            
            // Employee signature controls
            const clearEmployeeBtn = document.getElementById('clear-employee');
            const saveEmployeeBtn = document.getElementById('save-employee');
            
            if (clearEmployeeBtn) {
                clearEmployeeBtn.addEventListener('click', () => {
                    employeeSignaturePad.clear();
                    document.getElementById('employee_signature_data').value = '';
                });
            }
            
            if (saveEmployeeBtn) {
                saveEmployeeBtn.addEventListener('click', () => {
                    if (employeeSignaturePad.isEmpty()) {
                        alert('Please sign before saving');
                        return;
                    }
                    const signatureData = employeeSignaturePad.toDataURL();
                    document.getElementById('employee_signature_data').value = signatureData;
                    alert('Employee signature saved!');
                });
            }
        }
    });
    </script>
</body>
</html>
