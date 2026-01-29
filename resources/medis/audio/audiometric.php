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

// Prefill
$prefilled_patient_name = $_GET['patient_name'] ?? '';
$prefilled_employer = $_GET['employer'] ?? '';
$patient_id = $_GET['patient_id'] ?? '';
$surveillance_id = isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0;
$is_new_entry = isset($_GET['new']) && $_GET['new'] == '1';

// Fetch comprehensive patient information (same as employee information)
$patient_data = null;
if (!empty($patient_id)) {
    try {
        $patient_data = getClinicPatientById($patient_id);
        
        if ($patient_data && !isset($patient_data['error'])) {
            $prefilled_patient_name = $patient_data['first_name'] . ' ' . $patient_data['last_name'];
            $prefilled_employer = $patient_data['company_name'] ?? '';
            
            if (!empty($patient_data['company_name'])) {
                $company_data = getCompanyByName($patient_data['company_name']);
                if ($company_data) {
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
                    try {
                        $stmt = $clinic_pdo->prepare("
                            SELECT jkkp_approval_no FROM audiometric_tests
                            WHERE patient_id = ? AND jkkp_approval_no IS NOT NULL AND jkkp_approval_no != ''
                            ORDER BY examination_date DESC, created_at DESC LIMIT 1");
                        $stmt->execute([$patient_id]);
                        $jkkp_record = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($jkkp_record && !empty($jkkp_record['jkkp_approval_no'])) {
                            $patient_data['company_jkkp_approval_no'] = $jkkp_record['jkkp_approval_no'];
                        }
                    } catch (Exception $e) { error_log("Error fetching JKKP: " . $e->getMessage()); }
                }
            }
            
            $medical_staff_data = getLoggedInUserMedicalStaffInfo();
            if (!$medical_staff_data && !empty($surveillance_id)) {
                try {
                    $stmt = $clinic_pdo->prepare("SELECT examiner_name FROM chemical_information WHERE surveillance_id = ? LIMIT 1");
                    $stmt->execute([$surveillance_id]);
                    $surveillance_record = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($surveillance_record && !empty($surveillance_record['examiner_name'])) {
                        $examiner_name = trim($surveillance_record['examiner_name']);
                        $examiner_name = preg_replace('/^Dr\.?\s*/i', '', $examiner_name);
                        $name_parts = explode(' ', $examiner_name, 2);
                        if (count($name_parts) >= 2) {
                            $stmt = $clinic_pdo->prepare("SELECT * FROM medical_staff WHERE first_name = ? AND last_name = ? LIMIT 1");
                            $stmt->execute([$name_parts[0], $name_parts[1]]);
                            $medical_staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        }
                    }
                } catch (Exception $e) { error_log("Error fetching medical staff: " . $e->getMessage()); }
            }
            
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
            $_SESSION['error_message'] = $error_msg;
            $patient_data = null;
        }
    } catch (Exception $e) {
        error_log('Error fetching patient for audiometric tabs: ' . $e->getMessage());
        $_SESSION['error_message'] = 'Error loading patient information: ' . $e->getMessage();
        $patient_data = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiometric - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .tab-container { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 0; }
        .nav-tabs .nav-link { border: none; border-radius: 0; padding: 1rem 2rem; font-weight: 600; color: #6c757d; background: transparent; transition: all 0.3s ease; }
        .nav-tabs .nav-link:hover { border-color: transparent; color: #495057; background-color: #f8f9fa; }
        .nav-tabs .nav-link.active { color: #2c3e50; background-color: white; border-bottom: 3px solid #28a745; font-weight: 700; }
        .tab-content { padding: 2rem; }
        @media print { .navbar, .btn, .nav-tabs, .tab-content { display: none !important; } .tab-pane.active { display: block !important; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="tab-container">
            <ul class="nav nav-tabs" id="audiometricTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="test-tab" data-bs-toggle="tab" data-bs-target="#test" type="button" role="tab" aria-controls="test" aria-selected="true">
                        <i class="fas fa-headphones"></i> Audiometric Test
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab" aria-controls="summary" aria-selected="false">
                        <i class="fas fa-chart-line"></i> Summary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button" role="tab" aria-controls="report" aria-selected="false">
                        <i class="fas fa-file-medical"></i> Report
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="audiometricTabContent">
                <div class="tab-pane fade show active" id="test" role="tabpanel" aria-labelledby="test-tab">
                    <?php
                        $test_params = [];
                        if (!empty($patient_id)) { $test_params[] = 'patient_id=' . urlencode($patient_id); }
                        if ($surveillance_id > 0) { $test_params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $test_params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $test_params[] = 'employer=' . urlencode($prefilled_employer); }
                        if ($is_new_entry) { $test_params[] = 'new=1'; }
                        $test_params[] = 'iframe=1';
                    ?>
                    <iframe src="<?php echo app_url('audiometric_test') . ($test_params ? '?' . implode('&', $test_params) : ''); ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
                <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                    <?php
                        $sum_params = [];
                        if (!empty($patient_id)) { $sum_params[] = 'patient_id=' . urlencode($patient_id); }
                        if ($surveillance_id > 0) { $sum_params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $sum_params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $sum_params[] = 'employer=' . urlencode($prefilled_employer); }
                        if ($is_new_entry) { $sum_params[] = 'new=1'; }
                        $sum_params[] = 'iframe=1';
                    ?>
                    <iframe src="<?php echo app_url('audiometric_summary') . ($sum_params ? '?' . implode('&', $sum_params) : ''); ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
                <div class="tab-pane fade" id="report" role="tabpanel" aria-labelledby="report-tab">
                    <?php
                        $rep_params = [];
                        if (!empty($patient_id)) { $rep_params[] = 'patient_id=' . urlencode($patient_id); }
                        if ($surveillance_id > 0) { $rep_params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $rep_params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $rep_params[] = 'employer=' . urlencode($prefilled_employer); }
                        $rep_params[] = 'iframe=1';
                    ?>
                    <iframe src="<?php echo app_url('audiometric_report') . ($rep_params ? '?' . implode('&', $rep_params) : ''); ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
