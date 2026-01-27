<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('login.php'));
    exit();
}

// Include database connection
require_once 'config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';

// Get company_id and chemical from URL parameters
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
$company_name = isset($_GET['company_name']) ? $_GET['company_name'] : null;
$selected_chemical = isset($_GET['chemical']) ? $_GET['chemical'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $work_unit_name = $_POST['work_unit_name'] ?? '';
        $chra_date = $_POST['chra_date'] ?? '';
        $chra_report_no = $_POST['chra_report_no'] ?? '';
        $indication = $_POST['indication'] ?? [];
        $others_details = $_POST['others_details'] ?? '';
        $mrp_employees = (int)($_POST['mrp_employees'] ?? 0);
        $laboratory_name = $_POST['laboratory_name'] ?? '';
        $recommendation = $_POST['recommendation'] ?? '';
        $decision = $_POST['decision'] ?? '';
        $continue_justification = $_POST['continue_justification'] ?? '';
        $continue_date = $_POST['continue_date'] ?? '';
        $stop_justification = $_POST['stop_justification'] ?? '';
        $stop_date = $_POST['stop_date'] ?? '';
        
        // Prepare indication data
        $indication_data = json_encode([
            'indications' => $indication,
            'others_details' => $others_details
        ]);
        
        
        // Check if record exists for this company
        $check_stmt = $clinic_pdo->prepare("SELECT id FROM ms_report_data WHERE company_id = ?");
        $check_stmt->execute([$company_id]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_record) {
            // Update existing record
            $stmt = $clinic_pdo->prepare("
                UPDATE ms_report_data SET
                    work_unit_name = ?, chra_date = ?, chra_report_no = ?, indication_data = ?,
                    mrp_employees = ?, laboratory_name = ?, recommendation = ?, decision = ?,
                    continue_justification = ?, continue_date = ?, stop_justification = ?, stop_date = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE company_id = ?
            ");
            $stmt->execute([
                $work_unit_name, $chra_date, $chra_report_no, $indication_data,
                $mrp_employees, $laboratory_name, $recommendation, $decision,
                $continue_justification, $continue_date, $stop_justification, $stop_date,
                $company_id
            ]);
        } else {
            // Insert new record
            $stmt = $clinic_pdo->prepare("
                INSERT INTO ms_report_data (
                    company_id, work_unit_name, chra_date, chra_report_no, indication_data,
                    mrp_employees, laboratory_name, recommendation, decision,
                    continue_justification, continue_date, stop_justification, stop_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id, $work_unit_name, $chra_date, $chra_report_no, $indication_data,
                $mrp_employees, $laboratory_name, $recommendation, $decision,
                $continue_justification, $continue_date, $stop_justification, $stop_date
            ]);
        }
        
        // Set success message
        $_SESSION['ms_report_saved'] = true;
        
        // Log successful save
        error_log("MS Report data saved successfully for company_id: " . $company_id);
        
        // Redirect to prevent form resubmission
        header('Location: ms_report.php?company_id=' . $company_id . '&saved=1');
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error saving report data: " . $e->getMessage();
        error_log($error_message);
    }
}

// Initialize variables
$company_data = null;
$all_employees = [];
$total_surveillance_data = [];
$company_chemical_hazards = [];
$saved_report_data = null;
$success_message = '';

// Check for success message
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $success_message = 'Report information saved successfully!';
}

if ($company_id || $company_name) {
    try {
        // Get company information
        if ($company_id) {
            $stmt = $clinic_pdo->prepare("SELECT * FROM company WHERE id = ?");
            $stmt->execute([$company_id]);
            $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($company_name) {
            $stmt = $clinic_pdo->prepare("SELECT * FROM company WHERE company_name = ?");
            $stmt->execute([$company_name]);
            $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($company_data) {
                $company_id = $company_data['id'];
            }
        }
        
        if ($company_data && $company_id) {
            // Get all employees from this company
            $stmt = $clinic_pdo->prepare("
                SELECT p.*, oh.*
                FROM patient_information p
                INNER JOIN occupational_history oh ON p.id = oh.patient_id
                WHERE oh.company_name = ?
                ORDER BY p.first_name, p.last_name
            ");
            $stmt->execute([$company_data['company_name']]);
            $all_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all surveillance data for all employees in this company
            if (!empty($all_employees)) {
                $employee_ids = array_column($all_employees, 'id');
                $placeholders = str_repeat('?,', count($employee_ids) - 1) . '?';
                
                // Get surveillance data from chemical_information table
                $examined_patient_ids = [];
                $total_surveillance_data = [];
                
                try {
                    $stmt = $clinic_pdo->prepare("
                        SELECT DISTINCT patient_id, examiner_name, workplace, chemical, examination_date, examination_type,
                               dosh_reg_no, practice_name, practice_address, tel_no, hp_no, fax_no, examiner_email
                        FROM chemical_information 
                        WHERE patient_id IN ($placeholders)
                    ");
                    $stmt->execute($employee_ids);
                    $surveillance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get examined patient IDs
                    $examined_patient_ids = array_unique(array_column($surveillance_data, 'patient_id'));
                    
                    // Prepare surveillance data for display
                    $total_surveillance_data = $surveillance_data;
                    
                } catch (Exception $e) {
                    $examined_patient_ids = [];
                    $total_surveillance_data = [];
                    error_log("Error fetching surveillance data: " . $e->getMessage());
                }
                
                // Collect all chemical hazards from all employees
                foreach ($all_employees as $employee) {
                    if (!empty($employee['chemical_exposure_incidents'])) {
                        $company_chemical_hazards[] = $employee['chemical_exposure_incidents'];
                    }
                }
                $company_chemical_hazards = array_unique($company_chemical_hazards);
            }
            
            // Get saved MS report data for this company
            if ($company_id) {
                try {
                    $stmt = $clinic_pdo->prepare("SELECT * FROM ms_report_data WHERE company_id = ? ORDER BY updated_at DESC LIMIT 1");
                    $stmt->execute([$company_id]);
                    $saved_report_data = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Error fetching saved report data: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching company data: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary Report for Medical Surveillance - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
        .summary-table {
            background: white;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .certificate-form {
            background: white;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
        .underline-field {
             
            min-width: 100px;
            display: inline-block;
            text-align: center;
        }
        .min-height-60 {
            min-height: 3.75rem;
        }
        .min-height-40 {
            min-height: 2.5rem;
        }
        .certification-statement {
            margin-top: 1.5rem;
            padding: 1.5rem 0;
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
        .doctor-signature {
            margin-top: 1.5rem;
            padding: 1.5rem 0;
            border-top: 1px solid #dee2e6;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
        }

        /* Summary Report Styling */
        .summary-report-form {
            background: #f8f9fa;
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-left: 4px solid #28a745;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .summary-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        .legal-ref {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
        }
        .workplace-stats table,
        .results-table,
        .decision-table {
            font-size: 0.9rem;
        }
        .results-table th,
        .decision-table th {
            background-color: #0d6efd !important;
            color: white !important;
            font-weight: 600;
            text-align: center;
        }
        .results-table td,
        .decision-table td {
            vertical-align: middle;
        }
        .indication-options .form-check {
            margin-bottom: 0.5rem;
        }
        .declaration-statement {
            background: #f8f9fa;
            padding: 1rem;
            border-left: 3px solid #28a745;
            border-radius: 4px;
        }
        .footer-instructions .alert {
            background-color: #0d6efd !important;
            color: white !important;
            border: none;
        }
        .min-height-80 {
            min-height: 5rem;
        }
        
        /* Print styles */
        @media print {
            .summary-table,
            .certificate-form {
                background: white !important;
                border: 1px solid #000 !important;
                box-shadow: none;
                padding: 1rem;
            }
            .btn {
                display: none !important;
            }
            .results-table th,
            .decision-table th {
                background-color: #000 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
            }
            .footer-instructions .alert {
                background-color: #000 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Company Selection Form (Hidden when company is selected) -->
        <?php if (!$company_data): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-building"></i> Select Company</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label">Company Name</label>
                        <select class="form-select" id="company_name" name="company_name" onchange="this.form.submit()">
                            <option value="">Select Company...</option>
                            <?php
                            try {
                                $stmt = $clinic_pdo->prepare("SELECT DISTINCT company_name FROM company ORDER BY company_name");
                                $stmt->execute();
                                $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($companies as $company) {
                                    $selected = ($company_name == $company['company_name']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($company['company_name']) . "' $selected>" . htmlspecialchars(ucwords(strtolower($company['company_name']))) . "</option>";
                                }
                            } catch (Exception $e) {
                                echo "<option value=''>Error loading companies</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="/generate_msReport_pdf.php?company_id=<?php echo $company_id; ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($company_data): ?>

            <!-- Summary Report Form -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-medical-alt"></i> Summary Report for Medical Surveillance</h5>
                    <div>
                        <span class="badge bg-primary me-2">USECHH 4</span>
                    </div>
                </div>
                <div class="card-body">
                    <form id="msReportForm" method="POST" action="">
                        <div class="summary-table">
                            <h5 class="section-title">Medical Surveillance Summary Report</h5>

                <!-- Workplace Information -->
                <div class="workplace-section mb-4">
                    <h5 class="section-title">Workplace Information</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Name of Workplace:</label>
                            <div class="form-control-plaintext">
                                <?php echo htmlspecialchars($company_data['company_name'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">MyKKP Registration No.:</label>
                            <div class="form-control-plaintext">
                                <?php echo htmlspecialchars($company_data['mykpp_registration_no'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Address of Workplace:</label>
                            <div class="form-control-plaintext">
                                <?php 
                                $workplace_address = '';
                                if (!empty($company_data['address'])) {
                                    $workplace_address = $company_data['address'];
                                    
                                    // Add district, state, and postcode if available
                                    if (!empty($company_data['postcode'])) {
                                        $workplace_address .= "\n" . $company_data['postcode'];
                                    }
                                    if (!empty($company_data['district'])) {
                                        $workplace_address .= " " . $company_data['district'];
                                    }
                                    if (!empty($company_data['state'])) {
                                        $workplace_address .= ", " . $company_data['state'];
                                    }
                                   
                                }
                                echo nl2br(htmlspecialchars($workplace_address ?: 'Not specified'));
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Workplace Statistics Table -->
                    <div class="workplace-stats mb-4">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td class="fw-bold">Total number of workers in the workplace</td>
                                    <td class="text-center">
                                        <?php echo count($all_employees); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Name of the work unit where workers are in</td>
                                    <td class="text-center">
                                        <input type="text" class="form-control" id="work_unit_name" name="work_unit_name" placeholder="Enter work unit name" value="<?php echo htmlspecialchars($saved_report_data['work_unit_name'] ?? ''); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Total number of exposed workers in the work unit</td>
                                    <td class="text-center"><?php echo count($all_employees); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Total number of workers examined</td>
                                    <td class="text-center">
                                        <?php 
                                        // Use the examined_patient_ids from the data fetching logic
                                        $examined_workers = isset($examined_patient_ids) ? count($examined_patient_ids) : 0;
                                        echo $examined_workers;
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Chemical and CHRA Information -->
                <div class="chemical-section mb-4">
                    <h5 class="section-title">Chemical and CHRA Information</h5>
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Individual Chemical: (Use ONE USECHH 4 form for ONE chemical only)</label>
                            <div class="form-control-plaintext">
                                <?php echo htmlspecialchars($selected_chemical ?: 'Not specified'); ?>
                            </div>
                        </div>
                    </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Date of CHRA conducted:</label>
                                        <input type="date" class="form-control" id="chra_date" name="chra_date" value="<?php echo htmlspecialchars($saved_report_data['chra_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">CHRA report no.:</label>
                                        <input type="text" class="form-control" id="chra_report_no" name="chra_report_no" placeholder="Enter CHRA report number" value="<?php echo htmlspecialchars($saved_report_data['chra_report_no'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Indication for medical surveillance -->
                            <div class="indication-section mb-4">
                                <h5 class="section-title">Indication for medical surveillance based on CHRA report</h5>
                                <div class="indication-options">
                                    <?php 
                                    $saved_indications = [];
                                    if ($saved_report_data && $saved_report_data['indication_data']) {
                                        $indication_data = json_decode($saved_report_data['indication_data'], true);
                                        $saved_indications = $indication_data['indications'] ?? [];
                                    }
                                    ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="significant_exposure" name="indication[]" value="significant_exposure" <?php echo in_array('significant_exposure', $saved_indications) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="significant_exposure">
                                            Significant personal exposure (≥ 50% PEL)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="others" name="indication[]" value="others" <?php echo in_array('others', $saved_indications) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="others">
                                            Others (Please provide details)
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="health_effects" name="indication[]" value="health_effects" <?php echo in_array('health_effects', $saved_indications) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="health_effects">
                                            Reported health effects
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="skin_absorption" name="indication[]" value="skin_absorption" <?php echo in_array('skin_absorption', $saved_indications) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="skin_absorption">
                                            Skin absorption
                                        </label>
                                    </div>
                                </div>
                                <div class="mt-3" id="others_details" style="display: none;">
                                    <label class="form-label">Please provide details:</label>
                                    <textarea class="form-control" id="others_details_text" name="others_details" rows="3" placeholder="Enter details for others indication"><?php 
                                        if ($saved_report_data && $saved_report_data['indication_data']) {
                                            $indication_data = json_decode($saved_report_data['indication_data'], true);
                                            echo htmlspecialchars($indication_data['others_details'] ?? '');
                                        }
                                    ?></textarea>
                                </div>
                            </div>

                            <!-- Medical Surveillance Results Table -->
                            <div class="results-section mb-4">
                                <h5 class="section-title">MEDICAL SURVEILLANCE RESULTS</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered results-table">
                                        <thead class="table-primary">
                                            <tr>
                                                <th rowspan="2">Findings</th>
                                                <th rowspan="2">No. of workers with normal findings</th>
                                                <th colspan="2">No. of workers with abnormal findings</th>
                                                <th rowspan="2">No. of workers recommended for medical removal protection</th>
                                            </tr>
                                            <tr>
                                                <th>Occupational</th>
                                                <th>Non-occupational</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-bold">History of health effects due to chemical exposure</td>
                                                <td class="text-center"><?php echo isset($examined_patient_ids) ? count($examined_patient_ids) : 0; ?></td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">Not applicable</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Clinical findings</td>
                                                <td class="text-center"><?php echo isset($examined_patient_ids) ? count($examined_patient_ids) : 0; ?></td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Target organ function test(s). Please specify:</td>
                                                <td class="text-center"><?php echo isset($examined_patient_ids) ? count($examined_patient_ids) : 0; ?></td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">BEI determinant (BM/BEM). Please specify determinant:</td>
                                                <td class="text-center"><?php echo isset($examined_patient_ids) ? count($examined_patient_ids) : 0; ?></td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                                <td class="text-center">0</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">Continue in separate sheet if required. Please include details of abnormal examination/test results in USECHH 5ii form and Medical Removal Protection in USECHH 5i form.</small>
                                </div>
                            </div>

                            <!-- General Information -->
                            <div class="general-section mb-4">
                                <h5 class="section-title">General Information</h5>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Total no. of employees recommended for MRP</label>
                                        <input type="number" class="form-control" id="mrp_employees" name="mrp_employees" value="<?php echo htmlspecialchars($saved_report_data['mrp_employees'] ?? '0'); ?>" min="0" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Name of Laboratory:</label>
                                        <input type="text" class="form-control" id="laboratory_name" name="laboratory_name" value="<?php echo htmlspecialchars($saved_report_data['laboratory_name'] ?? 'Medical Surveillance Laboratory'); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Recommendation:</label>
                                        <textarea class="form-control" id="recommendation" name="recommendation" rows="4" required><?php echo htmlspecialchars($saved_report_data['recommendation'] ?? 'Continue regular medical surveillance as per schedule.'); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Decision Table -->
                            <div class="decision-section mb-4">
                                <h5 class="section-title">Decision</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered decision-table">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>*</th>
                                                <th>Decision</th>
                                                <th>Justification of Decision</th>
                                                <th>Date of implementation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="decision" value="continue" <?php echo ($saved_report_data['decision'] ?? 'continue') == 'continue' ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="fw-bold">Continue MS</td>
                                                <td>
                                                    <input type="text" class="form-control" name="continue_justification" value="<?php echo htmlspecialchars($saved_report_data['continue_justification'] ?? 'Regular surveillance required for chemical exposure'); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control" name="continue_date" value="<?php echo htmlspecialchars($saved_report_data['continue_date'] ?? date('Y-m-d')); ?>" required>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="radio" name="decision" value="stop" <?php echo ($saved_report_data['decision'] ?? '') == 'stop' ? 'checked' : ''; ?>>
                                                </td>
                                                <td class="fw-bold">Stop MS</td>
                                                <td>
                                                    <input type="text" class="form-control" name="stop_justification" placeholder="Enter justification" value="<?php echo htmlspecialchars($saved_report_data['stop_justification'] ?? ''); ?>">
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control" name="stop_date" value="<?php echo htmlspecialchars($saved_report_data['stop_date'] ?? ''); ?>">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">* Please ✓ where applicable</small>
                            </div>

                            <!-- Declaration and Doctor Information -->
                            <div class="declaration-section mb-4">
                                <div class="declaration-statement mb-3">
                                    <p class="fw-bold">I hereby declare that all particulars given in this report are accurate to the best of my knowledge.</p>
                               
                                <div class="d-flex align-items-baseline mb-3">
                                    <label class="form-label me-2 mb-0" style="white-space: nowrap;">Name of occupational Health Doctor:</label>
                                    <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                        <?php echo htmlspecialchars($total_surveillance_data[0]['examiner_name'] ?? 'Dr. System Administrator'); ?>
                                    </div>
                                </div>

                                <div class="d-flex align-items-baseline mb-3">
                                    <label class="form-label me-2 mb-0" style="white-space: nowrap;">OHD Registration No:</label>
                                    <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                        <?php echo htmlspecialchars($total_surveillance_data[0]['dosh_reg_no'] ?? 'OHD-REG-2024-001'); ?>
                                    </div>
                                </div>

                                <div class="d-flex align-items-baseline mb-3">
                                    <label class="form-label me-2 mb-0" style="white-space: nowrap;">Name of Practice & Address:</label>
                                    <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                        <?php 
                                        $practice_name = $total_surveillance_data[0]['practice_name'] ?? 'Medical Surveillance System';
                                        $practice_address = $total_surveillance_data[0]['practice_address'] ?? 'Occupational Health Clinic, Kuala Lumpur, Malaysia';
                                        echo htmlspecialchars($practice_name . ', ' . $practice_address);
                                        ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4 d-flex align-items-baseline">
                                        <label class="form-label me-2 mb-0" style="white-space: nowrap;">Tel No:</label>
                                        <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                            <?php echo htmlspecialchars($total_surveillance_data[0]['tel_no'] ?? '03-1234-5678'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-baseline">
                                        <label class="form-label me-2 mb-0" style="white-space: nowrap;">HP no:</label>
                                        <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                            <?php echo htmlspecialchars($total_surveillance_data[0]['hp_no'] ?? '012-345-6789'); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-baseline">
                                        <label class="form-label me-2 mb-0" style="white-space: nowrap;">Fax No:</label>
                                        <div class="flex-grow-1" style="  padding-bottom: 2px;">
                                            <?php echo htmlspecialchars($total_surveillance_data[0]['fax_no'] ?? '03-1234-5679'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-baseline mb-3">
                                    <label class="form-label me-2 mb-0" style="white-space: nowrap;">Email address:</label>
                                    <div class="flex-grow-1" style=" padding-bottom: 2px;">
                                        <?php echo htmlspecialchars($total_surveillance_data[0]['examiner_email'] ?? 'admin@medicalsurveillance.com'); ?>
                                    </div>
                                </div>

                                <div class="row mt-5">
                                    <div class="col-md-6 d-flex align-items-baseline">
                                        <label class="form-label me-2 mb-0" style="white-space: nowrap;">Signature:</label>
                                        <div style=" padding-bottom: 2px; min-width: 200px;">
                                            <!-- Signature space -->
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-baseline justify-content-end">
                                        <label class="form-label me-2 mb-0" style="white-space: nowrap;">Date:</label>
                                        <div class="flex-grow-1" >
                                            <?php echo date('d/m/Y'); ?>
                                        </div>
                                        
                                    </div>
                                </div>
                                </div>
                                
                                <!-- Save and Generate Buttons -->
                                <div class="text-center mt-4 mb-4">
                                    <button type="submit" class="btn btn-success btn-lg me-3" id="saveButton">
                                        <i class="fas fa-save"></i> Save Report Information
                                    </button>
                                    <a href="generate_msReport_pdf.php?company_id=<?php echo $company_id; ?>" class="btn btn-primary btn-lg" id="pdfButton" target="_blank" style="display: none;">
                                        <i class="fas fa-file-pdf"></i> Generate PDF
                                    </a>
                                </div>
                                
                </div>
                    </form>

                <!-- Certification Section -->
            <div class="card mt-4">
                <div class="card-body">
                <div class="legal-ref mb-2" style="color: #6c757d; font-size: 0.9rem;">Submit this form within 30 days of completion of medical surveillance to the Director General, Department of Occupational Safety and Health, Putrajaya. Download this form at .dosh.gov.my Please ensure all items in the form are completed.</div>
                <div class="legal-ref mb-3" style="color: #6c757d; font-size: 0.9rem;">Incomplete forms will not be accepted.</div>
                    
                </div>
            </div>


        <?php else: ?>
            <!-- No Company Selected -->
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>No Company Selected</h5>
                <p>Please select a company from the dropdown above to generate the medical surveillance summary report.</p>
            </div>
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle "Others" checkbox to show/hide details textarea
        document.getElementById('others').addEventListener('change', function() {
            const othersDetails = document.getElementById('others_details');
            if (this.checked) {
                othersDetails.style.display = 'block';
                document.getElementById('others_details_text').required = true;
            } else {
                othersDetails.style.display = 'none';
                document.getElementById('others_details_text').required = false;
            }
        });

        // Check if "others" is already checked on page load
        if (document.getElementById('others').checked) {
            document.getElementById('others_details').style.display = 'block';
            document.getElementById('others_details_text').required = true;
        }

        // Handle form submission
        document.getElementById('msReportForm').addEventListener('submit', function(e) {
            // Show loading state
            const saveButton = document.getElementById('saveButton');
            const originalText = saveButton.innerHTML;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            saveButton.disabled = true;
            
            // Allow normal form submission to proceed
            // The PHP will handle the redirect and data saving
        });

        // Handle decision radio buttons to show/hide relevant fields
        document.querySelectorAll('input[name="decision"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const continueJustification = document.querySelector('input[name="continue_justification"]');
                const continueDate = document.querySelector('input[name="continue_date"]');
                const stopJustification = document.querySelector('input[name="stop_justification"]');
                const stopDate = document.querySelector('input[name="stop_date"]');
                
                if (this.value === 'continue') {
                    continueJustification.required = true;
                    continueDate.required = true;
                    stopJustification.required = false;
                    stopDate.required = false;
                } else if (this.value === 'stop') {
                    continueJustification.required = false;
                    continueDate.required = false;
                    stopJustification.required = true;
                    stopDate.required = true;
                }
            });
        });

        // Show PDF button if data is already saved
        <?php if ($saved_report_data): ?>
        document.getElementById('pdfButton').style.display = 'inline-block';
        <?php endif; ?>

        // Show success message if redirected after save
        <?php if ($success_message): ?>
        alert('<?php echo $success_message; ?>');
        <?php endif; ?>
    </script>
</body>
</html>
