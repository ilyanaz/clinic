<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

$message = '';
$messageType = '';
$editData = null;
$surveillance_id = null;

// Get surveillance ID from URL
if (isset($_GET['id'])) {
    $surveillance_id = (int)$_GET['id'];
    $editData = getHealthSurveillanceById($surveillance_id);
    
    if (isset($editData['error'])) {
        $message = $editData['error'];
        $messageType = 'danger';
    }
} else {
    header('Location: ' . app_url('surveillance_list.php'));
    exit();
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && ($_POST['action'] == 'save_surveillance' || $_POST['action'] == 'submit_surveillance')) {
        try {
            // Prepare health surveillance data for update
            $healthData = [
                'surveillance_id' => $surveillance_id,
                'patient_id' => sanitizeInput($_POST['patient_id']),
                'workplace' => sanitizeInput($_POST['workplace']),
                // Handle chemical selection - if "Other" is selected, use the other field value
                'chemical' => ($_POST['chemical'] ?? '') === 'Other' && !empty($_POST['chemical_other']) 
                    ? sanitizeInput($_POST['chemical_other']) 
                    : sanitizeInput($_POST['chemical'] ?? ''),
                'examination_type' => sanitizeInput($_POST['examination_type']),
                'examination_date' => $_POST['examination_date'],
                'examiner_name' => sanitizeInput($_POST['examiner_name']),
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
                // Physical examination
                'weight' => $_POST['weight'] ?? '0.00',
                'height' => $_POST['height'] ?? '0.00',
                'BMI' => $_POST['BMI'] ?? '0.0',
                'blood_pressure' => sanitizeInput($_POST['blood_pressure'] ?? ''),
                // Parse blood pressure if in format "120/80"
                'blood_pressure_systolic' => !empty($_POST['blood_pressure']) && strpos($_POST['blood_pressure'], '/') !== false ? trim(explode('/', $_POST['blood_pressure'])[0]) : '',
                'blood_pressure_diastolic' => !empty($_POST['blood_pressure']) && strpos($_POST['blood_pressure'], '/') !== false ? trim(explode('/', $_POST['blood_pressure'])[1] ?? '') : '',
                'pulse_rate' => $_POST['pulse_rate'] ?? '0',
                'respiratory_rate' => $_POST['respiratory_rate'] ?? '0',
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
                'clinical_findings' => $_POST['clinical_findings'] ?? 'No',
                'target_organ' => $_POST['target_organ'] ?? 'No',
                'biological_monitoring' => $_POST['biological_monitoring'] ?? 'No',
                'pregnancy_breast_feeding' => $_POST['pregnancy_breast_feeding'] ?? 'No',
                'clinical_work_related' => $_POST['clinical_work_related'] ?? 'No',
                'organ_work_related' => $_POST['organ_work_related'] ?? 'No',
                'biological_work_related' => $_POST['biological_work_related'] ?? 'No',
                'fitness_status' => $_POST['fitness_status'] ?? 'Not specified',
                'final_assessment' => $_POST['fitness_status'] ?? null,
                // Recommendations
                'recommendations_type' => isset($_POST['recommendations_type']) ? implode(',', $_POST['recommendations_type']) : '',
                'date_of_MRP' => $_POST['date_of_MRP'] ?? '',
                'next_review_date' => $_POST['next_review_date'] ?? '',
                'recommendations_notes' => sanitizeInput($_POST['recommendations_notes'] ?? ''),
                // Biological monitoring
                // Handle biological exposure selection - if "Other" is selected, use the other field value
                'biological_exposure' => (isset($_POST['biological_exposure']) && $_POST['biological_exposure'] === 'Other' && !empty($_POST['biological_exposure_other'])) 
                    ? sanitizeInput($_POST['biological_exposure_other']) 
                    : sanitizeInput($_POST['biological_exposure'] ?? ''),
                'result_baseline' => sanitizeInput($_POST['result_baseline'] ?? ''),
                'result_annual' => sanitizeInput($_POST['result_annual'] ?? ''),
                // Respirator assessment
                'respirator_result' => $_POST['respirator_result'] ?? '',
                'respirator_justification' => sanitizeInput($_POST['respirator_justification'] ?? ''),
                // Target organ function
                'blood_count' => $_POST['blood_count'] ?? 'Normal',
                'renal_function' => $_POST['renal_function'] ?? 'Normal',
                'blood_comment' => sanitizeInput($_POST['blood_comment'] ?? ''),
                'renal_comment' => sanitizeInput($_POST['renal_comment'] ?? ''),
                // Clinical findings - handle has_clinical_findings radio button and elaboration textarea
                'has_clinical_findings' => $_POST['has_clinical_findings'] ?? 'No',
                'clinical_elaboration' => sanitizeInput($_POST['clinical_findings'] ?? '')
            ];
            
            // Update surveillance data
            $result = updateHealthSurveillance($healthData);
            
            if ($result['success']) {
                // Get company_id from patient's occupational history for redirect
                $patient_id_for_redirect = $healthData['patient_id'];
                $company_id_for_redirect = 0;
                
                try {
                    $stmt = $clinic_pdo->prepare("
                        SELECT c.id as company_id 
                        FROM company c
                        INNER JOIN occupational_history oh ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(oh.company_name))
                        WHERE oh.patient_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$patient_id_for_redirect]);
                    $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $company_id_for_redirect = $company_data ? $company_data['company_id'] : 0;
                } catch (Exception $e) {
                    error_log("Error getting company_id for redirect: " . $e->getMessage());
                }
                
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
                if ($is_not_fit && $company_id_for_redirect > 0) {
                    // Get patient name for pre-filling
                    $patient_name = '';
                    try {
                        $patient_stmt = $clinic_pdo->prepare("SELECT first_name, last_name FROM patient_information WHERE id = ?");
                        $patient_stmt->execute([$patient_id_for_redirect]);
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
                        $company_stmt->execute([$company_id_for_redirect]);
                        $company_info = $company_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($company_info) {
                            $company_name = $company_info['company_name'];
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching company name: " . $e->getMessage());
                    }
                    
                    $redirect_url = "medical_removal_protection.php?patient_id=" . $patient_id_for_redirect . "&patient_name=" . urlencode($patient_name) . "&employer=" . urlencode($company_name) . "&message=" . urlencode('Surveillance record updated successfully! Please complete the medical removal protection form.') . "&type=success";
                    error_log("Redirecting to medical removal protection: " . $redirect_url);
                } else {
                    // Redirect to surveillance_list.php with success message
                    $redirect_url = "surveillance_list.php?patient_id=" . $patient_id_for_redirect;
                    if ($company_id_for_redirect > 0) {
                        $redirect_url .= "&company_id=" . $company_id_for_redirect;
                    }
                    $redirect_url .= "&message=" . urlencode('Surveillance record updated successfully!') . "&type=success";
                    error_log("About to redirect to: " . $redirect_url);
                }
                
                header('Location: ' . $redirect_url);
                exit();
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error updating surveillance record: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Health Surveillance - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
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
            background-image: app_url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
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
        
        .fixed-save-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .fixed-save-button:hover {
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
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
        
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-end align-items-center mb-4">
                    <div>
                        <a href="<?php echo app_url('surveillance_list.php'); ?>" class="btn btn-outline-primary">
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
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Medical Surveillance</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Edit the health surveillance record below. All fields are optional except for patient selection. Complete the sections that are relevant to the patient's health assessment.
                        </div>
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($editData && !isset($editData['error'])): ?>
                <form method="POST" action="surveillance_edit.php?id=<?php echo $surveillance_id; ?>" id="surveillanceEditForm">
                    <input type="hidden" name="action" value="save_surveillance" id="formAction">
                    <input type="hidden" name="surveillance_id" value="<?php echo $surveillance_id; ?>">
                    <input type="hidden" name="patient_id" value="<?php echo $editData['surveillance']['patient_id']; ?>">
                    
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
                                                <?php echo htmlspecialchars($editData['surveillance']['examiner_name'] ?? 'System Administrator'); ?>
                                            </div>
                                            <input type="hidden" name="examiner_name" value="<?php echo htmlspecialchars($editData['surveillance']['examiner_name'] ?? 'System Administrator'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Examination Date</label>
                                            <div class="info-display">
                                                <?php echo isset($editData['surveillance']['examination_date']) ? date('d/m/Y', strtotime($editData['surveillance']['examination_date'])) : date('d/m/Y'); ?>
                                            </div>
                                            <input type="hidden" name="examination_date" value="<?php echo isset($editData['surveillance']['examination_date']) ? $editData['surveillance']['examination_date'] : date('Y-m-d'); ?>">
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
                                            <div class="info-display">
                                                <?php echo htmlspecialchars($editData['surveillance']['workplace'] ?? ''); ?>
                                            </div>
                                            <input type="hidden" name="workplace" value="<?php echo htmlspecialchars($editData['surveillance']['workplace'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Employee Name <span class="text-danger">*</span></label>
                                            <div class="info-display">
                                                <?php echo htmlspecialchars($editData['surveillance']['first_name'] . ' ' . $editData['surveillance']['last_name']); ?>
                                            </div>
                                            <input type="hidden" name="patient_id" value="<?php echo $editData['surveillance']['patient_id']; ?>">
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
                                                <option value="Lead (Inorganic & Organic)" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Lead (Inorganic & Organic)') ? 'selected' : ''; ?>>Lead (Inorganic & Organic)</option>
                                                <option value="Organophosphate pesticides" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Organophosphate pesticides') ? 'selected' : ''; ?>>Organophosphate pesticides</option>
                                                <option value="Benzene" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Benzene') ? 'selected' : ''; ?>>Benzene</option>
                                                <option value="Carbon Disulphide" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Carbon Disulphide') ? 'selected' : ''; ?>>Carbon Disulphide</option>
                                                <option value="n-Hexane" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'n-Hexane') ? 'selected' : ''; ?>>n-Hexane</option>
                                                <option value="Trichloroethylene" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Trichloroethylene') ? 'selected' : ''; ?>>Trichloroethylene</option>
                                                <option value="Arsenic (inorganic)" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Arsenic (inorganic)') ? 'selected' : ''; ?>>Arsenic (inorganic)</option>
                                                <option value="Cadmium" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Cadmium') ? 'selected' : ''; ?>>Cadmium</option>
                                                <option value="Chromium VI" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Chromium VI') ? 'selected' : ''; ?>>Chromium VI</option>
                                                <option value="Mercury" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Mercury') ? 'selected' : ''; ?>>Mercury</option>
                                                <option value="Nickel" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Nickel') ? 'selected' : ''; ?>>Nickel</option>
                                                <option value="Manganese" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Manganese') ? 'selected' : ''; ?>>Manganese</option>
                                                <option value="Toluene" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Toluene') ? 'selected' : ''; ?>>Toluene</option>
                                                <option value="Xylene" <?php echo (isset($editData['surveillance']['chemical']) && $editData['surveillance']['chemical'] == 'Xylene') ? 'selected' : ''; ?>>Xylene</option>
                                                <option value="Other" <?php echo (isset($editData['surveillance']['chemical']) && !in_array($editData['surveillance']['chemical'], ['Lead (Inorganic & Organic)', 'Organophosphate pesticides', 'Benzene', 'Carbon Disulphide', 'n-Hexane', 'Trichloroethylene', 'Arsenic (inorganic)', 'Cadmium', 'Chromium VI', 'Mercury', 'Nickel', 'Manganese', 'Toluene', 'Xylene'])) ? 'selected' : ''; ?>>Other (Specify)</option>
                                            </select>
                                            <input type="text" class="form-control mt-2" id="chemical_other" name="chemical_other" placeholder="Specify other chemical" style="display: <?php echo (isset($editData['surveillance']['chemical']) && !in_array($editData['surveillance']['chemical'], ['Lead (Inorganic & Organic)', 'Organophosphate pesticides', 'Benzene', 'Carbon Disulphide', 'n-Hexane', 'Trichloroethylene', 'Arsenic (inorganic)', 'Cadmium', 'Chromium VI', 'Mercury', 'Nickel', 'Manganese', 'Toluene', 'Xylene'])) ? 'block' : 'none'; ?>;" 
                                                   value="<?php echo (isset($editData['surveillance']['chemical']) && !in_array($editData['surveillance']['chemical'], ['Lead (Inorganic & Organic)', 'Organophosphate pesticides', 'Benzene', 'Carbon Disulphide', 'n-Hexane', 'Trichloroethylene', 'Arsenic (inorganic)', 'Cadmium', 'Chromium VI', 'Mercury', 'Nickel', 'Manganese', 'Toluene', 'Xylene'])) ? htmlspecialchars($editData['surveillance']['chemical']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="examination_type" class="form-label">Type of Medical Examination <span class="required">*</span></label>
                                            <select class="form-select" id="examination_type" name="examination_type" required>
                                                <option value="">Select examination type</option>
                                                <option value="Pre-employment" <?php echo ($editData['surveillance']['examination_type'] == 'Pre-employment') ? 'selected' : ''; ?>>Pre-employment</option>
                                                <option value="Periodic" <?php echo ($editData['surveillance']['examination_type'] == 'Periodic') ? 'selected' : ''; ?>>Periodic</option>
                                                <option value="Return to work" <?php echo ($editData['surveillance']['examination_type'] == 'Return to work') ? 'selected' : ''; ?>>Return to work</option>
                                                <option value="Exit" <?php echo ($editData['surveillance']['examination_type'] == 'Exit') ? 'selected' : ''; ?>>Exit</option>
                                               
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
                                                    <input class="form-check-input" type="radio" name="breathing_difficulty" value="Yes" <?php echo (!empty($editData['health_history']['breathing_difficulty']) && $editData['health_history']['breathing_difficulty'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="breathing_difficulty" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Cough</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="cough" value="Yes" <?php echo (!empty($editData['health_history']['cough']) && $editData['health_history']['cough'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="cough" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Sore throat</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="sore_throat" value="Yes" <?php echo (!empty($editData['health_history']['sore_throat']) && $editData['health_history']['sore_throat'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="sore_throat" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Sneezing</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="sneezing" value="Yes" <?php echo (!empty($editData['health_history']['sneezing']) && $editData['health_history']['sneezing'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="sneezing" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Chest Pain</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="chest_pain" value="Yes" <?php echo (!empty($editData['health_history']['chest_pain']) && $editData['health_history']['chest_pain'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="chest_pain" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Palpitation</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="palpitation" value="Yes" <?php echo (!empty($editData['health_history']['palpitation']) && $editData['health_history']['palpitation'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="palpitation" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Limb oedema</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="limb_oedema" value="Yes" <?php echo (!empty($editData['health_history']['limb_oedema']) && $editData['health_history']['limb_oedema'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="limb_oedema" value="No" <?php echo ''; ?>>
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
                                                    <input class="form-check-input" type="radio" name="drowsiness" value="Yes" <?php echo (!empty($editData['health_history']['drowsiness']) && $editData['health_history']['drowsiness'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="drowsiness" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Dizziness</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="dizziness" value="Yes" <?php echo (!empty($editData['health_history']['dizziness']) && $editData['health_history']['dizziness'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="dizziness" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Headache</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="headache" value="Yes" <?php echo (!empty($editData['health_history']['headache']) && $editData['health_history']['headache'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="headache" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Confusion</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="confusion" value="Yes" <?php echo (!empty($editData['health_history']['confusion']) && $editData['health_history']['confusion'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="confusion" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Lethargy</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="lethargy" value="Yes" <?php echo (!empty($editData['health_history']['lethargy']) && $editData['health_history']['lethargy'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="lethargy" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Nausea</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="nausea" value="Yes" <?php echo (!empty($editData['health_history']['nausea']) && $editData['health_history']['nausea'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="nausea" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Vomiting</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="vomiting" value="Yes" <?php echo (!empty($editData['health_history']['vomiting']) && $editData['health_history']['vomiting'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="vomiting" value="No" <?php echo ''; ?>>
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
                                                    <input class="form-check-input" type="radio" name="eye_irritations" value="Yes" <?php echo (!empty($editData['health_history']['eye_irritations']) && $editData['health_history']['eye_irritations'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="eye_irritations" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Blurred vision</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="blurred_vision" value="Yes" <?php echo (!empty($editData['health_history']['blurred_vision']) && $editData['health_history']['blurred_vision'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="blurred_vision" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Blisters</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="blisters" value="Yes" <?php echo (!empty($editData['health_history']['blisters']) && $editData['health_history']['blisters'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="blisters" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Burns</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="burns" value="Yes" <?php echo (!empty($editData['health_history']['burns']) && $editData['health_history']['burns'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="burns" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Itching</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="itching" value="Yes" <?php echo (!empty($editData['health_history']['itching']) && $editData['health_history']['itching'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="itching" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Rash</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="rash" value="Yes" <?php echo (!empty($editData['health_history']['rash']) && $editData['health_history']['rash'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="rash" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Redness</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="redness" value="Yes" <?php echo (!empty($editData['health_history']['redness']) && $editData['health_history']['redness'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="redness" value="No" <?php echo ''; ?>>
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
                                                    <input class="form-check-input" type="radio" name="abdominal_pain" value="Yes" <?php echo (!empty($editData['health_history']['abdominal_pain']) && $editData['health_history']['abdominal_pain'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="abdominal_pain" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Abdominal mass</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="abdominal_mass" value="Yes" <?php echo (!empty($editData['health_history']['abdominal_mass']) && $editData['health_history']['abdominal_mass'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="abdominal_mass" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Jaundice</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="jaundice" value="Yes" <?php echo (!empty($editData['health_history']['jaundice']) && $editData['health_history']['jaundice'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="jaundice" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Diarrhoea</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="diarrhoea" value="Yes" <?php echo (!empty($editData['health_history']['diarrhoea']) && $editData['health_history']['diarrhoea'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="diarrhoea" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Loss of weight</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="loss_of_weight" value="Yes" <?php echo (!empty($editData['health_history']['loss_of_weight']) && $editData['health_history']['loss_of_weight'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="loss_of_weight" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Loss of appetite</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="loss_of_appetite" value="Yes" <?php echo (!empty($editData['health_history']['loss_of_appetite']) && $editData['health_history']['loss_of_appetite'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="loss_of_appetite" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Dysuria</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="dysuria" value="Yes" <?php echo (!empty($editData['health_history']['dysuria']) && $editData['health_history']['dysuria'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="dysuria" value="No" <?php echo ''; ?>>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size: 0.8rem;">Haematuria</td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="haematuria" value="Yes" <?php echo (!empty($editData['health_history']['haematuria']) && $editData['health_history']['haematuria'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="haematuria" value="No" <?php echo ''; ?>>
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
                                      placeholder="Elaborate the frequency & severity..."><?php echo isset($editData['health_history']['others_symptoms']) ? htmlspecialchars($editData['health_history']['others_symptoms']) : ''; ?></textarea>
                        </div>
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
                                    <input class="form-check-input" type="radio" name="has_clinical_findings" value="Yes" id="has_clinical_yes" <?php echo (isset($editData['clinical_findings']['result_clinical_findings']) && $editData['clinical_findings']['result_clinical_findings'] === 'Yes') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_clinical_yes">Yes</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="has_clinical_findings" value="No" id="has_clinical_no" <?php echo (isset($editData['clinical_findings']['result_clinical_findings']) && $editData['clinical_findings']['result_clinical_findings'] === 'No') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_clinical_no">No</label>
                                </div>
                            </div>
                            
                            <!-- Elaboration Textarea (shown for both Yes and No) -->
                            <div class="mb-3" id="clinical_elaboration_div">
                                <label class="form-label"><strong>Please provide details: <span class="required">*</span></strong></label>
                                <textarea class="form-control" id="clinical_elaboration" name="clinical_findings" rows="6" 
                                          placeholder="If Yes: Describe the health effects currently experienced by the employees...&#10;If No: Explain why there are no clinical findings or provide additional context..."><?php echo !empty($editData['clinical_findings']['elaboration']) ? htmlspecialchars($editData['clinical_findings']['elaboration']) : (!empty($editData['surveillance']['clinical_findings']) ? htmlspecialchars($editData['surveillance']['clinical_findings']) : ''); ?></textarea>
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
                        
                        <!-- Physical Information Tab -->
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
                                        <td><input type="number" class="form-control form-control-sm" name="weight" id="weight" step="0.1" required value="<?php echo isset($editData['physical_exam']['weight']) ? htmlspecialchars($editData['physical_exam']['weight']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>Height (cm) <span class="required">*</span></td>
                                        <td><input type="number" class="form-control form-control-sm" name="height" id="height" required value="<?php echo isset($editData['physical_exam']['height']) ? htmlspecialchars($editData['physical_exam']['height']) : ''; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td>BMI</td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="bmi" id="bmi" step="0.1" readonly value="<?php echo isset($editData['physical_exam']['BMI']) ? htmlspecialchars($editData['physical_exam']['BMI']) : ''; ?>">
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
                                                    <input type="number" class="form-control form-control-sm" name="blood_pressure_systolic" id="bp_systolic" placeholder="Systolic" min="40" max="200" required value="<?php echo isset($editData['physical_exam']['bp_systolic']) ? htmlspecialchars($editData['physical_exam']['bp_systolic']) : (isset($editData['physical_exam']['blood_pressure']) && strpos($editData['physical_exam']['blood_pressure'], '/') !== false ? trim(explode('/', $editData['physical_exam']['blood_pressure'])[0]) : ''); ?>">
                                                   
                                                </div>
                                                <div class="col-6">
                                                    <input type="number" class="form-control form-control-sm" name="blood_pressure_diastolic" id="bp_diastolic" placeholder="Diastolic" min="20" max="120" required value="<?php echo isset($editData['physical_exam']['bp_distolic']) ? htmlspecialchars($editData['physical_exam']['bp_distolic']) : (isset($editData['physical_exam']['blood_pressure']) && strpos($editData['physical_exam']['blood_pressure'], '/') !== false ? trim(explode('/', $editData['physical_exam']['blood_pressure'])[1] ?? '') : ''); ?>">
                                                    
                                                </div>
                                            </div>
                                            <div id="bp_status" class="mt-1"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Pulse Rate (beats/min) <span class="required">*</span></td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="pulse_rate" id="pulse_rate" min="40" max="220" required value="<?php echo isset($editData['physical_exam']['pulse_rate']) ? htmlspecialchars($editData['physical_exam']['pulse_rate']) : ''; ?>">
                                            <div id="pulse_status" class="mt-1"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Respiratory Rate (breaths/min) <span class="required">*</span></td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="respiratory_rate" id="respiratory_rate" min="8" max="60" required value="<?php echo isset($editData['physical_exam']['respiratory_rate']) ? htmlspecialchars($editData['physical_exam']['respiratory_rate']) : ''; ?>">
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
                                                <input class="form-check-input" type="radio" name="general_appearance" value="Normal" id="general_appearance_normal" required <?php echo (isset($editData['physical_exam']['general_appearance']) && $editData['physical_exam']['general_appearance'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="general_appearance_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="general_appearance" value="Abnormal" id="general_appearance_abnormal" required <?php echo (isset($editData['physical_exam']['general_appearance']) && $editData['physical_exam']['general_appearance'] == 'Abnormal') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="s1_s2" value="Yes" id="s1_s2_yes" required <?php echo (!empty($editData['physical_exam']['s1_s2']) && $editData['physical_exam']['s1_s2'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="s1_s2_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="s1_s2" value="No" id="s1_s2_no" required <?php echo (!empty($editData['physical_exam']['s1_s2']) && $editData['physical_exam']['s1_s2'] == 'No') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="s1_s2_no">No</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Murmur <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="murmur" value="Yes" id="murmur_yes" required <?php echo (!empty($editData['physical_exam']['murmur']) && $editData['physical_exam']['murmur'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="murmur_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="murmur" value="No" id="murmur_no" required <?php echo (!empty($editData['physical_exam']['murmur']) && $editData['physical_exam']['murmur'] == 'No') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="murmur_no">No</label>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Ear, Nose and Throat -->
                                    <tr>
                                        <td><strong>(ii) Ear, nose and throat: <span class="required">*</span></strong></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="ent" value="Normal" id="ent_normal" required <?php echo (isset($editData['physical_exam']['ear_nose_throat']) && $editData['physical_exam']['ear_nose_throat'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="ent_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="ent" value="Abnormal" id="ent_abnormal" required <?php echo (isset($editData['physical_exam']['ear_nose_throat']) && $editData['physical_exam']['ear_nose_throat'] == 'Abnormal') ? 'checked' : ''; ?>>
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
                                                    <input type="text" class="form-control form-control-sm" name="visual_acuity_right" required value="<?php echo isset($editData['physical_exam']['visual_acuity_right']) ? htmlspecialchars($editData['physical_exam']['visual_acuity_right']) : ''; ?>">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small">L:</label>
                                                    <input type="text" class="form-control form-control-sm" name="visual_acuity_left" required value="<?php echo isset($editData['physical_exam']['visual_acuity_left']) ? htmlspecialchars($editData['physical_exam']['visual_acuity_left']) : ''; ?>">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Colour Blindness <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="colour_blindness" value="Yes" id="colour_blindness_yes" required <?php echo (!empty($editData['physical_exam']['colour_blindness']) && $editData['physical_exam']['colour_blindness'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="colour_blindness_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="colour_blindness" value="No" id="colour_blindness_no" required <?php echo (!empty($editData['physical_exam']['colour_blindness']) && $editData['physical_exam']['colour_blindness'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="gi_tenderness" value="Yes" id="gi_tenderness_yes" required <?php echo (!empty($editData['physical_exam']['gi_tenderness']) && $editData['physical_exam']['gi_tenderness'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="gi_tenderness_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="gi_tenderness" value="No" id="gi_tenderness_no" required <?php echo (!empty($editData['physical_exam']['gi_tenderness']) && $editData['physical_exam']['gi_tenderness'] == 'No') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="gi_tenderness_no">No</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Abdominal Mass <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="abdominal_mass_exam" value="Yes" id="abdominal_mass_exam_yes" required <?php echo (!empty($editData['physical_exam']['abdominal_mass_exam']) && $editData['physical_exam']['abdominal_mass_exam'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="abdominal_mass_exam_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="abdominal_mass_exam" value="No" id="abdominal_mass_exam_no" required <?php echo (!empty($editData['physical_exam']['abdominal_mass_exam']) && $editData['physical_exam']['abdominal_mass_exam'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="lymph_nodes" value="Palpable" id="lymph_nodes_palpable" required <?php echo (!empty($editData['physical_exam']['lymph_nodes']) && $editData['physical_exam']['lymph_nodes'] == 'Palpable') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="lymph_nodes_palpable">Palpable</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="lymph_nodes" value="Non-palpable" id="lymph_nodes_non_palpable" required <?php echo (!empty($editData['physical_exam']['lymph_nodes']) && $editData['physical_exam']['lymph_nodes'] == 'Non-palpable') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="lymph_nodes_non_palpable">Non-palpable</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Splenomegaly <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="splenomegaly" value="Yes" id="splenomegaly_yes" required <?php echo (!empty($editData['physical_exam']['splenomegaly']) && $editData['physical_exam']['splenomegaly'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="splenomegaly_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="splenomegaly" value="No" id="splenomegaly_no" required <?php echo (!empty($editData['physical_exam']['splenomegaly']) && $editData['physical_exam']['splenomegaly'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="kidney_tenderness" value="Yes" id="kidney_tenderness_yes" required <?php echo (!empty($editData['physical_exam']['kidney_tenderness']) && $editData['physical_exam']['kidney_tenderness'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="kidney_tenderness_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="kidney_tenderness" value="No" id="kidney_tenderness_no" required <?php echo (!empty($editData['physical_exam']['kidney_tenderness']) && $editData['physical_exam']['kidney_tenderness'] == 'No') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="kidney_tenderness_no">No</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Ballotable <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="ballotable" value="Yes" id="ballotable_yes" required <?php echo (!empty($editData['physical_exam']['ballotable']) && $editData['physical_exam']['ballotable'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="ballotable_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="ballotable" value="No" id="ballotable_no" required <?php echo (!empty($editData['physical_exam']['ballotable']) && $editData['physical_exam']['ballotable'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="liver_jaundice" value="Yes" id="liver_jaundice_yes" required <?php echo (!empty($editData['physical_exam']['liver_jaundice']) && $editData['physical_exam']['liver_jaundice'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="liver_jaundice_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="liver_jaundice" value="No" id="liver_jaundice_no" required <?php echo (!empty($editData['physical_exam']['liver_jaundice']) && $editData['physical_exam']['liver_jaundice'] == 'No') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="liver_jaundice_no">No</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Hepatomegaly <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="hepatomegaly" value="Yes" id="hepatomegaly_yes" required <?php echo (!empty($editData['physical_exam']['hepatomegaly']) && $editData['physical_exam']['hepatomegaly'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="hepatomegaly_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="hepatomegaly" value="No" id="hepatomegaly_no" required <?php echo (!empty($editData['physical_exam']['hepatomegaly']) && $editData['physical_exam']['hepatomegaly'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="muscle_tone" value="1" id="muscle_tone_1" required <?php echo (!empty($editData['physical_exam']['muscle_tone']) && $editData['physical_exam']['muscle_tone'] == '1') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tone_1">1</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tone" value="2" id="muscle_tone_2" required <?php echo (!empty($editData['physical_exam']['muscle_tone']) && $editData['physical_exam']['muscle_tone'] == '2') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tone_2">2</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tone" value="3" id="muscle_tone_3" required <?php echo (!empty($editData['physical_exam']['muscle_tone']) && $editData['physical_exam']['muscle_tone'] == '3') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tone_3">3</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tone" value="4" id="muscle_tone_4" required <?php echo (!empty($editData['physical_exam']['muscle_tone']) && $editData['physical_exam']['muscle_tone'] == '4') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tone_4">4</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tone" value="5" id="muscle_tone_5" required <?php echo (!empty($editData['physical_exam']['muscle_tone']) && $editData['physical_exam']['muscle_tone'] == '5') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tone_5">5</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Muscle Tenderness <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tenderness" value="Yes" id="muscle_tenderness_yes" required <?php echo (!empty($editData['physical_exam']['muscle_tenderness']) && $editData['physical_exam']['muscle_tenderness'] == 'Yes') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="muscle_tenderness_yes">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="muscle_tenderness" value="No" id="muscle_tenderness_no" required <?php echo (!empty($editData['physical_exam']['muscle_tenderness']) && $editData['physical_exam']['muscle_tenderness'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="power" value="1" id="power_1" required <?php echo (!empty($editData['physical_exam']['power']) && $editData['physical_exam']['power'] == '1') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="power_1">1</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="power" value="2" id="power_2" required <?php echo (!empty($editData['physical_exam']['power']) && $editData['physical_exam']['power'] == '2') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="power_2">2</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="power" value="3" id="power_3" required <?php echo (!empty($editData['physical_exam']['power']) && $editData['physical_exam']['power'] == '3') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="power_3">3</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="power" value="4" id="power_4" required <?php echo (!empty($editData['physical_exam']['power']) && $editData['physical_exam']['power'] == '4') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="power_4">4</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="power" value="5" id="power_5" required <?php echo (!empty($editData['physical_exam']['power']) && $editData['physical_exam']['power'] == '5') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="power_5">5</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Sensation <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="sensation" value="Normal" id="sensation_normal" required <?php echo (isset($editData['physical_exam']['sensation']) && $editData['physical_exam']['sensation'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="sensation_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="sensation" value="Abnormal" id="sensation_abnormal" required <?php echo (isset($editData['physical_exam']['sensation']) && $editData['physical_exam']['sensation'] == 'Abnormal') ? 'checked' : ''; ?>>
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
                                        <td style="padding-left: 30px;">Sound <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="respiratory_findings" value="Clear" id="respiratory_clear" required <?php echo (isset($editData['physical_exam']['respiratory_findings']) && $editData['physical_exam']['respiratory_findings'] == 'Clear') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="respiratory_clear">Clear</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="respiratory_findings" value="Rhonchi" id="respiratory_rhonchi" required <?php echo (isset($editData['physical_exam']['respiratory_findings']) && $editData['physical_exam']['respiratory_findings'] == 'Rhonchi') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="respiratory_rhonchi">Rhonchi</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="respiratory_findings" value="Crepitus" id="respiratory_crepitus" required <?php echo (isset($editData['physical_exam']['respiratory_findings']) && $editData['physical_exam']['respiratory_findings'] == 'Crepitus') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="respiratory_crepitus">Crepitus</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-left: 30px;">Air entry <span class="required">*</span></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="air_entry" value="Normal" id="air_entry_normal" required <?php echo (isset($editData['physical_exam']['air_entry']) && $editData['physical_exam']['air_entry'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="air_entry_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="air_entry" value="Abnormal" id="air_entry_abnormal" required <?php echo (isset($editData['physical_exam']['air_entry']) && $editData['physical_exam']['air_entry'] == 'Abnormal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="air_entry_abnormal">Abnormal</label>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Reproductive -->
                                    <tr>
                                        <td><strong>(xi) Reproductive: <span class="required">*</span></strong></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="reproductive" value="Normal" id="reproductive_normal" required <?php echo (isset($editData['physical_exam']['reproductive']) && $editData['physical_exam']['reproductive'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="reproductive_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="reproductive" value="Abnormal" id="reproductive_abnormal" required <?php echo (isset($editData['physical_exam']['reproductive']) && $editData['physical_exam']['reproductive'] == 'Abnormal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="reproductive_abnormal">Abnormal</label>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Skin -->
                                    <tr>
                                        <td><strong>(xii) Skin: <span class="required">*</span></strong></td>
                                        <td>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="skin" value="Normal" id="skin_normal" required <?php echo (isset($editData['physical_exam']['skin']) && $editData['physical_exam']['skin'] == 'Normal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="skin_normal">Normal</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="skin" value="Abnormal" id="skin_abnormal" required <?php echo (isset($editData['physical_exam']['skin']) && $editData['physical_exam']['skin'] == 'Abnormal') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="skin_abnormal">Abnormal</label>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Others -->
                                    <tr>
                                        <td><strong>(xiii) Others: <span class="required">*</span></strong></td>
                                        <td><input type="text" class="form-control form-control-sm" name="others_exam" required value="<?php echo isset($editData['physical_exam']['others_exam']) ? htmlspecialchars($editData['physical_exam']['others_exam']) : ''; ?>"></td>
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
                                                        <input class="form-check-input" type="radio" name="blood_count" value="Normal" id="blood_count_normal" required <?php echo (!empty($editData['target_organ']['blood_count']) && $editData['target_organ']['blood_count'] == 'Normal') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="blood_count_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="blood_count" value="Abnormal" id="blood_count_abnormal" required <?php echo (!empty($editData['target_organ']['blood_count']) && $editData['target_organ']['blood_count'] == 'Abnormal') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="blood_count_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="blood_comment" name="blood_comment" placeholder="Enter comments" value="<?php echo isset($editData['target_organ']['blood_comment']) ? htmlspecialchars($editData['target_organ']['blood_comment']) : ''; ?>">
                                                </td>
                                            </tr>
                                            
                                            <!-- Renal Function Test -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Renal Function Test</td>
                                                <td>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="renal_function" value="Normal" id="renal_function_normal" required <?php echo (!empty($editData['target_organ']['renal_function']) && $editData['target_organ']['renal_function'] == 'Normal') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="renal_function_normal">Normal</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="renal_function" value="Abnormal" id="renal_function_abnormal" required <?php echo (!empty($editData['target_organ']['renal_function']) && $editData['target_organ']['renal_function'] == 'Abnormal') ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="renal_function_abnormal">Abnormal</label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" id="renal_comment" name="renal_comment" placeholder="Enter comments" value="<?php echo isset($editData['target_organ']['renal_comment']) ? htmlspecialchars($editData['target_organ']['renal_comment']) : ''; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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
                                                        <option value="Blood Lead Level (BLL)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Blood Lead Level (BLL)') ? 'selected' : ''; ?>>Blood Lead Level (BLL)</option>
                                                        <option value="RBC Cholinesterase" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'RBC Cholinesterase') ? 'selected' : ''; ?>>RBC Cholinesterase</option>
                                                        <option value="Plasma Cholinesterase" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Plasma Cholinesterase') ? 'selected' : ''; ?>>Plasma Cholinesterase</option>
                                                        <option value="S-PMA (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'S-PMA (urine)') ? 'selected' : ''; ?>>S-PMA (urine)</option>
                                                        <option value="TTCA (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'TTCA (urine)') ? 'selected' : ''; ?>>TTCA (urine)</option>
                                                        <option value="2,5-Hexanedione (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == '2,5-Hexanedione (urine)') ? 'selected' : ''; ?>>2,5-Hexanedione (urine)</option>
                                                        <option value="TCA (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'TCA (urine)') ? 'selected' : ''; ?>>TCA (urine)</option>
                                                        <option value="Urinary As + metabolites" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary As + metabolites') ? 'selected' : ''; ?>>Urinary As + metabolites</option>
                                                        <option value="Urinary Cd" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary Cd') ? 'selected' : ''; ?>>Urinary Cd</option>
                                                        <option value="Blood Cd" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Blood Cd') ? 'selected' : ''; ?>>Blood Cd</option>
                                                        <option value="Urinary total chromium" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary total chromium') ? 'selected' : ''; ?>>Urinary total chromium</option>
                                                        <option value="Urinary mercury" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary mercury') ? 'selected' : ''; ?>>Urinary mercury</option>
                                                        <option value="Blood mercury" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Blood mercury') ? 'selected' : ''; ?>>Blood mercury</option>
                                                        <option value="Urinary nickel (Elemental Nickel)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary nickel (Elemental Nickel)') ? 'selected' : ''; ?>>Urinary nickel (Elemental Nickel)</option>
                                                        <option value="Urinary nickel (Soluble compounds)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Urinary nickel (Soluble compounds)') ? 'selected' : ''; ?>>Urinary nickel (Soluble compounds)</option>
                                                        <option value="Blood manganese" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Blood manganese') ? 'selected' : ''; ?>>Blood manganese</option>
                                                        <option value="Toluene (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Toluene (urine)') ? 'selected' : ''; ?>>Toluene (urine)</option>
                                                        <option value="Methylhippuric acids (urine)" <?php echo (isset($editData['surveillance']['biological_exposure']) && $editData['surveillance']['biological_exposure'] == 'Methylhippuric acids (urine)') ? 'selected' : ''; ?>>Methylhippuric acids (urine)</option>
                                                        <?php 
                                                        $biological_exposure_value = isset($editData['surveillance']['biological_exposure']) ? $editData['surveillance']['biological_exposure'] : '';
                                                        $predefined_options = [
                                                            'Blood Lead Level (BLL)', 'RBC Cholinesterase', 'Plasma Cholinesterase',
                                                            'S-PMA (urine)', 'TTCA (urine)', '2,5-Hexanedione (urine)', 'TCA (urine)',
                                                            'Urinary As + metabolites', 'Urinary Cd', 'Blood Cd', 'Urinary total chromium',
                                                            'Urinary mercury', 'Blood mercury', 'Urinary nickel (Elemental Nickel)',
                                                            'Urinary nickel (Soluble compounds)', 'Blood manganese', 'Toluene (urine)',
                                                            'Methylhippuric acids (urine)'
                                                        ];
                                                        $is_other = !empty($biological_exposure_value) && !in_array($biological_exposure_value, $predefined_options);
                                                        ?>
                                                        <option value="Other" <?php echo $is_other ? 'selected' : ''; ?>>Other (Specify)</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm mt-2" id="biological_exposure_other" name="biological_exposure_other" placeholder="Please specify" style="display: <?php echo $is_other ? 'block' : 'none'; ?>;" value="<?php echo $is_other ? htmlspecialchars($biological_exposure_value) : ''; ?>">
                                                </td>
                                                <td>
                                                    <label class="form-label small mb-1">Baseline:</label>
                                                    <input type="text" class="form-control form-control-sm" id="result_baseline" name="result_baseline" placeholder="Enter baseline results" required value="<?php echo isset($editData['surveillance']['result_baseline']) ? htmlspecialchars($editData['surveillance']['result_baseline']) : ''; ?>">
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <label class="form-label small mb-1">Annual:</label>
                                                    <input type="text" class="form-control form-control-sm" id="result_annual" name="result_annual" placeholder="Enter annual results" required value="<?php echo isset($editData['surveillance']['result_annual']) ? htmlspecialchars($editData['surveillance']['result_annual']) : ''; ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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
                                        <input class="form-check-input" type="radio" name="respirator_result" value="Fit" id="respirator_fit" required <?php echo (isset($editData['surveillance']['respirator_result']) && $editData['surveillance']['respirator_result'] == 'Fit') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="respirator_fit">Fit</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="respirator_result" value="Not Fit" id="respirator_not_fit" required <?php echo (isset($editData['surveillance']['respirator_result']) && $editData['surveillance']['respirator_result'] == 'Not Fit') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="respirator_not_fit">Not Fit</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="respirator_justification" class="form-label">Justification <span class="required">*</span></label>
                                    <textarea class="form-control" id="respirator_justification" name="respirator_justification" rows="3" placeholder="Please justify the decision" required><?php echo isset($editData['surveillance']['respirator_justification']) ? htmlspecialchars($editData['surveillance']['respirator_justification']) : ''; ?></textarea>
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
                                                    <input class="form-check-input" type="radio" name="history_of_health" value="Yes" id="history_yes" required <?php echo (!empty($editData['surveillance']['history_of_health']) && $editData['surveillance']['history_of_health'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="history_of_health" value="No" id="history_no" required <?php echo (!empty($editData['surveillance']['history_of_health']) && $editData['surveillance']['history_of_health'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                                <td colspan="2" style="text-align: center; background-color: #6c757d; color: white; font-weight: 500;">Not applicable</td>
                                            </tr>
                                            
                                            <!-- Clinical findings [I] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Clinical findings [I]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_findings" value="Yes" id="clinical_yes" required <?php echo (!empty($editData['surveillance']['clinical_findings']) && $editData['surveillance']['clinical_findings'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_findings" value="No" id="clinical_no" required <?php echo (!empty($editData['surveillance']['clinical_findings']) && $editData['surveillance']['clinical_findings'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_work_related" value="Yes" id="clinical_work_yes" required <?php echo (!empty($editData['surveillance']['clinical_work_related']) && $editData['surveillance']['clinical_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="clinical_work_related" value="No" id="clinical_work_no" required <?php echo (!empty($editData['surveillance']['clinical_work_related']) && $editData['surveillance']['clinical_work_related'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                            </tr>
                                            
                                            <!-- Target organ function test results (please specify) [J] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Target organ function test results (please specify) [J]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="target_organ" value="Yes" id="target_organ_yes" required <?php echo (!empty($editData['surveillance']['target_organ']) && $editData['surveillance']['target_organ'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="target_organ" value="No" id="target_organ_no" required <?php echo (!empty($editData['surveillance']['target_organ']) && $editData['surveillance']['target_organ'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="organ_work_related" value="Yes" id="organ_work_yes" required <?php echo (!empty($editData['surveillance']['organ_work_related']) && $editData['surveillance']['organ_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="organ_work_related" value="No" id="organ_work_no" required <?php echo (!empty($editData['surveillance']['organ_work_related']) && $editData['surveillance']['organ_work_related'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                            </tr>
                                            
                                            <!-- BEI determinant (BM/BEM) (please specify) [K] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">BEI determinant (BM/BEM) (please specify) [K]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_monitoring" value="Yes" id="biological_yes" required <?php echo (!empty($editData['surveillance']['biological_monitoring']) && $editData['surveillance']['biological_monitoring'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_monitoring" value="No" id="biological_no" required <?php echo (!empty($editData['surveillance']['biological_monitoring']) && $editData['surveillance']['biological_monitoring'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_work_related" value="Yes" id="biological_work_yes" required <?php echo (!empty($editData['surveillance']['biological_work_related']) && $editData['surveillance']['biological_work_related'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="biological_work_related" value="No" id="biological_work_no" required <?php echo (!empty($editData['surveillance']['biological_work_related']) && $editData['surveillance']['biological_work_related'] == 'No') ? 'checked' : ''; ?>>
                                                </td>
                                            </tr>
                                            
                                            <!-- Pregnancy/Breast feeding [P] -->
                                            <tr>
                                                <td style="background-color: #e8f5e8; font-weight: 500;">Pregnancy/Breast feeding [P]</td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="pregnancy_breast_feeding" value="Yes" id="pregnancy_yes" required <?php echo (!empty($editData['surveillance']['pregnancy_breast_feeding']) && $editData['surveillance']['pregnancy_breast_feeding'] == 'Yes') ? 'checked' : ''; ?>>
                                                </td>
                                                <td style="text-align: center;">
                                                    <input class="form-check-input" type="radio" name="pregnancy_breast_feeding" value="No" id="pregnancy_no" required <?php echo (!empty($editData['surveillance']['pregnancy_breast_feeding']) && $editData['surveillance']['pregnancy_breast_feeding'] == 'No') ? 'checked' : ''; ?>>
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
                                                <input class="form-check-input" type="radio" name="fitness_status" value="Fit for Work" id="fitness_fit" required <?php echo (isset($editData['surveillance']['final_assessment']) && $editData['surveillance']['final_assessment'] == 'Fit for Work') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="fitness_fit">Fit</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="fitness_status" value="Not Fit for Work" id="fitness_not_fit" required <?php echo (isset($editData['surveillance']['final_assessment']) && $editData['surveillance']['final_assessment'] == 'Not Fit for Work') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="fitness_not_fit">Not Fit</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                    <label class="form-label">Recommendations Type</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Fit to work with no restriction" id="rec_fit_no_restriction" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Fit to work with no restriction', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_fit_no_restriction">Fit to work with no restriction</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Fit for work with restriction" id="rec_fit_with_restriction" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Fit for work with restriction', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_fit_with_restriction">Fit for work with restriction</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Annual medical surveillance" id="rec_annual_surveillance" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Annual medical surveillance', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_annual_surveillance">Annual medical surveillance</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Temporary Medical Removal Protection" id="rec_temp_mrp" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Temporary Medical Removal Protection', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_temp_mrp">Temporary Medical Removal Protection</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Permanent Medical Removal Protection" id="rec_perm_mrp" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Permanent Medical Removal Protection', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_perm_mrp">Permanent Medical Removal Protection</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Follow up and review" id="rec_follow_up" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Follow up and review', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_follow_up">Follow up and review</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recommendations_type[]" value="Reinforce PPE and hygiene practices like stop smoking, job rotation and training" id="rec_ppe_hygiene" <?php echo (!empty($editData['surveillance']['recommendations_type']) && in_array('Reinforce PPE and hygiene practices like stop smoking, job rotation and training', explode(',', $editData['surveillance']['recommendations_type']))) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rec_ppe_hygiene">Reinforce PPE and hygiene practices like stop smoking, job rotation and training</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="date_of_MRP" class="form-label">Date of MRP</label>
                                    <input type="date" class="form-control" id="date_of_MRP" name="date_of_MRP" value="<?php echo isset($editData['surveillance']['date_of_MRP']) ? $editData['surveillance']['date_of_MRP'] : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="next_review_date" class="form-label">Next Review Date</label>
                                    <input type="date" class="form-control" id="next_review_date" name="next_review_date" value="<?php echo isset($editData['surveillance']['next_review_date']) ? $editData['surveillance']['next_review_date'] : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="recommendations_notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="recommendations_notes" name="recommendations_notes" rows="3" placeholder="Enter additional notes"><?php echo isset($editData['surveillance']['recommendations_notes']) ? htmlspecialchars($editData['surveillance']['recommendations_notes']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                        </div>
                        
                    </div>
                    <!-- End Tab Content -->
                    
                            </div>
                            <!-- End Tab Content -->
                            
                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between mt-4">
                                <a href="<?php echo app_url('surveillance_list.php'); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Surveillance
                                </a>
                            </div>
                            
                            <!-- Submit Button (only in recommendations tab) -->
                            <div class="d-flex justify-content-end mt-4 mb-3" id="submitButtonContainer" style="display: none !important;">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>
                                    <i class="fas fa-check-circle"></i> Submit (Complete Form)
                                </button>
                            </div>
                            
                            <!-- Fixed Save Button (available on all tabs) -->
                            <button type="button" class="btn btn-success fixed-save-button" id="saveBtn">
                                <i class="fas fa-save"></i> Save (Incomplete)
                            </button>
                        </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Error loading surveillance data. Please try again.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Tab validation and form handling
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('surveillanceEditForm');
        const saveBtn = document.getElementById('saveBtn');
        const submitBtn = document.getElementById('submitBtn');
        const formAction = document.getElementById('formAction');
        
        // Define tab mapping (matching surveillance_form.php order)
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
        
        // Function to validate a single tab (visual indication only, doesn't block submission)
        function validateTab(tabKey) {
            const tab = tabs[tabKey];
            if (!tab) return true;
            
            const tabPane = document.getElementById(tab.paneId);
            if (!tabPane) return true;
            
            let isValid = true;
            const checkedRadioGroups = new Set();
            
            // Check all radio button groups - if a group exists, at least one should be checked
            const allRadioGroups = new Set();
            tabPane.querySelectorAll('input[type="radio"]').forEach(radio => {
                allRadioGroups.add(radio.name);
            });
            
            allRadioGroups.forEach(radioName => {
                if (!checkedRadioGroups.has(radioName)) {
                    checkedRadioGroups.add(radioName);
                    const radioGroup = tabPane.querySelectorAll(`input[type="radio"][name="${radioName}"]`);
                    if (radioGroup.length > 0) {
                        const hasChecked = Array.from(radioGroup).some(r => r.checked);
                        if (!hasChecked) {
                            isValid = false;
                        }
                    }
                }
            });
            
            // Check all required fields (including those marked with required attribute or asterisk)
            const requiredFields = tabPane.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                // Skip if field is disabled or hidden
                if (field.disabled || field.style.display === 'none') {
                    const parent = field.closest('[style*="display: none"]');
                    if (parent) return;
                }
                
                // Skip radio buttons (already checked above)
                if (field.type === 'radio') {
                    return;
                }
                
                const value = field.value ? field.value.trim() : '';
                // Check for empty or placeholder values
                if (value === '' || value === '-- Select --' || value === '-- Select Company --' || value === '-- Select Company First --' || value === 'Select examination type') {
                    isValid = false;
                }
            });
            
            // Check all select dropdowns - if they have "-- Select --" as value, it's incomplete
            const selectFields = tabPane.querySelectorAll('select');
            selectFields.forEach(select => {
                if (select.disabled || select.style.display === 'none') {
                    return;
                }
                const value = select.value ? select.value.trim() : '';
                if (value === '' || value === '-- Select --') {
                    isValid = false;
                }
            });
            
            // Check textareas that are required (check for required attribute or if parent label has required indicator)
            const textareas = tabPane.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                if (textarea.disabled || textarea.style.display === 'none') {
                    return;
                }
                // Check if textarea is required (has required attribute or parent has required indicator)
                const isRequired = textarea.hasAttribute('required') || 
                                   textarea.closest('.mb-3')?.querySelector('.required') !== null ||
                                   textarea.closest('.form-section')?.querySelector('label .required') !== null;
                if (isRequired) {
                    const value = textarea.value ? textarea.value.trim() : '';
                    if (value === '') {
                        isValid = false;
                    }
                }
            });
            
            // Special check for General tab - chemical and workplace fields
            if (tabKey === 'general') {
                const chemical = tabPane.querySelector('#chemical');
                const workplace = tabPane.querySelector('#workplace');
                const examinationType = tabPane.querySelector('#examination_type');
                
                if (chemical && (!chemical.value || chemical.value.trim() === '')) {
                    isValid = false;
                }
                if (workplace && (!workplace.value || workplace.value.trim() === '')) {
                    isValid = false;
                }
                if (examinationType && (!examinationType.value || examinationType.value === '' || examinationType.value === 'Select examination type')) {
                    isValid = false;
                }
            }
            
            // Special check for Target Organ tab - blood_count and renal_function dropdowns
            if (tabKey === 'target') {
                const bloodCount = tabPane.querySelector('#blood_count');
                const renalFunction = tabPane.querySelector('#renal_function');
                
                if (bloodCount && (!bloodCount.value || bloodCount.value === '' || bloodCount.value === '-- Select --')) {
                    isValid = false;
                }
                if (renalFunction && (!renalFunction.value || renalFunction.value === '' || renalFunction.value === '-- Select --')) {
                    isValid = false;
                }
            }
            
            // Special check for Clinical Findings tab - has_clinical_findings radio and clinical_findings textarea
            if (tabKey === 'clinical') {
                const hasClinicalYes = tabPane.querySelector('#has_clinical_yes');
                const hasClinicalNo = tabPane.querySelector('#has_clinical_no');
                const clinicalTextarea = tabPane.querySelector('#clinical_elaboration');
                
                const hasClinicalChecked = (hasClinicalYes && hasClinicalYes.checked) || (hasClinicalNo && hasClinicalNo.checked);
                if (!hasClinicalChecked) {
                    isValid = false;
                }
                
                // Clinical findings textarea is required if radio button is selected
                if (hasClinicalChecked && clinicalTextarea && (!clinicalTextarea.value || clinicalTextarea.value.trim() === '')) {
                    isValid = false;
                }
            }
            
            // Special check for Biological Monitoring tab - check if all fields are empty
            if (tabKey === 'biological') {
                const biologicalExposure = tabPane.querySelector('#biological_exposure');
                const resultBaseline = tabPane.querySelector('#result_baseline');
                const resultAnnual = tabPane.querySelector('#result_annual');
                
                // If all fields are empty, tab is incomplete
                const allEmpty = (!biologicalExposure || !biologicalExposure.value || biologicalExposure.value.trim() === '') &&
                                (!resultBaseline || !resultBaseline.value || resultBaseline.value.trim() === '') &&
                                (!resultAnnual || !resultAnnual.value || resultAnnual.value.trim() === '');
                
                if (allEmpty) {
                    isValid = false;
                }
            }
            
            // Special check for Recommendations tab - check if at least one recommendation type is selected
            if (tabKey === 'recommendations') {
                const recommendationCheckboxes = tabPane.querySelectorAll('input[type="checkbox"][name="recommendations_type[]"]');
                let hasChecked = false;
                
                if (recommendationCheckboxes.length > 0) {
                    recommendationCheckboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            hasChecked = true;
                        }
                    });
                }
                
                // If no recommendation is selected, tab is incomplete
                if (!hasChecked) {
                    isValid = false;
                }
            }
            
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
        
        // Show submit button only in recommendations tab
        const recommendationsTab = document.getElementById('recommendations-tab');
        const submitButtonContainer = document.getElementById('submitButtonContainer');
        if (recommendationsTab && submitButtonContainer) {
            recommendationsTab.addEventListener('shown.bs.tab', function() {
                submitButtonContainer.style.display = 'flex';
            });
            
            // Hide submit button when leaving recommendations tab
            document.querySelectorAll('#surveillanceTabs button').forEach(btn => {
                if (btn.id !== 'recommendations-tab') {
                    btn.addEventListener('shown.bs.tab', function() {
                        submitButtonContainer.style.display = 'none';
                    });
                }
            });
        }
        
        // Next button handlers for tab navigation
        var nextButtons = document.querySelectorAll('.btn-next-tab');
        nextButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                var nextTabId = this.getAttribute('data-next-tab');
                if (nextTabId) {
                    var nextTab = document.getElementById(nextTabId);
                    if (nextTab) {
                        var tabTrigger = new bootstrap.Tab(nextTab);
                        tabTrigger.show();
                    }
                }
            });
        });
        
        // Clinical findings radio button handling
        const hasClinicalYes = document.getElementById('has_clinical_yes');
        const hasClinicalNo = document.getElementById('has_clinical_no');
        const clinicalElaborationDiv = document.getElementById('clinical_elaboration_div');
        const clinicalElaborationTextarea = document.getElementById('clinical_elaboration');
        
        function updateClinicalElaborationPlaceholder() {
            if (hasClinicalYes && hasClinicalYes.checked) {
                clinicalElaborationTextarea.placeholder = "Describe the health effects currently experienced by the employees...";
            } else if (hasClinicalNo && hasClinicalNo.checked) {
                clinicalElaborationTextarea.placeholder = "Explain why there are no clinical findings or provide additional context...";
            }
        }
        
        // Set initial state - always show elaboration field
        if (clinicalElaborationDiv) {
            clinicalElaborationDiv.style.display = 'block';
            updateClinicalElaborationPlaceholder();
            
            // Add event listeners
            if (hasClinicalYes) {
                hasClinicalYes.addEventListener('change', updateClinicalElaborationPlaceholder);
            }
            if (hasClinicalNo) {
                hasClinicalNo.addEventListener('change', updateClinicalElaborationPlaceholder);
            }
        }
        
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
            if (!bpStatus) return;
            
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
            if (!pulseStatus) return;
            
            let status = '';
            let className = '';
            
            if (pulse === 0) {
                status = '';
                className = '';
            } else {
                // Pulse Rate ranges based on Vital Signs Chart
                let pulseRange = [60, 100]; // Adult default
                
                if (age >= 0 && age <= 1) {
                    pulseRange = [100, 160];
                } else if (age >= 2 && age <= 2) {
                    pulseRange = [90, 140];
                } else if (age >= 3 && age <= 5) {
                    pulseRange = [80, 120];
                } else if (age >= 6 && age <= 12) {
                    pulseRange = [70, 110];
                } else if (age >= 13 && age <= 18) {
                    pulseRange = [60, 100];
                }
                
                if (pulse >= pulseRange[0] && pulse <= pulseRange[1]) {
                    status = 'Normal';
                    className = 'text-success';
                } else if (pulse < pulseRange[0]) {
                    status = 'Bradycardia';
                    className = 'text-warning';
                } else if (pulse > pulseRange[1]) {
                    status = 'Tachycardia';
                    className = 'text-danger';
                }
            }
            
            pulseStatus.innerHTML = status;
            pulseStatus.className = className;
        }
        
        function validateRespiratoryRate(respiratory, age) {
            const respiratoryStatus = document.getElementById('respiratory_status');
            if (!respiratoryStatus) return;
            
            let status = '';
            let className = '';
            
            if (respiratory === 0) {
                status = '';
                className = '';
            } else {
                // Respiratory Rate ranges based on Vital Signs Chart
                let respiratoryRange = [12, 20]; // Adult default
                
                if (age >= 0 && age <= 1) {
                    respiratoryRange = [30, 60];
                } else if (age >= 2 && age <= 2) {
                    respiratoryRange = [24, 40];
                } else if (age >= 3 && age <= 5) {
                    respiratoryRange = [22, 34];
                } else if (age >= 6 && age <= 12) {
                    respiratoryRange = [18, 30];
                } else if (age >= 13 && age <= 18) {
                    respiratoryRange = [12, 20];
                }
                
                if (respiratory >= respiratoryRange[0] && respiratory <= respiratoryRange[1]) {
                    status = 'Normal';
                    className = 'text-success';
                } else if (respiratory < respiratoryRange[0]) {
                    status = 'Bradypnea';
                    className = 'text-warning';
                } else if (respiratory > respiratoryRange[1]) {
                    status = 'Tachypnea';
                    className = 'text-danger';
                }
            }
            
            respiratoryStatus.innerHTML = status;
            respiratoryStatus.className = className;
        }
        
        // Add event listeners for vital signs validation
        const bpSystolic = document.getElementById('bp_systolic');
        const bpDiastolic = document.getElementById('bp_diastolic');
        const pulseRate = document.getElementById('pulse_rate');
        const respiratoryRate = document.getElementById('respiratory_rate');
        
        if (bpSystolic) bpSystolic.addEventListener('input', validateVitalSigns);
        if (bpDiastolic) bpDiastolic.addEventListener('input', validateVitalSigns);
        if (pulseRate) pulseRate.addEventListener('input', validateVitalSigns);
        if (respiratoryRate) respiratoryRate.addEventListener('input', validateVitalSigns);
        
        // Initialize validation on page load
        if (bpSystolic || bpDiastolic || pulseRate || respiratoryRate) {
            setTimeout(validateVitalSigns, 500);
        }
        
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
    });
    </script>
</body>
</html>
