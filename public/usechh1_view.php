<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('login.php'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get surveillance ID from URL (accept both 'id' and 'surveillance_id' parameters)
$surveillance_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['surveillance_id']) ? (int)$_GET['surveillance_id'] : 0);

// Get declaration ID from URL if provided
$declaration_id = isset($_GET['declaration_id']) ? (int)$_GET['declaration_id'] : 0;

if ($surveillance_id <= 0) {
    if ($surveillance_id == 0) {
        // Try to find the most recent surveillance record for debugging
        try {
            $stmt = $clinic_pdo->query("
                SELECT sm.*, pi.first_name, pi.last_name, pi.patient_id, pi.NRIC, pi.date_of_birth, pi.gender,
                       oh.company_name, oh.job_title
                FROM chemical_information sm
                INNER JOIN patient_information pi ON sm.patient_id = pi.id
                LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                WHERE sm.surveillance_id = 0
                ORDER BY sm.surveillance_id DESC
                LIMIT 1
            ");
            $zero_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($zero_record) {
                $error_message = 'This surveillance record has an invalid ID (ID = 0). The record exists but needs to be fixed in the database. Patient: ' . $zero_record['first_name'] . ' ' . $zero_record['last_name'] . ', Date: ' . $zero_record['examination_date'];
            } else {
                $error_message = 'Invalid surveillance ID (ID = 0). No surveillance record found with this ID.';
            }
        } catch (Exception $e) {
            $error_message = 'Invalid surveillance ID (ID = 0). Database error: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Invalid surveillance ID. Please provide a valid surveillance ID.';
    }
    $surveillance_data = null; // Ensure no data processing
    error_log("usechh1_view.php: Invalid surveillance_id: " . $surveillance_id);
}


// Fetch surveillance data
$surveillance_data = null;
$patient_data = null;
$declaration_data = null;

try {
    // Only fetch data if we have a valid surveillance_id
    if ($surveillance_id > 0) {
        // Get main surveillance data
        $stmt = $clinic_pdo->prepare("
            SELECT sm.*, pi.first_name, pi.last_name, pi.patient_id, pi.NRIC, pi.date_of_birth, pi.gender,
                   oh.company_name, oh.job_title
            FROM chemical_information sm
            INNER JOIN patient_information pi ON sm.patient_id = pi.id
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE sm.surveillance_id = ?
        ");
        $stmt->execute([$surveillance_id]);
        $surveillance_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$surveillance_data) {
            $error_message = 'Surveillance record not found for ID: ' . $surveillance_id;
            error_log("usechh1_view.php: No surveillance data found for ID: " . $surveillance_id);
        } else {
            // Log success for debugging
            error_log("usechh1_view.php: Surveillance data found for ID: " . $surveillance_id . ", Patient: " . $surveillance_data['first_name'] . ' ' . $surveillance_data['last_name']);
        }
    }
    
    // Only fetch related data if surveillance_data exists
    if ($surveillance_data) {
        // Get physical examination data for this specific surveillance
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM physical_examination 
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$surveillance_data['patient_id']]);
        $physical_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Get clinical findings data for this specific surveillance
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM clinical_findings 
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$surveillance_data['patient_id']]);
        $clinical_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Get conclusion MS finding data for this specific surveillance
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM conclusion_ms_finding 
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$surveillance_data['patient_id']]);
        $conclusion_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Get recommendations data for this specific surveillance
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM recommendations 
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$surveillance_data['patient_id']]);
        $recommendations_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get declaration data - only if surveillance data exists
    $declaration_data = null;
    
    if ($surveillance_data) {
        if ($declaration_id > 0) {
            // First priority: Find by specific declaration ID
            $stmt = $clinic_pdo->prepare("
                SELECT * FROM declarations 
                WHERE declaration_id = ?
                LIMIT 1
            ");
            $stmt->execute([$declaration_id]);
            $declaration_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($declaration_data) {
                error_log("usechh1_view.php: Declaration found by ID: " . $declaration_id);
            }
        }
        
        // Second priority: Find by surveillance_id if no declaration found by ID
        if (!$declaration_data && $surveillance_id > 0) {
            $stmt = $clinic_pdo->prepare("
                SELECT * FROM declarations 
                WHERE surveillance_id = ?
                ORDER BY declaration_id DESC
                LIMIT 1
            ");
            $stmt->execute([$surveillance_id]);
            $declaration_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($declaration_data) {
                error_log("usechh1_view.php: Declaration found by surveillance_id: " . $surveillance_id);
            }
        }
        
        // Third priority: Find by patient name and examination date (only if surveillance_id is valid)
        if (!$declaration_data && $surveillance_id > 0) {
            $stmt = $clinic_pdo->prepare("
                SELECT * FROM declarations 
                WHERE patient_name = ? AND DATE(patient_date) = DATE(?)
                ORDER BY declaration_id DESC
                LIMIT 1
            ");
            $stmt->execute([$surveillance_data['first_name'] . ' ' . $surveillance_data['last_name'], $surveillance_data['examination_date']]);
            $declaration_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($declaration_data) {
                error_log("usechh1_view.php: Declaration found by patient name and date for surveillance_id: " . $surveillance_id);
            }
        }
        
        if (!$declaration_data) {
            error_log("usechh1_view.php: No declaration found for surveillance_id: " . $surveillance_id . ", declaration_id: " . $declaration_id);
        }
    } else {
        error_log("usechh1_view.php: Cannot search for declarations - surveillance data not found for ID: " . $surveillance_id);
    }
    }
    
} catch (Exception $e) {
    error_log("usechh1_view.php: Database error: " . $e->getMessage());
    $error_message = 'Error fetching surveillance data: ' . $e->getMessage();
}

// Helper function to display radio button state
function displayRadioState($value, $expectedValue) {
    if ($value == $expectedValue) {
        return '<i class="fas fa-check-circle text-success"></i>';
    } else {
        return '<i class="fas fa-times-circle text-muted"></i>';
    }
}

// Helper function to display checkbox state
function displayCheckboxState($value) {
    if ($value == 'Yes' || $value == '1') {
        return '<i class="fas fa-check-square text-success"></i>';
    } else {
        return '<i class="fas fa-square text-muted"></i>';
    }
}

// Helper function to display text value
function displayTextValue($value, $default = '-') {
    return !empty($value) ? htmlspecialchars($value) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USECHH 1 View - KLINIK HAYDAR & KAMAL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .view-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .view-header {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1.5rem 2rem;
            border-radius: 8px 8px 0 0;
        }
        
        .view-content {
            padding: 2rem;
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
        
        .tab-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .view-header {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1.5rem 2rem;
            border-radius: 8px 8px 0 0;
        }
        
        .tab-content {
            padding: 2rem;
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
        
        .form-control-plaintext {
            padding: 0.5rem 0;
            font-size: 1rem;
            min-height: 2.5rem;
            display: flex;
            align-items: center;
            color: #495057;
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
        
        .doctor-signature {
            margin-top: 1.5rem;
            padding: 1.5rem 0;
            border-top: 1px solid #dee2e6;
        }
        
        .date-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .date-label {
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        
        .date-value {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .legal-ref {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #28a745;
            color: #28a745;
        }
        
        .nav-tabs .nav-link.active {
            color: #28a745;
            border-bottom-color: #28a745;
            background-color: transparent;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
        }
        
        @media print {
            .btn-group {
                display: none !important;
            }
            
            body {
                font-size: 10pt;
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
            }
            
            .view-container {
                border: none !important;
                box-shadow: none !important;
                font-family: Arial, sans-serif !important;
                padding: 0.3rem !important;
                margin: 0 !important;
            }
            
            .view-content {
                padding: 0.2rem !important;
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
            
            .table th {
                font-weight: bold !important;
                color: #000 !important;
                font-size: 10pt !important;
                margin-bottom: 0.25rem !important;
            }
            
            .table td {
                font-size: 9pt !important;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <!-- Main Content --><!-- Main Content -->
    <div class="container-fluid mt-4">
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                <br><br>
                <a href="company.php" class="btn btn-primary">Back to Company List</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$surveillance_data && !isset($error_message)): ?>
            <div class="alert alert-warning">
                <strong>Warning:</strong> No surveillance data found for ID <?php echo $surveillance_id; ?>.
                <br>This might be because:
                <ul>
                    <li>The surveillance record doesn't exist</li>
                    <li>The surveillance record is not linked to a patient</li>
                    <li>There's a database connection issue</li>
                </ul>
                <a href="company.php" class="btn btn-primary">Back to Company List</a>
            </div>
        <?php else: ?>
            <!-- USECHH 1 View Container -->
            <?php if ($surveillance_data && !isset($error_message)): ?>
            <div class="tab-container">
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
                
                <div class="view-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><i class="fas fa-file-medical"></i> USECHH 1</h4>
                            <p class="text-muted mb-0">Surveillance ID: <?php echo $surveillance_id; ?> | Examination Date: <?php echo date('d/m/Y', strtotime($surveillance_data['examination_date'])); ?></p>
                        </div>
                        <div class="btn-group">
                            
                            <?php if ($declaration_data): ?>
                            <a href="generate_declaration_pdf.php?id=<?php echo $declaration_data['declaration_id']; ?>" class="btn btn-outline-success" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate Declaration PDF
                            </a>
                            <?php endif; ?>
                            <a href="generate_surveillance_pdf.php?id=<?php echo $surveillance_id; ?>" class="btn btn-outline-info" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate Form PDF
                            </a>
                            <a href="company.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Company List
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="tab-content">
                    <!-- Declaration Tab Content -->
                    <div class="tab-pane fade show active" id="declaration" role="tabpanel" aria-labelledby="declaration-tab">
                        <!-- Medical Declaration Display -->
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
                                            <?php echo htmlspecialchars($surveillance_data['first_name'] . ' ' . $surveillance_data['last_name']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Name & Address of Employer:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['company_name'] ?? ''); ?>
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

                                <!-- Signature Display -->
                                <?php if ($declaration_data): ?>
                                <div class="doctor-signature">
                                    <div class="signature-section">
                                        <div class="signature-block">
                                            <div class="mb-4">
                                                <label class="form-label"><strong>Patient Signature (Tandatangan Pesakit):</strong></label>
                                                <div class="signature-pad">
                                                    <?php if (!empty($declaration_data['patient_signature'])): ?>
                                                        <img src="<?php echo htmlspecialchars($declaration_data['patient_signature']); ?>" style="width: 100%; height: 150px; border: 1px solid #ccc;" alt="Patient Signature">
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 150px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; color: #999;">
                                                            No Signature
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="date-container">
                                                    <div class="date-label">Date:</div>
                                                    <div class="date-value"><?php echo $declaration_data['patient_date'] ? date('d/m/Y', strtotime($declaration_data['patient_date'])) : '-'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="signature-block">
                                            <div class="mb-4">
                                                <label class="form-label"><strong>Doctor Signature (Tandatangan Doktor):</strong></label>
                                                <div class="signature-pad">
                                                    <?php if (!empty($declaration_data['doctor_signature'])): ?>
                                                        <img src="<?php echo htmlspecialchars($declaration_data['doctor_signature']); ?>" style="width: 100%; height: 150px; border: 1px solid #ccc;" alt="Doctor Signature">
                                                    <?php else: ?>
                                                        <div style="width: 100%; height: 150px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; color: #999;">
                                                            No Signature
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="date-container">
                                                    <div class="date-label">Date:</div>
                                                    <div class="date-value"><?php echo $declaration_data['doctor_date'] ? date('d/m/Y', strtotime($declaration_data['doctor_date'])) : '-'; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>No Declaration Found:</strong> No declaration has been saved for this examination date.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Tab Content -->
                    <div class="tab-pane fade" id="form" role="tabpanel" aria-labelledby="form-tab">
                        <!-- Surveillance Form Display -->
                        <div class="certificate-form">
                            <div class="text-center mb-5">
                                <h3 class="certificate-title">MEDICAL SURVEILLANCE FORM</h3>
                                <p class="text-muted">Examination Date: <?php echo date('d/m/Y', strtotime($surveillance_data['examination_date'])); ?></p>
                            </div>
                            
                            <!-- Patient Information -->
                            <div class="form-section">
                                <h5 class="section-title">Patient Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Patient Name:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['first_name'] . ' ' . $surveillance_data['last_name']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Patient ID:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['patient_id']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Company Name:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['company_name'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Job Title:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['job_title'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Surveillance Information -->
                            <div class="form-section">
                                <h5 class="section-title">Surveillance Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Workplace:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['workplace'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Chemical:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['chemical'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Examination Type:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['examination_type'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Examiner Name:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['examiner_name'] ?? 'Medical Officer'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Physical Examination -->
                            <?php if ($physical_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Physical Examination</h5>
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Weight (kg):</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['weight'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Height (cm):</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['height'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">BMI:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['BMI'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Blood Pressure:</label>
                                        <div class="form-control-plaintext">
                                            <?php 
                                            $bp = '';
                                            if (!empty($physical_data['bp_systolic']) && !empty($physical_data['bp_distolic'])) {
                                                $bp = $physical_data['bp_systolic'] . '/' . $physical_data['bp_distolic'];
                                            }
                                            echo htmlspecialchars($bp);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Pulse Rate:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['pulse_rate'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Respiratory Rate:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['respiratory_rate'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">General Appearance:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($physical_data['general_appearance'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Clinical Findings -->
                            <?php if ($clinical_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Clinical Findings</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Clinical Findings:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($clinical_data['result_clinical_findings'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Elaboration:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($clinical_data['elaboration'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Conclusion MS Finding -->
                            <?php if ($conclusion_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Conclusion MS Finding</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">History of Health:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($conclusion_data['history_of_health'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Conclusion MS Finding:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($conclusion_data['conclusion_ms_finding'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Health History Symptoms -->
                            <?php if ($surveillance_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Health History & Symptoms</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Breathing Difficulty:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['breathing_difficulty'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Cough:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['cough'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Sore Throat:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['sore_throat'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Chest Pain:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['chest_pain'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Headache:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['headache'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Dizziness:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['dizziness'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($surveillance_data['others_symptoms'])): ?>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Other Symptoms:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['others_symptoms']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Target Organ Assessment -->
                            <?php if ($surveillance_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Target Organ Assessment</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Target Organ:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['target_organ'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Biological Monitoring:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['biological_monitoring'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Pregnancy/Breast Feeding:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['pregnancy_breast_feeding'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Clinical Work Related:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['clinical_work_related'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Organ Work Related:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['organ_work_related'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Biological Work Related:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['biological_work_related'] ?? 'No'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Respirator Assessment -->
                            <?php if ($surveillance_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Respirator Assessment</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Respirator Result:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['respirator_result'] ?? 'Fit'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Justification:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['respirator_justification'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Biological Monitoring Results -->
                            <?php if ($surveillance_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Biological Monitoring Results</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Biological Exposure:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['biological_exposure'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Baseline Result:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['result_baseline'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Annual Result:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($surveillance_data['result_annual'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Recommendations -->
                            <?php if ($recommendations_data): ?>
                            <div class="form-section">
                                <h5 class="section-title">Recommendations</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Recommendations Type:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($recommendations_data['recommendations_type'] ?? ''); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of MRP:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo $recommendations_data['date_of_MRP'] ? date('d/m/Y', strtotime($recommendations_data['date_of_MRP'])) : '-'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Next Review Date:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo $recommendations_data['next_review_date'] ? date('d/m/Y', strtotime($recommendations_data['next_review_date'])) : '-'; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Notes:</label>
                                        <div class="form-control-plaintext">
                                            <?php echo htmlspecialchars($recommendations_data['notes'] ?? ''); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
