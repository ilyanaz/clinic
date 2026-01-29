<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';
// Load Composer autoloader if available
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
require_once __DIR__ . '/../setting/get_header_document.php';

// Set timezone to ensure current time is accurate
date_default_timezone_set('Asia/Kuala_Lumpur');

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . app_url("login.php"));
    exit();
}

// Get declaration ID from URL
$declaration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$declaration_id) {
    header("Location: " . app_url("medical_report.php"));
    exit();
}

// Get declaration data
try {
    $stmt = $clinic_pdo->prepare("SELECT * FROM declarations WHERE id = ?");
    $stmt->execute([$declaration_id]);
    $declaration = $stmt->fetch();
    
    if (!$declaration) {
        header("Location: " . app_url('medical_report.php') . "?error=Declaration not found");
        exit();
    }
} catch (Exception $e) {
    header("Location: " . app_url('medical_report.php') . "?error=" . urlencode($e->getMessage()));
    exit();
}

// Generate PDF content
$html = generateDeclarationPDFHTML($declaration);

// Function to generate HTML for PDF
function generateDeclarationPDFHTML($declaration) {
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
        <title>Medical Declaration Form</title>
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
            
            .report-title {
                font-size: 14pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 10px 0;
                text-transform: uppercase;
            }
            
            .report-subtitle {
                font-size: 10pt;
                color: #333;
                margin: 0 0 12px 0;
            }
            
            .report-date {
                font-size: 10pt;
                color: #666;
                margin: 0;
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
                color: #000;
                margin-bottom: 15px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #000;
                padding-bottom: 5px;
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
            
            .declaration-text {
                text-align: justify;
                line-height: 1.6;
                margin-bottom: 15px;
                font-size: 9pt;
            }
            
            .signature-section {
                width: 100%;
                margin-top: 40px;
                border-collapse: collapse;
            }
            
            .signature-section td {
                width: 50%;
                text-align: center;
                vertical-align: top;
                padding: 0 30px;
            }
            
            .signature-label {
                font-weight: bold;
                color: #000;
                margin-bottom: 10px;
                font-size: 9pt;
                text-transform: uppercase;
            }
            
            .signature-display {
                margin: 15px 0;
                min-height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .signature-display img {
                max-width: 180px;
                max-height: 50px;
                object-fit: contain;
            }
            
            .date-display {
                font-weight: bold;
                color: #000;
                margin-top: 10px;
                font-size: 9pt;
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
                size: A4;
            }
        </style>
    </head>
    <body>
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 1</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">MEDICAL DECLARATION</div>
        </div>
        
        <!-- Patient Information -->
        <div class="section">
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Patient Name:</span><br>
                        <span class="grid-value">' . htmlspecialchars($declaration['patient_name']) . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Employer:</span><br>
                        <span class="grid-value">' . htmlspecialchars($declaration['employer']) . '</span>
                    </td>
                </tr>
                
            </table>
        </div>
        
        <!-- Declaration Section -->
        <div class="section">
            <div class="section-title">Declaration (Pengisytiharan)</div>
            
            <div class="declaration-text">
                <strong>English:</strong><br>
                This is to certify that the above statement is true. I, hereby give consent to the Occupational Health Doctor (OHD) to perform medical examination, necessary tests, and communicate with the employer the results of my medical examination and work capability.
            </div>
            
            <div class="declaration-text">
                <strong>Bahasa Malaysia:</strong><br>
                Ini adalah untuk mengesahkan bahawa kenyataan di atas adalah benar. Saya, dengan ini memberi persetujuan kepada Doktor Kesihatan Pekerjaan (OHD) untuk melaksanakan pemeriksaan perubatan, ujian yang diperlukan, dan berkomunikasi dengan majikan hasil pemeriksaan perubatan dan keupayaan kerja saya.
            </div>
        </div>
        
        
        <!-- Signature Section -->
        <table class="signature-section" style="width: 100%; margin-top: 40px;">
            <tr>
                <td style="width: 50%; text-align: center; vertical-align: top; padding: 0 30px;">
                    <div class="signature-label">Patient Signature</div>
                    <div class="signature-display">';
    
    // Display patient signature if available
    if (!empty($declaration['patient_signature']) && strpos($declaration['patient_signature'], 'data:image') === 0) {
        $html .= '<img src="' . htmlspecialchars($declaration['patient_signature']) . '" alt="Patient Signature">';
    } else {
        $html .= '<div style="color: #999; font-style: italic;">No signature provided</div>';
    }
    
    $html .= '
                    </div>
                    <div class="date-display">Date: ' . date('d F Y', strtotime($declaration['patient_date'])) . '</div>
                </td>
                <td style="width: 50%; text-align: center; vertical-align: top; padding: 0 30px;">
                    <div class="signature-label">Doctor Signature</div>
                    <div class="signature-display">';
    
    // Display doctor signature if available
    if (!empty($declaration['doctor_signature']) && strpos($declaration['doctor_signature'], 'data:image') === 0) {
        $html .= '<img src="' . htmlspecialchars($declaration['doctor_signature']) . '" alt="Doctor Signature">';
    } else {
        $html .= '<div style="color: #999; font-style: italic;">No signature provided</div>';
    }
    
    $html .= '
                    </div>
                    <div class="date-display">Date: ' . date('d F Y', strtotime($declaration['doctor_date'])) . '</div>
                </td>
            </tr>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <div>This declaration was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
            <div>Professional medical declaration and consent form for occupational health monitoring</div>
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
$filename = 'Medical_Declaration_' . $declaration_id . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>

