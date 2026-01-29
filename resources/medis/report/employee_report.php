<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

// Check if user has permission
if ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Doctor') {
    header('Location: ' . app_url('index.php'));
    exit();
}

$patient_id = $_GET['patient_id'] ?? null;
$patient_name = $_GET['patient_name'] ?? '';
$patient_data = null;
$surveillance_data = [];
$chemical_hazards = '';

// Get patient data if patient_id is provided
if ($patient_id) {
    try {
        // First, get basic patient data
        $stmt = $clinic_pdo->prepare("SELECT * FROM patient_information WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_data) {
            // Get occupational history data
            $stmt = $clinic_pdo->prepare("
                SELECT company_name, job_title, employment_duration, chemical_exposure_duration, chemical_exposure_incidents 
                FROM occupational_history 
                WHERE patient_id = ? 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $occupational_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($occupational_data) {
                $patient_data = array_merge($patient_data, $occupational_data);
                
                // Get company address information
                $stmt = $clinic_pdo->prepare("
                    SELECT address, district, state, postcode 
                    FROM company 
                    WHERE company_name = ?
                ");
                $stmt->execute([$occupational_data['company_name']]);
                $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($company_data) {
                    $patient_data = array_merge($patient_data, $company_data);
                }
            }
            
            // Get chemical hazards information
            $chemical_hazards = $patient_data['chemical_exposure_incidents'] ?? '';
            
            // Get comprehensive surveillance data with all related information
            $stmt = $clinic_pdo->prepare("
                SELECT 
                    sm.*,
                    pe.*,
                    hoh.*,
                    cf.*
                FROM chemical_information sm
                LEFT JOIN physical_examination pe ON sm.patient_id = pe.patient_id
                LEFT JOIN history_of_health hoh ON sm.patient_id = hoh.patient_id
                LEFT JOIN clinical_findings cf ON sm.patient_id = cf.patient_id
                WHERE sm.patient_id = ?
                ORDER BY sm.examination_date DESC
            ");
            $stmt->execute([$patient_id]);
            $surveillance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $patient_data = null;
        $surveillance_data = [];
        // Debug: Log the error
        error_log("Employee Report Error: " . $e->getMessage());
        echo "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
    }
}

// If no patient_id but patient_name is provided, try to find patient by name
if (!$patient_data && $patient_name) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM patient_information WHERE CONCAT(first_name, ' ', last_name) = ?");
        $stmt->execute([$patient_name]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient_data) {
            $patient_id = $patient_data['id']; // Set patient_id for surveillance data
            // Get occupational history and surveillance data (same as above)
            $stmt = $clinic_pdo->prepare("
                SELECT company_name, job_title, employment_duration, chemical_exposure_duration, chemical_exposure_incidents 
                FROM occupational_history 
                WHERE patient_id = ? 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $occupational_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($occupational_data) {
                $patient_data = array_merge($patient_data, $occupational_data);
                
                // Get company address information
                $stmt = $clinic_pdo->prepare("
                    SELECT address, district, state, postcode 
                    FROM company 
                    WHERE company_name = ?
                ");
                $stmt->execute([$occupational_data['company_name']]);
                $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($company_data) {
                    $patient_data = array_merge($patient_data, $company_data);
                }
            }
            
            $chemical_hazards = $patient_data['chemical_exposure_incidents'] ?? '';
            
            $stmt = $clinic_pdo->prepare("
                SELECT 
                    sm.*,
                    pe.*,
                    hoh.*,
                    cf.*
                FROM chemical_information sm
                LEFT JOIN physical_examination pe ON sm.patient_id = pe.patient_id
                LEFT JOIN history_of_health hoh ON sm.patient_id = hoh.patient_id
                LEFT JOIN clinical_findings cf ON sm.patient_id = cf.patient_id
                WHERE sm.patient_id = ?
                ORDER BY sm.examination_date DESC
            ");
            $stmt->execute([$patient_id]);
            $surveillance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Employee Report Name Search Error: " . $e->getMessage());
    }
}

// Get all patients for dropdown
$all_patients = getAllClinicPatients();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Medical Surveillance Report - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
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
            color: #7f8c8d;
            text-align: center;
            margin-bottom: 0.2rem;
        }
        .document-id {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .worker-chemical-info {
            background: white;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .worker-name-field, .chemical-name-field {
            border-bottom: 2px solid #000;
            padding: 0.8rem 0;
            min-height: 3rem;
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .worker-chemical-info .form-label {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.8rem;
        }
        .legal-ref {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .report-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 1rem 0;
        }
        .report-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
        }
        .summary-table {
            background: white;
            border: 1px solid #dee2e6;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            font-size: 0.75rem;
        }
        .summary-table th {
            background: #495057;
            color: white;
            font-weight: 600;
            padding: 0.8rem 0.5rem;
            border: 1px solid #495057;
            font-size: 0.8rem;
            text-align: center;
            vertical-align: middle;
            letter-spacing: 0.1px;
            white-space: nowrap;
        }
        .summary-table td {
            padding: 0.7rem 0.5rem;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            font-size: 0.8rem;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .summary-table .col-no { width: 5%; }
        .summary-table .col-date { width: 9%; }
        .summary-table .col-type { width: 11%; }
        .summary-table .col-history { width: 11%; }
        .summary-table .col-clinical { width: 11%; }
        .summary-table .col-target { width: 9%; }
        .summary-table .col-bei { width: 9%; }
        .summary-table .col-work { width: 9%; }
        .summary-table .col-conclusion { width: 10%; }
        .summary-table .col-mr { width: 9%; }
        .summary-table .col-ohd { width: 8%; }
        
        /* Certificate of Fitness Styling */
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

        
        /* Print styles for certificate */
        @media print {
            .certificate-form {
                background: white !important;
                border: 1px solid #000 !important;
                border-left: 4px solid #000 !important;
                box-shadow: none;
                padding: 1rem;
            }
            .doctor-signature {
                border-top: 1px solid #000 !important;
            }
            .certificate-title {
                font-size: 1.5rem;
            }
            .form-control-plaintext {
                font-size: 0.9rem;
            }
            .btn {
                display: none !important;
            }
        }
        
        /* Make table fit on one page */
        .summary-table table {
            width: 100%;
            table-layout: fixed;
        }
        
        /* Print styles for single page */
        @media print {
            .summary-table {
                font-size: 0.7rem !important;
                page-break-inside: avoid;
            }
            .summary-table th,
            .summary-table td {
                padding: 0.3rem 0.2rem !important;
                font-size: 0.7rem !important;
            }
        }
        .summary-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .summary-table tbody tr:hover {
            background-color: #e8f4f8;
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
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            font-weight: 600;
        }
        .print-section {
            display: none;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-section {
                display: block !important;
            }
            body {
                background: white !important;
                font-family: 'Times New Roman', serif !important;
                font-size: 11px !important;
                line-height: 1.3 !important;
            }
            .report-header {
                background: white !important;
                color: black !important;
                border: 2px solid black !important;
                box-shadow: none !important;
                page-break-after: avoid;
                margin-bottom: 20px !important;
            }
            .report-title {
                font-size: 16px !important;
                font-weight: bold !important;
                text-align: center !important;
            }
            .legal-ref {
                font-size: 9px !important;
                text-align: center !important;
            }
            .summary-table {
                border: 1px solid black !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .summary-table th {
                background: #f0f0f0 !important;
                color: black !important;
                border: 1px solid black !important;
                font-size: 8px !important;
                padding: 4px 2px !important;
                font-weight: bold !important;
            }
            .summary-table td {
                border: 1px solid black !important;
                font-size: 7px !important;
                padding: 3px 2px !important;
                vertical-align: top !important;
            }
            .worker-chemical-info {
                box-shadow: none !important;
                border: 1px solid black !important;
                page-break-inside: avoid;
                margin-bottom: 15px !important;
            }
            .worker-name-field, .chemical-name-field {
                border-bottom: 1px solid black !important;
                font-size: 10px !important;
                padding: 2px 0 !important;
            }
            .badge {
                background: white !important;
                color: black !important;
                border: 1px solid black !important;
                font-size: 6px !important;
                padding: 1px 3px !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Employee Selection Form (Hidden when patient is selected) -->
        <?php if (!$patient_data): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user"></i> Select Employee</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="patient_id" class="form-label">Employee Name</label>
                        <select class="form-select" id="patient_id" name="patient_id" onchange="this.form.submit()">
                            <option value="">Select Employee...</option>
                            <?php foreach ($all_patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo ($patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucwords(strtolower($patient['first_name'] . ' ' . $patient['last_name']))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="<?php echo app_url('generate_employee_pdf.php'); ?>?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($patient_data): ?>
        <!-- Medical Surveillance Report -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-medical"></i> Medical Surveillance Report</h5>
                <div>
                    <span class="badge bg-primary me-2">USECHH 2</span>
                    <a href="<?php echo app_url('generate_employee_pdf.php'); ?>?patient_id=<?php echo $patient_id; ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="fas fa-file-pdf"></i> Generate PDF
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="legal-ref mb-2">Occupational Safety and Health Act 1994 (Act 514)</div>
                    <div class="legal-ref mb-3">Use and Standard of Exposure of Chemical Hazardous to Health Regulations 2000</div>
                    <div class="report-title">SUMMARY REPORTS OF EMPLOYEE</div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered mb-0 summary-table">
                <thead>
                    <tr>
                        <th class="col-no">No.</th>
                        <th class="col-date">MS Date</th>
                        <th class="col-type">Assessment Type</th>
                        <th class="col-history">Health Effects</th>
                        <th class="col-clinical">Clinical Findings</th>
                        <th class="col-target">Organ Function</th>
                        <th class="col-bei">BEI Test</th>
                        <th class="col-work">Work Related</th>
                        <th class="col-conclusion">MS Result</th>
                        <th class="col-mr">MRP Date</th>
                        <th class="col-ohd">OHD Reg.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($surveillance_data)): ?>
                        <?php foreach ($surveillance_data as $index => $exam): ?>
                        <tr>
                            <td class="text-center"><strong><?php echo $index + 1; ?></strong></td>
                            <td class="text-center">
                                <strong><?php echo date('d/m/Y', strtotime($exam['examination_date'])); ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success" style="font-size: 0.75rem; padding: 0.4rem 0.6rem;"><?php echo htmlspecialchars($exam['examination_type'] ?? 'Periodic'); ?></span>
                            </td>
                            <td>
                                <?php 
                                // Check for health effects from history_of_health
                                $health_effects = [];
                                if (!empty($exam['breathing_difficulty']) && $exam['breathing_difficulty'] == 'Yes') $health_effects[] = 'Breathing';
                                if (!empty($exam['cough']) && $exam['cough'] == 'Yes') $health_effects[] = 'Cough';
                                if (!empty($exam['headache']) && $exam['headache'] == 'Yes') $health_effects[] = 'Headache';
                                if (!empty($exam['nausea']) && $exam['nausea'] == 'Yes') $health_effects[] = 'Nausea';
                                if (!empty($exam['eye_irritations']) && $exam['eye_irritations'] == 'Yes') $health_effects[] = 'Eye';
                                if (!empty($exam['skin_issues']) && $exam['skin_issues'] == 'Yes') $health_effects[] = 'Skin';
                                
                                echo !empty($health_effects) ? implode(', ', $health_effects) : 'None';
                                ?>
                            </td>
                            <td>
                                <?php 
                                // Check clinical findings from physical examination
                                $findings = [];
                                if (!empty($exam['general_appearance']) && $exam['general_appearance'] == 'Abnormal') $findings[] = 'Abnormal';
                                if (!empty($exam['ent']) && $exam['ent'] == 'Abnormal') $findings[] = 'ENT';
                                if (!empty($exam['skin']) && $exam['skin'] == 'Abnormal') $findings[] = 'Skin';
                                if (!empty($exam['respiratory']) && $exam['respiratory'] == 'Abnormal') $findings[] = 'Respiratory';
                                
                                echo !empty($findings) ? implode(', ', $findings) : '<span class="text-success fw-bold">Normal</span>';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                // Check organ function from physical examination
                                $organ_function = [];
                                if (!empty($exam['hepatomegaly']) && $exam['hepatomegaly'] == 'Yes') $organ_function[] = 'Liver';
                                if (!empty($exam['splenomegaly']) && $exam['splenomegaly'] == 'Yes') $organ_function[] = 'Spleen';
                                if (!empty($exam['lymph_nodes']) && $exam['lymph_nodes'] == 'Palpable') $organ_function[] = 'Lymph';
                                
                                echo !empty($organ_function) ? implode(', ', $organ_function) : 'N/A';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                // Check BEI test from biological monitoring
                                $bei_tests = [];
                                if (!empty($exam['biological_exposure'])) $bei_tests[] = 'Exposure';
                                if (!empty($exam['result_baseline'])) $bei_tests[] = 'Baseline';
                                if (!empty($exam['result_annual'])) $bei_tests[] = 'Annual';
                                
                                echo !empty($bei_tests) ? implode(', ', $bei_tests) : 'N/A';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                // Check work relatedness from MS findings
                                $work_related = [];
                                if (!empty($exam['clinical_work_related']) && $exam['clinical_work_related'] == 'Yes') $work_related[] = 'Clinical';
                                if (!empty($exam['organ_work_related']) && $exam['organ_work_related'] == 'Yes') $work_related[] = 'Organ';
                                if (!empty($exam['biological_work_related']) && $exam['biological_work_related'] == 'Yes') $work_related[] = 'Biological';
                                
                                echo !empty($work_related) ? implode(', ', $work_related) : 'Review';
                                ?>
                            </td>
                            <td class="text-center">
                                <?php 
                                $fitness_status = $exam['final_assessment'] ?? 'Pending';
                                if (!empty($fitness_status) && $fitness_status != 'Pending'): ?>
                                    <span class="badge bg-<?php 
                                        echo $fitness_status == 'Fit for Work' ? 'success' : 
                                            ($fitness_status == 'Not Fit for Work' ? 'danger' : 'warning'); 
                                    ?>" style="font-size: 0.75rem; padding: 0.4rem 0.6rem;">
                                        <?php echo htmlspecialchars($fitness_status); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <small><?php echo !empty($exam['date_of_MRP']) ? date('d/m/Y', strtotime($exam['date_of_MRP'])) : 'N/A'; ?></small>
                            </td>
                            <td class="text-center">
                                <small><?php echo htmlspecialchars($exam['examiner_name'] ?? 'Dr. Admin'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                                    <div><strong>No surveillance data available</strong></div>
                                    <div>This employee has not undergone any medical surveillance examinations.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Certificate of Fitness -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-certificate"></i> Certificate of Fitness</h5>
                <div>
                    <span class="badge bg-primary me-2">USECHH 3</span>
                    <a href="<?php echo app_url('generate_certificate_pdf.php'); ?>?patient_id=<?php echo $patient_id; ?>" class="btn btn-success btn-sm" target="_blank">
                        <i class="fas fa-file-pdf"></i> Generate PDF
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="certificate-form">
                    <div class="text-center mb-5">
                        
                        <div class="legal-ref mb-2">Occupational Safety and Health Act 1994 (Act 514)</div>
                        <div class="legal-ref mb-3">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
                        <h3 class="certificate-title">CERTIFICATE OF FITNESS</h3>
                    </div>

                    <div class="certificate-content">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Name of Person examined:</label>
                                <div class="form-control-plaintext">
                                    <?php echo htmlspecialchars(ucwords(strtolower($patient_data['first_name'] . ' ' . $patient_data['last_name']))); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NRIC/ Passport No.:</label>
                                <div class="form-control-plaintext">
                                    <?php 
                                    // Check for NRIC first, then passport_no
                                    $id_number = $patient_data['NRIC'] ?? $patient_data['passport_no'] ?? 'Not specified';
                                    echo htmlspecialchars($id_number);
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date of Birth:</label>
                                <div class="form-control-plaintext">
                                    <?php echo date('d/m/Y', strtotime($patient_data['date_of_birth'])); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sex:</label>
                                <div class="form-control-plaintext">
                                    <?php echo htmlspecialchars($patient_data['gender']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Name & Address of Employee:</label>
                                <div class="form-control-plaintext">
                                    <?php 
                                    $company_info = '';
                                    if (!empty($patient_data['company_name'])) {
                                        $company_info = $patient_data['company_name'];
                                        
                                        // Add address details if available
                                        if (!empty($patient_data['address'])) {
                                            $company_info .= "\n" . $patient_data['address'];
                                        }
                                        if (!empty($patient_data['postcode'])) {
                                            $company_info .= "\n" . $patient_data['postcode'];
                                        }
                                        if (!empty($patient_data['district'])) {
                                            $company_info .= " " . $patient_data['district'];
                                        }
                                        if (!empty($patient_data['state'])) {
                                            $company_info .= ", " . $patient_data['state'];
                                        }
                                        
                                    }
                                    echo nl2br(htmlspecialchars($company_info ?: 'Not specified'));
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Examination/Tests done and the results:</label>
                                <div class="form-control-plaintext">
                                <p>I hereby certify that I have examined the above-named person on <span class="underline-field"><?php echo !empty($surveillance_data) ? date('d/m/Y', strtotime($surveillance_data[0]['examination_date'])) : 'Not specified'; ?></span> and he/she is <span class="underline-field"><?php 
                                $fitness_status = 'Not specified';
                                if (!empty($surveillance_data) && !empty($surveillance_data[0]['final_assessment'])) {
                                    $fitness_status = $surveillance_data[0]['final_assessment'] == 'Fit for Work' ? 'fit' : 'not fit';
                                }
                                echo $fitness_status;
                            ?></span> for work which may expose him to <span class="underline-field"><?php 
                                // Get chemical from surveillance data instead of occupational history
                                $surveillance_chemical = '';
                                if (!empty($surveillance_data) && !empty($surveillance_data[0]['chemical'])) {
                                    $surveillance_chemical = $surveillance_data[0]['chemical'];
                                }
                                
                                // Use surveillance chemical if available, otherwise fall back to occupational history
                                $display_chemical = !empty($surveillance_chemical) ? $surveillance_chemical : $chemical_hazards;
                                $display_chemical = !empty($display_chemical) ? $display_chemical : 'chemical hazards';
                                echo htmlspecialchars($display_chemical);
                            ?></span>.</p>         
                            
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-12">
                                <label class="form-label">Remarks (if any):</label>
                                <div class="form-control-plaintext min-height-60">
                                    <?php if (!empty($surveillance_data) && !empty($surveillance_data[0]['notes'])): ?>
                                        <?php echo htmlspecialchars($surveillance_data[0]['notes']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No remarks</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="doctor-signature">
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <label class="form-label">Signature & Date:</label>
                                    <div class="form-control-plaintext min-height-40">
                                        <span class="text-muted">Signature required</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name of Occupational Health Doctor (in BLOCK letters):</label>
                                    <div class="form-control-plaintext">
                                        <?php echo htmlspecialchars($surveillance_data[0]['examiner_name'] ?? 'DR. ADMIN'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label">DOSH Reg. No.:</label>
                                    <div class="form-control-plaintext">
                                        <?php echo htmlspecialchars($surveillance_data[0]['dosh_reg_no'] ?? 'REG123456'); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address of Practice:</label>
                                    <div class="form-control-plaintext">
                                        Medical Surveillance System<br>Occupational Health Clinic
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <?php else: ?>
        <!-- No Patient Selected -->
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-3"></i>
            <h5>No Employee Selected</h5>
            <p>Please select an employee from the dropdown above to view their medical surveillance report.</p>
        </div>
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        
        
        function printCertificate() {
            // Print only the certificate section
            const printContent = document.querySelector('.certificate-content').outerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = `
                <div class="certificate-form">
                    <div class="text-center mb-4">
                        <h3 class="certificate-title">CERTIFICATE OF FITNESS</h3>
                        <div class="legal-ref mb-3">Occupational Safety and Health Act 1994 (Act 514)</div>
                        <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
                    </div>
                    ${printContent}
                </div>
            `;
            
            window.print();
            document.body.innerHTML = originalContent;
        }

        function exportReport() {
            // Create a new window for PDF generation
            const printWindow = window.open('', '_blank');
            const patientName = '<?php echo $patient_data ? htmlspecialchars(ucwords(strtolower($patient_data["first_name"] . " " . $patient_data["last_name"]))) : ""; ?>';
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Employee Medical Surveillance Report - ${patientName}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .report-header { 
                            background: white; 
                            color: black; 
                            padding: 20px; 
                            border: 2px solid black; 
                            margin-bottom: 20px;
                            text-align: center;
                        }
                        .report-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                        .report-subtitle { font-size: 12px; margin-bottom: 5px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 10px; }
                        th { background-color: #f0f0f0; font-weight: bold; }
                        .worker-chemical-info { margin-bottom: 20px; }
                        .worker-name-field, .chemical-name-field { 
                            border-bottom: 1px solid #000; 
                            padding: 5px 0; 
                            margin-bottom: 10px;
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <div class="report-title">SUMMARY REPORTS OF EMPLOYEE</div>
                        <div class="report-subtitle">Occupational Safety and Health Act 1994 (Act 514)</div>
                        <div class="report-subtitle">Use and Standard of Exposure of Chemical Hazardous to Health Regulations 2000</div>
                        <div style="text-align: right; margin-top: 10px;">
                            <div class="report-subtitle">USECHH 2</div>
                            <div class="report-subtitle">${new Date().toLocaleDateString()}</div>
                        </div>
                    </div>
                    
                    <div class="worker-chemical-info">
                        <h4>Employee: ${patientName}</h4>
                        <p><strong>Report Generated:</strong> ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    ${document.querySelector('.summary-table').outerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }
    </script>
</body>
</html>
