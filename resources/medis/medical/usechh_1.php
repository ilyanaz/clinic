<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get doctor's saved signature from database
$doctor_signature_image = null;
try {
    $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'user_signatures'")->fetch();
    if ($tableCheck) {
        $stmt = $clinic_pdo->prepare("SELECT file_path FROM user_signatures WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $signature = $stmt->fetch();
        if ($signature) {
            $doctor_signature_image = $signature['file_path'];
        }
    }
} catch (Exception $e) {
    error_log("Error getting doctor signature: " . $e->getMessage());
}

// Get pre-filled patient name and employer from URL
$prefilled_patient_name = $_GET['patient_name'] ?? '';
$prefilled_employer = $_GET['employer'] ?? '';

// Get surveillance_id from URL for viewing existing records
$surveillance_id = isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0;

// Get company_id from URL or patient data
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;

// If patient_id is provided, fetch patient data
$patient = null;
$company = null;
if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT pi.id, pi.first_name, pi.last_name, pi.patient_id,
                   oh.company_name, oh.job_title
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE pi.id = ?
        ");
        $stmt->execute([$_GET['patient_id']]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_data) {
            $prefilled_patient_name = $patient_data['first_name'] . ' ' . $patient_data['last_name'];
            $prefilled_employer = $patient_data['company_name'] ?? '';
            $patient = $patient_data;
            
            // Get company_id from patient's occupational history if not provided
            if ($company_id <= 0 && $prefilled_employer) {
                try {
                    $stmt = $clinic_pdo->prepare("SELECT id, company_name FROM company WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?)) LIMIT 1");
                    $stmt->execute([$prefilled_employer]);
                    $company = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($company) {
                        $company_id = $company['id'];
                    }
                } catch (Exception $e) {
                    error_log("Error fetching company: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching patient data: " . $e->getMessage());
    }
} elseif (isset($_GET['patient_name']) && !empty($_GET['patient_name'])) {
    // If only patient_name is provided, try to find the patient by name
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT pi.id, pi.first_name, pi.last_name, pi.patient_id,
                   oh.company_name, oh.job_title
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE CONCAT(pi.first_name, ' ', pi.last_name) = ?
            LIMIT 1
        ");
        $stmt->execute([$_GET['patient_name']]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_data) {
            $prefilled_patient_name = $patient_data['first_name'] . ' ' . $patient_data['last_name'];
            $prefilled_employer = $patient_data['company_name'] ?? '';
            $patient = $patient_data;
            
            // Get company_id from patient's occupational history if not provided
            if ($company_id <= 0 && $prefilled_employer) {
                try {
                    $stmt = $clinic_pdo->prepare("SELECT id, company_name FROM company WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?)) LIMIT 1");
                    $stmt->execute([$prefilled_employer]);
                    $company = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($company) {
                        $company_id = $company['id'];
                    }
                } catch (Exception $e) {
                    error_log("Error fetching company: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching patient data by name: " . $e->getMessage());
    }
}

// If we don't have company data yet, fetch it
if (!$company && $company_id > 0) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT id, company_name FROM company WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching company by ID: " . $e->getMessage());
    }
}

// Check if this is for new surveillance (clear signatures)
$is_new_surveillance = isset($_GET['new_surveillance']) && $_GET['new_surveillance'] == '1';

// If surveillance_id is provided, fetch existing surveillance data
$existing_surveillance_data = null;
if ($surveillance_id > 0) {
    try {
        error_log("Fetching surveillance data for ID: " . $surveillance_id);
        $stmt = $clinic_pdo->prepare("
            SELECT sm.*, pi.first_name, pi.last_name, pi.patient_id, pi.NRIC, pi.date_of_birth, pi.gender,
                   oh.company_name, oh.job_title
            FROM chemical_information sm
            INNER JOIN patient_information pi ON sm.patient_id = pi.id
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE sm.surveillance_id = ?
        ");
        $stmt->execute([$surveillance_id]);
        $existing_surveillance_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_surveillance_data) {
            error_log("Surveillance data found for ID: " . $surveillance_id . ", Patient: " . $existing_surveillance_data['first_name'] . ' ' . $existing_surveillance_data['last_name']);
            // Update prefilled data with existing surveillance data
            $prefilled_patient_name = $existing_surveillance_data['first_name'] . ' ' . $existing_surveillance_data['last_name'];
            $prefilled_employer = $existing_surveillance_data['company_name'] ?? '';
        } else {
            error_log("No surveillance data found for ID: " . $surveillance_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching surveillance data: " . $e->getMessage());
    }
}

// Handle form submission for declaration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['declaration_form'])) {
    $patient_name = $_POST['patient_name'] ?? '';
    $employer = $_POST['employer'] ?? '';
    $patient_date = $_POST['patient_date'] ?? '';
    $doctor_date = $_POST['doctor_date'] ?? '';
    $patient_signature = $_POST['patient_signature'] ?? '';
    $doctor_signature = $_POST['doctor_signature'] ?? '';
    
    // Insert declaration into database
    try {
        // Check if table exists first
        $checkTable = $clinic_pdo->query("SHOW TABLES LIKE 'declarations'");
        if ($checkTable->rowCount() == 0) {
            // Create the declarations table if it doesn't exist
            $createTable = "CREATE TABLE declarations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_name VARCHAR(255) NOT NULL,
                employer VARCHAR(255) NOT NULL,
                patient_signature LONGTEXT,
                patient_date DATE,
                doctor_signature LONGTEXT,
                doctor_date DATE,
                created_by VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $clinic_pdo->exec($createTable);
        } else {
            // Check if created_by column exists, if not add it
            $checkColumn = $clinic_pdo->query("SHOW COLUMNS FROM declarations LIKE 'created_by'");
            if ($checkColumn->rowCount() == 0) {
                $clinic_pdo->exec("ALTER TABLE declarations ADD COLUMN created_by VARCHAR(255)");
            }
            
            // Check and fix signature column types if they're VARCHAR
            $checkPatientSig = $clinic_pdo->query("SHOW COLUMNS FROM declarations WHERE Field = 'patient_signature'");
            $patientSigColumn = $checkPatientSig->fetch();
            if ($patientSigColumn && strpos($patientSigColumn['Type'], 'varchar') !== false) {
                $clinic_pdo->exec("ALTER TABLE declarations MODIFY COLUMN patient_signature LONGTEXT");
            }
            
            $checkDoctorSig = $clinic_pdo->query("SHOW COLUMNS FROM declarations WHERE Field = 'doctor_signature'");
            $doctorSigColumn = $checkDoctorSig->fetch();
            if ($doctorSigColumn && strpos($doctorSigColumn['Type'], 'varchar') !== false) {
                $clinic_pdo->exec("ALTER TABLE declarations MODIFY COLUMN doctor_signature LONGTEXT");
            }
        }
        
        // Check if surveillance_id column exists, if not add it
        $checkSurveillanceColumn = $clinic_pdo->query("SHOW COLUMNS FROM declarations LIKE 'surveillance_id'");
        if ($checkSurveillanceColumn->rowCount() == 0) {
            $clinic_pdo->exec("ALTER TABLE declarations ADD COLUMN surveillance_id INT NULL");
        }
        
        // Get current surveillance_id from URL or patient data
        $current_surveillance_id = null;
        if (isset($_GET['surveillance_id']) && $_GET['surveillance_id'] > 0) {
            $current_surveillance_id = $_GET['surveillance_id'];
        } elseif ($existing_surveillance_data && isset($existing_surveillance_data['id'])) {
            $current_surveillance_id = $existing_surveillance_data['id'];
        }
        
        // If no surveillance_id is available, we'll use NULL
        // The declaration will be linked later when the surveillance record is created
        if (!$current_surveillance_id) {
            $current_surveillance_id = null;
        }
        
        // Get patient_id from patient_data or URL
        $current_patient_id = null;
        if ($patient_data && isset($patient_data['id'])) {
            $current_patient_id = $patient_data['id'];
        } elseif (isset($_GET['patient_id']) && $_GET['patient_id'] > 0) {
            $current_patient_id = $_GET['patient_id'];
        }
        
        $stmt = $clinic_pdo->prepare("INSERT INTO declarations (patient_id, patient_name, employer, patient_signature, patient_date, doctor_signature, doctor_date, created_by, surveillance_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_patient_id, $patient_name, $employer, $patient_signature, $patient_date, $doctor_signature, $doctor_date, $user_name, $current_surveillance_id]);
        
        $declaration_id = $clinic_pdo->lastInsertId();
        
        $_SESSION['success_message'] = 'Declaration saved successfully! Declaration ID: ' . $declaration_id;
        
        // Note: Signatures are now stored in database and loaded by declaration_id
        // No need to store in session to prevent conflicts between declarations
        
        // Stay on the same page and show success message
        $redirect_url = 'usechh_1.php?patient_name=' . urlencode($patient_name) . '&employer=' . urlencode($employer) . '&saved=1&declaration_id=' . $declaration_id;
        if ($surveillance_id > 0) {
            $redirect_url .= '&surveillance_id=' . $surveillance_id;
        }
        if (isset($_GET['patient_id'])) {
            $redirect_url .= '&patient_id=' . $_GET['patient_id'];
        }
        if (isset($_GET['company_id'])) {
            $redirect_url .= '&company_id=' . $_GET['company_id'];
        }
        header('Location: ' . $redirect_url);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error saving declaration: ' . $e->getMessage();
    }
}

// Handle error messages from save_declaration.php
if (isset($_GET['error'])) {
    $_SESSION['error_message'] = $_GET['error'];
}

// Debug session data (removed to prevent interference with form tab)
// if (isset($_SESSION['saved_declaration_id'])) {
//     echo "<!-- Debug: Declaration ID in session: " . $_SESSION['saved_declaration_id'] . " -->";
// } else {
//     echo "<!-- Debug: No declaration ID in session -->";
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USECHH 1 - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        /* Navigation Breadcrumb */
        .breadcrumb-nav {
            background: #f8fff9;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
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
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .tab-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0;
            padding: 1rem 2rem;
            font-weight: 600;
            color: #6c757d;
            background: transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: #495057;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            color: #2c3e50;
            background-color: white;
            border-bottom: 3px solid #28a745;
            font-weight: 700;
        }
        
        .tab-content {
            padding: 2rem;
        }
        
        .report-header {
            background: white;
            color: #2c3e50;
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 2px solid #34495e;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .report-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: #2c3e50;
            text-align: center;
            letter-spacing: 1px;
        }
        
        .report-subtitle {
            font-size: 1rem;
            color: #34495e;
            text-align: center;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }
        
        .legal-ref {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .document-id {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .declaration-form {
            background: white;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .form-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            letter-spacing: 0.5px;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
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
        }
        
        .signature-controls {
            margin-top: 10px;
        }
        
        .signature-controls button {
            margin-right: 10px;
            padding: 5px 15px;
            font-size: 12px;
        }
        
        .declaration-text {
            text-align: justify;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 1rem;
            color: #495057;
            padding: 1rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .certificate-form {
            background: #f8f9fa;
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .certificate-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        
        .certificate-content .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
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
        
        .min-height-60 {
            min-height: 3.75rem;
        }
        
        .min-height-40 {
            min-height: 2.5rem;
        }
        
        .certification-statement {
            padding: 1.5rem 0;
            margin: 1.5rem 0;
        }
        
        .certification-statement p {
            margin: 0 !important;
            padding: 0 !important;
            font-size: 1.1rem !important;
            line-height: 1.6 !important;
            display: block !important;
            width: 100% !important;
            white-space: nowrap !important;
        }
        
        .underline-field {
            font-weight: 600;
            color: #2c3e50;
            display: inline-block;
            padding: 0 0.5rem;
        }
        
        .doctor-signature {
            margin-top: 1.5rem;
            padding: 1.5rem 0;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            font-weight: 500;
            border-radius: 4px;
        }
        
        .btn-outline-primary {
            border-color: #3498db;
            color: #3498db;
        }
        
        .btn-outline-primary:hover {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
        }
        
        
        @media print {
            .navbar,
            .btn,
            .nav-tabs,
            .tab-content {
                display: none !important;
            }
            
            .tab-pane.active {
                display: block !important;
            }
            
            body {
                font-size: 10pt;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            
            .declaration-form {
                border: none !important;
                box-shadow: none !important;
                font-family: Arial, sans-serif !important;
                padding: 0.3rem !important;
                margin: 0 !important;
            }
            
            .form-section {
                background: transparent !important;
                border: none !important;
                padding: 0.2rem 0 !important;
                margin: 0.2rem 0 !important;
            }
            
            .section-title {
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0.1rem 0 !important;
                margin-bottom: 0.2rem !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                text-align: left !important;
                color: #000 !important;
                font-size: 11pt !important;
            }
            
            .form-control {
                border: none !important;
                border-radius: 0 !important;
                background: transparent !important;
                font-weight: normal !important;
                padding: 0.1rem 0 !important;
                margin: 0.1rem 0 !important;
            }
            
            .signature-line {
                display: none !important;
            }
            
            .form-label {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 10pt !important;
                margin-bottom: 0.25rem !important;
            }
            
            .declaration-text {
                text-align: justify !important;
                line-height: 1.3 !important;
                margin-bottom: 0.2rem !important;
            }
            
            .signature-section {
                margin-top: 0.5rem !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
                gap: 2rem !important;
            }
            
            .signature-block {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                width: 45% !important;
                min-width: 200px !important;
            }
            
            .signature-block .mb-4 {
                width: 100% !important;
                margin-bottom: 0.5rem !important;
            }
            
            .signature-block .mb-3 {
                width: 100% !important;
                margin-bottom: 0.3rem !important;
            }
            
            .date-container {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                width: 100% !important;
            }
            
            .date-label {
                margin-bottom: 0.5rem !important;
                font-weight: bold !important;
            }
            
            .date-value {
                font-weight: bold !important;
                font-size: 12pt !important;
            }
            
            .mb-3, .mb-4 {
                margin-bottom: 0.5rem !important;
            }
            
            .mt-4 {
                margin-top: 0.5rem !important;
            }
            
            .text-center {
                text-align: left !important;
            }
            
            .row {
                margin: 0 !important;
                display: flex !important;
                gap: 2rem !important;
            }
            
            .col-md-6 {
                padding: 0.25rem !important;
                flex: 1 !important;
            }
            
            .btn-lg {
                display: none !important;
            }
        }
        
        /* Enhanced styling from usechh1_view.php */
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
        
        .info-value.company-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            margin-top: 2rem;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
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
        
        .readonly-radio {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .readonly-checkbox {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .form-control-plaintext {
            padding: 0.5rem 0;
            font-size: 1rem;
            min-height: 2.5rem;
            display: flex;
            align-items: center;
            color: #495057;
        }
        
        .btn-group {
            margin-top: 2rem;
        }
        
        .btn {
            font-weight: 500;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid mt-4">

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($surveillance_id > 0 && $existing_surveillance_data): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle"></i>
                <strong>Viewing Existing Surveillance:</strong> Loading surveillance data for examination date <?php echo date('d/m/Y', strtotime($existing_surveillance_data['examination_date'])); ?>.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Success Messages -->
        <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <strong>Success!</strong> Declaration has been saved successfully! Declaration ID: <?php echo $_GET['declaration_id'] ?? ''; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['surveillance_saved']) && $_GET['surveillance_saved'] == '1'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <strong>Success!</strong> Surveillance record has been saved successfully! Surveillance ID: <?php echo $_GET['surveillance_id'] ?? ''; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- USECHH 1 Tab Container -->
        <div class="tab-container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-2" style="padding: 0.5rem 2rem 0.5rem 2rem;">
                <ol class="breadcrumb breadcrumb-custom mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo app_url('medical_list.php'); ?>">Company</a></li>
                    <?php if (isset($_GET['patient_id']) && $_GET['patient_id'] > 0 && isset($patient) && $company): ?>
                        <li class="breadcrumb-item"><a href="<?php echo app_url('surveillance_list.php'); ?>?company_id=<?php echo $company_id; ?>"><?php echo htmlspecialchars($company['company_name']); ?></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo app_url('surveillance_list.php'); ?>?company_id=<?php echo $company_id; ?>&patient_id=<?php echo $_GET['patient_id']; ?>"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Medical Surveillance</li>
                    <?php elseif (isset($_GET['patient_id']) && $_GET['patient_id'] > 0 && isset($patient)): ?>
                        <li class="breadcrumb-item"><a href="surveillance_list.php<?php 
                            if ($company_id > 0) echo '?company_id=' . $company_id;
                            if (isset($_GET['patient_id'])) echo ($company_id > 0 ? '&' : '?') . 'patient_id=' . $_GET['patient_id'];
                        ?>">Surveillance List</a></li>
                        <li class="breadcrumb-item active" aria-current="page">USECHH 1</li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="surveillance_list.php<?php 
                            if ($company_id > 0) echo '?company_id=' . $company_id;
                            if (isset($_GET['patient_id'])) echo ($company_id > 0 ? '&' : '?') . 'patient_id=' . $_GET['patient_id'];
                        ?>">Surveillance List</a></li>
                        <li class="breadcrumb-item active" aria-current="page">USECHH 1</li>
                    <?php endif; ?>
                </ol>
            </nav>
            <hr class="my-2" style="margin-left: 2rem; margin-right: 2rem;">
            <ul class="nav nav-tabs" id="usechhTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="declaration-tab" data-bs-toggle="tab" data-bs-target="#declaration" type="button" role="tab" aria-controls="declaration" aria-selected="true">
                        <i class="fas fa-file-medical"></i> Declaration
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="form-tab" data-bs-toggle="tab" data-bs-target="#form" type="button" role="tab" aria-controls="form" aria-selected="false">
                        <i class="fas fa-clipboard-list"></i> Form
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="usechhTabContent">
                <!-- Declaration Tab -->
                <div class="tab-pane fade show active" id="declaration" role="tabpanel" aria-labelledby="declaration-tab">
                    <!-- Medical Declaration Form -->
                            <div class="certificate-form">
                                <div class="text-center mb-5">
                                    <div class="legal-ref mb-2">Occupational Safety and Health Act 1994 (Act 514)</div>
                                    <div class="legal-ref mb-3">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
                                    <h3 class="certificate-title">MEDICAL DECLARATION</h3>
                                </div>
                                <div class="certificate-content">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Name of Person examined:</label>
                                            <div class="form-control-plaintext">
                                                <?php echo htmlspecialchars($prefilled_patient_name); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Name & Address of Employer:</label>
                                            <div class="form-control-plaintext">
                                                <?php echo htmlspecialchars($prefilled_employer); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Declaration Statement -->
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <label class="form-label">Declaration:</label>
                                            <div class="form-control-plaintext">
                                                <p>I hereby certify that the above statement is true. I, hereby give consent to the Occupational Health Doctor (OHD) to perform medical examination, necessary tests, and communicate with the employer the results of my medical examination and work capability.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Signature Section -->
                                    <?php if ($is_new_surveillance): ?>
                                    
                                    <?php elseif (isset($_GET['clear_signatures']) && $_GET['clear_signatures'] == '1'): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>New Declaration:</strong> Please provide fresh signatures for this declaration. Previous signatures have been cleared.
                                    </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="declarationForm">
                                        <input type="hidden" name="declaration_form" value="1">
                                        <!-- Hidden fields for form data -->
                                        <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($prefilled_patient_name); ?>">
                                        <input type="hidden" name="employer" value="<?php echo htmlspecialchars($prefilled_employer); ?>">
                                        <input type="hidden" name="patient_date" value="<?php echo date('Y-m-d'); ?>">
                                        <input type="hidden" name="doctor_date" value="<?php echo date('Y-m-d'); ?>">
                                        
                                        <div class="doctor-signature">
                                            <div class="signature-section">
                                                <div class="signature-block">
                                                    <div class="mb-4">
                                                        <label class="form-label"><strong>Patient Signature</strong></label>
                                                        <div class="signature-pad">
                                                            <canvas id="patient-signature-pad" width="400" height="150" style="border:1px solid #ccc; width: 100%; height: 150px;"></canvas>
                                                        </div>
                                                        <div class="signature-controls">
                                                            <button type="button" id="clear-patient" class="btn btn-outline-secondary btn-sm">Clear</button>
                                                            <button type="button" id="save-patient" class="btn btn-outline-primary btn-sm">Save Signature</button>
                                                        </div>
                                                        <input type="hidden" id="patient_signature_data" name="patient_signature" value="">
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="date-container">
                                                            <div class="date-label">Date:</div>
                                                            <div class="date-value"><?php echo date('d/m/Y'); ?></div>
                                                        </div>
                                                        <input type="hidden" name="patient_date" value="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="signature-block">
                                                    <div class="mb-4">
                                                        <label class="form-label"><strong>Doctor Signature</strong></label>
                                                        <div class="signature-pad">
                                                            <canvas id="doctor-signature-pad" width="400" height="150" style="border:1px solid #ccc; width: 100%; height: 150px;"></canvas>
                                                        </div>
                                                        <!--<div class="signature-controls">
                                                            <button type="button" id="clear-doctor" class="btn btn-outline-secondary btn-sm">Clear</button>
                                                            <button type="button" id="save-doctor" class="btn btn-outline-primary btn-sm">Save Signature</button>
                                                        </div>-->
                                                        <input type="hidden" id="doctor_signature_data" name="doctor_signature" value="">
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="date-container">
                                                            <div class="date-label">Date:</div>
                                                            <div class="date-value"><?php echo date('d/m/Y'); ?></div>
                                                        </div>
                                                        <input type="hidden" name="doctor_date" value="<?php echo date('Y-m-d'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Save and Generate PDF Buttons -->
                                        <div class="text-center mt-4">
                                            
                                            <button type="submit" class="btn btn-primary btn-lg me-3">
                                                <i class="fas fa-save"></i> Save Declaration
                                            </button>
                                            <button type="button" class="btn btn-success btn-lg" onclick="generatePDF(event); return false;">
                                                <i class="fas fa-file-pdf"></i> Generate PDF
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                </div>
                
                <!-- Form Tab -->
                <div class="tab-pane fade" id="form" role="tabpanel" aria-labelledby="form-tab">
                    <iframe src="surveillance_form.php<?php 
                        $iframe_params = [];
                        
                        // Ensure patient_id is passed - get it from various sources
                        $current_patient_id = null;
                        if (isset($_GET['patient_id'])) {
                            $current_patient_id = $_GET['patient_id'];
                        } elseif ($patient_data && isset($patient_data['id'])) {
                            $current_patient_id = $patient_data['id'];
                        } elseif ($existing_surveillance_data && isset($existing_surveillance_data['patient_id'])) {
                            $current_patient_id = $existing_surveillance_data['patient_id'];
                        }
                        
                        if ($current_patient_id) {
                            $iframe_params[] = 'patient_id=' . $current_patient_id;
                        }
                        
                        // Add company_id if available
                        if ($company_id > 0) {
                            $iframe_params[] = 'company_id=' . $company_id;
                        }
                        
                        if ($surveillance_id > 0) {
                            $iframe_params[] = 'surveillance_id=' . $surveillance_id;
                        }
                        $iframe_params[] = 'iframe=1';
                        if ($is_new_surveillance) {
                            $iframe_params[] = 'new_surveillance=1';
                        }
                        // Add patient data to preserve form state
                        if (isset($prefilled_patient_name)) {
                            $iframe_params[] = 'preserve_patient_name=' . urlencode($prefilled_patient_name);
                        }
                        if (isset($prefilled_employer)) {
                            $iframe_params[] = 'preserve_employer=' . urlencode($prefilled_employer);
                        }
                        echo '?' . implode('&', $iframe_params);
                    ?>" width="100%" height="100%" frameborder="0" style="border: none; min-height: 800px;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('assets/js/main.js'); ?>"></script>
    
    <script>
        function generatePDF(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            const patientSignature = document.getElementById('patient_signature_data').value;
            const doctorSignature = document.getElementById('doctor_signature_data').value;
            
            if (!patientSignature || !doctorSignature) {
                alert('Please provide both patient and doctor signatures before generating PDF');
                return false;
            }
            
            // Check if declaration is already saved
            <?php if (isset($_SESSION['saved_declaration_id'])): ?>
                console.log('Declaration ID found: <?php echo $_SESSION['saved_declaration_id']; ?>');
                // Clear localStorage before redirecting
                localStorage.removeItem('patient_signature');
                localStorage.removeItem('doctor_signature');
                // Redirect directly to generate PDF with the saved declaration ID
                window.location.href = 'generate_declaration_pdf.php?id=<?php echo $_SESSION['saved_declaration_id']; ?>';
            <?php else: ?>
                console.log('No saved declaration ID found');
                alert('Please save the declaration first before generating PDF');
            <?php endif; ?>
            
            return false;
        }
    </script>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing signature pads...');
            
            // Initialize signature pads
            const patientCanvas = document.getElementById('patient-signature-pad');
            const patientSignaturePad = new SignaturePad(patientCanvas);
            
            const doctorCanvas = document.getElementById('doctor-signature-pad');
            const doctorSignaturePad = new SignaturePad(doctorCanvas);
            
            console.log('Patient canvas found:', patientCanvas);
            console.log('Doctor canvas found:', doctorCanvas);
            
            if (!patientCanvas) {
                console.error('Patient canvas element not found!');
                return;
            }
            
            // Check if SignaturePad is available
            if (typeof SignaturePad === 'undefined') {
                console.error('SignaturePad library not loaded!');
                return;
            }
            
            console.log('Signature pads created:', patientSignaturePad, doctorSignaturePad);
            
            // Configure signature pads
            console.log('Configuring signature pads...');
            patientSignaturePad.penColor = '#000000';
            doctorSignaturePad.penColor = '#000000';
            console.log('Signature pads configured successfully');
            
            // Preload doctor's saved signature from database for new surveillance
            // Only preload if not loading from an existing declaration
            <?php if ($doctor_signature_image && file_exists($doctor_signature_image) && !isset($_GET['declaration_id'])): ?>
            setTimeout(function() {
                try {
                    <?php
                    // Convert the saved signature image to base64
                    $signature_base64 = base64_encode(file_get_contents($doctor_signature_image));
                    $mime_type = mime_content_type($doctor_signature_image);
                    $data_uri = 'data:' . $mime_type . ';base64,' . $signature_base64;
                    ?>
                    const savedSignature = '<?php echo addslashes($data_uri); ?>';
                    doctorSignaturePad.fromDataURL(savedSignature);
                    document.getElementById('doctor_signature_data').value = savedSignature;
                    console.log('Doctor signature preloaded from database');
                } catch (e) {
                    console.error('Error preloading doctor signature:', e);
                }
            }, 100);
            <?php endif; ?>
            
            // Check if we're returning from a save operation or new surveillance
            <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
            console.log('Returning from save operation');
            
            <?php if (isset($_GET['clear_signatures']) && $_GET['clear_signatures'] == '1'): ?>
            console.log('Clearing signatures for new declaration');
            // Clear signatures for new declaration
            patientSignaturePad.clear();
            doctorSignaturePad.clear();
            document.getElementById('patient_signature_data').value = '';
            document.getElementById('doctor_signature_data').value = '';
            // Clear declaration-specific localStorage for new declaration
            localStorage.removeItem('patient_signature_new');
            localStorage.removeItem('doctor_signature_new');
            <?php else: ?>
            // Load signatures from database for the specific declaration ID
            <?php if (isset($_GET['declaration_id']) && $_GET['declaration_id'] > 0): ?>
            console.log('Loading signatures from database for declaration ID: <?php echo $_GET['declaration_id']; ?>');
            <?php
            try {
                $declaration_stmt = $clinic_pdo->prepare("SELECT patient_signature, doctor_signature FROM declarations WHERE declaration_id = ?");
                $declaration_stmt->execute([$_GET['declaration_id']]);
                $declaration_signatures = $declaration_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($declaration_signatures && !empty($declaration_signatures['patient_signature'])) {
                    echo "setTimeout(function() {\n";
                    echo "    try {\n";
                    echo "        document.getElementById('patient_signature_data').value = '" . addslashes($declaration_signatures['patient_signature']) . "';\n";
                    echo "        patientSignaturePad.fromDataURL('" . addslashes($declaration_signatures['patient_signature']) . "');\n";
                    echo "        console.log('Patient signature loaded from database for declaration ID: " . $_GET['declaration_id'] . "');\n";
                    echo "    } catch (e) {\n";
                    echo "        console.error('Error loading patient signature:', e);\n";
                    echo "    }\n";
                    echo "}, 200);\n";
                }
                
                if ($declaration_signatures && !empty($declaration_signatures['doctor_signature'])) {
                    echo "setTimeout(function() {\n";
                    echo "    try {\n";
                    echo "        document.getElementById('doctor_signature_data').value = '" . addslashes($declaration_signatures['doctor_signature']) . "';\n";
                    echo "        doctorSignaturePad.fromDataURL('" . addslashes($declaration_signatures['doctor_signature']) . "');\n";
                    echo "        console.log('Doctor signature loaded from database for declaration ID: " . $_GET['declaration_id'] . "');\n";
                    echo "    } catch (e) {\n";
                    echo "        console.error('Error loading doctor signature:', e);\n";
                    echo "    }\n";
                    echo "}, 200);\n";
                }
            } catch (Exception $e) {
                error_log("Error loading declaration signatures: " . $e->getMessage());
            }
            ?>
            <?php else: ?>
            console.log('No declaration ID provided - starting with blank signatures');
            <?php endif; ?>
            <?php endif; ?>
            <?php else: ?>
            console.log('Not returning from save operation');
            <?php endif; ?>
            
            // Check if this is for new surveillance
            <?php if ($is_new_surveillance): ?>
            console.log('New surveillance detected - clearing patient signature only');
            // Clear patient signature for new surveillance, but keep doctor's saved signature
            setTimeout(function() {
                patientSignaturePad.clear();
                document.getElementById('patient_signature_data').value = '';
                // Clear declaration-specific localStorage for new surveillance
                localStorage.removeItem('patient_signature_new');
                localStorage.removeItem('doctor_signature_new');
                console.log('Patient signature cleared for new surveillance');
            }, 100);
            // Clear session signatures for new surveillance
            <?php
            unset($_SESSION['saved_declaration_id']);
            unset($_SESSION['saved_patient_signature']);
            unset($_SESSION['saved_doctor_signature']);
            ?>
            <?php endif; ?>
            
            // Note: Signatures are now loaded only from database based on declaration_id
            // No localStorage fallback to prevent signature conflicts between declarations
            
            // Patient signature controls
            document.getElementById('clear-patient').addEventListener('click', () => {
                patientSignaturePad.clear();
                document.getElementById('patient_signature_data').value = '';
            });
            
            document.getElementById('save-patient').addEventListener('click', () => {
                if (patientSignaturePad.isEmpty()) {
                    alert('Please sign before saving');
                    return;
                }
                const signatureData = patientSignaturePad.toDataURL();
                document.getElementById('patient_signature_data').value = signatureData;
                // Note: Not storing in localStorage to prevent conflicts between declarations
                alert('Patient signature saved!');
            });
            
            // Doctor signature controls - only if canvas exists
            const clearDoctorBtn = document.getElementById('clear-doctor');
            const saveDoctorBtn = document.getElementById('save-doctor');
            
            if (clearDoctorBtn && saveDoctorBtn) {
                clearDoctorBtn.addEventListener('click', () => {
                    doctorSignaturePad.clear();
                    document.getElementById('doctor_signature_data').value = '';
                });
                
                saveDoctorBtn.addEventListener('click', () => {
                    if (doctorSignaturePad.isEmpty()) {
                        alert('Please sign before saving');
                        return;
                    }
                    const signatureData = doctorSignaturePad.toDataURL();
                    document.getElementById('doctor_signature_data').value = signatureData;
                    // Note: Not storing in localStorage to prevent conflicts between declarations
                    alert('Doctor signature saved!');
                });
            }
            
            // Form submission validation
            document.getElementById('declarationForm').addEventListener('submit', function(e) {
                const patientSignature = document.getElementById('patient_signature_data').value;
                const doctorSignature = document.getElementById('doctor_signature_data').value;
                
                if (!patientSignature || !doctorSignature) {
                    e.preventDefault();
                    alert('Please provide both patient and doctor signatures before saving');
                    return false;
                }
            });
            
            // Clear all signatures function
            window.clearAllSignatures = function() {
                if (confirm('Are you sure you want to clear all signatures? This action cannot be undone.')) {
                    patientSignaturePad.clear();
                    doctorSignaturePad.clear();
                    document.getElementById('patient_signature_data').value = '';
                    document.getElementById('doctor_signature_data').value = '';
                    // Note: Not clearing localStorage to prevent conflicts between declarations
                    alert('All signatures have been cleared. You can now provide new signatures.');
                }
            };
            
            console.log('Signature pad initialization completed successfully');
        });
        
        // Note: Form tab redirects are now handled directly by the iframe
        
    </script>
</body>
</html>
