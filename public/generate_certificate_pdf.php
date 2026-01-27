<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';
require_once __DIR__ . '/includes/clinic_functions.php';
// Load Composer autoloader if available
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

// Get patient_id from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: " . url("employee_report.php"));
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
    error_log("Certificate PDF Error: " . $e->getMessage());
}

if (!$patient_data) {
    header("Location: " . url("employee_report.php?error=" . urlencode("Employee not found")));
    exit();
}

// Get doctor's saved signature (if any) for use in the certificate
$doctor_signature_html = '';
try {
    $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'user_signatures'")->fetch();
    if ($tableCheck && isset($_SESSION['user_id'])) {
        $stmt = $clinic_pdo->prepare("
            SELECT file_path 
            FROM user_signatures 
            WHERE user_id = ? 
            ORDER BY uploaded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $signature = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($signature && !empty($signature['file_path']) && file_exists($signature['file_path'])) {
            if (extension_loaded('gd')) {
                $imageData = base64_encode(file_get_contents($signature['file_path']));
                $doctor_signature_html = '<img src="data:image/png;base64,' . $imageData . '" alt="Doctor Signature" style="max-height:80px; max-width:200px;">';
            } else {
                // Fallback message if GD is missing (avoids DomPDF fatal errors)
                $doctor_signature_html = '<em>Signature stored, please enable PHP GD extension to render image.</em>';
            }
        }
    }
} catch (Exception $e) {
    error_log("Certificate PDF - Error getting doctor signature: " . $e->getMessage());
}

// Generate PDF content
$html = generateCertificatePDFHTML($patient_data, $surveillance_data, $chemical_hazards, $doctor_signature_html);

// Function to generate HTML for PDF
function generateCertificatePDFHTML($patient_data, $surveillance_data, $chemical_hazards, $doctor_signature_html) {
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
        <title>Certificate of Fitness</title>
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
                margin-bottom: 15px;
            }
            
            .info-table td {
                padding: 6px 8px;
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
                margin-bottom: 15px;
            }
            
            .grid-table td {
                padding: 4px 6px;
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
            
            .certificate-content {
                margin: 15px 0;
            }
            
            .certificate-content .form-label {
                font-weight: bold;
                color: #000;
                font-size: 9pt;
                margin-bottom: 3px;
            }
            
            .form-control-plaintext {
                padding: 3px 0;
                font-size: 9pt;
                min-height: 15px;
                display: flex;
                align-items: center;
                border-bottom: 1px solid #000;
                border-top: none;
                border-left: none;
                border-right: none;
                background: transparent;
            }
            
            .underline-field {
                font-weight: 600;
                color: #000;
                display: inline-block;
                padding: 0 3px;
                border-bottom: 1px solid #000;
                min-width: 80px;
            }
            
            .doctor-signature {
                margin-top: 15px;
                padding: 10px 0;
                border-top: 1px solid #dee2e6;
            }
            
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 9pt;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 10px;
            }
            
            .assessment-box {
                background: #f9f9f9;
                border: 1px solid #ccc;
                padding: 10px;
                margin: 15px 0;
            }
            
            .assessment-title {
                font-weight: bold;
                color: #000;
                margin-bottom: 8px;
                font-size: 10pt;
            }
            
            .assessment-content {
                color: #333;
                line-height: 1.3;
                font-size: 9pt;
            }
            
            @page {
                margin: 1cm;
                size: A4;
            }
        </style>
    </head>
    <body>
        ' . $headerHtml . '
        <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 3</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">CERTIFICATE OF FITNESS</div>
        </div>
        
        <!-- Certificate of Fitness -->
        <div class="section">
            <div class="certificate-content">
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 10pt; line-height: 1.4;">
                        <strong>Name of Person examined:</strong> ' . htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) . '. <strong>NRIC/ Passport No.:</strong> ' . htmlspecialchars($patient_data['NRIC'] ?? $patient_data['passport_no'] ?? 'Not specified') . '
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 10pt; line-height: 1.4;">
                        <strong>Date of Birth:</strong> ' . date('d/m/Y', strtotime($patient_data['date_of_birth'])) . '. <strong>Sex:</strong> ' . htmlspecialchars($patient_data['gender']) . '
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 10pt; line-height: 1.4;">
                        <strong>Name & Address of Employee:</strong> ' . ucwords(strtolower(htmlspecialchars($patient_data['company_name'] ?? 'Not specified'))) . ', ' . htmlspecialchars($patient_data['address'] ?? '') . '
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 10pt; line-height: 1.4;">
                        I hereby certify that I have examined the above-named person on <strong>' . (!empty($surveillance_data) ? date('d/m/Y', strtotime($surveillance_data[0]['examination_date'])) : 'Not specified') . '</strong> and he/she is <strong>' . 
                        (function() use ($surveillance_data) {
                            // Default text
                            $fitness_status = 'Not specified';

                            if (!empty($surveillance_data)) {
                                $raw = trim((string)($surveillance_data[0]['final_assessment'] ?? ''));

                                if ($raw === '') {
                                    // If there is surveillance but no explicit status, default to "fit"
                                    $fitness_status = 'fit';
                                } else {
                                    // Normalise value (handles "Fit", "Fit for Work", "FIT", etc.)
                                    $lower = strtolower($raw);
                                    if (strpos($lower, 'fit') !== false && strpos($lower, 'not fit') === false && strpos($lower, 'unfit') === false) {
                                        $fitness_status = 'fit';
                                    } else {
                                        $fitness_status = 'not fit';
                                    }
                                }
                            }

                            return $fitness_status;
                        })() . '</strong> for work which may expose him to <strong>' . htmlspecialchars($chemical_hazards ?: 'chemical hazards') . '</strong>.
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 10pt; line-height: 1.4;">
                        <strong>Remarks (if any):</strong> ' . (!empty($surveillance_data) && !empty($surveillance_data[0]['notes']) ? htmlspecialchars($surveillance_data[0]['notes']) : 'No remarks') . '
                    </div>
                </div>
                
                <div class="doctor-signature" style="text-align: right; margin-top: 40px;">
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 10pt; line-height: 1.4;">
                            <strong>Signature & Date:</strong><br>
                            ' . (!empty($doctor_signature_html)
                                ? $doctor_signature_html
                                : '[Signature not available – please upload in Settings]') . '<br>
                            ' . date('d/m/Y') . '
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 10pt; line-height: 1.4;">
                            <strong>Name of Occupational Health Doctor:</strong> ' . strtoupper(htmlspecialchars($surveillance_data[0]['examiner_name'] ?? 'DR. ADMIN')) . '
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 10pt; line-height: 1.4;">
                            <strong>DOSH Reg. No.:</strong> ' . htmlspecialchars($surveillance_data[0]['dosh_reg_no'] ?? 'REG123456') . '
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 10pt; line-height: 1.4;">
                            <strong>Address of Practice:</strong><br>
                            ' . (function($patient_data) {
                                $lines = [];
                                
                                if (!empty($patient_data['company_name'])) {
                                    $lines[] = htmlspecialchars($patient_data['company_name']);
                                }
                                
                                if (!empty($patient_data['address'])) {
                                    $segments = array_filter(array_map('trim', explode(',', $patient_data['address'])));
                                    $segmentCount = count($segments);
                                    foreach ($segments as $index => $segment) {
                                        $hasTrailing = ($index < $segmentCount - 1) ||
                                            (!empty($patient_data['district']) || !empty($patient_data['state']) || !empty($patient_data['postcode']));
                                        $line = $segment;
                                        if ($hasTrailing && substr($line, -1) !== ',') {
                                            $line .= ',';
                                        }
                                        $lines[] = htmlspecialchars($line);
                                    }
                                }
                                
                                $finalLineParts = [];
                                if (!empty($patient_data['postcode'])) {
                                    $finalLineParts[] = trim($patient_data['postcode']);
                                }
                                if (!empty($patient_data['district'])) {
                                    $finalLineParts[] = trim($patient_data['district']);
                                }
                                $finalLine = '';
                                if (!empty($finalLineParts)) {
                                    $finalLine = implode(' ', $finalLineParts);
                                }
                                if (!empty($patient_data['state'])) {
                                    $finalLine .= ($finalLine !== '' ? ', ' : '') . trim($patient_data['state']);
                                }
                                if ($finalLine !== '') {
                                    if (substr($finalLine, -1) !== '.') {
                                        $finalLine .= '.';
                                    }
                                    $lines[] = htmlspecialchars($finalLine);
                                }
                                
                                if (empty($lines)) {
                                    $lines[] = 'Not specified';
                                }
                                
                                return implode('<br>', $lines);
                            })($patient_data) . '
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>This certificate was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
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
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Generate filename
$filename = 'Certificate_of_Fitness_' . $patient_data['first_name'] . '_' . $patient_data['last_name'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>
