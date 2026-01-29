<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . app_url('login.php'));
    exit();
}

// Initialize variables
$company_data = null;
$company_id = null;
$all_employees = [];
$abnormal_workers = [];
$company_chemical_hazards = [];

// Get company data
if (isset($_GET['company_id'])) {
    $company_id = $_GET['company_id'];
    $stmt = $clinic_pdo->prepare("SELECT * FROM company WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($company_data) {
        $company_id = $company_data['id'];
    }
} elseif (isset($_GET['company_name'])) {
    $company_name = $_GET['company_name'];
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
    
    // Get surveillance data for employees with abnormal findings
    if (!empty($all_employees)) {
        $employee_ids = array_column($all_employees, 'id');
        $placeholders = str_repeat('?,', count($employee_ids) - 1) . '?';
        
        try {
            $stmt = $clinic_pdo->prepare("
                SELECT DISTINCT patient_id, examiner_name, workplace, chemical, examination_date, examination_type, final_assessment
                FROM chemical_information 
                WHERE patient_id IN ($placeholders)
                AND (final_assessment IS NULL OR final_assessment LIKE '%abnormal%' OR final_assessment LIKE '%not fit%' OR final_assessment LIKE '%unfit%')
            ");
            $stmt->execute($employee_ids);
            $abnormal_workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $abnormal_workers = [];
            error_log("Error fetching abnormal workers data: " . $e->getMessage());
        }
        
        // Collect all chemical hazards from all employees
        foreach ($all_employees as $employee) {
            if (!empty($employee['chemical_exposure_incidents'])) {
                $company_chemical_hazards[] = $employee['chemical_exposure_incidents'];
            }
        }
        $company_chemical_hazards = array_unique($company_chemical_hazards);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abnormal Workers Report - USECHH 5i</title>
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
            font-size: 1.4rem;
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
        
        .company-selection {
            background: white;
            border: 1px solid #dee2e6;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
        
        .form-label {
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
            border-bottom: 1px solid #000;
            min-width: 100px;
            display: inline-block;
            text-align: center;
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
        .summary-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .summary-table tbody tr:hover {
            background-color: #e8f4f8;
        }
        
        .col-no { width: 5%; }
        .col-name { width: 15%; }
        .col-nric { width: 12%; }
        .col-sex { width: 6%; }
        .col-job { width: 12%; }
        .col-assessment { width: 10%; }
        .col-history { width: 12%; }
        .col-clinical { width: 12%; }
        .col-target-organ { width: 10%; }
        .col-bm { width: 8%; }
        .col-work-related { width: 8%; }
        .col-recommendation { width: 15%; }
        .col-conclusion { width: 12%; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            border-bottom: 2px solid #28a745;
            padding-bottom: 0.5rem;
        }
        
        .footer-instructions {
            background: #f8f9fa;
            padding: 1rem;
            border-left: 3px solid #28a745;
            border-radius: 4px;
            margin-top: 2rem;
        }
        
        .footer-instructions .alert {
            background-color: #0d6efd !important;
            color: white !important;
            border: none;
        }
        
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .action-buttons .btn {
            margin-left: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        @media print {
            .no-print {
                display: none !important;
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

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <!-- Display success/error messages from URL parameters -->
        <?php if (isset($_GET['message']) && isset($_GET['type'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_GET['type']); ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $_GET['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars(urldecode($_GET['message'])); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$company_data): ?>
            <div class="card mt-4">
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
                                        echo "<option value='" . htmlspecialchars($company['company_name']) . "'>" . htmlspecialchars(ucwords(strtolower($company['company_name']))) . "</option>";
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
                                <a href="<?php echo app_url('generate_abnormal_pdf.php'); ?>?company_id=<?php echo $company_id; ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Download PDF
                                </a>
                            </div>
                        </div>
                        </form>
                </div>
            </div>

            <div class="alert alert-info text-center mt-4">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>No Company Selected</h5>
                <p>Please select a company from the dropdown above to generate the abnormal examination results report.</p>
            </div>

        <?php else: ?>
            <!-- Abnormal Workers Report -->
            <div class="card mt-4" style="border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="card-header" style="background: white; border-top: 3px solid #155724; border-left: none; border-right: none; border-bottom: none; border-radius: 8px 8px 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-plus-square me-2" style="color: #155724; font-size: 1.2rem;"></i>
                            <span class="fw-bold" style="color: #155724; font-size: 1.1rem;">Medical Surveillance Report</span>
                        </div>
                        <div>
                            <span class="badge" style="background: #155724; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; margin-right: 10px;">USECHH 5i</span>
                            <a href="<?php echo app_url('generate_abnormal_pdf.php'); ?>?company_id=<?php echo $company_id; ?>" class="btn btn-success btn-sm" target="_blank">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                       
                        <div class="report-title" style="color: #495057; font-size: 1.4rem; font-weight: bold;">DETAILS OF WORKERS WITH ABNORMAL EXAMINATION RESULTS</div>
            </div>

                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="border-collapse: collapse;">
                        <thead>
                            <tr style="background: #495057; color: white;">
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">No.</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Employee's<br>Name</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">NRIC/<br>Passport</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Sex</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Job Category/<br>Designation</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Type of<br>Assessment</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">History of Health<br>Effect Due to<br>CHTH Exposure</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Clinical<br>Findings</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Target Organ<br>Function Test<br>(Specify Organ)</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">BM<br>Determinant</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Work<br>Relatedness</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Recommendation/<br>Action Taken<br>(MRP, Retraining,<br>Refit of PPE)</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;">Conclusion of<br>MS Findings<br>(Fit/Not Fit)</th>
                                <th style="background: #495057; color: white; font-weight: bold; text-align: center; padding: 8px 4px; font-size: 0.7rem; white-space: nowrap;"></th>
                            </tr>
                        </thead>
                        <tbody>
                                <?php if (!empty($abnormal_workers)): ?>
                            <?php foreach ($abnormal_workers as $index => $worker): ?>
                                <?php
                                // Get patient details for this worker
                                $stmt = $clinic_pdo->prepare("SELECT * FROM patient_information WHERE id = ?");
                                $stmt->execute([$worker['patient_id']]);
                                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Get occupational details
                                $stmt = $clinic_pdo->prepare("SELECT * FROM occupational_history WHERE patient_id = ? AND company_name = ?");
                                $stmt->execute([$worker['patient_id'], $company_data['company_name']]);
                                $occupational = $stmt->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <tr>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><strong><?php echo $index + 1; ?></strong></td>
                                    <td style="text-align: left; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo htmlspecialchars(ucwords(strtolower(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')))); ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo htmlspecialchars($patient['NRIC'] ?? $patient['passport_no'] ?? 'N/A'); ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></td>
                                    <td style="text-align: left; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo htmlspecialchars($occupational['job_title'] ?? 'N/A'); ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;">
                                        <span class="badge" style="background: #28a745; color: white; font-size: 0.6rem; padding: 0.2rem 0.4rem; border-radius: 10px;"><?php echo htmlspecialchars($worker['examination_type'] ?? 'Pre-employment'); ?></span>
                                    </td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo !empty($worker['history_of_health']) && $worker['history_of_health'] == 'Yes' ? 'Yes' : 'No'; ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;">
                                        <?php 
                                        if (!empty($worker['clinical_findings']) && $worker['clinical_findings'] == 'Yes'): ?>
                                            <span class="fw-bold" style="color: #dc3545;">Yes</span>
                                        <?php else: ?>
                                            <span class="fw-bold" style="color: #28a745;">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo !empty($worker['target_organ']) && $worker['target_organ'] == 'Yes' ? 'Yes' : 'No'; ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"><?php echo !empty($worker['biological_monitoring']) && $worker['biological_monitoring'] == 'Yes' ? 'Yes' : 'No'; ?></td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;">
                                        <?php 
                                        $work_related = [];
                                        if (!empty($worker['clinical_work_related']) && $worker['clinical_work_related'] == 'Yes') $work_related[] = 'Clinical';
                                        if (!empty($worker['organ_work_related']) && $worker['organ_work_related'] == 'Yes') $work_related[] = 'Organ';
                                        if (!empty($worker['biological_work_related']) && $worker['biological_work_related'] == 'Yes') $work_related[] = 'Biological';
                                        echo !empty($work_related) ? implode(', ', $work_related) : 'Review';
                                        ?>
                                    </td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;">
                                        <?php 
                                        $recommendations = [];
                                        if (!empty($worker['recommendations_type'])) $recommendations[] = $worker['recommendations_type'];
                                        if (!empty($worker['date_of_MRP'])) $recommendations[] = 'MRP';
                                        echo !empty($recommendations) ? implode(', ', $recommendations) : 'N/A';
                                        ?>
                                    </td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;">
                                        <?php 
                                        $fitness_status = $worker['final_assessment'] ?? 'Pending';
                                        if ($fitness_status == 'Fit for Work'): ?>
                                            <span class="badge" style="background: #28a745; color: white; font-size: 0.6rem; padding: 0.2rem 0.4rem;">Fit</span>
                                        <?php elseif ($fitness_status == 'Not Fit for Work'): ?>
                                            <span class="badge" style="background: #dc3545; color: white; font-size: 0.6rem; padding: 0.2rem 0.4rem;">Not Fit</span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.7rem;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; padding: 8px 4px; border: 1px solid #dee2e6; font-size: 0.7rem;"></td>
                                </tr>
                            <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" class="text-center py-4" style="border: 1px solid #dee2e6;">
                                            <div class="text-muted">
                                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                                <div><strong>No abnormal findings</strong></div>
                                                <div>All workers from <?php echo htmlspecialchars($company_data['company_name']); ?> have normal examination results.</div>
                                                <div>No workers require reporting on this form.</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- Certification Section -->
            <div class="card mt-4">
                <div class="card-body">
                <div class="legal-ref mb-2" style="color: #6c757d; font-size: 0.9rem;">Submit this form together with USECHH 4 form within 30 days of completion of the medical surveillance to The Director General, Department
of Occupational Safety and Health, Putrajaya.</div>
                <div class="legal-ref mb-3" style="color: #6c757d; font-size: 0.9rem;">This form can be downloaded from http://www.dosh.gov.my Continue in separate sheet if
                    required.</div>
                    
                </div>
            </div>

           
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
