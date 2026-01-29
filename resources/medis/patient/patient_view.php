<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if patient ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: patients.php?message=' . urlencode('Patient ID is required') . '&type=danger');
    exit();
}

$patient_id = $_GET['id'];
$patient = getClinicPatientById($patient_id);

// Check if patient exists
if (isset($patient['error'])) {
    header('Location: patients.php?message=' . urlencode($patient['error']) . '&type=danger');
    exit();
}

if (!$patient) {
    header('Location: patients.php?message=' . urlencode('Patient not found') . '&type=danger');
    exit();
}

$pageTitle = 'Patient Details - ' . $patient['first_name'] . ' ' . $patient['last_name'];
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
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- View Patient -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-eye"></i> View Patient</h5>
                        <div>
                        <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Patients
                            </a>
                            <a href="<?php echo app_url('generate_patient_pdf.php'); ?>?id=<?php echo $patient_id; ?>" class="btn btn-success me-2" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </a>
                           
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Patient ID</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['patient_id']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Gender</label>
                                    <p>
                                        <span class="badge bg-<?php echo $patient['gender'] == 'Male' ? 'primary' : 'pink'; ?>">
                                            <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <p class="form-control-plaintext">
                                        <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Date of Birth</label>
                                    <p class="form-control-plaintext">
                                        <?php echo isset($patient['date_of_birth']) ? date('d/m/Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">NRIC</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['NRIC'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Passport No</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['passport_no'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Ethnicity</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['ethnicity'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Citizenship</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['citizenship'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Marital Status</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['martial_status'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Number of Children</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['no_of_children'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($patient['years_married']): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Years Married</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['years_married']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Phone</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['telephone_no'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">State</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['state'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">District</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['district'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Postcode</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['postcode'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($patient['address']): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['address'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Medical History Section -->
                        <?php if (!empty($patient['diagnosed_history']) || !empty($patient['medication_history']) || 
                                 !empty($patient['admitted_history']) || !empty($patient['family_history']) || 
                                 !empty($patient['others_history'])): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-heartbeat me-2"></i>Medical History</h6>
                        
                        <?php if (!empty($patient['diagnosed_history'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Diagnosed History</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['diagnosed_history'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['medication_history'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Medication History</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['medication_history'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['admitted_history'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Admitted History</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['admitted_history'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['family_history'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Family History</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['family_history'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['others_history'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Other Relevant History</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['others_history'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Personal & Social History Section -->
                        <?php if (!empty($patient['smoking_history']) || !empty($patient['vaping_history']) || 
                                 !empty($patient['hobby']) || !empty($patient['parttime_job'])): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-smoking me-2"></i>Personal & Social History</h6>
                        
                        <?php if (!empty($patient['smoking_history'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Smoking History</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['smoking_history']); ?></p>
                                </div>
                            </div>
                            <?php if (($patient['smoking_history'] === 'Current' || $patient['smoking_history'] === 'Ex - Smoker') && $patient['years_of_smoking']): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Years of Smoking</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['years_of_smoking']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (($patient['smoking_history'] === 'Current' || $patient['smoking_history'] === 'Ex - Smoker') && $patient['no_of_cigarettes']): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Number of Cigarettes per Day</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['no_of_cigarettes']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($patient['vaping_history'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Vaping History</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['vaping_history']); ?></p>
                                </div>
                            </div>
                            <?php if ($patient['vaping_history'] === 'Yes' && $patient['years_of_vaping']): ?>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Years of Vaping</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['years_of_vaping']); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['hobby'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hobby</label>
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['hobby']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['parttime_job'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Part-time Job</label>
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['parttime_job']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Occupational History Section -->
                        <?php if (!empty($patient['job_title']) || !empty($patient['company_name']) || 
                                 !empty($patient['employment_duration']) || !empty($patient['chemical_exposure_duration']) || 
                                 !empty($patient['chemical_exposure_incidents'])): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-briefcase me-2"></i>Occupational History</h6>
                        
                        <?php if (!empty($patient['job_title'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Job Title</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['job_title']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Company</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['company_name']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['employment_duration'])): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Employment Duration</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['employment_duration']); ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chemical Exposure Duration</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['chemical_exposure_duration']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($patient['chemical_exposure_incidents'])): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chemical Exposure Incidents</label>
                            <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['chemical_exposure_incidents'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <!-- Training History Section -->
                        <?php if (!empty($patient['handling_of_chemical']) || !empty($patient['sign_symptoms']) || 
                                 !empty($patient['chemical_poisoning']) || !empty($patient['proper_PPE']) || 
                                 !empty($patient['PPE_usage'])): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-3"><i class="fas fa-graduation-cap me-2"></i>Training History</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($patient['handling_of_chemical'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Handling of Chemical</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['handling_of_chemical']); ?></p>
                                </div>
                                <?php if (!empty($patient['chemical_comments'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chemical Comments</label>
                                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['chemical_comments'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($patient['sign_symptoms'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Sign & Symptoms Training</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['sign_symptoms']); ?></p>
                                </div>
                                <?php if (!empty($patient['sign_symptoms_comments'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Sign & Symptoms Comments</label>
                                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['sign_symptoms_comments'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <?php if (!empty($patient['chemical_poisoning'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chemical Poisoning Training</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['chemical_poisoning']); ?></p>
                                </div>
                                <?php if (!empty($patient['poisoning_comments'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Poisoning Comments</label>
                                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['poisoning_comments'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($patient['proper_PPE'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Proper PPE Training</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['proper_PPE']); ?></p>
                                </div>
                                <?php if (!empty($patient['proper_PPE_comments'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Proper PPE Comments</label>
                                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['proper_PPE_comments'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($patient['PPE_usage'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">PPE Usage</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($patient['PPE_usage']); ?></p>
                                </div>
                                <?php if (!empty($patient['PPE_usage_comment'])): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">PPE Usage Comments</label>
                                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($patient['PPE_usage_comment'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Patients
                            </a>
                            <div>
                                <a href="<?php echo app_url('declaration.php'); ?>?patient_name=<?php echo urlencode($patient['first_name'] . ' ' . $patient['last_name']); ?>&employer=<?php echo urlencode($patient['company_name'] ?? ''); ?>" 
                                   class="btn btn-info me-2" target="_blank">
                                    <i class="fas fa-file-alt"></i> Create Declaration
                                </a>
                                <a href="<?php echo app_url('medical_removal_protection.php'); ?>?patient_name=<?php echo urlencode($patient['first_name'] . ' ' . $patient['last_name']); ?>&employer=<?php echo urlencode($patient['company_name'] ?? ''); ?>&patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-warning me-2" target="_blank">
                                    <i class="fas fa-shield-alt"></i> Medical Removal Form
                                </a>
                                <a href="<?php echo app_url('patient_form.php'); ?>?edit=<?php echo $patient['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Patient
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('assets/js/main.js'); ?>"></script>
</body>
</html>
