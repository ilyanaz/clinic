<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$messageType = '';

// Handle messages from URL parameters (after redirect)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'];
}

// Get company name from URL parameter (when coming from company_view.php)
$preSelectedCompany = isset($_GET['company']) ? urldecode($_GET['company']) : '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] == 'save_patient_form') {
        
        try {
            // Prepare patient data for clinic database
            $patientData = [
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
                'years_of_smoking' => $_POST['years_smoking'] ? (int)$_POST['years_smoking'] : 0,
                'no_of_cigarettes' => $_POST['cigarettes_per_day'] ? (int)$_POST['cigarettes_per_day'] : 0,
                'vaping_history' => $_POST['vaping'] ?? 'No',
                'years_of_vaping' => $_POST['years_vaping'] ? (int)$_POST['years_vaping'] : 0,
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
            
            // Save patient to clinic database
            $result = addPatientToClinic($patientData);
            
            if ($result['success']) {
                // Redirect to patients.php with success message
                header("Location: patients.php?message=" . urlencode("Patient information saved successfully!") . "&type=success");
                exit();
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error saving patient information: ' . $e->getMessage();
            $messageType = 'danger';
            
            // Redirect to prevent form resubmission
            header("Location: patient_form.php?message=" . urlencode($message) . "&type=danger");
            exit();
        }
    }
}

// Patient form doesn't need to load existing patients
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Patient Information - Medical Surveillance System</title>
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
                <div class="d-flex justify-content-end align-items-center mb-4">
                    <div>
                        <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> View Patients
                        </a>
                    </div>
                </div>
            </div>
        
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> WORKER INFORMATION</h5>
                        
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo app_url('patient_form.php'); ?>">
                            <input type="hidden" name="action" value="save_patient_form">
                            
                            <!-- Basic Patient Information -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> General Information</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ic_number" class="form-label">NRIC <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="ic_number" name="ic_number" placeholder="000000-00-0000" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="passport" class="form-label">Passport No <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="passport" name="passport" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="date_of_birth" class="form-label">Date of Birth <span class="required">*</span></label>
                                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                                            <select class="form-select" id="gender" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address (Alamat) <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="state" class="form-label">State (Negeri) <span class="required">*</span></label>
                                            <select class="form-select" id="state" name="state" required onchange="updateDistricts()">
                                                <option value="">Select State</option>
                                                <option value="Johor">Johor</option>
                                                <option value="Kedah">Kedah</option>
                                                <option value="Kelantan">Kelantan</option>
                                                <option value="Melaka">Melaka (Malacca)</option>
                                                <option value="Negeri Sembilan">Negeri Sembilan</option>
                                                <option value="Pahang">Pahang</option>
                                                <option value="Perak">Perak</option>
                                                <option value="Perlis">Perlis</option>
                                                <option value="Pulau Pinang">Pulau Pinang (Penang)</option>
                                                <option value="Sabah">Sabah</option>
                                                <option value="Sarawak">Sarawak</option>
                                                <option value="Selangor">Selangor</option>
                                                <option value="Terengganu">Terengganu</option>
                                                <option value="Kuala Lumpur">Kuala Lumpur</option>
                                                <option value="Labuan">Labuan</option>
                                                <option value="Putrajaya">Putrajaya</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="district" class="form-label">District (Daerah) <span class="required">*</span></label>
                                            <select class="form-select" id="district" name="district" required>
                                                <option value="">Select District</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="postcode" class="form-label">Post-code <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="postcode" name="postcode" required>
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
                                                    <input type="tel" class="form-control" id="telephone" name="telephone" placeholder="12-3456789" required>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="patient@example.com">
                                           
                                        </div>
                                    </div>
                                </div>
                                    
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ethnic" class="form-label">Ethnicity <span class="required">*</span></label>
                                            <select class="form-select" id="ethnic" name="ethnic" required>
                                                <option value="">Select Ethnicity</option>
                                                <option value="Malay">Malay</option>
                                                <option value="Chinese">Chinese</option>
                                                <option value="Indian">Indian</option>
                                                <option value="Orang Asli">Orang Asli</option>
                                                <option value="Others">Others</option>
                                            </select>
                                            <input type="text" class="form-control mt-2" id="ethnic_other" name="ethnic_other" placeholder="Specify ethnicity" style="display: none;">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="citizenship" class="form-label">Citizenship <span class="required">*</span></label>
                                            <select class="form-select" id="citizenship" name="citizenship" required>
                                                <option value="">Select Citizenship</option>
                                                <option value="Malaysian Citizen">Malaysian Citizen</option>
                                                <option value="Indonesian">Indonesian</option>
                                                <option value="Filipino">Filipino</option>
                                                <option value="Thai">Thai</option>
                                                <option value="Vietnamese">Vietnamese</option>
                                                <option value="Myanmar">Myanmar</option>
                                                <option value="Bangladeshi">Bangladeshi</option>
                                                <option value="Indian">Indian</option>
                                                <option value="Chinese">Chinese</option>
                                                <option value="Others">Others</option>
                                            </select>
                                            <input type="text" class="form-control mt-2" id="citizenship_other" name="citizenship_other" placeholder="Specify citizenship" style="display: none;">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Marital Status <span class="required">*</span></label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="Single">Single</option>
                                                <option value="Married">Married</option>
                                                <option value="Divorced">Divorced</option>
                                                <option value="Widowed">Widowed</option>
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
                                            <input type="number" class="form-control" id="no_of_children" name="no_of_children" min="0" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="years_married" class="form-label">Years Married (Bilangan tahun berkahwin)</label>
                                            <input type="number" class="form-control" id="years_married" name="years_married" min="0" disabled>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>

                            <!-- Section B: Medical History -->
                            <div class="form-section">
                                <h5><i class="fas fa-heartbeat"></i> Medical History</h5>
                                
                                <div class="mb-3">
                                    <label for="disease_diagnosed" class="form-label">Have you been diagnosed with any disease? If yes, please provide details: <span class="required">*</span></label>
                                    <textarea class="form-control" id="disease_diagnosed" name="disease_diagnosed" rows="3" placeholder="Please provide details of any diagnosed diseases..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="medication_followup" class="form-label">Are you on any medication or follow up? <span class="required">*</span></label>
                                    <textarea class="form-control" id="medication_followup" name="medication_followup" rows="3" placeholder="Please provide details of any current medications or follow-up treatments..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hospitalized" class="form-label">Have you ever been hospitalized? <span class="required">*</span></label>
                                    <textarea class="form-control" id="hospitalized" name="hospitalized" rows="3" placeholder="Please provide details of any hospitalization history..." required></textarea>
                                </div>
                            </div>

                            <!-- Section C: Personal & Social History -->
                            <div class="form-section">
                                <h5><i class="fas fa-user"></i> Personal & Social History</h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Smoking (Merokok) <span class="required">*</span></label>
                                            <div class="row">
                                                <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="smoking_status" id="smoking_current" value="Current" required>
                                                        <label class="form-check-label" for="smoking_current">Current</label>
                                            </div>
                                                </div>
                                                <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="smoking_status" id="smoking_ex" value="Ex-smoker" required>
                                                        <label class="form-check-label" for="smoking_ex">Ex-smoker</label>
                                            </div>
                                                </div>
                                                <div class="col-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="smoking_status" id="smoking_non" value="Non-smoker" required>
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
                                                    <input type="number" class="form-control" id="years_smoking" name="years_smoking" min="0">
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="cigarettes_per_day" class="form-label">Cigarettes/Day</label>
                                                    <input type="number" class="form-control" id="cigarettes_per_day" name="cigarettes_per_day" min="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Vaping <span class="required">*</span></label>
                                            <div class="row">
                                                <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="vaping" id="vaping_yes" value="Yes" required>
                                                        <label class="form-check-label" for="vaping_yes">Yes</label>
                                            </div>
                                                </div>
                                                <div class="col-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="vaping" id="vaping_no" value="No" required>
                                                        <label class="form-check-label" for="vaping_no">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="years_vaping" class="form-label">Years Vaping</label>
                                            <input type="number" class="form-control" id="years_vaping" name="years_vaping" min="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hobby" class="form-label">Hobby <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="hobby" name="hobby" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="part_time_job" class="form-label">Part-time Job <span class="required">*</span></label>
                                            <input type="text" class="form-control" id="part_time_job" name="part_time_job" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section D: Family History -->
                            <div class="form-section">
                                <h5><i class="fas fa-users"></i> Relevant Family History</h5>
                                
                                <div class="mb-3">
                                    <label for="family_history" class="form-label">Family Medical History <span class="required">*</span></label>
                                    <textarea class="form-control" id="family_history" name="family_history" rows="4" placeholder="Please provide details of any relevant family medical history including hereditary diseases, allergies, or conditions that may be relevant to occupational health..." required></textarea>
                                    <small class="form-text text-muted">Include information about parents, siblings, and close relatives</small>
                                </div>
                            </div>

                            <!-- Section E: Other Relevant Histories -->
                            <div class="form-section">
                                <h5><i class="fas fa-file-medical"></i> Other Relevant Histories</h5>
                                
                                <div class="mb-3">
                                    <label for="other_relevant_history" class="form-label">Other Relevant History <span class="required">*</span></label>
                                    <textarea class="form-control" id="other_relevant_history" name="other_relevant_history" rows="4" placeholder="Please provide any other relevant historical information including previous workplace incidents, medical procedures, or conditions that may affect occupational health..." required></textarea>
                                    <small class="form-text text-muted">Include any additional medical history, workplace incidents, or relevant information not covered in previous sections</small>
                                </div>
                            </div>

                            <!-- Section F: Occupational History -->
                            <div class="form-section">
                                <h5><i class="fas fa-briefcase"></i> Occupational History</h5>
                                
                                <!-- Present Employment -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-briefcase"></i> Present Employment</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_job_title" class="form-label">Job Title <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="present_job_title" name="present_job_title" placeholder="Enter current job title" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_company" class="form-label">Company Name <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="present_company" name="present_company" value="<?php echo htmlspecialchars($preSelectedCompany); ?>" <?php echo $preSelectedCompany ? 'readonly' : ''; ?> required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_duration" class="form-label">Employment Duration <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="present_duration" name="present_duration" placeholder="E.g., 2 years, 6 months" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="present_exposure_duration" class="form-label">Chemical Exposure Duration <span class="required">*</span></label>
                                                <input type="text" class="form-control" id="present_exposure_duration" name="present_exposure_duration" placeholder="E.g., 1 year, 3 months" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label for="present_chemical_exposure" class="form-label">Chemical Exposure Incidents <span class="required">*</span></label>
                                                <textarea class="form-control" id="present_chemical_exposure" name="present_chemical_exposure" rows="3" placeholder="Describe any chemical exposure incidents (spills, splashes, etc.)" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Past Employment -->
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 text-primary"><i class="fas fa-history"></i> Past Employment</h6>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="addPastEmployment">
                                            <i class="fas fa-plus"></i> Add Past Employment
                                        </button>
                                    </div>
                                    
                                    <!-- Past Employment Container -->
                                    <div id="pastEmploymentContainer">
                                        <!-- First past employment entry -->
                                        <div class="past-employment-entry mb-4 p-3 border rounded" data-entry="1">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0 text-secondary">Past Employment #1</h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm removePastEmployment" style="display: none;">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Job Title</label>
                                                        <input type="text" class="form-control" name="past_job_title[]" placeholder="Enter previous job title">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Company Name</label>
                                                        <input type="text" class="form-control" name="past_company[]" placeholder="Enter company name">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Employment Duration</label>
                                                        <input type="text" class="form-control" name="past_duration[]" placeholder="E.g., 3 years, 8 months">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Chemical Exposure Duration</label>
                                                        <input type="text" class="form-control" name="past_exposure_duration[]" placeholder="E.g., 2 years, 4 months">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="mb-3">
                                                        <label class="form-label">Chemical Exposure Incidents</label>
                                                        <textarea class="form-control" name="past_chemical_exposure[]" rows="3" placeholder="Describe any chemical exposure incidents (spills, splashes, etc.)"></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section G: History of Training -->
                            <div class="form-section">
                                <h5><i class="fas fa-graduation-cap"></i> History of Training</h5>
                                
                                <!-- Training Questions -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Training Experience</h6>
                                    
                                    <!-- Safe Handling Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Safe Handling of Chemicals <span class="required">*</span></label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_safe_handling" id="trained_safe_yes" value="Yes">
                                                            <label class="form-check-label" for="trained_safe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_safe_handling" id="trained_safe_no" value="No">
                                                            <label class="form-check-label" for="trained_safe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_safe_handling_comments" placeholder="Additional details">
                                            </div>
                                        </div>
                                </div>
                                
                                    <!-- Symptoms Recognition Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Recognizing Signs and Symptoms of Disease <span class="required">*</span></label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_recognizing_symptoms" id="trained_symptoms_yes" value="Yes">
                                                            <label class="form-check-label" for="trained_symptoms_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_recognizing_symptoms" id="trained_symptoms_no" value="No">
                                                            <label class="form-check-label" for="trained_symptoms_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_recognizing_symptoms_comments" placeholder="Additional details">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Chemical Poisoning Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Chemical Poisoning at Workplace <span class="required">*</span></label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_chemical_poisoning" id="trained_poisoning_yes" value="Yes">
                                                            <label class="form-check-label" for="trained_poisoning_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_chemical_poisoning" id="trained_poisoning_no" value="No">
                                                            <label class="form-check-label" for="trained_poisoning_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_chemical_poisoning_comments" placeholder="Additional details">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PPE Usage Training -->
                                    <div class="mb-3 p-3 border rounded">
                                        <label class="form-label fw-bold">Proper PPE Usage <span class="required">*</span></label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_ppe_usage" id="trained_ppe_yes" value="Yes">
                                                            <label class="form-check-label" for="trained_ppe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="trained_ppe_usage" id="trained_ppe_no" value="No">
                                                            <label class="form-check-label" for="trained_ppe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="trained_ppe_usage_comments" placeholder="Additional details">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PPE Usage Question -->
                                <div class="mb-4">
                                    <h6 class="mb-3 text-primary"><i class="fas fa-shield-alt"></i> PPE Usage <span class="required">*</span></h6>
                                    <div class="p-3 border rounded">
                                        <label class="form-label fw-bold">Do you use PPE when handling chemicals?</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="use_ppe_handling" id="use_ppe_yes" value="Yes">
                                                            <label class="form-check-label" for="use_ppe_yes">Yes</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="use_ppe_handling" id="use_ppe_no" value="No">
                                                            <label class="form-check-label" for="use_ppe_no">No</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Comments</label>
                                                <input type="text" class="form-control" name="use_ppe_handling_comments" placeholder="Additional details">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Workers
                                </a>
                                <button type="submit" class="btn btn-primary" id="saveBtn">
                                    <i class="fas fa-save"></i> Save Worker Form
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
        // Handle IC/Passport conditional logic - simplified
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
        
        // Handle ethnicity "Others" field
        const ethnicitySelect = document.getElementById('ethnic');
        const ethnicOtherField = document.getElementById('ethnic_other');
        
        ethnicitySelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                ethnicOtherField.style.display = 'block';
                ethnicOtherField.required = true;
            } else {
                ethnicOtherField.style.display = 'none';
                ethnicOtherField.required = false;
                ethnicOtherField.value = '';
            }
        });
        
        // Handle citizenship "Others" field
        const citizenshipSelect = document.getElementById('citizenship');
        const citizenshipOtherField = document.getElementById('citizenship_other');
        
        citizenshipSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                citizenshipOtherField.style.display = 'block';
                citizenshipOtherField.required = true;
            } else {
                citizenshipOtherField.style.display = 'none';
                citizenshipOtherField.required = false;
                citizenshipOtherField.value = '';
            }
        });
        
        // Handle marital status - enable/disable children and years married fields
        const statusSelect = document.getElementById('status');
        const childrenField = document.getElementById('no_of_children');
        const yearsMarriedField = document.getElementById('years_married');
        
        statusSelect.addEventListener('change', function() {
            if (this.value === 'Single') {
                childrenField.disabled = true;
                yearsMarriedField.disabled = true;
                childrenField.value = '';
                yearsMarriedField.value = '';
            } else {
                childrenField.disabled = false;
                yearsMarriedField.disabled = false;
            }
        });
        
        // Handle smoking status conditional fields
        const smokingRadios = document.querySelectorAll('input[name="smoking_status"]');
        const yearsSmokingField = document.getElementById('years_smoking');
        const cigarettesField = document.getElementById('cigarettes_per_day');
        
        smokingRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'Non-smoker') {
                    yearsSmokingField.disabled = true;
                    cigarettesField.disabled = true;
                    yearsSmokingField.value = '';
                    cigarettesField.value = '';
                } else {
                    yearsSmokingField.disabled = false;
                    cigarettesField.disabled = false;
                }
            });
        });
        
        // Handle vaping conditional field
        const vapingRadios = document.querySelectorAll('input[name="vaping"]');
        const yearsVapingField = document.getElementById('years_vaping');
        
        vapingRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'No') {
                    yearsVapingField.disabled = true;
                    yearsVapingField.value = '';
                } else {
                    yearsVapingField.disabled = false;
                }
            });
        });
        
        // Handle dynamic past employment
        let pastEmploymentCounter = 1;
        const addPastEmploymentBtn = document.getElementById('addPastEmployment');
        const pastEmploymentContainer = document.getElementById('pastEmploymentContainer');
        
        addPastEmploymentBtn.addEventListener('click', function() {
            pastEmploymentCounter++;
            
            const newEntry = document.createElement('div');
            newEntry.className = 'past-employment-entry mb-4 p-3 border rounded';
            newEntry.setAttribute('data-entry', pastEmploymentCounter);
            
            newEntry.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 text-secondary">Past Employment #${pastEmploymentCounter}</h6>
                    <button type="button" class="btn btn-outline-danger btn-sm removePastEmployment">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Job Title</label>
                            <input type="text" class="form-control" name="past_job_title[]" placeholder="Enter previous job title">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" name="past_company[]" placeholder="Enter company name">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Employment Duration</label>
                            <input type="text" class="form-control" name="past_duration[]" placeholder="E.g., 3 years, 8 months">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Chemical Exposure Duration</label>
                            <input type="text" class="form-control" name="past_exposure_duration[]" placeholder="E.g., 2 years, 4 months">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Chemical Exposure Incidents</label>
                            <textarea class="form-control" name="past_chemical_exposure[]" rows="3" placeholder="Describe any chemical exposure incidents (spills, splashes, etc.)"></textarea>
                        </div>
                    </div>
                </div>
            `;
            
            pastEmploymentContainer.appendChild(newEntry);
            updateRemoveButtons();
        });
        
        // Handle remove past employment
        pastEmploymentContainer.addEventListener('click', function(e) {
            if (e.target.closest('.removePastEmployment')) {
                const entry = e.target.closest('.past-employment-entry');
                entry.remove();
                updateRemoveButtons();
                updateEntryNumbers();
            }
        });
        
        function updateRemoveButtons() {
            const entries = document.querySelectorAll('.past-employment-entry');
            entries.forEach(entry => {
                const removeBtn = entry.querySelector('.removePastEmployment');
                if (entries.length === 1) {
                    removeBtn.style.display = 'none';
                } else {
                    removeBtn.style.display = 'block';
                }
            });
        }
        
        function updateEntryNumbers() {
            const entries = document.querySelectorAll('.past-employment-entry');
            entries.forEach((entry, index) => {
                const title = entry.querySelector('h6');
                title.textContent = `Past Employment #${index + 1}`;
            });
        }
        
        // Initialize remove buttons visibility
        updateRemoveButtons();
        
        // Initialize smoking and vaping fields as disabled
        yearsSmokingField.disabled = true;
        cigarettesField.disabled = true;
        yearsVapingField.disabled = true;
        
        // Form submission handled normally - no JavaScript interference
        
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
    </script>
    
    <script>
    // Aggressively prevent form resubmission dialog
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    
    // Replace history state to prevent back button issues
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    
    // Force clean URL
    if (window.location.search.includes('message=')) {
        window.history.replaceState(null, null, window.location.pathname);
    }
    
    function updateDistricts() {
        const stateSelect = document.getElementById('state');
        const districtSelect = document.getElementById('district');
        const selectedState = stateSelect.value;
        
        // Clear existing options
        districtSelect.innerHTML = '<option value="">Select District</option>';
        
        if (!selectedState) return;
        
        const districts = {
            'Johor': [
                'Johor Bahru', 'Batu Pahat', 'Kluang', 'Kota Tinggi', 'Kulai', 
                'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak'
            ],
            'Kedah': [
                'Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu',
                'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan'
            ],
            'Kelantan': [
                'Bachok', 'Gua Musang', 'Jeli', 'Kota Bharu', 'Kuala Krai',
                'Machang', 'Pasir Mas', 'Pasir Puteh', 'Tanah Merah', 'Tumpat'
            ],
            'Melaka': [
                'Melaka Tengah', 'Alor Gajah', 'Jasin'
            ],
            'Negeri Sembilan': [
                'Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau', 'Seremban', 'Tampin'
            ],
            'Pahang': [
                'Bentong', 'Bera', 'Cameron Highlands', 'Jerantut', 'Kuantan',
                'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh'
            ],
            'Perak': [
                'Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar', 'Kerian',
                'Kuala Kangsar', 'Kinta', 'Larut, Matang dan Selama', 'Manjung', 'Muallim', 'Perak Tengah', 'Bagans Serai (Kerian)'
            ],
            'Perlis': [
                'Kangar'
            ],
            'Pulau Pinang': [
                'Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan',
                'Timur Laut (George Town)', 'Barat Daya'
            ],
            'Selangor': [
                'Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang', 'Kuala Langat',
                'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang'
            ],
            'Terengganu': [
                'Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Terengganu',
                'Marang', 'Setiu', 'Kuala Nerus'
            ],
            'Sabah': [
                'Kota Kinabalu', 'Penampang', 'Putatan', 'Papar', 'Tuaran', 'Kota Belud', 'Ranau',
                'Beaufort', 'Kuala Penyu', 'Tenom', 'Keningau', 'Tambunan', 'Nabawan',
                'Sandakan', 'Beluran', 'Kinabatangan', 'Telupid', 'Tongod',
                'Tawau', 'Lahad Datu', 'Kunak', 'Semporna',
                'Kudat', 'Kota Marudu', 'Pitas'
            ],
            'Sarawak': [
                'Kuching', 'Bau', 'Lundu', 'Samarahan', 'Asajaya', 'Simunjan',
                'Sri Aman', 'Lubok Antu', 'Betong', 'Saratok', 'Sibu', 'Selangau', 'Kanowit',
                'Mukah', 'Dalat', 'Miri', 'Marudi', 'Subis', 'Beluru', 'Telang Usan',
                'Limbang', 'Lawas', 'Bintulu', 'Tatau', 'Kapit', 'Song', 'Belaga',
                'Sarikei', 'Meradong', 'Julau', 'Pakan', 'Serian', 'Tebedu', 'Siburan'
            ],
            'Kuala Lumpur': [
                'Kuala Lumpur'
            ],
            'Labuan': [
                'Labuan'
            ],
            'Putrajaya': [
                'Putrajaya'
            ]
        };
        
        if (districts[selectedState]) {
            districts[selectedState].forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                districtSelect.appendChild(option);
            });
        }
    }
    </script>
</body>
</html>

