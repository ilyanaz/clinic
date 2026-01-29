<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get parameters from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;
$test_date = $_GET['test_date'] ?? '';
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;

// Check if we have any search parameters
$has_search_params = ($test_id > 0) || (!empty($test_date) && $patient_id > 0) || ($patient_id > 0);

// Fetch audiometric test record based on parameters
$test_record = null;
$patient_data = null;
$company_data = null;

if ($has_search_params) {
    try {
        // Build query based on available parameters
        // Use simpler query that matches audiometric_history.php (which works)
        if ($test_id > 0) {
            // Fetch by test_id (most specific)
            $stmt = $clinic_pdo->prepare("
                SELECT t.*, 
                       pi.first_name, pi.last_name, pi.patient_id as patient_code,
                       oh.company_name
                FROM audiometric_tests t
                LEFT JOIN patient_information pi ON t.patient_id = pi.id
                LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                WHERE t.id = ?
            ");
            $stmt->execute([$test_id]);
            $test_record = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($test_date) && $patient_id > 0) {
            // Fetch by test_date and patient_id
            $stmt = $clinic_pdo->prepare("
                SELECT t.*, 
                       pi.first_name, pi.last_name, pi.patient_id as patient_code,
                       oh.company_name
                FROM audiometric_tests t
                LEFT JOIN patient_information pi ON t.patient_id = pi.id
                LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                WHERE t.patient_id = ? AND t.examination_date = ?
                ORDER BY t.id DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id, $test_date]);
            $test_record = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($patient_id > 0) {
            // Fetch latest test for patient
            $stmt = $clinic_pdo->prepare("
                SELECT t.*, 
                       pi.first_name, pi.last_name, pi.patient_id as patient_code,
                       oh.company_name
                FROM audiometric_tests t
                LEFT JOIN patient_information pi ON t.patient_id = pi.id
                LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                WHERE t.patient_id = ?
                ORDER BY t.examination_date DESC, t.id DESC
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $test_record = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Fetch additional patient details if record found
        if ($test_record && !empty($test_record['patient_id'])) {
            try {
                $patientStmt = $clinic_pdo->prepare("
                    SELECT NRIC, date_of_birth, gender
                    FROM patient_information
                    WHERE id = ?
                ");
                $patientStmt->execute([$test_record['patient_id']]);
                $patientDetails = $patientStmt->fetch(PDO::FETCH_ASSOC);
                if ($patientDetails) {
                    $test_record['NRIC'] = $patientDetails['NRIC'] ?? null;
                    $test_record['date_of_birth'] = $patientDetails['date_of_birth'] ?? null;
                    $test_record['gender'] = $patientDetails['gender'] ?? null;
                }
                
                // Fetch occupational history details
                $occStmt = $clinic_pdo->prepare("
                    SELECT job_title, department
                    FROM occupational_history
                    WHERE patient_id = ?
                    LIMIT 1
                ");
                $occStmt->execute([$test_record['patient_id']]);
                $occDetails = $occStmt->fetch(PDO::FETCH_ASSOC);
                if ($occDetails) {
                    $test_record['job_title'] = $occDetails['job_title'] ?? null;
                    $test_record['department'] = $occDetails['department'] ?? null;
                }
            } catch (Exception $e) {
                // Silently ignore - these are optional fields
                error_log('Optional field fetch error: ' . $e->getMessage());
            }
        }
        
        if ($test_record) {
            $patient_id = $test_record['patient_id'];
            $patient_data = [
                'id' => $test_record['patient_id'],
                'first_name' => $test_record['first_name'] ?? '',
                'last_name' => $test_record['last_name'] ?? '',
                'patient_id' => $test_record['patient_code'] ?? '',
                'NRIC' => $test_record['NRIC'] ?? null,
                'date_of_birth' => $test_record['date_of_birth'] ?? null,
                'gender' => $test_record['gender'] ?? null,
                'company_name' => $test_record['company_name'] ?? '',
                'job_title' => $test_record['job_title'] ?? null,
                'department' => $test_record['department'] ?? null
            ];
            
            // Get company_id if company_name is available
            if (!empty($test_record['company_name']) && $company_id <= 0) {
                $companyStmt = $clinic_pdo->prepare("SELECT id FROM company WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?)) LIMIT 1");
                $companyStmt->execute([$test_record['company_name']]);
                $companyRow = $companyStmt->fetch(PDO::FETCH_ASSOC);
                if ($companyRow) {
                    $company_id = $companyRow['id'];
                }
            }
            
            // Get company data if company_id is available
            if ($company_id > 0) {
                $companyStmt = $clinic_pdo->prepare("SELECT * FROM company WHERE id = ?");
                $companyStmt->execute([$company_id]);
                $company_data = $companyStmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log('Database error fetching audiometric test for view: ' . $e->getMessage() . ' | SQL State: ' . $e->getCode());
        // Try a simpler fallback query
        try {
            if ($test_id > 0) {
                $fallbackStmt = $clinic_pdo->prepare("
                    SELECT t.*, 
                           pi.first_name, pi.last_name, pi.patient_id as patient_code,
                           oh.company_name
                    FROM audiometric_tests t
                    LEFT JOIN patient_information pi ON t.patient_id = pi.id
                    LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                    WHERE t.id = ?
                ");
                $fallbackStmt->execute([$test_id]);
                $test_record = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($test_record) {
                    // Set default values for missing fields
                    $test_record['NRIC'] = $test_record['NRIC'] ?? null;
                    $test_record['date_of_birth'] = $test_record['date_of_birth'] ?? null;
                    $test_record['gender'] = $test_record['gender'] ?? null;
                    $test_record['job_title'] = $test_record['job_title'] ?? null;
                    $test_record['department'] = $test_record['department'] ?? null;
                }
            }
            
            if (!$test_record) {
                $_SESSION['error_message'] = 'Database error: Unable to load audiometric test data. Please try again.';
                header('Location: ' . app_url('audiometric_history') . ($company_id > 0 ? '?company_id=' . $company_id : ''));
                exit();
            }
        } catch (Exception $fallbackError) {
            error_log('Fallback query also failed: ' . $fallbackError->getMessage());
            $_SESSION['error_message'] = 'Database error: Unable to load audiometric test data. Please try again.';
            header('Location: ' . app_url('audiometric_history') . ($company_id > 0 ? '?company_id=' . $company_id : ''));
            exit();
        }
    } catch (Exception $e) {
        error_log('Error fetching audiometric test for view: ' . $e->getMessage());
        $_SESSION['error_message'] = 'Error loading audiometric test data: ' . htmlspecialchars($e->getMessage());
        header('Location: ' . app_url('audiometric_history') . ($company_id > 0 ? '?company_id=' . $company_id : ''));
        exit();
    }
    
    // Only redirect with error if we had search parameters but found no record
    if (!$test_record && $has_search_params) {
        $_SESSION['error_message'] = 'Audiometric test record not found.';
        header('Location: ' . app_url('audiometric_history') . ($company_id > 0 ? '?company_id=' . $company_id : ''));
        exit();
    }
} else {
    // No search parameters provided - redirect to history without error
    header('Location: ' . app_url('audiometric_history') . ($company_id > 0 ? '?company_id=' . $company_id : ''));
    exit();
}

$prefilled_patient_name = ($patient_data['first_name'] ?? '') . ' ' . ($patient_data['last_name'] ?? '');
$prefilled_employer = $patient_data['company_name'] ?? '';
$surveillance_id = $test_record['surveillance_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Audiometric - KLINIK HAYDAR & KAMAL</title>
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
    <?php // include __DIR__ . '/../../views/includes/navigation.php'; ?>

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
                    <iframe id="testIframe" src="audiometric_test.php<?php 
                        $params = [];
                        if ($test_id > 0) { $params[] = 'test_id=' . $test_id; }
                        if ($patient_id > 0) { $params[] = 'patient_id=' . $patient_id; }
                        if (!empty($test_date)) { $params[] = 'test_date=' . urlencode($test_date); }
                        if ($company_id > 0) { $params[] = 'company_id=' . $company_id; }
                        if ($surveillance_id > 0) { $params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $params[] = 'employer=' . urlencode($prefilled_employer); }
                        $params[] = 'view=1';
                        $params[] = 'iframe=1';
                        echo ($params ? '?' . implode('&', $params) : '');
                    ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
                <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                    <iframe src="audiometric_summary.php<?php 
                        $params = [];
                        if ($test_id > 0) { $params[] = 'test_id=' . $test_id; }
                        if (!empty($patient_id)) { $params[] = 'patient_id=' . urlencode($patient_id); }
                        if (!empty($test_date)) { $params[] = 'test_date=' . urlencode($test_date); }
                        if ($company_id > 0) { $params[] = 'company_id=' . $company_id; }
                        if ($surveillance_id > 0) { $params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $params[] = 'employer=' . urlencode($prefilled_employer); }
                        $params[] = 'iframe=1';
                        echo ($params ? '?' . implode('&', $params) : '');
                    ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
                <div class="tab-pane fade" id="report" role="tabpanel" aria-labelledby="report-tab">
                    <iframe src="audiometric_report.php<?php 
                        $params = [];
                        if ($test_id > 0) { $params[] = 'test_id=' . $test_id; }
                        if (!empty($patient_id)) { $params[] = 'patient_id=' . urlencode($patient_id); }
                        if (!empty($test_date)) { $params[] = 'test_date=' . urlencode($test_date); }
                        if ($company_id > 0) { $params[] = 'company_id=' . $company_id; }
                        if ($surveillance_id > 0) { $params[] = 'surveillance_id=' . $surveillance_id; }
                        if (!empty($prefilled_patient_name)) { $params[] = 'patient_name=' . urlencode($prefilled_patient_name); }
                        if (!empty($prefilled_employer)) { $params[] = 'employer=' . urlencode($prefilled_employer); }
                        $params[] = 'iframe=1';
                        echo ($params ? '?' . implode('&', $params) : '');
                    ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 900px;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ensure iframe content is fully loaded before interacting
        document.addEventListener('DOMContentLoaded', function() {
            const testIframe = document.getElementById('testIframe');
            if (testIframe) {
                testIframe.addEventListener('load', function() {
                    console.log('Test iframe loaded');
                    // Give extra time for charts to initialize
                    setTimeout(function() {
                        try {
                            // Try to trigger chart update in iframe if possible
                            const iframeWindow = testIframe.contentWindow;
                            if (iframeWindow && typeof iframeWindow.forceUpdateCharts === 'function') {
                                iframeWindow.forceUpdateCharts();
                            }
                        } catch (e) {
                            // Cross-origin or other error - ignore
                            console.log('Cannot access iframe content (expected if cross-origin)');
                        }
                    }, 2000);
                });
            }
        });
    </script>
</body>
</html>
