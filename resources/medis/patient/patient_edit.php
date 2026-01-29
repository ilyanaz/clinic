<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

// Check if patient ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ' . app_url('patients.php') . '?message=' . urlencode('Patient ID is required') . '&type=danger');
    exit();
}

$patient_id = $_GET['id'];
$patient = getClinicPatientById($patient_id);

// Check if patient exists
if (isset($patient['error'])) {
    header('Location: ' . app_url('patients.php') . '?message=' . urlencode($patient['error']) . '&type=danger');
    exit();
}

if (!$patient) {
    header('Location: ' . app_url('patients.php') . '?message=' . urlencode('Patient not found') . '&type=danger');
    exit();
}

$message = '';
$messageType = '';

// Handle messages from URL parameters
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'];
}

// Handle form submissions
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_patient') {
    try {
        // Prepare patient data for update
        $patientData = [
            'id' => $patient_id,
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'NRIC' => sanitizeInput($_POST['ic_number']),
            'passport_no' => sanitizeInput($_POST['passport']),
            'date_of_birth' => $_POST['date_of_birth'],
            'gender' => $_POST['gender'],
            'address' => sanitizeInput($_POST['address']),
            'state' => sanitizeInput($_POST['state']),
            'district' => sanitizeInput($_POST['district']),
            'postcode' => sanitizeInput($_POST['postcode']),
            'telephone_no' => sanitizeInput($_POST['telephone']),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'ethnicity' => sanitizeInput($_POST['ethnic']),
            'citizenship' => sanitizeInput($_POST['citizenship']),
            'martial_status' => $_POST['status'],
            'no_of_children' => (int)($_POST['no_of_children'] ?? 0),
            'years_married' => $_POST['years_married'] ? (int)$_POST['years_married'] : null,
            // Medical history
            'diagnosed_history' => sanitizeInput($_POST['disease_diagnosed'] ?? ''),
            'medication_history' => sanitizeInput($_POST['medication_followup'] ?? ''),
            'admitted_history' => sanitizeInput($_POST['hospitalized'] ?? ''),
            'family_history' => sanitizeInput($_POST['family_history'] ?? ''),
            'others_history' => sanitizeInput($_POST['other_relevant_history'] ?? ''),
            // Personal social history
            'smoking_history' => $_POST['smoking_status'] ?? 'Non-Smoker',
            'years_of_smoking' => $_POST['years_smoking'] ? (int)$_POST['years_smoking'] : null,
            'no_of_cigarettes' => $_POST['cigarettes_per_day'] ? (int)$_POST['cigarettes_per_day'] : null,
            'vaping_history' => $_POST['vaping'] ?? 'No',
            'years_of_vaping' => $_POST['years_vaping'] ? (int)$_POST['years_vaping'] : null,
            'hobby' => sanitizeInput($_POST['hobby'] ?? ''),
            'parttime_job' => sanitizeInput($_POST['part_time_job'] ?? ''),
            // Occupational history
            'job_title' => sanitizeInput($_POST['present_job_title'] ?? ''),
            'company_name' => sanitizeInput($_POST['present_company'] ?? ''),
            'employment_duration' => sanitizeInput($_POST['present_duration'] ?? ''),
            'chemical_exposure_duration' => sanitizeInput($_POST['present_exposure_duration'] ?? ''),
            'chemical_exposure_incidents' => sanitizeInput($_POST['present_chemical_exposure'] ?? ''),
            // Training history
            'handling_of_chemical' => $_POST['trained_safe_handling'] ?? 'No',
            'chemical_comments' => sanitizeInput($_POST['trained_safe_handling_comments'] ?? ''),
            'sign_symptoms' => $_POST['trained_recognizing_symptoms'] ?? 'No',
            'sign_symptoms_comments' => sanitizeInput($_POST['trained_recognizing_symptoms_comments'] ?? ''),
            'chemical_poisoning' => $_POST['trained_chemical_poisoning'] ?? 'No',
            'poisoning_comments' => sanitizeInput($_POST['trained_chemical_poisoning_comments'] ?? ''),
            'proper_PPE' => $_POST['trained_ppe_usage'] ?? 'No',
            'proper_PPE_comments' => sanitizeInput($_POST['trained_ppe_usage_comments'] ?? ''),
            'PPE_usage' => $_POST['use_ppe_handling'] ?? 'No',
            'PPE_usage_comment' => sanitizeInput($_POST['use_ppe_handling_comments'] ?? '')
        ];
        
        // Update patient in clinic database
        $result = updateClinicPatient($patient_id, $patientData);
        
        if ($result['success']) {
            // Redirect to patients.php with success message
            header("Location: " . app_url('patients.php') . "?message=" . urlencode("Patient information updated successfully!") . "&type=success");
            exit();
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } catch (Exception $e) {
        $message = 'Error updating patient information: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$pageTitle = 'Edit Patient - ' . $patient['first_name'] . ' ' . $patient['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        .required {
            color: #dc3545;
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
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4><i class="fas fa-user-edit"></i> Edit Patient Information</h4>
                    <div>
                        <a href="<?php echo app_url('patient_details.php'); ?>?id=<?php echo $patient['id']; ?>" class="btn btn-outline-info me-2">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-list"></i> Back to Patients
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Patient Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="patient_edit.php?id=<?php echo $patient['id']; ?>">
                            <input type="hidden" name="action" value="update_patient">
                            
                            <!-- Basic Patient Information -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> GENERAL INFORMATION</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ic_number" class="form-label">NRIC <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="ic_number" name="ic_number" 
                                                   placeholder="000000-00-0000" value="<?php echo htmlspecialchars($patient['NRIC']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="passport" class="form-label">Passport No <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="passport" name="passport" 
                                                   value="<?php echo htmlspecialchars($patient['passport_no'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                   value="<?php echo $patient['date_of_birth']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                                            <select class="form-select" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address (Alamat) <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="state" class="form-label">State (Negeri) <span class="required">*</span></label>
                                            <select class="form-select" id="state" name="state" required onchange="updateDistricts()">
                                                <option value="">Select State</option>
                                                <option value="Johor" <?php echo $patient['state'] == 'Johor' ? 'selected' : ''; ?>>Johor</option>
                                                <option value="Kedah" <?php echo $patient['state'] == 'Kedah' ? 'selected' : ''; ?>>Kedah</option>
                                                <option value="Kelantan" <?php echo $patient['state'] == 'Kelantan' ? 'selected' : ''; ?>>Kelantan</option>
                                                <option value="Melaka" <?php echo $patient['state'] == 'Melaka' ? 'selected' : ''; ?>>Melaka (Malacca)</option>
                                                <option value="Negeri Sembilan" <?php echo $patient['state'] == 'Negeri Sembilan' ? 'selected' : ''; ?>>Negeri Sembilan</option>
                                                <option value="Pahang" <?php echo $patient['state'] == 'Pahang' ? 'selected' : ''; ?>>Pahang</option>
                                                <option value="Perak" <?php echo $patient['state'] == 'Perak' ? 'selected' : ''; ?>>Perak</option>
                                                <option value="Perlis" <?php echo $patient['state'] == 'Perlis' ? 'selected' : ''; ?>>Perlis</option>
                                                <option value="Pulau Pinang" <?php echo $patient['state'] == 'Pulau Pinang' ? 'selected' : ''; ?>>Pulau Pinang (Penang)</option>
                                                <option value="Sabah" <?php echo $patient['state'] == 'Sabah' ? 'selected' : ''; ?>>Sabah</option>
                                                <option value="Sarawak" <?php echo $patient['state'] == 'Sarawak' ? 'selected' : ''; ?>>Sarawak</option>
                                                <option value="Selangor" <?php echo $patient['state'] == 'Selangor' ? 'selected' : ''; ?>>Selangor</option>
                                                <option value="Terengganu" <?php echo $patient['state'] == 'Terengganu' ? 'selected' : ''; ?>>Terengganu</option>
                                                <option value="Kuala Lumpur" <?php echo $patient['state'] == 'Kuala Lumpur' ? 'selected' : ''; ?>>Kuala Lumpur</option>
                                                <option value="Labuan" <?php echo $patient['state'] == 'Labuan' ? 'selected' : ''; ?>>Labuan</option>
                                                <option value="Putrajaya" <?php echo $patient['state'] == 'Putrajaya' ? 'selected' : ''; ?>>Putrajaya</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="district" class="form-label">District (Daerah) <span class="required">*</span></label>
                                            <select class="form-select" id="district" name="district" required>
                                                <option value="">Select District</option>
                                                <option value="<?php echo htmlspecialchars($patient['district']); ?>" selected><?php echo htmlspecialchars($patient['district']); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="postcode" class="form-label">Post-code <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="postcode" name="postcode" 
                                                   value="<?php echo htmlspecialchars($patient['postcode'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telephone" class="form-label">Telephone number <span class="required">*</span></label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <select class="form-select" id="country_code" name="country_code">
                                                        <option value="+60">MY +60</option>
                                                        <option value="+65">SG +65</option>
                                                        <option value="+1">US +1</option>
                                                    </select>
                                                </div>
                                                <div class="col-8">
                                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                                           value="<?php echo htmlspecialchars($patient['telephone_no'] ?? ''); ?>" placeholder="12-3456789" required>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Select your country and enter phone number</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>" placeholder="patient@example.com">
                                            <small class="form-text text-muted">Enter a valid email address</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ethnic" class="form-label">Ethnicity <span class="required">*</span></label>
                                            <select class="form-select" id="ethnic" name="ethnic" required>
                                                <option value="">Select Ethnicity</option>
                                                <option value="Malay" <?php echo $patient['ethnicity'] == 'Malay' ? 'selected' : ''; ?>>Malay</option>
                                                <option value="Chinese" <?php echo $patient['ethnicity'] == 'Chinese' ? 'selected' : ''; ?>>Chinese</option>
                                                <option value="Indian" <?php echo $patient['ethnicity'] == 'Indian' ? 'selected' : ''; ?>>Indian</option>
                                                <option value="Orang Asli" <?php echo $patient['ethnicity'] == 'Orang Asli' ? 'selected' : ''; ?>>Orang Asli</option>
                                                <option value="Others" <?php echo $patient['ethnicity'] == 'Others' ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="citizenship" class="form-label">Citizenship <span class="required">*</span></label>
                                            <select class="form-select" id="citizenship" name="citizenship" required>
                                                <option value="">Select Citizenship</option>
                                                <option value="Malaysian Citizen" <?php echo $patient['citizenship'] == 'Malaysian Citizen' ? 'selected' : ''; ?>>Malaysian Citizen</option>
                                                <option value="Indonesian" <?php echo $patient['citizenship'] == 'Indonesian' ? 'selected' : ''; ?>>Indonesian</option>
                                                <option value="Filipino" <?php echo $patient['citizenship'] == 'Filipino' ? 'selected' : ''; ?>>Filipino</option>
                                                <option value="Thai" <?php echo $patient['citizenship'] == 'Thai' ? 'selected' : ''; ?>>Thai</option>
                                                <option value="Vietnamese" <?php echo $patient['citizenship'] == 'Vietnamese' ? 'selected' : ''; ?>>Vietnamese</option>
                                                <option value="Myanmar" <?php echo $patient['citizenship'] == 'Myanmar' ? 'selected' : ''; ?>>Myanmar</option>
                                                <option value="Bangladeshi" <?php echo $patient['citizenship'] == 'Bangladeshi' ? 'selected' : ''; ?>>Bangladeshi</option>
                                                <option value="Indian" <?php echo $patient['citizenship'] == 'Indian' ? 'selected' : ''; ?>>Indian</option>
                                                <option value="Chinese" <?php echo $patient['citizenship'] == 'Chinese' ? 'selected' : ''; ?>>Chinese</option>
                                                <option value="Others" <?php echo $patient['citizenship'] == 'Others' ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Marital Status <span class="required">*</span></label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="Single" <?php echo $patient['martial_status'] == 'Single' ? 'selected' : ''; ?>>Single</option>
                                                <option value="Married" <?php echo $patient['martial_status'] == 'Married' ? 'selected' : ''; ?>>Married</option>
                                                <option value="Divorced" <?php echo $patient['martial_status'] == 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                                <option value="Widowed" <?php echo $patient['martial_status'] == 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <!-- Empty column for spacing -->
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="no_of_children" class="form-label">Number of Children (Bilangan anak)</label>
                                            <input type="number" class="form-control" id="no_of_children" name="no_of_children" 
                                                   min="0" value="<?php echo $patient['no_of_children'] ?? 0; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="years_married" class="form-label">Years Married (Bilangan tahun berkahwin)</label>
                                            <input type="number" class="form-control" id="years_married" name="years_married" 
                                                   min="0" value="<?php echo $patient['years_married'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Medical History Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-heartbeat"></i> Medical History</h5>
                                
                                <div class="mb-3">
                                    <label for="disease_diagnosed" class="form-label">Have you been diagnosed with any disease? If yes, please provide details:</label>
                                    <textarea class="form-control" id="disease_diagnosed" name="disease_diagnosed" rows="3" 
                                              placeholder="Please provide details of any diagnosed diseases..."><?php echo htmlspecialchars($patient['diagnosed_history'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="medication_followup" class="form-label">Are you on any medication or follow up?</label>
                                    <textarea class="form-control" id="medication_followup" name="medication_followup" rows="3" 
                                              placeholder="Please provide details of any current medications or follow-up treatments..."><?php echo htmlspecialchars($patient['medication_history'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hospitalized" class="form-label">Have you ever been hospitalized?</label>
                                    <textarea class="form-control" id="hospitalized" name="hospitalized" rows="3" 
                                              placeholder="Please provide details of any hospitalization history..."><?php echo htmlspecialchars($patient['admitted_history'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="family_history" class="form-label">Family Medical History</label>
                                    <textarea class="form-control" id="family_history" name="family_history" rows="4" 
                                              placeholder="Please provide details of any relevant family medical history including hereditary diseases, allergies, or conditions that may be relevant to occupational health..."><?php echo htmlspecialchars($patient['family_history'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Include information about parents, siblings, and close relatives</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="other_relevant_history" class="form-label">Other Relevant History</label>
                                    <textarea class="form-control" id="other_relevant_history" name="other_relevant_history" rows="4" 
                                              placeholder="Please provide any other relevant historical information including previous workplace incidents, medical procedures, or conditions that may affect occupational health..."><?php echo htmlspecialchars($patient['others_history'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Include any additional medical history, workplace incidents, or relevant information not covered in previous sections</small>
                                </div>
                            </div>

                            <!-- Personal & Social History Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-smoking"></i> Personal & Social History</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Smoking (Merokok)</label>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="smoking_status" id="smoking_current" 
                                                               value="Current" <?php echo ($patient['smoking_history'] ?? '') == 'Current' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="smoking_current">Current</label>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="smoking_status" id="smoking_ex" 
                                                               value="Ex-Smoker" <?php echo ($patient['smoking_history'] ?? '') == 'Ex-Smoker' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="smoking_ex">Ex-smoker</label>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="smoking_status" id="smoking_non" 
                                                               value="Non-Smoker" <?php echo ($patient['smoking_history'] ?? '') == 'Non-Smoker' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="smoking_non">Non-smoker</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="years_smoking" class="form-label">Years Smoking</label>
                                                    <input type="number" class="form-control" id="years_smoking" name="years_smoking" 
                                                           min="0" value="<?php echo $patient['years_of_smoking'] ?? ''; ?>">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="cigarettes_per_day" class="form-label">Cigarettes/Day</label>
                                                    <input type="number" class="form-control" id="cigarettes_per_day" name="cigarettes_per_day" 
                                                           min="0" value="<?php echo $patient['no_of_cigarettes'] ?? ''; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Vaping</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="vaping" id="vaping_yes" 
                                                               value="Yes" <?php echo ($patient['vaping_history'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="vaping_yes">Yes</label>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="vaping" id="vaping_no" 
                                                               value="No" <?php echo ($patient['vaping_history'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="vaping_no">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="years_vaping" class="form-label">Years Vaping</label>
                                            <input type="number" class="form-control" id="years_vaping" name="years_vaping" 
                                                   min="0" value="<?php echo $patient['years_of_vaping'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hobby" class="form-label">Hobby</label>
                                            <input type="text" class="form-control" id="hobby" name="hobby" 
                                                   value="<?php echo htmlspecialchars($patient['hobby'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="part_time_job" class="form-label">Part-time Job</label>
                                            <input type="text" class="form-control" id="part_time_job" name="part_time_job" 
                                                   value="<?php echo htmlspecialchars($patient['parttime_job'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Occupational History Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-briefcase"></i> Occupational History</h5>
                                
                                <!-- Present Employment -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-briefcase"></i> Present Employment</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_job_title" class="form-label">Job Title</label>
                                                <input type="text" class="form-control" id="present_job_title" name="present_job_title" 
                                                       placeholder="Enter current job title" value="<?php echo htmlspecialchars($patient['job_title'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_company" class="form-label">Company Name</label>
                                                <input type="text" class="form-control" id="present_company" name="present_company" 
                                                       placeholder="Enter company name" value="<?php echo htmlspecialchars($patient['company_name'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_duration" class="form-label">Employment Duration</label>
                                                <input type="text" class="form-control" id="present_duration" name="present_duration" 
                                                       placeholder="E.g., 2 years, 6 months" value="<?php echo htmlspecialchars($patient['employment_duration'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_exposure_duration" class="form-label">Chemical Exposure Duration</label>
                                                <input type="text" class="form-control" id="present_exposure_duration" name="present_exposure_duration" 
                                                       placeholder="E.g., 1 year, 3 months" value="<?php echo htmlspecialchars($patient['chemical_exposure_duration'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label for="present_chemical_exposure" class="form-label">Chemical Exposure Incidents</label>
                                                <textarea class="form-control" id="present_chemical_exposure" name="present_chemical_exposure" rows="3" 
                                                          placeholder="Describe any chemical exposure incidents (spills, splashes, etc.)"><?php echo htmlspecialchars($patient['chemical_exposure_incidents'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Training History Section -->
                            <div class="form-section">
                                <h5><i class="fas fa-graduation-cap"></i> History of Training</h5>
                                
                                <!-- Training Questions -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Training Experience</h6>
                                    
                                    <!-- Safe Handling Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Safe Handling of Chemicals</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_safe_handling" id="trained_safe_yes" 
                                                                   value="Yes" <?php echo ($patient['handling_of_chemical'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_safe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_safe_handling" id="trained_safe_no" 
                                                                   value="No" <?php echo ($patient['handling_of_chemical'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_safe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_safe_handling_comments" 
                                                       placeholder="Additional details" value="<?php echo htmlspecialchars($patient['chemical_comments'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Symptoms Recognition Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Recognizing Signs and Symptoms of Disease</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_recognizing_symptoms" id="trained_symptoms_yes" 
                                                                   value="Yes" <?php echo ($patient['sign_symptoms'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_symptoms_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_recognizing_symptoms" id="trained_symptoms_no" 
                                                                   value="No" <?php echo ($patient['sign_symptoms'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_symptoms_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_recognizing_symptoms_comments" 
                                                       placeholder="Additional details" value="<?php echo htmlspecialchars($patient['sign_symptoms_comments'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Chemical Poisoning Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Chemical Poisoning at Workplace</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_chemical_poisoning" id="trained_poisoning_yes" 
                                                                   value="Yes" <?php echo ($patient['chemical_poisoning'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_poisoning_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_chemical_poisoning" id="trained_poisoning_no" 
                                                                   value="No" <?php echo ($patient['chemical_poisoning'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_poisoning_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_chemical_poisoning_comments" 
                                                       placeholder="Additional details" value="<?php echo htmlspecialchars($patient['poisoning_comments'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PPE Usage Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Proper PPE Usage</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_ppe_usage" id="trained_ppe_yes" 
                                                                   value="Yes" <?php echo ($patient['proper_PPE'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_ppe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_ppe_usage" id="trained_ppe_no" 
                                                                   value="No" <?php echo ($patient['proper_PPE'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="trained_ppe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_ppe_usage_comments" 
                                                       placeholder="Additional details" value="<?php echo htmlspecialchars($patient['proper_PPE_comments'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PPE Usage Question -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-shield-alt"></i> PPE Usage</h6>
                                    <div class="p-3 border rounded">
                                        <label class="form-label fw-bold">Do you use PPE when handling chemicals?</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="use_ppe_handling" id="use_ppe_yes" 
                                                                   value="Yes" <?php echo ($patient['PPE_usage'] ?? '') == 'Yes' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="use_ppe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="use_ppe_handling" id="use_ppe_no" 
                                                                   value="No" <?php echo ($patient['PPE_usage'] ?? '') == 'No' ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="use_ppe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="use_ppe_handling_comments" 
                                                       placeholder="Additional details" value="<?php echo htmlspecialchars($patient['PPE_usage_comment'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Patients
                                </a>
                                <button type="submit" class="btn btn-primary" id="saveBtn">
                                    <i class="fas fa-save"></i> Update Patient Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('assets/js/email-validation.js'); ?>"></script>
    
    <script>
    // Conditional field handling
    document.addEventListener('DOMContentLoaded', function() {
        // Handle marital status - enable/disable children and years married fields
        const statusSelect = document.getElementById('status');
        const childrenField = document.getElementById('no_of_children');
        const yearsMarriedField = document.getElementById('years_married');
        
        function updateMaritalFields() {
            if (statusSelect.value === 'Single') {
                childrenField.disabled = true;
                yearsMarriedField.disabled = true;
                childrenField.value = '';
                yearsMarriedField.value = '';
            } else {
                childrenField.disabled = false;
                yearsMarriedField.disabled = false;
            }
        }
        
        statusSelect.addEventListener('change', updateMaritalFields);
        updateMaritalFields(); // Initialize
        
        // Handle smoking status conditional fields
        const smokingRadios = document.querySelectorAll('input[name="smoking_status"]');
        const yearsSmokingField = document.getElementById('years_smoking');
        const cigarettesField = document.getElementById('cigarettes_per_day');
        
        function updateSmokingFields() {
            const selectedSmoking = document.querySelector('input[name="smoking_status"]:checked');
            if (selectedSmoking && selectedSmoking.value === 'Non-Smoker') {
                yearsSmokingField.disabled = true;
                cigarettesField.disabled = true;
                yearsSmokingField.value = '';
                cigarettesField.value = '';
            } else {
                yearsSmokingField.disabled = false;
                cigarettesField.disabled = false;
            }
        }
        
        smokingRadios.forEach(radio => {
            radio.addEventListener('change', updateSmokingFields);
        });
        updateSmokingFields(); // Initialize
        
        // Handle vaping conditional field
        const vapingRadios = document.querySelectorAll('input[name="vaping"]');
        const yearsVapingField = document.getElementById('years_vaping');
        
        function updateVapingFields() {
            const selectedVaping = document.querySelector('input[name="vaping"]:checked');
            if (selectedVaping && selectedVaping.value === 'No') {
                yearsVapingField.disabled = true;
                yearsVapingField.value = '';
            } else {
                yearsVapingField.disabled = false;
            }
        }
        
        vapingRadios.forEach(radio => {
            radio.addEventListener('change', updateVapingFields);
        });
        updateVapingFields(); // Initialize
        
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
    });
    
    function updateDistricts() {
        const stateSelect = document.getElementById('state');
        const districtSelect = document.getElementById('district');
        const selectedState = stateSelect.value;
        
        // Clear existing options except the current one
        const currentDistrict = "<?php echo htmlspecialchars($patient['district'] ?? ''); ?>";
        districtSelect.innerHTML = '<option value="">Select District</option>';
        
        if (!selectedState) return;
        
        const districts = {
            'Johor': ['Johor Bahru', 'Batu Pahat', 'Kluang', 'Kota Tinggi', 'Kulai', 'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak'],
            'Kedah': ['Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu', 'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan'],
            'Kelantan': ['Bachok', 'Gua Musang', 'Jeli', 'Kota Bharu', 'Kuala Krai', 'Machang', 'Pasir Mas', 'Pasir Puteh', 'Tanah Merah', 'Tumpat'],
            'Melaka': ['Melaka Tengah', 'Alor Gajah', 'Jasin'],
            'Negeri Sembilan': ['Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau', 'Seremban', 'Tampin'],
            'Pahang': ['Bentong', 'Bera', 'Cameron Highlands', 'Jerantut', 'Kuantan', 'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh'],
            'Perak': ['Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar', 'Kerian', 'Kuala Kangsar', 'Kinta', 'Larut, Matang dan Selama', 'Manjung', 'Muallim', 'Perak Tengah', 'Bagans Serai (Kerian)'],
            'Perlis': ['Kangar'],
            'Pulau Pinang': ['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan', 'Timur Laut (George Town)', 'Barat Daya'],
            'Selangor': ['Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang', 'Kuala Langat', 'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang'],
            'Terengganu': ['Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Terengganu', 'Marang', 'Setiu', 'Kuala Nerus'],
            'Sabah': ['Kota Kinabalu', 'Penampang', 'Putatan', 'Papar', 'Tuaran', 'Kota Belud', 'Ranau', 'Beaufort', 'Kuala Penyu', 'Tenom', 'Keningau', 'Tambunan', 'Nabawan', 'Sandakan', 'Beluran', 'Kinabatangan', 'Telupid', 'Tongod', 'Tawau', 'Lahad Datu', 'Kunak', 'Semporna', 'Kudat', 'Kota Marudu', 'Pitas'],
            'Sarawak': ['Kuching', 'Bau', 'Lundu', 'Samarahan', 'Asajaya', 'Simunjan', 'Sri Aman', 'Lubok Antu', 'Betong', 'Saratok', 'Sibu', 'Selangau', 'Kanowit', 'Mukah', 'Dalat', 'Miri', 'Marudi', 'Subis', 'Beluru', 'Telang Usan', 'Limbang', 'Lawas', 'Bintulu', 'Tatau', 'Kapit', 'Song', 'Belaga', 'Sarikei', 'Meradong', 'Julau', 'Pakan', 'Serian', 'Tebedu', 'Siburan'],
            'Kuala Lumpur': ['Kuala Lumpur'],
            'Labuan': ['Labuan'],
            'Putrajaya': ['Putrajaya']
        };
        
        if (districts[selectedState]) {
            districts[selectedState].forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                if (district === currentDistrict) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            });
        }
    }
    
      // Initialize districts on page load
      document.addEventListener('DOMContentLoaded', function() {
          updateDistricts();
          
          // Handle IC/Passport conditional logic
          const icField = document.getElementById('ic_number');
          const passportField = document.getElementById('passport');
          
          function updateFieldRequirements() {
              const icHasValue = icField.value.trim() !== '';
              const passportHasValue = passportField.value.trim() !== '';
              
              if (icHasValue && !passportHasValue) {
                  // If IC has value, passport becomes optional
                  passportField.required = false;
                  passportField.closest('.mb-3').querySelector('label').innerHTML = 'Passport No';
              } else if (!icHasValue && passportHasValue) {
                  // If passport has value, IC becomes optional
                  icField.required = false;
                  icField.closest('.mb-3').querySelector('label').innerHTML = 'NRIC';
              } else if (!icHasValue && !passportHasValue) {
                  // If neither has value, both are required
                  icField.required = true;
                  passportField.required = true;
                  icField.closest('.mb-3').querySelector('label').innerHTML = 'NRIC <span class="required">*</span>';
                  passportField.closest('.mb-3').querySelector('label').innerHTML = 'Passport No <span class="required">*</span>';
              }
          }
          
          icField.addEventListener('input', updateFieldRequirements);
          passportField.addEventListener('input', updateFieldRequirements);
          
          // Initialize field requirements
          updateFieldRequirements();
          
          // Auto-format NRIC input
          document.getElementById('ic_number').addEventListener('input', function(e) {
              let value = e.target.value.replace(/\D/g, '');
              if (value.length >= 6) {
                  value = value.substring(0, 6) + '-' + value.substring(6, 8) + '-' + value.substring(8, 12);
              }
              e.target.value = value;
          });
          
          // Auto-format phone number
          document.getElementById('telephone').addEventListener('input', function(e) {
              let value = e.target.value.replace(/\D/g, '');
              if (value.length >= 3) {
                  value = value.substring(0, 2) + '-' + value.substring(2);
              }
              e.target.value = value;
          });
      });
    </script>
</body>
</html>
