<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get patient_id and test_id from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$audiometric_test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$questionnaire_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize variables
$patient_data = null;
$questionnaire_data = null;
$message = '';
$messageType = '';

// Fetch patient data if patient_id is provided
$patient_data = null;
if ($patient_id > 0) {
    try {
        $patient_data = getClinicPatientById($patient_id);
        if ($patient_data && !isset($patient_data['error']) && is_array($patient_data)) {
            // Calculate age from date of birth
            if (!empty($patient_data['date_of_birth'])) {
                $birthDate = new DateTime($patient_data['date_of_birth']);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
                $patient_data['age'] = $age;
            }
            
            // Fetch department from occupational_history if not already in patient_data
            if (empty($patient_data['department']) && $patient_id > 0) {
                try {
                    $dept_stmt = $clinic_pdo->prepare("SELECT department FROM occupational_history WHERE patient_id = ? LIMIT 1");
                    $dept_stmt->execute([$patient_id]);
                    $dept_result = $dept_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($dept_result && !empty($dept_result['department'])) {
                        $patient_data['department'] = $dept_result['department'];
                    }
                } catch (Exception $e) {
                    // Department is optional, so we can ignore errors
                    error_log("Optional: Error fetching department: " . $e->getMessage());
                }
            }
        } else {
            $patient_data = null; // Reset to null if there's an error
        }
    } catch (Exception $e) {
        error_log("Error fetching patient data: " . $e->getMessage());
        $patient_data = null;
    }
}

// Fetch existing questionnaire data if editing or if patient_id is provided
if ($questionnaire_id > 0) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM audiometric_questionnaire WHERE id = ?");
        $stmt->execute([$questionnaire_id]);
        $questionnaire_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($questionnaire_data && $questionnaire_data['patient_id']) {
            $patient_id = $questionnaire_data['patient_id'];
            if (!$patient_data || isset($patient_data['error'])) {
                $patient_data = getClinicPatientById($patient_id);
                if ($patient_data && !isset($patient_data['error']) && is_array($patient_data)) {
                    // Calculate age from date of birth
                    if (!empty($patient_data['date_of_birth'])) {
                        $birthDate = new DateTime($patient_data['date_of_birth']);
                        $today = new DateTime();
                        $age = $today->diff($birthDate)->y;
                        $patient_data['age'] = $age;
                    }
                    
                    // Fetch department from occupational_history if not already in patient_data
                    if (empty($patient_data['department']) && $patient_id > 0) {
                        try {
                            $dept_stmt = $clinic_pdo->prepare("SELECT department FROM occupational_history WHERE patient_id = ? LIMIT 1");
                            $dept_stmt->execute([$patient_id]);
                            $dept_result = $dept_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($dept_result && !empty($dept_result['department'])) {
                                $patient_data['department'] = $dept_result['department'];
                            }
                        } catch (Exception $e) {
                            // Department is optional, so we can ignore errors
                            error_log("Optional: Error fetching department: " . $e->getMessage());
                        }
                    }
                } else {
                    $patient_data = null;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching questionnaire data: " . $e->getMessage());
    }
} elseif ($patient_id > 0) {
    // Try to fetch the latest questionnaire for this patient
    try {
        $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'audiometric_questionnaire'")->fetch();
        if ($tableCheck) {
            $stmt = $clinic_pdo->prepare("SELECT * FROM audiometric_questionnaire WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$patient_id]);
            $questionnaire_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($questionnaire_data) {
                $questionnaire_id = $questionnaire_data['id'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching existing questionnaire: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $data = [
            'patient_id' => $_POST['patient_id'] ?? null,
            'audiometric_test_id' => null, // Always set to null since test results section was removed
            'patient_name' => sanitizeInput($_POST['patient_name'] ?? ''),
            'age' => !empty($_POST['age']) ? (int)$_POST['age'] : null,
            'ic_passport_no' => sanitizeInput($_POST['ic_passport_no'] ?? ''),
            'gender' => sanitizeInput($_POST['gender'] ?? ''),
            'company' => sanitizeInput($_POST['company'] ?? ''),
            'department' => sanitizeInput($_POST['department'] ?? ''),
            'job' => sanitizeInput($_POST['job'] ?? ''),
            'years_of_service' => !empty($_POST['years_of_service']) ? (float)$_POST['years_of_service'] : null,
            'test_date' => $_POST['test_date'] ?? date('Y-m-d'),
            
            // Questionnaire Questions
            'q1_noise_14hours' => $_POST['q1_noise_14hours'] ?? null,
            'q2_illness_hearing' => $_POST['q2_illness_hearing'] ?? null,
            'q3_ear_operation' => $_POST['q3_ear_operation'] ?? null,
            'q4_medication_hearing' => $_POST['q4_medication_hearing'] ?? null,
            'q5_exposed_loud_noise' => $_POST['q5_exposed_loud_noise'] ?? null,
            'q6_family_hearing_loss' => $_POST['q6_family_hearing_loss'] ?? null,
            'q7_night_clubs' => $_POST['q7_night_clubs'] ?? null,
            'q8_personal_stereo' => $_POST['q8_personal_stereo'] ?? null,
            'q9_loud_music_instruments' => $_POST['q9_loud_music_instruments'] ?? null,
            'q10_noisy_jobs_past' => $_POST['q10_noisy_jobs_past'] ?? null,
            'q11_hearing_protectors' => $_POST['q11_hearing_protectors'] ?? null,
            'q12_audiometric_test_before' => $_POST['q12_audiometric_test_before'] ?? null,
            
            // Audiometry Test Results - Air Conduction Right Ear
            'air_right_500' => null,
            'air_right_1k' => null,
            'air_right_2k' => null,
            'air_right_3k' => null,
            'air_right_4k' => null,
            'air_right_6k' => null,
            'air_right_8k' => null,
            
            // Audiometry Test Results - Air Conduction Left Ear
            'air_left_500' => null,
            'air_left_1k' => null,
            'air_left_2k' => null,
            'air_left_3k' => null,
            'air_left_4k' => null,
            'air_left_6k' => null,
            'air_left_8k' => null,
            
            // Audiometry Test Results - Bone Conduction Right Ear
            'bone_right_500' => null,
            'bone_right_1k' => null,
            'bone_right_2k' => null,
            'bone_right_3k' => null,
            'bone_right_4k' => null,
            'bone_right_6k' => null,
            'bone_right_8k' => null,
            
            // Audiometry Test Results - Bone Conduction Left Ear
            'bone_left_500' => null,
            'bone_left_1k' => null,
            'bone_left_2k' => null,
            'bone_left_3k' => null,
            'bone_left_4k' => null,
            'bone_left_6k' => null,
            'bone_left_8k' => null,
            
            // Visual Examination
            'visual_examination' => null,
            'visual_examination_details' => null,
            'technician_signature' => null,
            'technician_name' => null,
            
            'created_by' => $_SESSION['user_id']
        ];
        
        
        // Check if table exists, create if not
        $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'audiometric_questionnaire'")->fetch();
        if (!$tableCheck) {
            // Read and execute the SQL file
            $sql = file_get_contents('sql/create_audiometric_questionnaire_table.sql');
            $clinic_pdo->exec($sql);
        }
        
        // Check if updating existing record
        $update_id = $_POST['questionnaire_id'] ?? $questionnaire_id ?? 0;
        
        if ($update_id > 0) {
            // Update existing record
            $sql = "UPDATE audiometric_questionnaire SET 
                    patient_id = :patient_id,
                    audiometric_test_id = :audiometric_test_id,
                    patient_name = :patient_name,
                    age = :age,
                    ic_passport_no = :ic_passport_no,
                    gender = :gender,
                    company = :company,
                    department = :department,
                    job = :job,
                    years_of_service = :years_of_service,
                    test_date = :test_date,
                    q1_noise_14hours = :q1_noise_14hours,
                    q2_illness_hearing = :q2_illness_hearing,
                    q3_ear_operation = :q3_ear_operation,
                    q4_medication_hearing = :q4_medication_hearing,
                    q5_exposed_loud_noise = :q5_exposed_loud_noise,
                    q6_family_hearing_loss = :q6_family_hearing_loss,
                    q7_night_clubs = :q7_night_clubs,
                    q8_personal_stereo = :q8_personal_stereo,
                    q9_loud_music_instruments = :q9_loud_music_instruments,
                    q10_noisy_jobs_past = :q10_noisy_jobs_past,
                    q11_hearing_protectors = :q11_hearing_protectors,
                    q12_audiometric_test_before = :q12_audiometric_test_before,
                    updated_at = NOW()
                    WHERE id = :id";
            
            $data['id'] = $update_id;
            $stmt = $clinic_pdo->prepare($sql);
            $stmt->execute($data);
            
            $message = 'Questionnaire updated successfully!';
            $messageType = 'success';
        } else {
            // Insert new record
            $sql = "INSERT INTO audiometric_questionnaire (
                    patient_id, audiometric_test_id, patient_name, age, ic_passport_no, gender,
                    company, department, job, years_of_service, test_date,
                    q1_noise_14hours, q2_illness_hearing, q3_ear_operation, q4_medication_hearing,
                    q5_exposed_loud_noise, q6_family_hearing_loss, q7_night_clubs, q8_personal_stereo,
                    q9_loud_music_instruments, q10_noisy_jobs_past, q11_hearing_protectors, q12_audiometric_test_before,
                    air_right_500, air_right_1k, air_right_2k, air_right_3k, air_right_4k, air_right_6k, air_right_8k,
                    air_left_500, air_left_1k, air_left_2k, air_left_3k, air_left_4k, air_left_6k, air_left_8k,
                    bone_right_500, bone_right_1k, bone_right_2k, bone_right_3k, bone_right_4k, bone_right_6k, bone_right_8k,
                    bone_left_500, bone_left_1k, bone_left_2k, bone_left_3k, bone_left_4k, bone_left_6k, bone_left_8k,
                    visual_examination, visual_examination_details, technician_signature, technician_name, created_by
                    ) VALUES (
                    :patient_id, :audiometric_test_id, :patient_name, :age, :ic_passport_no, :gender,
                    :company, :department, :job, :years_of_service, :test_date,
                    :q1_noise_14hours, :q2_illness_hearing, :q3_ear_operation, :q4_medication_hearing,
                    :q5_exposed_loud_noise, :q6_family_hearing_loss, :q7_night_clubs, :q8_personal_stereo,
                    :q9_loud_music_instruments, :q10_noisy_jobs_past, :q11_hearing_protectors, :q12_audiometric_test_before,
                    :air_right_500, :air_right_1k, :air_right_2k, :air_right_3k, :air_right_4k, :air_right_6k, :air_right_8k,
                    :air_left_500, :air_left_1k, :air_left_2k, :air_left_3k, :air_left_4k, :air_left_6k, :air_left_8k,
                    :bone_right_500, :bone_right_1k, :bone_right_2k, :bone_right_3k, :bone_right_4k, :bone_right_6k, :bone_right_8k,
                    :bone_left_500, :bone_left_1k, :bone_left_2k, :bone_left_3k, :bone_left_4k, :bone_left_6k, :bone_left_8k,
                    :visual_examination, :visual_examination_details, :technician_signature, :technician_name, :created_by
                    )";
            
            $stmt = $clinic_pdo->prepare($sql);
            $stmt->execute($data);
            
            $new_questionnaire_id = $clinic_pdo->lastInsertId();
            $message = 'Questionnaire saved successfully!';
            $messageType = 'success';
            $update_id = $new_questionnaire_id;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . app_url('audiometric_questionnaire') . '?id=' . $update_id . '&patient_id=' . $patient_id . '&message=' . urlencode($message) . '&type=' . $messageType);
        exit();
        
    } catch (Exception $e) {
        $message = 'Error saving questionnaire: ' . $e->getMessage();
        $messageType = 'danger';
        error_log("Audiometric Questionnaire Error: " . $e->getMessage());
    }
}

// Handle messages from URL
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questionnaire Form for Audiometric Testing - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .container {
            max-width: 100%;
            width: 100%;
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
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        
        .form-table td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .form-table th {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            background: #f8f9fa;
            font-weight: 600;
            text-align: center;
        }
        
        .question-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .question-text {
            margin-bottom: 0.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .audiometry-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
        }
        
        .audiometry-table td,
        .audiometry-table th {
            border: 1px solid #dee2e6;
            padding: 0.5rem;
            text-align: center;
        }
        
        .audiometry-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .input-small {
            width: 70px;
            text-align: center;
        }
        
        .signature-section {
            margin-top: 2rem;
            display: flex;
            justify-content: space-between;
            gap: 2rem;
        }
        
        .signature-block {
            flex: 1;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .signature-pad {
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            margin: 10px 0;
        }
        
        .signature-pad canvas {
            border-radius: 6px;
            width: 100%;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php if ($questionnaire_id > 0): ?>
                                <i class="fas fa-edit"></i> Edit Audiometric Questionnaire
                            <?php else: ?>
                                <i class="fas fa-clipboard-list"></i> Questionnaire Form for Audiometric Testing
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="questionnaireForm">
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    <input type="hidden" name="audiometric_test_id" value="">
                    <?php if ($questionnaire_id > 0): ?>
                        <input type="hidden" name="questionnaire_id" value="<?php echo $questionnaire_id; ?>">
                    <?php endif; ?>
                    
                    <!-- Patient Information Section -->
                    <div class="form-section">
                        <h5><i class="fas fa-user"></i> Patient Information</h5>
                        <?php
                        // Safely get patient name
                        $patient_name = '';
                        if (!empty($questionnaire_data['patient_name'])) {
                            $patient_name = $questionnaire_data['patient_name'];
                        } elseif ($patient_data && is_array($patient_data) && !isset($patient_data['error'])) {
                            $first_name = isset($patient_data['first_name']) ? $patient_data['first_name'] : '';
                            $last_name = isset($patient_data['last_name']) ? $patient_data['last_name'] : '';
                            $patient_name = trim($first_name . ' ' . $last_name);
                        }
                        
                        // Safely get employer name
                        $employer_name = '';
                        if (!empty($questionnaire_data['company'])) {
                            $employer_name = $questionnaire_data['company'];
                        } elseif ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && !empty($patient_data['company_name'])) {
                            $employer_name = $patient_data['company_name'];
                        }
                        
                        // Safely get age
                        $age = '';
                        if (!empty($questionnaire_data['age'])) {
                            $age = $questionnaire_data['age'];
                        } elseif ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && isset($patient_data['age'])) {
                            $age = $patient_data['age'];
                        }
                        
                        // Safely get IC/Passport
                        $ic_passport = '';
                        if (!empty($questionnaire_data['ic_passport_no'])) {
                            $ic_passport = $questionnaire_data['ic_passport_no'];
                        } elseif ($patient_data && is_array($patient_data) && !isset($patient_data['error'])) {
                            $ic_passport = isset($patient_data['NRIC']) ? $patient_data['NRIC'] : (isset($patient_data['passport_no']) ? $patient_data['passport_no'] : '');
                        }
                        
                        // Safely get gender
                        $gender = '';
                        if (!empty($questionnaire_data['gender'])) {
                            $gender = $questionnaire_data['gender'];
                        } elseif ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && !empty($patient_data['gender'])) {
                            $gender = $patient_data['gender'];
                        }
                        
                        // Safely get job title from database
                        $job_title = '';
                        if ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && !empty($patient_data['job_title'])) {
                            $job_title = $patient_data['job_title'];
                        } elseif (!empty($questionnaire_data['job'])) {
                            $job_title = $questionnaire_data['job'];
                        }
                        
                        // Safely get department from database
                        $department = '';
                        if ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && !empty($patient_data['department'])) {
                            $department = $patient_data['department'];
                        } elseif (!empty($questionnaire_data['department'])) {
                            $department = $questionnaire_data['department'];
                        }
                        
                        // Safely get years of service from database (employment_duration)
                        $years_of_service = '';
                        if ($patient_data && is_array($patient_data) && !isset($patient_data['error']) && !empty($patient_data['employment_duration'])) {
                            $years_of_service = $patient_data['employment_duration'];
                        } elseif (!empty($questionnaire_data['years_of_service'])) {
                            $years_of_service = $questionnaire_data['years_of_service'];
                        }
                        
                        // Always use current date for test date
                        $test_date = date('Y-m-d');
                        ?>
                        <!-- Personal Information -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <h6 class="text-primary mb-2" style="font-size: 1rem;"><i class="fas fa-user-circle"></i> Personal Information</h6>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Name of Person examined:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($patient_name); ?>
                                    </div>
                                    <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($patient_name); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Age:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($age); ?>
                                    </div>
                                    <input type="hidden" name="age" value="<?php echo htmlspecialchars($age); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Gender:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($gender); ?>
                                    </div>
                                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender); ?>">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>IC/Passport No:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($ic_passport); ?>
                                    </div>
                                    <input type="hidden" name="ic_passport_no" value="<?php echo htmlspecialchars($ic_passport); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Test Date:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo date('d/m/Y'); ?>
                                    </div>
                                    <input type="hidden" name="test_date" value="<?php echo htmlspecialchars($test_date); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Information -->
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="text-primary mb-2" style="font-size: 1rem;"><i class="fas fa-briefcase"></i> Employment Information</h6>
                            </div>
                            <div class="col-md-5">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Name & Address of Employer:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($employer_name); ?>
                                    </div>
                                    <input type="hidden" name="company" value="<?php echo htmlspecialchars($employer_name); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Department:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($department); ?>
                                    </div>
                                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($department); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Job Title:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($job_title); ?>
                                    </div>
                                    <input type="hidden" name="job" value="<?php echo htmlspecialchars($job_title); ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 0.9rem;"><strong>Years of Service:</strong></label>
                                    <div class="form-control-plaintext" style="font-size: 0.95rem; padding: 0.25rem 0;">
                                        <?php echo htmlspecialchars($years_of_service); ?>
                                    </div>
                                    <input type="hidden" name="years_of_service" value="<?php echo htmlspecialchars($years_of_service); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Questions Table -->
                    <table class="form-table">
                        <tr>
                            <td style="width: 50%;">
                                <div class="question-label">1. Were you exposed to loud noise within 14 hours prior to today's test?</div>
                                <div class="question-text">Adakah anda terdedah kepada bunyi yang kuat dalam masa 14 jam sebelum ujian hari ini?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q1_noise_14hours" id="q1_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q1_noise_14hours'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q1_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q1_noise_14hours" id="q1_no" value="NO" 
                                           <?php echo ($questionnaire_data['q1_noise_14hours'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q1_no">NO</label>
                                </div>
                                <small class="text-danger">*If YES please abort and reschedule test with advice</small>
                            </td>
                            <td style="width: 50%;">
                                <div class="question-label">7. Do you attend night clubs'/pubs/ discotheques or pop/rock concerts?</div>
                                <div class="question-text">Adakah anda menghadiri kelab malam/pub/diskotek atau konsert pop/rock?</div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q7_night_clubs" id="q7_never" value="NEVER" 
                                           <?php echo ($questionnaire_data['q7_night_clubs'] ?? '') == 'NEVER' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q7_never">NEVER (TIDAK PERNAH)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q7_night_clubs" id="q7_once" value="ONCE A YEAR" 
                                           <?php echo ($questionnaire_data['q7_night_clubs'] ?? '') == 'ONCE A YEAR' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q7_once">ONCE A YEAR (SETAHUN SEKALI)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q7_night_clubs" id="q7_more" value="MORE THAN ONCE A YEAR" 
                                           <?php echo ($questionnaire_data['q7_night_clubs'] ?? '') == 'MORE THAN ONCE A YEAR' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q7_more">MORE THAN ONCE A YEAR (LEBIH DARIPADA SETAHUN SEKALI)</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="question-label">2. Have you suffered any illness that has affected your hearing (e.g.: infection, tinnitus, discharge etc)?</div>
                                <div class="question-text">Adakah anda mengalami sebarang penyakit yang telah menjejaskan pendengaran anda (cth: jangkitan, desing, nanah dll)?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q2_illness_hearing" id="q2_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q2_illness_hearing'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q2_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q2_illness_hearing" id="q2_no" value="NO" 
                                           <?php echo ($questionnaire_data['q2_illness_hearing'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q2_no">NO</label>
                                </div>
                            </td>
                            <td>
                                <div class="question-label">8. Do you use a personal stereo?</div>
                                <div class="question-text">Adakah anda menggunakan stereo peribadi?</div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q8_personal_stereo" id="q8_never" value="NEVER" 
                                           <?php echo ($questionnaire_data['q8_personal_stereo'] ?? '') == 'NEVER' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q8_never">NEVER (TIDAK PERNAH)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q8_personal_stereo" id="q8_less" value="LESS THAN 2 HOURS A WEEK" 
                                           <?php echo ($questionnaire_data['q8_personal_stereo'] ?? '') == 'LESS THAN 2 HOURS A WEEK' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q8_less">LESS THAN 2 HOURS A WEEK (KURANG DARIPADA 2 JAM SEMINGGU)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="q8_personal_stereo" id="q8_more" value="MORE THAN 2 HOURS A WEEK" 
                                           <?php echo ($questionnaire_data['q8_personal_stereo'] ?? '') == 'MORE THAN 2 HOURS A WEEK' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q8_more">MORE THAN 2 HOURS A WEEK (LEBIH DARIPADA 2 JAM SEMINGGU)</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="question-label">3. Have you ever had an ear operation or any other major operation that affected your hearing?</div>
                                <div class="question-text">Pernahkah anda menjalani pembedahan telinga atau sebarang pembedahan besar lain yang menjejaskan pendengaran anda?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q3_ear_operation" id="q3_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q3_ear_operation'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q3_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q3_ear_operation" id="q3_no" value="NO" 
                                           <?php echo ($questionnaire_data['q3_ear_operation'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q3_no">NO</label>
                                </div>
                            </td>
                            <td>
                                <div class="question-label">9. Do you play loud music instruments?</div>
                                <div class="question-text">Adakah anda memainkan alat muzik yang kuat?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q9_loud_music_instruments" id="q9_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q9_loud_music_instruments'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q9_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q9_loud_music_instruments" id="q9_no" value="NO" 
                                           <?php echo ($questionnaire_data['q9_loud_music_instruments'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q9_no">NO</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="question-label">4. Have you ever taken any medication (tablets or injection) that affected your hearing?</div>
                                <div class="question-text">Pernahkah anda mengambil sebarang ubat (tablet atau suntikan) yang menjejaskan pendengaran anda?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q4_medication_hearing" id="q4_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q4_medication_hearing'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q4_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q4_medication_hearing" id="q4_no" value="NO" 
                                           <?php echo ($questionnaire_data['q4_medication_hearing'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q4_no">NO</label>
                                </div>
                            </td>
                            <td>
                                <div class="question-label">10. Have you worked in noisy jobs in the past?</div>
                                <div class="question-text">Adakah anda pernah bekerja dalam pekerjaan yang bising pada masa lalu?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q10_noisy_jobs_past" id="q10_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q10_noisy_jobs_past'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q10_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q10_noisy_jobs_past" id="q10_no" value="NO" 
                                           <?php echo ($questionnaire_data['q10_noisy_jobs_past'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q10_no">NO</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="question-label">5. Have you been exposed to loud noise (e.g.: chainsaw, firecrackers, explosion, gunfire, motorcycles?)?</div>
                                <div class="question-text">Adakah anda terdedah kepada bunyi yang kuat (cth: gergaji besi, mercun, letupan, tembakan, motosikal?)?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q5_exposed_loud_noise" id="q5_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q5_exposed_loud_noise'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q5_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q5_exposed_loud_noise" id="q5_no" value="NO" 
                                           <?php echo ($questionnaire_data['q5_exposed_loud_noise'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q5_no">NO</label>
                                </div>
                            </td>
                            <td>
                                <div class="question-label">11. Were you wearing personal hearing protectors at that time?</div>
                                <div class="question-text">Adakah anda memakai pelindung pendengaran peribadi pada masa itu?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q11_hearing_protectors" id="q11_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q11_hearing_protectors'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q11_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q11_hearing_protectors" id="q11_no" value="NO" 
                                           <?php echo ($questionnaire_data['q11_hearing_protectors'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q11_no">NO</label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="question-label">6. Any family history of hearing loss/disorders?</div>
                                <div class="question-text">Mana-mana sejarah keluarga yang mengalami kehilangan/gangguan pendengaran?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q6_family_hearing_loss" id="q6_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q6_family_hearing_loss'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q6_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q6_family_hearing_loss" id="q6_no" value="NO" 
                                           <?php echo ($questionnaire_data['q6_family_hearing_loss'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q6_no">NO</label>
                                </div>
                            </td>
                            <td>
                                <div class="question-label">12. Have you had an audiometric test before?</div>
                                <div class="question-text">Adakah anda pernah menjalani ujian audiometrik sebelum ini?</div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q12_audiometric_test_before" id="q12_yes" value="YES" 
                                           <?php echo ($questionnaire_data['q12_audiometric_test_before'] ?? '') == 'YES' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q12_yes">YES</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="q12_audiometric_test_before" id="q12_no" value="NO" 
                                           <?php echo ($questionnaire_data['q12_audiometric_test_before'] ?? '') == 'NO' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="q12_no">NO</label>
                                </div>
                            </td>
                        </tr>
                        </table>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo app_url('audiometric_list'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <div>
                            <?php if ($questionnaire_id > 0): ?>
                                <a href="<?php echo app_url('generate_audiometric_questionnaire_pdf'); ?>?id=<?php echo $questionnaire_id; ?>" 
                                   class="btn btn-success me-2" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Generate Report
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Questionnaire
                            </button>
                        </div>
                    </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
