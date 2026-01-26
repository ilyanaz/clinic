<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/get_header_document.php';

// Set timezone to ensure current time is accurate
date_default_timezone_set('Asia/Kuala_Lumpur');

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

// Get patient_id from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: /employee_report.php");
    exit();
}

// Initialize variables
$patient_data = null;
$surveillance_data = [];
$chemical_hazards = '';

// Get patient data
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
    error_log("Employee PDF Error: " . $e->getMessage());
}

if (!$patient_data) {
    header("Location: /employee_report.php?error=" . urlencode("Employee not found"));
    exit();
}

// Generate PDF content
$html = generateEmployeePDFHTML($patient_data, $surveillance_data, $chemical_hazards);

// Function to generate HTML for PDF
function generateEmployeePDFHTML($patient_data, $surveillance_data, $chemical_hazards) {
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
        <title>Employee Medical Surveillance Report</title>
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
                font-size: 7pt;
                text-align: center;
            }
            
            .summary-table td:first-child {
                text-align: left;
                font-weight: bold;
            }
            
            .certificate-form {
                background: #f8f9fa;
                padding: 20px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                margin: 20px 0;
            }
            
            .certificate-title {
                font-size: 14pt;
                font-weight: bold;
                color: #000;
                margin-bottom: 15px;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .certificate-content .form-label {
                font-weight: bold;
                color: #000;
                font-size: 9pt;
                margin-bottom: 5px;
            }
            
            .form-control-plaintext {
                padding: 5px 0;
                font-size: 9pt;
                min-height: 20px;
                display: flex;
                align-items: center;
                border-bottom: 1px solid #ccc;
            }
            
            .underline-field {
                font-weight: 600;
                color: #000;
                display: inline-block;
                padding: 0 5px;
                border-bottom: 1px solid #000;
                min-width: 100px;
            }
            
            .doctor-signature {
                margin-top: 20px;
                padding: 15px 0;
                border-top: 1px solid #dee2e6;
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
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 2</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">SUMMARY REPORTS OF EMPLOYEE</div>
        </div>
        
        <!-- Medical Surveillance Results -->
        <div class="section">
            <div class="section-title">Medical Surveillance Results</div>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">No.</th>
                        <th style="width: 9%;">MS Date</th>
                        <th style="width: 11%;">Assessment Type</th>
                        <th style="width: 11%;">Health Effects</th>
                        <th style="width: 11%;">Clinical Findings</th>
                        <th style="width: 9%;">Organ Function</th>
                        <th style="width: 9%;">BEI Test</th>
                        <th style="width: 9%;">Work Related</th>
                        <th style="width: 10%;">MS Result</th>
                        <th style="width: 9%;">MRP Date</th>
                        <th style="width: 8%;">OHD Reg.</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (!empty($surveillance_data)) {
        foreach ($surveillance_data as $index => $exam) {
            // Check for health effects
            $health_effects = [];
            if (!empty($exam['breathing_difficulty']) && $exam['breathing_difficulty'] == 'Yes') $health_effects[] = 'Breathing';
            if (!empty($exam['cough']) && $exam['cough'] == 'Yes') $health_effects[] = 'Cough';
            if (!empty($exam['headache']) && $exam['headache'] == 'Yes') $health_effects[] = 'Headache';
            if (!empty($exam['nausea']) && $exam['nausea'] == 'Yes') $health_effects[] = 'Nausea';
            if (!empty($exam['eye_irritations']) && $exam['eye_irritations'] == 'Yes') $health_effects[] = 'Eye';
            if (!empty($exam['skin_issues']) && $exam['skin_issues'] == 'Yes') $health_effects[] = 'Skin';
            
            // Check clinical findings
            $findings = [];
            if (!empty($exam['general_appearance']) && $exam['general_appearance'] == 'Abnormal') $findings[] = 'Abnormal';
            if (!empty($exam['ent']) && $exam['ent'] == 'Abnormal') $findings[] = 'ENT';
            if (!empty($exam['skin']) && $exam['skin'] == 'Abnormal') $findings[] = 'Skin';
            if (!empty($exam['respiratory']) && $exam['respiratory'] == 'Abnormal') $findings[] = 'Respiratory';
            
            // Check organ function
            $organ_function = [];
            if (!empty($exam['hepatomegaly']) && $exam['hepatomegaly'] == 'Yes') $organ_function[] = 'Liver';
            if (!empty($exam['splenomegaly']) && $exam['splenomegaly'] == 'Yes') $organ_function[] = 'Spleen';
            if (!empty($exam['lymph_nodes']) && $exam['lymph_nodes'] == 'Palpable') $organ_function[] = 'Lymph';
            
            // Check BEI test
            $bei_tests = [];
            if (!empty($exam['biological_exposure'])) $bei_tests[] = 'Exposure';
            if (!empty($exam['result_baseline'])) $bei_tests[] = 'Baseline';
            if (!empty($exam['result_annual'])) $bei_tests[] = 'Annual';
            
            // Check work relatedness
            $work_related = [];
            if (!empty($exam['clinical_work_related']) && $exam['clinical_work_related'] == 'Yes') $work_related[] = 'Clinical';
            if (!empty($exam['organ_work_related']) && $exam['organ_work_related'] == 'Yes') $work_related[] = 'Organ';
            if (!empty($exam['biological_work_related']) && $exam['biological_work_related'] == 'Yes') $work_related[] = 'Biological';
            
            $fitness_status = $exam['final_assessment'] ?? 'Pending';
            
            $html .= '
                    <tr>
                        <td style="text-align: center; font-weight: bold;">' . ($index + 1) . '</td>
                        <td style="text-align: center; font-weight: bold;">' . date('d/m/Y', strtotime($exam['examination_date'])) . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($exam['examination_type'] ?? 'Periodic') . '</td>
                        <td>' . (!empty($health_effects) ? implode(', ', $health_effects) : 'None') . '</td>
                        <td>' . (!empty($findings) ? implode(', ', $findings) : 'Normal') . '</td>
                        <td style="text-align: center;">' . (!empty($organ_function) ? implode(', ', $organ_function) : 'N/A') . '</td>
                        <td style="text-align: center;">' . (!empty($bei_tests) ? implode(', ', $bei_tests) : 'N/A') . '</td>
                        <td style="text-align: center;">' . (!empty($work_related) ? implode(', ', $work_related) : 'Review') . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($fitness_status) . '</td>
                        <td style="text-align: center;">' . (!empty($exam['date_of_MRP']) ? date('d/m/Y', strtotime($exam['date_of_MRP'])) : 'N/A') . '</td>
                        <td style="text-align: center;">' . htmlspecialchars($exam['examiner_name'] ?? 'Dr. Admin') . '</td>
                    </tr>';
        }
    } else {
        $html .= '
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px;">
                            <strong>No surveillance data available</strong><br>
                            This employee has not undergone any medical surveillance examinations.
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
                <div class="assessment-title">Employee Surveillance Summary</div>
                <div class="assessment-content">
                    This comprehensive employee surveillance report contains all relevant medical surveillance data 
                    for ' . htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) . '. 
                    All surveillance examinations and assessments have been documented and reviewed for accuracy and completeness.
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
$filename = 'Employee_Report_' . $patient_data['first_name'] . '_' . $patient_data['last_name'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>
