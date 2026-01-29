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

// Get surveillance ID from URL
$surveillance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$surveillance_id) {
    header("Location: " . app_url("surveillance_list.php"));
    exit();
}

// Get surveillance data
$data = getHealthSurveillanceById($surveillance_id);

if (isset($data['error'])) {
    header("Location: " . app_url('surveillance_list.php') . "?error=" . urlencode($data['error']));
    exit();
}

$surveillance = $data['surveillance'];
$health_history = $data['health_history'];
$clinical_findings = $data['clinical_findings'];
$physical_exam = $data['physical_exam'];

// Generate PDF content
$html = generateSurveillancePDFHTML($surveillance, $health_history, $clinical_findings, $physical_exam);

// Function to generate HTML for PDF
function generateSurveillancePDFHTML($surveillance, $health_history, $clinical_findings, $physical_exam) {
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
        <title>Health Surveillance Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                line-height: 1.4;
                margin: 0;
                padding: 0;
                color: #000;
                background: #fff;
            }
            
            .header {
                text-align: center;
                margin-bottom: 40px;
                border-bottom: 1px solid #000;
                padding-bottom: 20px;
            }
            
            .report-title {
                font-size: 14pt;
                font-weight: bold;
                color: #000;
                margin: 0 0 8px 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
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
            
            .medical-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                border: 1px solid #000;
            }
            
            .medical-table th {
                background: #f5f5f5;
                color: #000;
                padding: 8px 12px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #000;
                font-size: 10pt;
            }
            
            .medical-table td {
                border: 1px solid #000;
                padding: 8px 12px;
                vertical-align: top;
                font-size: 9pt;
            }
            
            .category-cell {
                font-weight: bold;
                background: #f9f9f9;
                color: #000;
                border-right: 1px solid #000;
                width: 35%;
                font-size: 9pt;
            }
            
            .symptoms-cell {
                width: 65%;
                background: #fff;
            }
            
            .symptom-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 4px;
                padding: 2px 0;
                border-bottom: 1px dotted #ccc;
            }
            
            .symptom-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            
            .symptom-name {
                font-weight: normal;
                color: #333;
                flex: 1;
                font-size: 9pt;
            }
            
            .symptom-result {
                font-weight: bold;
                color: #000;
                font-size: 9pt;
                padding: 1px 6px;
                border: 1px solid #ccc;
                background: #f9f9f9;
                min-width: 40px;
                text-align: center;
            }
            
            /* Simple Medical Table Styling - Matching surveillance_view_new.php */
            .enhanced-medical-table {
                width: 100%;
                border-collapse: collapse;
                border: 1px solid #000;
                margin-bottom: 10px;
                font-size: 8pt;
            }
            
            .enhanced-medical-table th {
                background: #f5f5f5;
                color: #000;
                padding: 4px 6px;
                text-align: center;
                font-weight: bold;
                border: 1px solid #000;
                font-size: 9pt;
            }
            
            .enhanced-medical-table td {
                border: 1px solid #000;
                padding: 2px 4px;
                vertical-align: top;
                font-size: 8pt;
            }
            
            .enhanced-medical-table .symptoms-cell {
                padding-right: 0;
            }
            
            .category-cell {
                font-weight: 600;
                color: #2c3e50;
                background: #f8f9fa;
                text-align: left;
                width: 35%;
                padding-left: 4px;
            }
            
            .symptoms-cell {
                background: white;
                width: 65%;
                padding-right: 0;
            }
            
            .symptom-item {
                position: relative;
                padding: 1px 0;
                border-bottom: 1px solid #e9ecef;
                width: 100%;
                height: 14px;
            }
            
            .symptom-item:last-child {
                border-bottom: none;
            }
            
            .symptom-name {
                font-weight: 500;
                color: #495057;
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                font-size: 8pt;
            }
            
            .symptom-result {
                font-weight: normal;
                padding: 0;
                border: none;
                background: none;
                font-size: 8pt;
                color: #000;
                position: absolute;
                right: 4px;
                top: 50%;
                transform: translateY(-50%);
                text-align: right;
                white-space: nowrap;
            }
            
            
            /* Clinical findings results - plain text without badges */
            .clinical-findings .symptom-result {
                background: transparent;
                padding: 0;
                border-radius: 0;
                font-size: 8pt;
                font-weight: normal;
                color: #000;
                position: absolute;
                right: 4px;
                top: 50%;
                transform: translateY(-50%);
                text-align: right;
                white-space: nowrap;
            }
            
            .vital-signs {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 20px;
                margin: 15px 0;
            }
            
            .vital-item {
                text-align: center;
                padding: 10px;
                border: 1px solid #ccc;
                background: #f9f9f9;
            }
            
            .vital-label {
                font-size: 9pt;
                color: #666;
                margin-bottom: 5px;
                font-weight: bold;
            }
            
            .vital-value {
                font-size: 11pt;
                font-weight: bold;
                color: #000;
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
            
            .vital-signs {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .vital-item {
                text-align: center;
                padding: 15px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .vital-item:last-child {
                border-bottom: none;
            }
            
            .vital-label {
                font-size: 9pt;
                color: #6c757d;
                margin-bottom: 5px;
                font-weight: normal;
            }
            
            .vital-value {
                font-size: 12pt;
                color: #000;
                font-weight: bold;
            }
            
            @page {
                margin: 0;
                size: A4;
            }
            
            .page-header {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .header-on-every-page {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1000;
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
            <div class="report-title">HEALTH SURVEILLANCE REPORT</div>
        </div>
        
        <!-- Patient Information -->
        <div class="section">
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Patient Name:</span><br>
                        <span class="grid-value">' . htmlspecialchars(ucwords(strtolower($surveillance['first_name'] . ' ' . $surveillance['last_name']))) . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Age:</span><br>
                        <span class="grid-value">' . (function() use ($surveillance) {
                            if (isset($surveillance['date_of_birth']) && $surveillance['date_of_birth']) {
                                $birthDate = new DateTime($surveillance['date_of_birth']);
                                $today = new DateTime();
                                $age = $birthDate->diff($today);
                                return $age->y . ' years ' . $age->m . ' months';
                            }
                            return 'N/A';
                        })() . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">NRIC/Passport:</span><br>
                        <span class="grid-value">' . htmlspecialchars($surveillance['NRIC']) . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Gender:</span><br>
                        <span class="grid-value">' . htmlspecialchars($surveillance['gender']) . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Date of Birth:</span><br>
                        <span class="grid-value">' . date('d F Y', strtotime($surveillance['date_of_birth'])) . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Workplace:</span><br>
                        <span class="grid-value">' . htmlspecialchars($surveillance['workplace'] ?? 'N/A') . '</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Chemical Exposure:</span><br>
                        <span class="grid-value">' . htmlspecialchars($surveillance['chemical'] ?? 'N/A') . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Examination Information -->
        <div class="section">
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Examination Date:</span><br>
                        <span class="grid-value">' . date('d F Y', strtotime($surveillance['examination_date'])) . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Examination Type:</span><br>
                        <span class="grid-value">' . htmlspecialchars($surveillance['examination_type']) . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Physical Findings -->
        <div class="section">';
    
    if ($physical_exam) {
        // Calculate BMI
        $bmi_info = '';
        if (!empty($physical_exam['weight']) && !empty($physical_exam['height']) && $physical_exam['weight'] > 0 && $physical_exam['height'] > 0) {
            $height_m = $physical_exam['height'] / 100;
            $bmi = $physical_exam['weight'] / ($height_m * $height_m);
            $bmi_rounded = round($bmi, 1);
            
            $bmi_category = '';
            if ($bmi < 18.5) {
                $bmi_category = 'Underweight';
            } elseif ($bmi >= 18.5 && $bmi < 25) {
                $bmi_category = 'Normal weight';
            } elseif ($bmi >= 25 && $bmi < 30) {
                $bmi_category = 'Overweight';
            } else {
                $bmi_category = 'Obese';
            }
            
            $bmi_info = $bmi_rounded . ' kg/m² (' . $bmi_category . ')';
        } elseif (!empty($physical_exam['BMI'])) {
            $bmi_info = htmlspecialchars($physical_exam['BMI']);
        } else {
            $bmi_info = 'N/A';
        }
        
        $html .= '
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Weight:</span><br>
                        <span class="grid-value">' . htmlspecialchars($physical_exam['weight'] ?? 'N/A') . ' kg</span>
                    </td>
                    <td>
                        <span class="grid-label">Height:</span><br>
                        <span class="grid-value">' . htmlspecialchars($physical_exam['height'] ?? 'N/A') . ' cm</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">BMI:</span><br>
                        <span class="grid-value">' . $bmi_info . '</span>
                    </td>
                </tr>
            </table>
            
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Blood Pressure:</span><br>
                        <span class="grid-value">' . htmlspecialchars($physical_exam['blood_pressure'] ?? 'N/A') . ' mmHg</span>
                    </td>
                    <td>
                        <span class="grid-label">Pulse Rate:</span><br>
                        <span class="grid-value">' . htmlspecialchars($physical_exam['pulse_rate'] ?? 'N/A') . ' bpm</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Respiratory Rate:</span><br>
                        <span class="grid-value">' . htmlspecialchars($physical_exam['respiratory_rate'] ?? 'N/A') . ' breaths/min</span>
                    </td>
                </tr>
            </table>';
    }
    
    $html .= '
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 2 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Health Effects -->
        <div class="section">
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">SYMPTOMS</th>
                    </tr>
                </thead>
                <tbody>';
    
    if ($health_history) {
        $html .= '
                    <tr>
                        <td class="category-cell">Respiratory & Cardiovascular</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Breathing difficulty</span>
                                <span class="symptom-result">' . (($health_history['breathing_difficulty'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Cough</span>
                                <span class="symptom-result">' . (($health_history['cough'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Sore throat</span>
                                <span class="symptom-result">' . (($health_history['sore_throat'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Sneezing</span>
                                <span class="symptom-result">' . (($health_history['sneezing'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Chest pain</span>
                                <span class="symptom-result">' . (($health_history['chest_pain'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Palpitation</span>
                                <span class="symptom-result">' . (($health_history['palpitation'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Limb oedema</span>
                                <span class="symptom-result">' . (($health_history['limb_oedema'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Nervous System</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Drowsiness</span>
                                <span class="symptom-result">' . (($health_history['drowsiness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Dizziness</span>
                                <span class="symptom-result">' . (($health_history['dizziness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Headache</span>
                                <span class="symptom-result">' . (($health_history['headache'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Confusion</span>
                                <span class="symptom-result">' . (($health_history['confusion'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Lethargy</span>
                                <span class="symptom-result">' . (($health_history['lethargy'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Nausea</span>
                                <span class="symptom-result">' . (($health_history['nausea'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Vomiting</span>
                                <span class="symptom-result">' . (($health_history['vomiting'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Skin and Eyes</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Eye irritations</span>
                                <span class="symptom-result">' . (($health_history['eye_irritations'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Blurred vision</span>
                                <span class="symptom-result">' . (($health_history['blurred_vision'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Blisters</span>
                                <span class="symptom-result">' . (($health_history['blisters'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Burns</span>
                                <span class="symptom-result">' . (($health_history['burns'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Itching</span>
                                <span class="symptom-result">' . (($health_history['itching'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Rash</span>
                                <span class="symptom-result">' . (($health_history['rash'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Redness</span>
                                <span class="symptom-result">' . (($health_history['redness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Gastrointestinal & Genitourinary</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Abdominal pain</span>
                                <span class="symptom-result">' . (($health_history['abdominal_pain'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Abdominal mass</span>
                                <span class="symptom-result">' . (($health_history['abdominal_mass'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Jaundice</span>
                                <span class="symptom-result">' . (($health_history['jaundice'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Diarrhoea</span>
                                <span class="symptom-result">' . (($health_history['diarrhoea'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Loss of weight</span>
                                <span class="symptom-result">' . (($health_history['loss_of_weight'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Loss of appetite</span>
                                <span class="symptom-result">' . (($health_history['loss_of_appetite'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Dysuria</span>
                                <span class="symptom-result">' . (($health_history['dysuria'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Haematuria</span>
                                <span class="symptom-result">' . (($health_history['haematuria'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>';
        
        if (!empty($health_history['others_symptoms'])) {
            $html .= '
                    <tr>
                        <td class="category-cell">Other Symptoms</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . htmlspecialchars($health_history['others_symptoms']) . '</span>
                            </div>
                        </td>
                    </tr>';
        }
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 3 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Clinical Findings -->
        <div class="section clinical-findings">
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">FINDINGS</th>
                    </tr>
                </thead>
                <tbody>';
    
    if ($physical_exam) {
        $html .= '
                    <tr>
                        <td class="category-cell">(i) General Appearance</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">General Appearance</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['general_appearance'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(ii) Cardiovascular System</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">S1 & S2</span>
                                <span class="symptom-result">' . (($physical_exam['s1_s2'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Murmur</span>
                                <span class="symptom-result">' . (($physical_exam['murmur'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(iii) Ear, Nose and Throat</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">ENT</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['ent'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(iv) Eyes</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Visual Acuity (R/L)</span>
                                <span class="symptom-result">' . htmlspecialchars($physical_exam['visual_acuity_right'] ?? 'N/A') . ' / ' . htmlspecialchars($physical_exam['visual_acuity_left'] ?? 'N/A') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Colour Blindness</span>
                                <span class="symptom-result">' . (($physical_exam['colour_blindness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(v) Gastrointestinal</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Tenderness</span>
                                <span class="symptom-result">' . (($physical_exam['gi_tenderness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Abdominal Mass</span>
                                <span class="symptom-result">' . (($physical_exam['abdominal_mass_exam'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(vi) Haematology</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Lymph nodes</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['lymph_nodes'] ?? 'Non-palpable')) . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Splenomegaly</span>
                                <span class="symptom-result">' . (($physical_exam['splenomegaly'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(vii) Kidney</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Tenderness</span>
                                <span class="symptom-result">' . (($physical_exam['kidney_tenderness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Ballotable</span>
                                <span class="symptom-result">' . (($physical_exam['ballotable'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(viii) Liver</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Jaundice</span>
                                <span class="symptom-result">' . (($physical_exam['liver_jaundice'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Hepatomegaly</span>
                                <span class="symptom-result">' . (($physical_exam['hepatomegaly'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(ix) Musculoskeletal</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Muscle Tone</span>
                                <span class="symptom-result">' . htmlspecialchars($physical_exam['muscle_tone'] ?? '3') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Muscle Tenderness</span>
                                <span class="symptom-result">' . (($physical_exam['muscle_tenderness'] ?? 'No') == 'Yes' ? 'Yes' : 'No') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(x) Nervous System</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Power</span>
                                <span class="symptom-result">' . htmlspecialchars($physical_exam['power'] ?? '3') . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Sensation</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['sensation'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(xi) Respiratory</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Clear, Rhonchi, Crepitus</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['respiratory_findings'] ?? 'N/A')) . '</span>
                            </div>
                            <div class="symptom-item">
                                <span class="symptom-name">Air Entry</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['air_entry'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(xii) Reproductive</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Reproductive</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['reproductive'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">(xiii) Skin</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Skin</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['skin'] ?? 'Normal')) . '</span>
                            </div>
                        </td>
                    </tr>';
        
        if (!empty($physical_exam['others_exam'])) {
            $html .= '
                    <tr>
                        <td class="category-cell">(xiv) Others</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-name">Others</span>
                                <span class="symptom-result">' . htmlspecialchars(ucwords($physical_exam['others_exam'])) . '</span>
                            </div>
                        </td>
                    </tr>';
        }
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 4 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Assessment Summary -->
        <div class="section">
            <div class="assessment-box">
                <div class="assessment-title">Final Assessment</div>
                <div class="assessment-content">
                    Based on the comprehensive health surveillance examination conducted on ' . date('d F Y', strtotime($surveillance['examination_date'])) . ', 
                    the patient demonstrates appropriate health monitoring for occupational chemical exposure. 
                    All findings have been documented and reviewed by the examining physician.
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>This report was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
            <div>Professional medical surveillance and patient management for occupational health monitoring</div>
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
$filename = 'Health_Surveillance_Report_' . $surveillance_id . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>

