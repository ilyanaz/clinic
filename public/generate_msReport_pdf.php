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

// Get company_id from URL parameter
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
$company_name = isset($_GET['company_name']) ? $_GET['company_name'] : null;

// Initialize variables
$company_data = null;
$all_employees = [];
$total_surveillance_data = [];
$company_chemical_hazards = [];
$saved_report_data = null;

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
                    
                    // Log the retrieved data for debugging
                    if ($saved_report_data) {
                        error_log("Retrieved MS report data for company_id: " . $company_id . " - Work unit: " . ($saved_report_data['work_unit_name'] ?? 'N/A'));
                    } else {
                        error_log("No saved MS report data found for company_id: " . $company_id);
                    }
                } catch (Exception $e) {
                    error_log("Error fetching saved report data: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching company data: " . $e->getMessage());
    }
}

if (!$company_data) {
    header("Location: /ms_report.php?error=" . urlencode("Company not found"));
    exit();
}

// Generate PDF content
$html = generateMSReportPDFHTML($company_data, $all_employees, $total_surveillance_data, $company_chemical_hazards, $saved_report_data);

// Function to generate HTML for PDF
function generateMSReportPDFHTML($company_data, $all_employees, $total_surveillance_data, $company_chemical_hazards, $saved_report_data = null) {
    $examined_workers = isset($examined_patient_ids) ? count($examined_patient_ids) : 0;
    
    
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
        <title>Summary Report for Medical Surveillance</title>
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
            
            .workplace-stats table,
            .results-table,
            .decision-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                border: 1px solid #000;
            }
            
            .workplace-stats table td,
            .results-table td,
            .decision-table td {
                border: 1px solid #000;
                padding: 8px 12px;
                vertical-align: middle;
                font-size: 9pt;
            }
            
            .workplace-stats table td:first-child,
            .results-table td:first-child,
            .decision-table td:first-child {
                font-weight: bold;
                background: #f5f5f5;
            }
            
            .results-table th,
            .decision-table th {
                background: #000 !important;
                color: white !important;
                font-weight: 600;
                text-align: center;
                padding: 8px 12px;
                border: 1px solid #000;
                font-size: 9pt;
            }
            
            .results-table td,
            .decision-table td {
                text-align: center;
            }
            
            .indication-options {
                margin: 15px 0;
            }
            
            .indication-options .form-check {
                margin-bottom: 8px;
            }
            
            .declaration-statement {
                background: #f8f9fa;
                padding: 15px;
                border-left: 3px solid #28a745;
                border-radius: 4px;
                margin: 20px 0;
            }
            
            .declaration-statement p {
                margin: 0;
                font-weight: bold;
                font-size: 11pt;
            }
            
            .doctor-info {
                margin: 20px 0;
            }
            
            .doctor-info .form-label {
                font-weight: bold;
                color: #000;
                font-size: 9pt;
                margin-bottom: 5px;
            }
            
            .doctor-info .form-control-plaintext {
                padding: 5px 0;
                font-size: 9pt;
                min-height: 20px;
                display: flex;
                align-items: center;
                border-bottom: 1px solid #ccc;
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
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 4</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">SUMMARY REPORT FOR MEDICAL SURVEILLANCE</div>
        </div>
        
        <!-- Workplace Information -->
        <div class="section">
            <div class="section-title">Workplace Information</div>
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Name of Workplace:</span><br>
                        <span class="grid-value">' . htmlspecialchars($company_data['company_name'] ?? 'Not specified') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">MyKKP Registration No.:</span><br>
                        <span class="grid-value">' . htmlspecialchars($company_data['mykpp_registration_no'] ?? 'Not specified') . '</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Address of Workplace:</span><br>
                        <span class="grid-value">' . htmlspecialchars($company_data['address'] ?? 'Not specified') . '</span>
                    </td>
                </tr>
            </table>
            
            <!-- Workplace Statistics Table -->
            <div class="workplace-stats">
                <table>
                    <tbody>
                        <tr>
                            <td>Total number of workers in the workplace</td>
                            <td class="text-center">' . count($all_employees) . '</td>
                        </tr>
                        <tr>
                            <td>Name of the work unit where workers are in</td>
                            <td class="text-center">' . htmlspecialchars($saved_report_data['work_unit_name'] ?? implode(', ', array_unique(array_filter(array_column($all_employees, 'job_title')))) ?: 'Not specified') . '</td>
                        </tr>
                        <tr>
                            <td>Total number of exposed workers in the work unit</td>
                            <td class="text-center">' . count($all_employees) . '</td>
                        </tr>
                        <tr>
                            <td>Total number of workers examined</td>
                            <td class="text-center">' . (isset($examined_patient_ids) ? count($examined_patient_ids) : 0) . '</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Chemical and CHRA Information -->
        <div class="section">
            <div class="section-title">Chemical and CHRA Information</div>
            <table class="grid-table">
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Individual Chemical: (Use ONE USECHH 4 form for ONE chemical only)</span><br>
                        <span class="grid-value">' . htmlspecialchars(implode(', ', $company_chemical_hazards) ?: 'Not specified') . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="grid-label">Date of CHRA conducted:</span><br>
                        <span class="grid-value">' . htmlspecialchars($saved_report_data['chra_date'] ?? 'Not specified') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">CHRA report no.:</span><br>
                        <span class="grid-value">' . htmlspecialchars($saved_report_data['chra_report_no'] ?? 'Not specified') . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Indication for medical surveillance -->
        <div class="section">
            <div class="section-title">Indication for medical surveillance based on CHRA report</div>
            <div class="indication-options">';
            
            // Parse saved indication data
            $saved_indications = [];
            $indication_data = [];
            if ($saved_report_data && !empty($saved_report_data['indication_data'])) {
                $indication_data = json_decode($saved_report_data['indication_data'], true);
                $saved_indications = $indication_data['indications'] ?? [];
                
            }
            
            // Ensure we have an array to work with
            if (!is_array($saved_indications)) {
                $saved_indications = [];
            }
            
            
            $html .= '
                <div class="form-check">
                    <span style="font-weight: bold;">' . (in_array('significant_exposure', $saved_indications) ? '✓' : '☐') . '</span> Significant personal exposure (≥ 50% PEL)
                </div>
                <div class="form-check">
                    <span style="font-weight: bold;">' . (in_array('others', $saved_indications) ? '✓' : '☐') . '</span> Others (Please provide details)';
            
            if (in_array('others', $saved_indications) && !empty($indication_data['others_details'])) {
                $html .= '<br><span style="margin-left: 20px; font-style: italic;">Details: ' . htmlspecialchars($indication_data['others_details']) . '</span>';
            }
            
            $html .= '
                </div>
                <div class="form-check">
                    <span style="font-weight: bold;">' . (in_array('health_effects', $saved_indications) ? '✓' : '☐') . '</span> Reported health effects
                </div>
                <div class="form-check">
                    <span style="font-weight: bold;">' . (in_array('skin_absorption', $saved_indications) ? '✓' : '☐') . '</span> Skin absorption
                </div>
            </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 2 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Medical Surveillance Results Table -->
        <div class="section">
            <div class="section-title">MEDICAL SURVEILLANCE RESULTS</div>
            <table class="results-table">
                <thead>
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
                        <td style="text-align: left; font-weight: bold;">History of health effects due to chemical exposure</td>
                        <td>' . (isset($examined_patient_ids) ? count($examined_patient_ids) : 0) . '</td>
                        <td>0</td>
                        <td>0</td>
                        <td>Not applicable</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Clinical findings</td>
                        <td>' . (isset($examined_patient_ids) ? count($examined_patient_ids) : 0) . '</td>
                        <td>0</td>
                        <td>0</td>
                        <td>0</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Target organ function test(s). Please specify:</td>
                        <td>' . (isset($examined_patient_ids) ? count($examined_patient_ids) : 0) . '</td>
                        <td>0</td>
                        <td>0</td>
                        <td>0</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">BEI determinant (BM/BEM). Please specify determinant:</td>
                        <td>' . (isset($examined_patient_ids) ? count($examined_patient_ids) : 0) . '</td>
                        <td>0</td>
                        <td>0</td>
                        <td>0</td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 10px; font-size: 8pt; color: #666;">
                Continue in separate sheet if required. Please include details of abnormal examination/test results in USECHH 5ii form and Medical Removal Protection in USECHH 5i form.
            </div>
        </div>
        
        <!-- General Information -->
        <div class="section">
            <div class="section-title">General Information</div>
            <table class="grid-table">
                <tr>
                    <td>
                        <span class="grid-label">Total no. of employees recommended for MRP</span><br>
                        <span class="grid-value">' . htmlspecialchars($saved_report_data['mrp_employees'] ?? '0') . '</span>
                    </td>
                    <td>
                        <span class="grid-label">Name of Laboratory:</span><br>
                        <span class="grid-value">' . htmlspecialchars($saved_report_data['laboratory_name'] ?? 'Medical Surveillance Laboratory') . '</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <span class="grid-label">Recommendation:</span><br>
                        <span class="grid-value">' . htmlspecialchars($saved_report_data['recommendation'] ?? 'Continue regular medical surveillance as per schedule.') . '</span>
                    </td>
                </tr>
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
        <!-- Decision Table -->
        <div class="section">
            <div class="section-title">Decision</div>
            <table class="decision-table">
                <thead>
                    <tr>
                        <th>*</th>
                        <th>Decision</th>
                        <th>Justification of Decision</th>
                        <th>Date of implementation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: center;">
                            <span style="font-weight: bold;">' . (($saved_report_data['decision'] ?? 'continue') == 'continue' ? '✓' : '☐') . '</span>
                        </td>
                        <td style="text-align: left; font-weight: bold;">Continue MS</td>
                        <td style="text-align: left;">' . htmlspecialchars($saved_report_data['continue_justification'] ?? 'Regular surveillance required for chemical exposure') . '</td>
                        <td>' . htmlspecialchars($saved_report_data['continue_date'] ?? date('Y-m-d')) . '</td>
                    </tr>
                    <tr>
                        <td style="text-align: center;">
                            <span style="font-weight: bold;">' . (($saved_report_data['decision'] ?? '') == 'stop' ? '✓' : '☐') . '</span>
                        </td>
                        <td style="text-align: left; font-weight: bold;">Stop MS</td>
                        <td style="text-align: left; color: #666;">' . htmlspecialchars($saved_report_data['stop_justification'] ?? 'Not applicable') . '</td>
                        <td style="color: #666;">' . htmlspecialchars($saved_report_data['stop_date'] ?? '-') . '</td>
                    </tr>
                </tbody>
            </table>
            <div style="margin-top: 10px; font-size: 8pt; color: #666;">
                * Please ✓ where applicable
            </div>
        </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 4 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Declaration and Doctor Information -->
        <div class="section">
            <div class="declaration-statement">
                <p>I hereby declare that all particulars given in this report are accurate to the best of my knowledge.</p>
            </div>
            
            <div class="doctor-info">
                <div style="margin-bottom: 15px;">
                    <div class="form-label">Name of occupational Health Doctor:</div>
                    <div class="form-control-plaintext">
                        ' . htmlspecialchars($total_surveillance_data[0]['examiner_name'] ?? 'Dr. System Administrator') . '
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div class="form-label">OHD Registration No:</div>
                    <div class="form-control-plaintext">
                        ' . htmlspecialchars($total_surveillance_data[0]['dosh_reg_no'] ?? 'OHD-REG-2024-001') . '
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div class="form-label">Name of Practice & Address:</div>
                    <div class="form-control-plaintext">
                        ' . htmlspecialchars(($total_surveillance_data[0]['practice_name'] ?? 'Medical Surveillance System') . ', ' . ($total_surveillance_data[0]['practice_address'] ?? 'Occupational Health Clinic, Kuala Lumpur, Malaysia')) . '
                    </div>
                </div>
                
                <div style="display: flex; margin-bottom: 15px;">
                    <div style="flex: 1; margin-right: 20px;">
                        <div class="form-label">Tel No:</div>
                        <div class="form-control-plaintext">
                            ' . htmlspecialchars($total_surveillance_data[0]['tel_no'] ?? '03-1234-5678') . '
                        </div>
                    </div>
                    <div style="flex: 1; margin-right: 20px;">
                        <div class="form-label">HP no:</div>
                        <div class="form-control-plaintext">
                            ' . htmlspecialchars($total_surveillance_data[0]['hp_no'] ?? '012-345-6789') . '
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div class="form-label">Fax No:</div>
                        <div class="form-control-plaintext">
                            ' . htmlspecialchars($total_surveillance_data[0]['fax_no'] ?? '03-1234-5679') . '
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <div class="form-label">Email address:</div>
                    <div class="form-control-plaintext">
                        ' . htmlspecialchars($total_surveillance_data[0]['examiner_email'] ?? 'admin@medicalsurveillance.com') . '
                    </div>
                </div>
                
                <div style="display: flex; margin-top: 30px;">
                    <div style="flex: 1; margin-right: 20px;">
                        <div class="form-label">Signature:</div>
                        <div style="border-bottom: 1px solid #ccc; min-height: 30px; margin-top: 10px;">
                            <!-- Signature space -->
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div class="form-label">Date:</div>
                        <div style="border-bottom: 1px solid #ccc; min-height: 30px; margin-top: 10px; padding-top: 5px;">
                            ' . date('d/m/Y') . '
                        </div>
                    </div>
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
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Generate filename
$filename = 'MS_Summary_Report_' . $company_data['company_name'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>


