<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';
// Load Composer autoloader if available (for Laravel/PDF libraries)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/get_header_document.php';

// Set timezone to ensure current time is accurate
date_default_timezone_set('Asia/Kuala_Lumpur');

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url("login.php"));
    exit();
}

// Get company data
$company_data = null;
$company_id = null;
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

if (!$company_data) {
    header("Location: " . url('abnormal_workers_report.php') . "?error=" . urlencode("Company not found"));
    exit();
}

// Generate PDF content
$html = generateAbnormalPDFHTML($company_data, $abnormal_workers, $company_chemical_hazards);

// Function to generate HTML for PDF
function generateAbnormalPDFHTML($company_data, $abnormal_workers, $company_chemical_hazards) {
    // Get uploaded header document
    $headerDocumentPath = getHeaderDocument();
    $headerHtml = '';
    
    if ($headerDocumentPath && file_exists($headerDocumentPath)) {
        $fileExtension = strtolower(pathinfo($headerDocumentPath, PATHINFO_EXTENSION));
        $fileContent = file_get_contents($headerDocumentPath);
        $base64Content = base64_encode($fileContent);
        
        if ($fileExtension === 'pdf') {
            // For PDF headers, we'll embed as base64
            $headerHtml = '<div class="page-header">
                <div class="header-document">
                    <object data="data:application/pdf;base64,' . $base64Content . '" type="application/pdf" width="100%" height="200px">
                        <p>Header document could not be displayed</p>
                    </object>
                </div>
            </div>';
        } elseif (in_array($fileExtension, ['jpg', 'jpeg'])) {
            // For image headers, display as image
            $headerHtml = '<div class="page-header">
                <div class="header-document">
                    <img src="data:image/jpeg;base64,' . $base64Content . '" alt="Header Document" style="width: 100%; max-height: 200px; object-fit: contain;">
                </div>
            </div>';
        }
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Abnormal Workers Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                color: #000;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 20px;
            }
            
            .clinic-name {
                font-size: 18pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 0;
            }
            
            .report-title {
                font-size: 14pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 10px 0;
                text-transform: uppercase;
            }
            
            .report-date {
                font-size: 10pt;
                color: #666;
            }
            
            .legal-ref {
                font-size: 9pt;
                color: #333;
                margin: 3px 0;
                text-align: center;
            }
            
            .page-header {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            .header-document {
                width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .header-document img,
            .header-document object {
                width: 100%;
                max-height: 200px;
                object-fit: contain;
                display: block;
                margin: 0;
                padding: 0;
            }
            
            .content-wrapper {
                padding: 20px;
            }
            
            .section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            .section-title {
                font-size: 12pt;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 15px;
                padding: 8px;
                background: #f8f9fa;
                border-left: 4px solid #389B5B;
                text-transform: uppercase;
            }
            
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .info-table td {
                padding: 8px 12px;
                vertical-align: top;
                border-bottom: 1px solid #ddd;
            }
            
            .info-label {
                font-weight: bold;
                color: #000;
                width: 40%;
                font-size: 9pt;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            
            .info-value {
                width: 60%;
                color: #333;
                font-size: 9pt;
                font-weight: normal;
            }
            
            .grid-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .grid-table td {
                padding: 6px 8px;
                vertical-align: top;
                border-bottom: 1px solid #ddd;
                width: 50%;
            }
            
            .grid-label {
                font-weight: bold;
                color: #000;
                font-size: 8pt;
                text-transform: uppercase;
                letter-spacing: 0.2px;
            }
            
            .grid-value {
                color: #333;
                font-size: 8pt;
                font-weight: normal;
            }
            
            .summary-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                border: 1px solid #000;
                font-size: 8pt;
            }
            
            .summary-table th {
                background: #000;
                color: white;
                font-weight: 600;
                text-align: center;
                padding: 6px 4px;
                border: 1px solid #000;
                font-size: 8pt;
            }
            
            .summary-table td {
                border: 1px solid #000;
                padding: 4px 3px;
                vertical-align: top;
                font-size: 8pt;
            }
            
            .summary-table td:first-child {
                text-align: left;
                font-weight: bold;
            }
            
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 9pt;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 15px;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .assessment-box {
                background: #f9f9f9;
                border: 1px solid #ccc;
                padding: 15px;
                margin: 20px 0;
            }
            
            .assessment-title {
                font-weight: bold;
                color: #000;
                margin-bottom: 10px;
                font-size: 11pt;
            }
            
            .assessment-content {
                color: #333;
                line-height: 1.4;
                font-size: 10pt;
            }
            
            @page {
                margin: 1cm;
                size: A4 landscape;
            }
        </style>
    </head>
    <body>
        ' . $headerHtml . '
        <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 5ii</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">DETAILS OF WORKERS WITH ABNORMAL EXAMINATION RESULTS</div>
        </div>
        
        <!-- Company Information -->
        <div class="section">
            <table class="grid-table">
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Company Name:</span><br>
                        <span class="grid-value">' . htmlspecialchars($company_data['company_name']) . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Address:</span><br>
                        <span class="grid-value">' . htmlspecialchars($company_data['address'] ?? 'Not specified') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Chemical Hazards:</span><br>
                        <span class="grid-value">' . htmlspecialchars(implode(', ', $company_chemical_hazards) ?: 'Not specified') . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Abnormal Workers Results -->
        <div class="section">
            <div class="section-title">Abnormal Workers Report</div>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 15%;">Employee Name</th>
                        <th style="width: 12%;">NRIC/Passport</th>
                        <th style="width: 8%;">Sex</th>
                        <th style="width: 12%;">Job Category</th>
                        <th style="width: 10%;">Assessment Type</th>
                        <th style="width: 12%;">Health Effects</th>
                        <th style="width: 12%;">Clinical Findings</th>
                        <th style="width: 10%;">Target Organ</th>
                        <th style="width: 8%;">BM Determinant</th>
                        <th style="width: 10%;">Work Related</th>
                        <th style="width: 12%;">Recommendation</th>
                        <th style="width: 10%;">MS Result</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (!empty($abnormal_workers)) {
        foreach ($abnormal_workers as $index => $worker) {
            // Get patient details for this worker
            global $clinic_pdo;
            $stmt = $clinic_pdo->prepare("SELECT * FROM patient_information WHERE id = ?");
            $stmt->execute([$worker['patient_id']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get occupational details
            $stmt = $clinic_pdo->prepare("SELECT * FROM occupational_history WHERE patient_id = ? AND company_name = ?");
            $stmt->execute([$worker['patient_id'], $company_data['company_name']]);
            $occupational = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $html .= '
                    <tr>
                        <td style="text-align: center;"><strong>' . ($index + 1) . '</strong></td>
                        <td>' . htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($patient['NRIC'] ?? $patient['passport_no'] ?? 'N/A') . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($patient['gender'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($occupational['job_title'] ?? 'N/A') . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($worker['examination_type'] ?? 'Pre-employment') . '</td>
                        <td style="text-align: center;">' . (!empty($worker['history_of_health']) && $worker['history_of_health'] == 'Yes' ? 'Yes' : 'No') . '</td>
                        <td style="text-align: center;">' . (!empty($worker['clinical_findings']) && $worker['clinical_findings'] == 'Yes' ? 'Yes' : 'No') . '</td>
                        <td style="text-align: center;">' . (!empty($worker['target_organ']) && $worker['target_organ'] == 'Yes' ? 'Yes' : 'No') . '</td>
                        <td style="text-align: center;">' . (!empty($worker['biological_monitoring']) && $worker['biological_monitoring'] == 'Yes' ? 'Yes' : 'No') . '</td>
                        <td style="text-align: center;">';
            
            $work_related = [];
            if (!empty($worker['clinical_work_related']) && $worker['clinical_work_related'] == 'Yes') $work_related[] = 'Clinical';
            if (!empty($worker['organ_work_related']) && $worker['organ_work_related'] == 'Yes') $work_related[] = 'Organ';
            if (!empty($worker['biological_work_related']) && $worker['biological_work_related'] == 'Yes') $work_related[] = 'Biological';
            $html .= !empty($work_related) ? implode(', ', $work_related) : 'Review';
            
            $html .= '</td>
                        <td style="text-align: center;">';
            
            $recommendations = [];
            if (!empty($worker['recommendations_type'])) $recommendations[] = $worker['recommendations_type'];
            if (!empty($worker['date_of_MRP'])) $recommendations[] = 'MRP';
            $html .= !empty($recommendations) ? implode(', ', $recommendations) : 'N/A';
            
            $html .= '</td>
                        <td style="text-align: center;">';
            
            $fitness_status = $worker['final_assessment'] ?? 'Pending';
            if ($fitness_status == 'Fit for Work') {
                $html .= 'Fit';
            } elseif ($fitness_status == 'Not Fit for Work') {
                $html .= 'Not Fit';
            } else {
                $html .= 'Pending';
            }
            
            $html .= '</td>
                    </tr>';
        }
    } else {
        $html .= '
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 20px;">
                            <strong>No abnormal findings</strong><br>
                            All workers from ' . htmlspecialchars($company_data['company_name']) . ' have normal examination results.<br>
                            No workers require reporting on this form.
                        </td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 2 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Assessment Summary -->
        <div class="section">
            <div class="assessment-box">
                <div class="assessment-title">Abnormal Workers Summary</div>
                <div class="assessment-content">
                    This report contains details of workers with abnormal examination results from ' . htmlspecialchars($company_data['company_name']) . '. 
                    ' . (count($abnormal_workers) > 0 ? 'A total of ' . count($abnormal_workers) . ' workers have been identified with abnormal findings requiring attention.' : 'No workers with abnormal findings were identified.') . '
                    All findings have been documented and reviewed for accuracy and completeness.
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>This report was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
            <div>Professional medical surveillance and occupational health monitoring</div>
        </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Configure DomPDF options
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

// Create DomPDF instance
$dompdf = new Dompdf($options);

// Load HTML content
$dompdf->loadHtml($html);

// Set paper size and orientation
$dompdf->setPaper('A4', 'landscape');

// Render the HTML as PDF
$dompdf->render();

// Generate filename
$filename = 'Abnormal_Workers_Report_' . $company_data['company_name'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>
