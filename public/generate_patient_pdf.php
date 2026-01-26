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

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$patient_id) {
    header("Location: /patients.php");
    exit();
}

// Get patient data
$patient = getClinicPatientById($patient_id);

if (isset($patient['error'])) {
    header("Location: /patients.php?error=" . urlencode($patient['error']));
    exit();
}

if (!$patient) {
    header("Location: /patients.php?error=Patient not found");
    exit();
}

// Generate PDF content
$html = generatePatientPDFHTML($patient);

// Function to generate HTML for PDF
function generatePatientPDFHTML($patient) {
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
        <title>Patient Details Report</title>
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
                padding: 15px;
            }
            
            .section {
                margin-bottom: 10px;
                page-break-inside: avoid;
            }
            .section-title {
                font-size: 11pt;
                font-weight: bold;
                color: #000;
                margin-bottom: 5px;
                margin-top: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #000;
                padding-bottom: 3px;
            }
            
            .info-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 8px;
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
                margin-bottom: 8px;
            }
            
            .grid-table td {
                padding: 4px 8px;
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
                margin-bottom: 8px;
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
            
            .footer {
                margin-top: 10px;
                text-align: center;
                font-size: 8pt;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 5px;
            }
            
            .assessment-box {
                background: #f9f9f9;
                border: 1px solid #ccc;
                padding: 8px;
                margin: 8px 0;
            }
            
            .assessment-title {
                font-weight: bold;
                color: #000;
                margin-bottom: 3px;
                font-size: 10pt;
            }
            
            .assessment-content {
                color: #333;
                line-height: 1.2;
                font-size: 9pt;
            }
            
            @page {
                margin: 0.7cm;
                size: A4;
            }
        </style>
    </head>
    <body>
        ' . $headerHtml . '
        <div class="content-wrapper">
        <!-- Header -->
        <div class="header">
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">PATIENT DETAILS REPORT</div>
            <div class="report-date">Generated on: ' . date('d F Y') . '</div>
        </div>
        
        <!-- A: GENERAL INFORMATION (MAKLUMAT UMUM) -->
        <div class="section">
            <div class="section-title">A: GENERAL INFORMATION (MAKLUMAT UMUM)</div>
            <table class="grid-table">
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Name of Worker (Nama pekerja):</span><br>
                        <span class="grid-value">' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Address (Alamat):</span><br>
                        <span class="grid-value">' . (!empty($patient['address']) ? htmlspecialchars($patient['address']) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">District (Daerah):</span><br>
                        <span class="grid-value">' . (!empty($patient['district']) ? htmlspecialchars($patient['district']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">State (Negeri):</span><br>
                        <span class="grid-value">' . (!empty($patient['state']) ? htmlspecialchars($patient['state']) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Post-code:</span><br>
                        <span class="grid-value">' . (!empty($patient['postcode']) ? htmlspecialchars($patient['postcode']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Date of Birth:</span><br>
                        <span class="grid-value">' . (!empty($patient['date_of_birth']) ? date('d F Y', strtotime($patient['date_of_birth'])) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">IC Number:</span><br>
                        <span class="grid-value">' . (!empty($patient['NRIC']) ? htmlspecialchars($patient['NRIC']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Passport:</span><br>
                        <span class="grid-value">' . (!empty($patient['passport_no']) ? htmlspecialchars($patient['passport_no']) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Telephone Number:</span><br>
                        <span class="grid-value">' . (!empty($patient['telephone_no']) ? htmlspecialchars($patient['telephone_no']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Email:</span><br>
                        <span class="grid-value">' . (!empty($patient['email']) ? htmlspecialchars($patient['email']) : 'Not recorded') . '</span>
                    </td>
                </tr>
            </table>
            
            <!-- Gender, Ethnic, Status Information -->
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Gender (Jantina):</span><br>
                        <span class="grid-value">' . (!empty($patient['gender']) ? htmlspecialchars($patient['gender']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Ethnic (Bangsa):</span><br>
                        <span class="grid-value">' . (!empty($patient['ethnicity']) ? htmlspecialchars($patient['ethnicity']) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Status:</span><br>
                        <span class="grid-value">' . (!empty($patient['martial_status']) ? htmlspecialchars($patient['martial_status']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">No. of Children (Bilangan anak):</span><br>
                        <span class="grid-value">' . (isset($patient['no_of_children']) && $patient['no_of_children'] !== null && $patient['no_of_children'] !== '' ? htmlspecialchars($patient['no_of_children']) : 'Not recorded') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">No. of Years Married (Bilangan tahun berkahwin):</span><br>
                        <span class="grid-value">' . (isset($patient['years_married']) && $patient['years_married'] !== null && $patient['years_married'] !== '' ? htmlspecialchars($patient['years_married']) : 'Not recorded') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Malaysian Citizen (Warganegara Malaysia):</span><br>
                        <span class="grid-value">' . (!empty($patient['citizenship']) ? htmlspecialchars($patient['citizenship']) : 'Not recorded') . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- B: MEDICAL HISTORY (SEJARAH PERUBATAN) -->
        <div class="section">
            <div class="section-title">B: MEDICAL HISTORY (SEJARAH PERUBATAN)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">1. Have you been diagnosed with any disease? (Anda pernah menghidap penyakit?)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['diagnosed_history']) ? nl2br(htmlspecialchars($patient['diagnosed_history'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">2. Current Medications (Ubat semasa)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['medication_history']) ? nl2br(htmlspecialchars($patient['medication_history'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">3. Hospital Admissions (Kemasukan hospital)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['admitted_history']) ? nl2br(htmlspecialchars($patient['admitted_history'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- C: PERSONAL & SOCIAL HISTORY (SEJARAH PERIBADI & SOSIAL) -->
        <div class="section">
            <div class="section-title">C: PERSONAL & SOCIAL HISTORY (SEJARAH PERIBADI & SOSIAL)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">Smoking (Merokok)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['smoking_history']) ? htmlspecialchars($patient['smoking_history']) : 'Not recorded') . 
                                (!empty($patient['years_of_smoking']) ? ' (' . htmlspecialchars($patient['years_of_smoking']) . ' years)' : '') .
                                (!empty($patient['no_of_cigarettes']) ? ' - ' . htmlspecialchars($patient['no_of_cigarettes']) . ' cigarettes/day' : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Vaping</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['vaping_history']) ? htmlspecialchars($patient['vaping_history']) : 'Not recorded') . 
                                (!empty($patient['years_of_vaping']) ? ' (' . htmlspecialchars($patient['years_of_vaping']) . ' years)' : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Hobby</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['hobby']) ? htmlspecialchars($patient['hobby']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Part-time Job (Kerja sambilan)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['parttime_job']) ? htmlspecialchars($patient['parttime_job']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- D: RELEVANT FAMILY HISTORY (SEJARAH PENYAKIT DALAM KELUARGA) -->
        <div class="section">
            <div class="section-title">D: RELEVANT FAMILY HISTORY (SEJARAH PENYAKIT DALAM KELUARGA)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">Family Medical History</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['family_history']) ? nl2br(htmlspecialchars($patient['family_history'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
        
        <!-- E: OTHER RELEVANT HISTORIES (SEJARAH LAIN YANG RELEVAN) -->
        <div class="section">
            <div class="section-title">E: OTHER RELEVANT HISTORIES (SEJARAH LAIN YANG RELEVAN)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">Other Relevant Histories</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['others_history']) ? nl2br(htmlspecialchars($patient['others_history'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- F: OCCUPATIONAL HISTORY (SEJARAH PEKERJAAN) -->
        <div class="section">
            <div class="section-title">F: OCCUPATIONAL HISTORY (SEJARAH PEKERJAAN)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">Job Title (Jawatan kerja)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['job_title']) ? htmlspecialchars($patient['job_title']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Company Name (Nama syarikat)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['company_name']) ? htmlspecialchars($patient['company_name']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Employment Duration (Tempoh pekerjaan)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['employment_duration']) ? htmlspecialchars($patient['employment_duration']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Chemical Exposure Duration (Tempoh pendedahan bahan kimia)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['chemical_exposure_duration']) ? htmlspecialchars($patient['chemical_exposure_duration']) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">Chemical Exposure Incidents (Kejadian pendedahan bahan kimia)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['chemical_exposure_incidents']) ? nl2br(htmlspecialchars($patient['chemical_exposure_incidents'])) : 'Not recorded') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- G: HISTORY OF TRAINING (SEJARAH LATIHAN) -->
        <div class="section">
            <div class="section-title">G: HISTORY OF TRAINING (SEJARAH LATIHAN)</div>
            <table class="enhanced-medical-table">
                <thead>
                    <tr>
                        <th class="category-col">CATEGORY</th>
                        <th class="symptoms-col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="category-cell">a) Safe handling of chemicals (Kendali bahan kimia dengan selamat)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['handling_of_chemical']) ? htmlspecialchars($patient['handling_of_chemical']) : 'Not recorded') . 
                                (!empty($patient['chemical_comments']) ? ' - ' . htmlspecialchars($patient['chemical_comments']) : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">b) Recognizing signs and symptoms of disease (Kenalpasti gejala penyakit)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['sign_symptoms']) ? htmlspecialchars($patient['sign_symptoms']) : 'Not recorded') . 
                                (!empty($patient['sign_symptoms_comments']) ? ' - ' . htmlspecialchars($patient['sign_symptoms_comments']) : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">c) Chemical poisoning at workplace (Keracunan bahan kimia)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['chemical_poisoning']) ? htmlspecialchars($patient['chemical_poisoning']) : 'Not recorded') . 
                                (!empty($patient['poisoning_comments']) ? ' - ' . htmlspecialchars($patient['poisoning_comments']) : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">d) Proper PPE usage (Pakai PPE dengan betul)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['proper_PPE']) ? htmlspecialchars($patient['proper_PPE']) : 'Not recorded') . 
                                (!empty($patient['proper_PPE_comments']) ? ' - ' . htmlspecialchars($patient['proper_PPE_comments']) : '') . '</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="category-cell">e) PPE Usage (Penggunaan PPE)</td>
                        <td class="symptoms-cell">
                            <div class="symptom-item">
                                <span class="symptom-result">' . (!empty($patient['PPE_usage']) ? htmlspecialchars($patient['PPE_usage']) : 'Not recorded') . 
                                (!empty($patient['PPE_usage_comment']) ? ' - ' . htmlspecialchars($patient['PPE_usage_comment']) : '') . '</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>';
    
    $html .= '
        <!-- Assessment Summary -->
        <div class="section">
            <div class="assessment-box">
                <div class="assessment-title">Patient Summary</div>
                <div class="assessment-content">
                    This comprehensive patient record contains all relevant medical and personal information 
                    for ' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '. 
                    All information has been documented and reviewed for accuracy and completeness.
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div>This report was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
            <div>Professional patient management and medical record keeping</div>
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
$filename = 'Patient_Details_' . $patient['patient_id'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>