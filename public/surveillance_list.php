<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('login.php'));
    exit();
}

/**
 * Attach declaration metadata to each surveillance record without altering
 * historical signatures from other declarations.
 *
 * @param array $reports
 * @param PDO   $pdo
 * @return array
 */
function attachDeclarationsToReports(array $reports, PDO $pdo): array
{
    if (empty($reports)) {
        return $reports;
    }

    $needsLookup = false;
    foreach ($reports as $report) {
        if (!array_key_exists('declaration_id', $report)) {
            $needsLookup = true;
            break;
        }
    }

    if (!$needsLookup) {
        return $reports;
    }

    $ids = [];
    foreach ($reports as $report) {
        $sid = $report['surveillance_id'] ?? null;
        if ($sid) {
            $ids[$sid] = true;
        }
    }

    if (empty($ids)) {
        foreach ($reports as &$report) {
            $report['declaration_id'] = $report['declaration_id'] ?? null;
            $report['patient_signature'] = $report['patient_signature'] ?? null;
            $report['doctor_signature'] = $report['doctor_signature'] ?? null;
            $report['patient_date'] = $report['patient_date'] ?? null;
            $report['doctor_date'] = $report['doctor_date'] ?? null;
        }
        unset($report);
        return $reports;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT declaration_id, surveillance_id, patient_signature, doctor_signature, patient_date, doctor_date
        FROM declarations
        WHERE surveillance_id IN ($placeholders)
        ORDER BY declaration_id DESC
    ");
    $stmt->execute(array_keys($ids));

    $declarationMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sid = $row['surveillance_id'];
        if (!isset($declarationMap[$sid])) {
            $declarationMap[$sid] = $row;
        }
    }

    foreach ($reports as &$report) {
        $sid = $report['surveillance_id'] ?? null;
        if ($sid && isset($declarationMap[$sid])) {
            $decl = $declarationMap[$sid];
            $report['declaration_id'] = $decl['declaration_id'];
            $report['patient_signature'] = $decl['patient_signature'];
            $report['doctor_signature'] = $decl['doctor_signature'];
            $report['patient_date'] = $decl['patient_date'];
            $report['doctor_date'] = $decl['doctor_date'];
        } else {
            $report['declaration_id'] = $report['declaration_id'] ?? null;
            $report['patient_signature'] = $report['patient_signature'] ?? null;
            $report['doctor_signature'] = $report['doctor_signature'] ?? null;
            $report['patient_date'] = $report['patient_date'] ?? null;
            $report['doctor_date'] = $report['doctor_date'] ?? null;
        }
    }
    unset($report);

    return $reports;
}

function uniqueBySurveillanceId(array $reports): array
{
    $unique = [];
    $seen = [];

    foreach ($reports as $report) {
        $sid = $report['surveillance_id'] ?? null;
        if ($sid === null) {
            $unique[] = $report;
            continue;
        }

        if (!isset($seen[$sid])) {
            $unique[] = $report;
            $seen[$sid] = true;
        }
    }

    return $unique;
}

// Get company ID from URL
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$show_all = isset($_GET['show_all']) ? (bool)$_GET['show_all'] : false;

// If no company_id provided, try to get it from patient_id or show all companies
if ($company_id <= 0) {
    if ($patient_id > 0) {
        // Try to get company_id from patient's occupational history
        try {
            $stmt = $clinic_pdo->prepare("
                SELECT c.id as company_id 
                FROM company c
                INNER JOIN occupational_history oh ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(oh.company_name))
                WHERE oh.patient_id = ?
                LIMIT 1
            ");
            $stmt->execute([$patient_id]);
            $company_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $company_id = $company_data ? $company_data['company_id'] : 0;
        } catch (Exception $e) {
            error_log("Error getting company_id from patient: " . $e->getMessage());
        }
    }
    
    // If still no company_id, redirect to company selection
    if ($company_id <= 0) {
        $_SESSION['error_message'] = 'Please select a company to view surveillance records.';
        header('Location: ' . url('company.php'));
        exit();
    }
}

// Get company details directly
try {
    $stmt = $clinic_pdo->prepare("SELECT * FROM company WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        $_SESSION['error_message'] = 'Company not found.';
        header('Location: ' . url('company.php'));
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: company.php');
    exit();
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_surveillance') {
    $surveillance_id_to_delete = (int)$_POST['surveillance_id'];
    
    if ($surveillance_id_to_delete > 0) {
        try {
            // Delete related records first
            $clinic_pdo->beginTransaction();
            
            // Delete from related tables
            $tables_to_clean = ['physical_examination', 'clinical_findings', 'conclusion_ms_finding', 'recommendations'];
            foreach ($tables_to_clean as $table) {
                $stmt = $clinic_pdo->prepare("DELETE FROM $table WHERE patient_id = (SELECT patient_id FROM chemical_information WHERE surveillance_id = ?)");
                $stmt->execute([$surveillance_id_to_delete]);
            }
            
            // Delete the main surveillance record
            $stmt = $clinic_pdo->prepare("DELETE FROM chemical_information WHERE surveillance_id = ?");
            $stmt->execute([$surveillance_id_to_delete]);
            
            $clinic_pdo->commit();
            
            $_SESSION['success_message'] = 'Surveillance record deleted successfully.';
            
            // Redirect to refresh the page
            header('Location: ' . url('surveillance_list.php?' . http_build_query($_GET)));
            exit();
            
        } catch (Exception $e) {
            $clinic_pdo->rollBack();
            $_SESSION['error_message'] = 'Error deleting surveillance record: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid surveillance ID for deletion.';
    }
}

// Get surveillance reports for the company or specific patient
try {
    
    if ($patient_id > 0) {
        // Get surveillance reports for specific patient (show all records)
        // First try the simple query to ensure we get all records
        $stmt = $clinic_pdo->prepare("
            SELECT sm.*,
                   p.patient_id as patient_code, p.first_name, p.last_name, p.NRIC, p.date_of_birth, p.gender,
                   oh.company_name, oh.job_title
            FROM chemical_information sm
            LEFT JOIN patient_information p ON sm.patient_id = p.id
            LEFT JOIN (
                SELECT oh1.*
                FROM occupational_history oh1
                INNER JOIN (
                    SELECT patient_id, MAX(id) AS latest_id
                    FROM occupational_history
                    GROUP BY patient_id
                ) oh2 ON oh2.patient_id = oh1.patient_id AND oh2.latest_id = oh1.id
            ) oh ON oh.patient_id = p.id
            WHERE sm.patient_id = ?
            ORDER BY sm.examination_date DESC, sm.surveillance_id DESC
        ");
        $stmt->execute([$patient_id]);
        $surveillance_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Attach declarations after getting the basic records
        $surveillance_reports = attachDeclarationsToReports($surveillance_reports, $clinic_pdo);
        $surveillance_reports = uniqueBySurveillanceId($surveillance_reports);
        
        // Debug: Log the results
        error_log("surveillance_list.php: Found " . count($surveillance_reports) . " records for patient_id: " . $patient_id);
        if (!empty($surveillance_reports)) {
            error_log("surveillance_list.php: First record ID: " . $surveillance_reports[0]['surveillance_id']);
            error_log("surveillance_list.php: All record IDs: " . implode(', ', array_column($surveillance_reports, 'surveillance_id')));
        }
        
        // If still no records found, verify the data exists in the database
        if (empty($surveillance_reports)) {
            error_log("surveillance_list.php: No records found, checking if data exists in chemical_information table");
            $debug_stmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM chemical_information WHERE patient_id = ?");
            $debug_stmt->execute([$patient_id]);
            $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);
            error_log("surveillance_list.php: Found " . $debug_result['count'] . " records in chemical_information for patient_id: " . $patient_id);
            
            if ($debug_result['count'] > 0) {
                // Try a very simple query to get the records
                $stmt = $clinic_pdo->prepare("SELECT * FROM chemical_information WHERE patient_id = ? ORDER BY examination_date DESC, surveillance_id DESC");
                $stmt->execute([$patient_id]);
                $surveillance_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get patient info separately
                $stmt = $clinic_pdo->prepare("SELECT patient_id, first_name, last_name, NRIC, date_of_birth, gender FROM patient_information WHERE id = ?");
                $stmt->execute([$patient_id]);
                $patient_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get company info separately
                $stmt = $clinic_pdo->prepare("
                    SELECT oh.company_name, oh.job_title
                    FROM occupational_history oh
                    WHERE oh.patient_id = ?
                    ORDER BY oh.id DESC
                    LIMIT 1
                ");
                $stmt->execute([$patient_id]);
                $company_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Add patient and company info to each record
                foreach ($surveillance_reports as &$record) {
                    if ($patient_info) {
                        $record['patient_code'] = $patient_info['patient_id'];
                        $record['first_name'] = $patient_info['first_name'];
                        $record['last_name'] = $patient_info['last_name'];
                        $record['NRIC'] = $patient_info['NRIC'];
                        $record['date_of_birth'] = $patient_info['date_of_birth'];
                        $record['gender'] = $patient_info['gender'];
                    }
                    if ($company_info) {
                        $record['company_name'] = $company_info['company_name'];
                        $record['job_title'] = $company_info['job_title'];
                    }
                }
                unset($record);
                
                // Attach declarations
                $surveillance_reports = attachDeclarationsToReports($surveillance_reports, $clinic_pdo);
            }
        }
        
        // Debug: Log the final reports
        error_log("surveillance_list.php: Final reports count: " . count($surveillance_reports));
        foreach ($surveillance_reports as $report) {
            error_log("surveillance_list.php: Final report - ID: " . $report['surveillance_id'] . ", Date: " . $report['examination_date'] . ", Patient: " . $report['patient_id']);
        }
        
        // Get patient details for header
        $patient_stmt = $clinic_pdo->prepare("
            SELECT pi.*, oh.company_name, oh.job_title
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE pi.id = ?
        ");
        $patient_stmt->execute([$patient_id]);
        $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($show_all) {
        // Get ALL surveillance reports from database organized by company and patient
        $stmt = $clinic_pdo->prepare("
            SELECT sm.*,
                   pi.first_name, pi.last_name, pi.patient_id as patient_code, pi.NRIC, pi.date_of_birth, pi.gender,
                   oh.company_name, oh.job_title,
                   d.declaration_id, d.patient_signature, d.doctor_signature, d.patient_date, d.doctor_date
            FROM chemical_information sm
            LEFT JOIN (
                SELECT d1.*
                FROM declarations d1
                INNER JOIN (
                    SELECT surveillance_id, MAX(declaration_id) AS latest_declaration_id
                    FROM declarations
                    GROUP BY surveillance_id
                ) ld ON ld.surveillance_id = d1.surveillance_id AND ld.latest_declaration_id = d1.declaration_id
            ) d ON d.surveillance_id = sm.surveillance_id
            LEFT JOIN patient_information pi ON (sm.patient_id = pi.id OR (d.patient_id IS NOT NULL AND d.patient_id = pi.id))
            LEFT JOIN (
                SELECT oh1.*
                FROM occupational_history oh1
                INNER JOIN (
                    SELECT patient_id, MAX(id) AS latest_id
                    FROM occupational_history
                    GROUP BY patient_id
                ) oh2 ON oh2.patient_id = oh1.patient_id AND oh2.latest_id = oh1.id
            ) oh ON oh.patient_id = pi.id
            ORDER BY oh.company_name ASC, pi.first_name ASC, pi.last_name ASC, sm.examination_date DESC, sm.surveillance_id DESC
        ");
        $stmt->execute();
        $surveillance_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $surveillance_reports = attachDeclarationsToReports($surveillance_reports, $clinic_pdo);
        $surveillance_reports = uniqueBySurveillanceId($surveillance_reports);
        
        // Debug: Log all records results
        error_log("surveillance_list.php: Found " . count($surveillance_reports) . " total records from all companies");
        if (!empty($surveillance_reports)) {
            error_log("surveillance_list.php: All records - First record ID: " . $surveillance_reports[0]['surveillance_id']);
        }
        
        // Group records by company for display
        $grouped_reports = [];
        foreach ($surveillance_reports as $report) {
            $company_name = $report['company_name'] ?: 'Unknown Company';
            if (!isset($grouped_reports[$company_name])) {
                $grouped_reports[$company_name] = [];
            }
            $grouped_reports[$company_name][] = $report;
        }
        
    } else {
        // Get surveillance reports for all patients in the company (show all records)
        $stmt = $clinic_pdo->prepare("
            SELECT sm.*,
                   pi.first_name, pi.last_name, pi.patient_id as patient_code, pi.NRIC, pi.date_of_birth, pi.gender,
                   oh.company_name, oh.job_title,
                   d.declaration_id, d.patient_signature, d.doctor_signature, d.patient_date, d.doctor_date
            FROM chemical_information sm
            LEFT JOIN (
                SELECT d1.*
                FROM declarations d1
                INNER JOIN (
                    SELECT surveillance_id, MAX(declaration_id) AS latest_declaration_id
                    FROM declarations
                    GROUP BY surveillance_id
                ) ld ON ld.surveillance_id = d1.surveillance_id AND ld.latest_declaration_id = d1.declaration_id
            ) d ON d.surveillance_id = sm.surveillance_id
            LEFT JOIN patient_information pi ON (sm.patient_id = pi.id OR (d.patient_id IS NOT NULL AND d.patient_id = pi.id))
            LEFT JOIN (
                SELECT oh1.*
                FROM occupational_history oh1
                INNER JOIN (
                    SELECT patient_id, MAX(id) AS latest_id
                    FROM occupational_history
                    GROUP BY patient_id
                ) oh2 ON oh2.patient_id = oh1.patient_id AND oh2.latest_id = oh1.id
            ) oh ON oh.patient_id = pi.id
            WHERE TRIM(LOWER(IFNULL(oh.company_name, ''))) = TRIM(LOWER(?))
            ORDER BY oh.company_name, pi.first_name, pi.last_name, sm.examination_date DESC, sm.surveillance_id DESC
        ");
        $stmt->execute([$company['company_name']]);
        $surveillance_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $surveillance_reports = attachDeclarationsToReports($surveillance_reports, $clinic_pdo);
        $surveillance_reports = uniqueBySurveillanceId($surveillance_reports);
        $surveillance_reports = array_values($surveillance_reports);
        
        // Debug: Log company-wide results
        error_log("surveillance_list.php: Company-wide query found " . count($surveillance_reports) . " records for company: " . $company['company_name']);
        if (!empty($surveillance_reports)) {
            error_log("surveillance_list.php: Company-wide record IDs: " . implode(', ', array_column($surveillance_reports, 'surveillance_id')));
        }
        
        // Debug: Log the final reports for company-wide query
        error_log("surveillance_list.php: Company-wide records retrieved: " . count($surveillance_reports));
        foreach ($surveillance_reports as $report) {
            error_log("surveillance_list.php: Company-wide record - ID: " . $report['surveillance_id'] . ", Date: " . $report['examination_date'] . ", Patient: " . $report['patient_id']);
        }
    }
} catch (PDOException $e) {
    $surveillance_reports = [];
    $patient = false;
    error_log("surveillance_list.php PDO Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
}

// Debug: Log the number of surveillance reports found
error_log("surveillance_list.php: Found " . count($surveillance_reports) . " surveillance reports for patient_id: " . $patient_id);

// Debug: Log the first few records if any exist
if (!empty($surveillance_reports)) {
    error_log("surveillance_list.php: First record: " . json_encode($surveillance_reports[0]));
} else {
    error_log("surveillance_list.php: No surveillance reports found");
}

// Debug: Check if chemical_information table has any records
try {
    $debug_stmt = $clinic_pdo->query("SELECT COUNT(*) as total FROM chemical_information");
    $debug_result = $debug_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("surveillance_list.php: Total records in chemical_information: " . $debug_result['total']);
    
    if ($patient_id > 0) {
        $debug_stmt2 = $clinic_pdo->prepare("SELECT COUNT(*) as total FROM chemical_information WHERE patient_id = ?");
        $debug_stmt2->execute([$patient_id]);
        $debug_result2 = $debug_stmt2->fetch(PDO::FETCH_ASSOC);
        error_log("surveillance_list.php: Records for patient_id $patient_id: " . $debug_result2['total']);
    }
} catch (Exception $e) {
    error_log("surveillance_list.php: Debug query error: " . $e->getMessage());
}

// Check for success/error messages
$message = '';
$messageType = '';

if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance Management - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
        
        .worker-count-badge {
            background: linear-gradient(135deg, #389B5B 0%, #319755 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .contact-info-cell {
            font-size: 0.9rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .contact-icon {
            color: #389B5B;
            margin-right: 0.5rem;
            width: 14px;
            text-align: center;
        }
        
        .contact-text {
            font-size: 0.85rem;
        }
        
        .contact-email {
            color: #389B5B;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .contact-email:hover {
            text-decoration: underline;
        }
        
        .mykpp-number {
            color: #6f42c1;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
        }
        
        .btn-view {
            background: #28a745;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-view:hover {
            background: #218838;
            color: white;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            color: #212529;
        }
        
        .btn-delete:hover {
            background: #c82333;
            color: white;
        }
        
        .btn-declaration {
            background: #6f42c1;
            color: white;
        }
        
        .btn-declaration:hover {
            background: #5a32a3;
            color: white;
        }
        
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
        
        /* Table column width adjustments */
        .table-responsive table {
            table-layout: auto;
            width: auto;
            min-width: 100%;
        }
        
        .table-responsive th:nth-child(1),
        .table-responsive td:nth-child(1) {
            text-align: center;
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(2),
        .table-responsive td:nth-child(2) {
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(3),
        .table-responsive td:nth-child(3) {
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(4),
        .table-responsive td:nth-child(4) {
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(5),
        .table-responsive td:nth-child(5) {
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(6),
        .table-responsive td:nth-child(6) {
            white-space: nowrap;
        }
        
        .table-responsive th:nth-child(7),
        .table-responsive td:nth-child(7) {
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        
    

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        
        <!-- Surveillance List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <nav aria-label="breadcrumb" class="mb-2">
                            <ol class="breadcrumb breadcrumb-custom mb-0">
                                <li class="breadcrumb-item"><a href="medical.php">Company</a></li>
                                <?php if ($patient_id > 0 && isset($patient)): ?>
                                    <li class="breadcrumb-item"><a href="medical_list.php?company_id=<?php echo $company_id; ?>"><?php echo htmlspecialchars($company['company_name']); ?></a></li>
                                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($company['company_name']); ?></li>
                                <?php endif; ?>
                            </ol>
                        </nav>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-stethoscope"></i> History of Medical Surveillance</h5>
                        <div class="btn-group">
                            <?php if ($show_all): ?>
                                <a href="surveillance_list.php?company_id=<?php echo $company_id; ?><?php echo $patient_id > 0 ? '&patient_id=' . $patient_id : ''; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-filter"></i> Filter by Company
                                </a>
                           
                            <?php endif; ?>
                            <?php if ($patient_id > 0): ?>
                                <a href="usechh_1.php?patient_id=<?php echo $patient_id; ?>&new_surveillance=1" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add New Surveillance
                                </a>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label small">Date Assessment</label>
                                <input type="date" class="form-control" id="dateAssessmentFilter">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Type of Examination</label>
                                <select class="form-select" id="examTypeFilter">
                                    <option value="">All Types</option>
                                    <option value="Pre-employment">Pre-employment</option>
                                    <option value="Periodic">Periodic</option>
                                    <option value="Post-employment">Post-employment</option>
                                    <option value="Special">Special</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">&nbsp;</label>
                                <div>
                                    <button class="btn btn-outline-secondary" onclick="clearFilters()">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Results Counter -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="text-muted">
                                    <small id="resultsCounter">
                                        <?php if (isset($grouped_reports) && !empty($grouped_reports)): ?>
                                            Showing <?php echo count($surveillance_reports); ?> surveillance records from <?php echo count($grouped_reports); ?> companies
                                        <?php else: ?>
                                            Showing all surveillance records
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Surveillance Table -->
                        <?php if (empty($surveillance_reports) && !isset($grouped_reports)): ?>
                            <div class="text-center py-5" id="emptyState">
                                <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No surveillance records found</h5>
                                <p class="text-muted">
                                    <?php if ($patient_id > 0): ?>
                                        No surveillance examinations have been recorded for this patient yet.
                                    <?php else: ?>
                                        No surveillance examinations have been recorded for this company yet.
                                    <?php endif; ?>
                                </p>
                                <?php if ($patient_id > 0): ?>
                                    <a href="usechh_1.php?patient_id=<?php echo $patient_id; ?>&new_surveillance=1" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Create First Examination
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php elseif (isset($grouped_reports) && !empty($grouped_reports)): ?>
                            <!-- Grouped Records Display -->
                            <?php foreach ($grouped_reports as $company_name => $company_reports): ?>
                                <div class="mb-4">
                                    <h6 class="text-primary border-bottom pb-2 mb-3">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($company_name); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo count($company_reports); ?> records</span>
                                    </h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr style="text-align: center;">
                                                    <th>NO</th>
                                                    <th>PATIENT NAME</th>
                                                    <th>PATIENT ID</th>
                                                    <th>ASSESSMENT DATE</th>
                                                    <th>CHEMICAL</th>
                                                    <th>TYPE</th>
                                                    <th>EXAMINER</th>
                                                    <th>ACTIONS</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($company_reports as $index => $report): ?>
                                                <tr data-patient-id="<?php echo htmlspecialchars(strtolower($report['patient_code'] ?? 'N/A')); ?>"
                                                    data-patient-name="<?php echo htmlspecialchars(strtolower(isset($report['first_name']) && isset($report['last_name']) ? $report['first_name'] . ' ' . $report['last_name'] : 'Unknown Patient')); ?>"
                                                    data-exam-date="<?php echo isset($report['examination_date']) ? date('d/m/Y', strtotime($report['examination_date'])) : 'N/A'; ?>"
                                                    data-chemical="<?php echo htmlspecialchars(strtolower($report['chemical'] ?? 'N/A')); ?>"
                                                    data-exam-type="<?php echo htmlspecialchars(strtolower($report['examination_type'] ?? 'Health Surveillance')); ?>"
                                                    data-examiner="<?php echo htmlspecialchars(strtolower($report['examiner_name'] ?? 'Medical Officer')); ?>">
                                                    <td style="text-align: center;">
                                                        <?php echo $index + 1; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars(ucwords(strtolower($report['first_name'] . ' ' . $report['last_name']))); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($report['patient_code'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo isset($report['examination_date']) ? date('d/m/Y', strtotime($report['examination_date'])) : 'N/A'; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(ucwords(strtolower($report['chemical'] ?? 'N/A'))); ?>
                                                    </td>
                                                    <td>
                                                        <span class><?php echo htmlspecialchars($report['examination_type'] ?? 'Health Surveillance'); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($report['examiner_name'] ?? 'Medical Officer'); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="usechh1_view.php?id=<?php echo $report['surveillance_id']; ?><?php echo !empty($report['declaration_id']) ? '&declaration_id=' . $report['declaration_id'] : ''; ?>" 
                                                               class="btn btn-outline-primary" title="View Details (ID: <?php echo $report['surveillance_id']; ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="surveillance_edit.php?id=<?php echo $report['surveillance_id']; ?>" 
                                                               class="btn btn-outline-warning" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="confirmDelete(<?php echo $report['surveillance_id']; ?>, '<?php echo htmlspecialchars(ucwords(strtolower($report['first_name'] . ' ' . $report['last_name']))); ?>')" 
                                                                    title="Delete Surveillance Record">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr style="text-align: center;">
                                            <th>NO</th>
                                            <th>PATIENT NAME</th>
                                            <th>ASSESSMENT DATE</th>
                                            <th>CHEMICAL</th>
                                            <th>TYPE</th>
                                            <th>EXAMINER</th>
                                            <th>ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="surveillanceTableBody">
                                        <?php foreach ($surveillance_reports as $index => $report): ?>
                                        <tr data-patient-id="<?php echo htmlspecialchars(strtolower($report['patient_code'] ?? 'N/A')); ?>"
                                            data-patient-name="<?php echo htmlspecialchars(strtolower(isset($report['first_name']) && isset($report['last_name']) ? $report['first_name'] . ' ' . $report['last_name'] : 'Unknown Patient')); ?>"
                                            data-exam-date="<?php echo isset($report['examination_date']) ? date('d/m/Y', strtotime($report['examination_date'])) : 'N/A'; ?>"
                                            data-chemical="<?php echo htmlspecialchars(strtolower($report['chemical'] ?? 'N/A')); ?>"
                                            data-exam-type="<?php echo htmlspecialchars(strtolower($report['examination_type'] ?? 'Health Surveillance')); ?>"
                                            data-examiner="<?php echo htmlspecialchars(strtolower($report['examiner_name'] ?? 'Medical Officer')); ?>">
                                            <td style="text-align: center;">
                                                <?php echo $index + 1; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars(ucwords(strtolower($report['first_name'] . ' ' . $report['last_name']))); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo isset($report['examination_date']) ? date('d/m/Y', strtotime($report['examination_date'])) : 'N/A'; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(ucwords(strtolower($report['chemical'] ?? 'N/A'))); ?>
                                            </td>
                                            <td>
                                                <span class><?php echo htmlspecialchars($report['examination_type'] ?? 'Health Surveillance'); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($report['examiner_name'] ?? 'Medical Officer'); ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="usechh1_view.php?id=<?php echo $report['surveillance_id']; ?><?php echo !empty($report['declaration_id']) ? '&declaration_id=' . $report['declaration_id'] : ''; ?>" 
                                                       class="btn btn-outline-primary" title="View Details (ID: <?php echo $report['surveillance_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="surveillance_edit.php?id=<?php echo $report['surveillance_id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $report['surveillance_id']; ?>, '<?php echo htmlspecialchars(ucwords(strtolower($report['first_name'] . ' ' . $report['last_name']))); ?>')" 
                                                            title="Delete Surveillance Record">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the surveillance record for <strong id="patientNameToDelete"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_surveillance">
                        <input type="hidden" name="surveillance_id" id="surveillanceIdToDelete">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Surveillance Record
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete confirmation function
        function confirmDelete(surveillanceId, patientName) {
            document.getElementById('surveillanceIdToDelete').value = surveillanceId;
            document.getElementById('patientNameToDelete').textContent = patientName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Filter functionality
        function filterReports() {
            const dateFilter = document.getElementById('dateAssessmentFilter').value;
            const examTypeFilter = document.getElementById('examTypeFilter').value;
            const rows = document.querySelectorAll('#surveillanceTableBody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const examDate = row.getAttribute('data-exam-date');
                const examType = row.getAttribute('data-exam-type');
                
                // Date filter
                let matchesDate = true;
                if (dateFilter !== '') {
                    // Convert examDate from d/m/Y to Y-m-d format for comparison
                    const examDateParts = examDate.split('/');
                    if (examDateParts.length === 3) {
                        const examDateFormatted = examDateParts[2] + '-' + examDateParts[1].padStart(2, '0') + '-' + examDateParts[0].padStart(2, '0');
                        matchesDate = examDateFormatted === dateFilter;
                    } else {
                        matchesDate = false;
                    }
                }
                
                // Exam type filter
                const matchesExamType = examTypeFilter === '' || examType === examTypeFilter.toLowerCase();
                
                // Show/hide row based on filters
                if (matchesDate && matchesExamType) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updateResultsCount(visibleCount);
        }
        
        function clearFilters() {
            document.getElementById('dateAssessmentFilter').value = '';
            document.getElementById('examTypeFilter').value = '';
            filterReports();
        }
        
        function updateResultsCount(count) {
            const totalCount = document.querySelectorAll('#surveillanceTableBody tr').length;
            let resultsText = '';
            
            if (count === totalCount) {
                resultsText = `Showing all ${totalCount} surveillance records`;
            } else {
                resultsText = `Showing ${count} of ${totalCount} surveillance records`;
            }
            
            document.getElementById('resultsCounter').textContent = resultsText;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const dateFilter = document.getElementById('dateAssessmentFilter');
            const examTypeFilter = document.getElementById('examTypeFilter');
            
            if (dateFilter) {
                dateFilter.addEventListener('change', filterReports);
            }
            if (examTypeFilter) {
                examTypeFilter.addEventListener('change', filterReports);
            }
            
            // Initialize results counter
            updateResultsCount(document.querySelectorAll('#surveillanceTableBody tr').length);
        });
    </script>
</body>
</html>
