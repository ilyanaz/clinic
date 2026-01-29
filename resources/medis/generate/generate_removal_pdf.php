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

// Get patient_id from URL
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    header("Location: " . app_url("medical_removal_protection.php"));
    exit();
}

// Initialize variables
$patient_data = null;
$removal_data = null;

// Get patient data
try {
    $patient_data = getClinicPatientById($patient_id);
    
    // Check if patient was found and has no error
    if (!$patient_data || isset($patient_data['error']) || empty($patient_data['id'])) {
        $_SESSION['error_message'] = isset($patient_data['error']) ? $patient_data['error'] : "Patient not found";
        $redirect_url = "/medical_removal_protection.php";
        if ($patient_id) {
            $redirect_url .= "?patient_id=" . urlencode($patient_id);
        }
        header("Location: " . $redirect_url);
        exit();
    }
    
    // Get the latest medical removal protection data for this patient
    // Try by patient_id first, then fallback to patient_name
    $stmt = $clinic_pdo->prepare("
        SELECT * FROM medical_removal_protection 
        WHERE patient_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$patient_id]);
    $removal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found by patient_id, try by patient_name as fallback
    if (!$removal_data) {
        $stmt = $clinic_pdo->prepare("
            SELECT * FROM medical_removal_protection 
            WHERE patient_name = ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_data['first_name'] . ' ' . $patient_data['last_name']]);
        $removal_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    error_log("Removal PDF Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading patient data: " . $e->getMessage();
    $redirect_url = "/medical_removal_protection.php";
    if ($patient_id) {
        $redirect_url .= "?patient_id=" . urlencode($patient_id);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Get doctor's saved signature (if any)
$doctor_signature_html = '';
try {
    $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'user_signatures'")->fetch();
    if ($tableCheck && isset($_SESSION['user_id'])) {
        $stmt = $clinic_pdo->prepare("SELECT file_path FROM user_signatures WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $signature = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($signature && !empty($signature['file_path']) && file_exists($signature['file_path'])) {
            // Check if GD extension is available for image rendering
            if (extension_loaded('gd')) {
                $imageData = base64_encode(file_get_contents($signature['file_path']));
                $fileExtension = strtolower(pathinfo($signature['file_path'], PATHINFO_EXTENSION));
                $mimeType = ($fileExtension === 'png') ? 'image/png' : 'image/jpeg';
                $doctor_signature_html = '<img src="data:' . $mimeType . ';base64,' . $imageData . '" alt="Doctor Signature" style="max-height:80px; max-width:200px;">';
            } else {
                $doctor_signature_html = '<div style="font-style: italic; color: #666;">Signature stored, please enable PHP GD extension to render image.</div>';
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting doctor signature for removal PDF: " . $e->getMessage());
}

// Fetch logged-in user's medical staff information for OHD section
$ohd_data = getLoggedInUserMedicalStaffInfo();

// Generate PDF content
$html = generateRemovalPDFHTML($patient_data, $removal_data, $doctor_signature_html, $ohd_data);

// Function to generate HTML for PDF
function generateRemovalPDFHTML($patient_data, $removal_data, $doctor_signature_html = '', $ohd_data = null) {
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
        <title>Medical Removal Protection Report</title>
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
            .clinic-name {
                font-size: 18pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 0;
            }
            .report-title {
                font-size: 14pt;
                font-weight: bold;
                color: #000;
                margin: 0 0 8px 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
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
            
            .form-section {
                margin: 15px 0;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .form-label {
                font-weight: bold;
                color: #000;
                font-size: 9pt;
                margin-bottom: 5px;
                display: block;
            }
            
            .form-control-plaintext {
                padding: 4px 0;
                font-size: 9pt;
                min-height: 18px;
                display: flex;
                align-items: center;
                border-bottom: 1px solid #ccc;
                color: #333;
            }
            
            .checkbox-group {
                display: flex;
                flex-wrap: wrap;
                gap: 1rem;
                margin-top: 5px;
            }
            
            .checkbox-item {
                display: flex;
                align-items: center;
                gap: 0.3rem;
                font-size: 9pt;
            }
            
            .signature-section {
                display: flex;
                justify-content: space-between;
                margin-top: 15px;
                gap: 2rem;
            }
            
            .signature-block {
                flex: 1;
            }
            
            .signature-line {
                border-bottom: 2px solid #dee2e6;
                height: 30px;
                margin: 10px 0;
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
            
            .note-section {
                margin: 20px 0;
                font-size: 9pt;
                color: #333;
                line-height: 1.5;
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
            <div style="text-align: right; font-size: 10pt; color: #666; margin-bottom: 20px;">USECHH 5i</div>
            <div class="legal-ref">Occupational Safety and Health Act 1994</div>
            <div class="legal-ref">(Act 514)</div>
            <div class="legal-ref">Use and Standard of Exposure of Chemicals Hazardous to Health Regulations 2000</div>
            <div class="report-title">MEDICAL REMOVAL PROTECTION</div>
        </div>';
    
    if ($removal_data) {
        $html .= '
        <!-- Medical Removal Protection Type -->
        <div class="section">
            <div class="section-title">Medical Removal Protection Type</div>
            <div class="form-section">
                <div class="checkbox-group">
                    <div class="checkbox-item">' . ($removal_data['removal_type'] == 'Temporary' ? '✓' : '□') . ' Temporary</div>
                    <div class="checkbox-item">' . ($removal_data['removal_type'] == 'Permanent' ? '✓' : '□') . ' Permanent</div>
                </div>
            </div>
        </div>
        
        <!-- Worker Information -->
        <div class="section">
            <div class="section-title">Worker Information</div>
            <div class="form-section">
                <table class="grid-table">
                    <tr>
                        <td>
                            <div class="form-label">1. Name of Worker:</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['patient_name']) ? htmlspecialchars($removal_data['patient_name']) : htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name'])) . '</div>
                        </td>
                        <td>
                            <div class="form-label">2. NRIC/Passport No:</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['nric_passport']) ? htmlspecialchars($removal_data['nric_passport']) : htmlspecialchars($patient_data['NRIC'] ?? $patient_data['passport_no'] ?? 'Not specified')) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="form-label">3. Date of Birth:</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['date_of_birth']) ? date('d F Y', strtotime($removal_data['date_of_birth'])) : (!empty($patient_data['date_of_birth']) ? date('d F Y', strtotime($patient_data['date_of_birth'])) : 'Not specified')) . '</div>
                        </td>
                        <td>
                            <div class="form-label">4. Sex:</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['sex']) ? htmlspecialchars($removal_data['sex']) : htmlspecialchars($patient_data['gender'] ?? 'Not specified')) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="form-label">5. Name and Address of Workplace:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($removal_data['workplace_name_address'] ?? $patient_data['company_name'] ?? 'Not specified') . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="form-label">6. Date of starting employment:</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['employment_start_date']) ? date('d F Y', strtotime($removal_data['employment_start_date'])) : 'Not specified') . '</div>
                        </td>
                        <td>
                            <div class="form-label">Duration of Employment (Years):</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['employment_duration']) ? htmlspecialchars($removal_data['employment_duration']) : 'Not specified') . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="form-label">7. Health Hazard Present (Use one form for one chemical):</div>
                            <div class="form-control-plaintext">' . (!empty($removal_data['health_hazard_present']) ? htmlspecialchars($removal_data['health_hazard_present']) : 'Not specified') . '</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 2 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- Certification Statement -->
        <div class="section">
            <div class="section-title">Certification Statement</div>
            <div class="form-section">
                <div class="form-group">
                    <div class="form-control-plaintext">I certify that the above named person examined by me on <strong>' . (!empty($removal_data['examination_date']) ? date('d/m/Y', strtotime($removal_data['examination_date'])) : 'Not specified') . '</strong> should not continue to work as <strong>' . (!empty($removal_data['designated_work']) ? htmlspecialchars($removal_data['designated_work']) : 'Not specified') . '</strong> in <strong>' . (!empty($removal_data['place_of_work']) ? htmlspecialchars($removal_data['place_of_work']) : '') . (!empty($removal_data['department_section']) ? ' - ' . htmlspecialchars($removal_data['department_section']) : '') . '</strong> department/section for <strong>' . (!empty($removal_data['removal_months']) ? htmlspecialchars($removal_data['removal_months']) : 'Not specified') . '</strong> months, subject to a review on <strong>' . (!empty($removal_data['review_date']) ? date('d/m/Y', strtotime($removal_data['review_date'])) : 'Not specified') . '</strong>.</div>
                </div>
            </div>
        </div>
        
        <!-- Alternative Work Recommendation -->
        <div class="section">
            <div class="section-title">Alternative Work Recommendation</div>
            <div class="form-section">
                <div class="form-group">
                    <div class="form-control-plaintext">In the meantime, he should be given alternative work in ' . (!empty($removal_data['alternative_work_department']) ? '<strong>' . htmlspecialchars($removal_data['alternative_work_department']) . '</strong>' : 'another department/section') . ' which does not expose him to <strong>' . (!empty($removal_data['chemical_name']) ? htmlspecialchars($removal_data['chemical_name']) : 'the chemical') . '</strong>.</div>
                </div>
            </div>
        </div>
        
        <!-- Reasons for Recommendations -->
        <div class="section">
            <div class="section-title">Reasons for Recommendations</div>
            <div class="form-section">
                <div class="form-group">
                    <div class="form-control-plaintext">The reasons for my recommendations are as follows (Please √):</div>
                </div>
                <table class="grid-table">
                    <tr>
                        <td>
                            <div class="checkbox-item">' . ($removal_data['pregnancy'] ? '✓' : '□') . ' Pregnancy</div>
                            <div class="checkbox-item">' . ($removal_data['breastfeeding'] ? '✓' : '□') . ' Breastfeeding</div>
                        </td>
                        <td>
                            <div class="checkbox-item">' . ($removal_data['abnormal_bem_result'] ? '✓' : '□') . ' Abnormal BM/BEM result</div>
                            <div class="checkbox-item">' . ($removal_data['adverse_health_effects'] ? '✓' : '□') . ' Adverse health effects based on clinical findings</div>
                            <div class="checkbox-item">' . ($removal_data['target_organ_abnormality'] ? '✓' : '□') . ' Target organ function test abnormality</div>
                        </td>
                    </tr>
                </table>
                <div class="form-group">
                    <div class="form-label">Specify others:</div>
                    <div class="form-control-plaintext">' . htmlspecialchars($removal_data['other_reasons'] ?? '') . '</div>
                </div>
            </div>
        </div>
        </div>
        
        <!-- Page Break -->
        <div class="page-break"></div>
        
        <!-- Header Photo for Page 3 -->
        <div class="page-header">
            ' . $headerHtml . '
        </div>
        <div class="content-wrapper">
        <!-- OHD Information -->
        <div class="section">
            <div class="section-title">OHD (Occupational Health Doctor) Information</div>
            <div class="form-section">';
    
    // Use logged-in user's medical staff information for OHD section
    $ohd_name = '';
    $ohd_email = '';
    $ohd_address = '5405-B, Jalan Kuala Krai, Bandar Kota Bharu, Kota Bharu, Kelantan, Malaysia, 15150';
    $ohd_hp = '';
    $ohd_tel = '097444451';
    $ohd_fax = '';
    
    if ($ohd_data) {
        // Use logged-in user's data
        $ohd_name = $ohd_data['first_name'] . ' ' . $ohd_data['last_name'];
        $ohd_email = $ohd_data['email'] ?? '';
        $ohd_hp = $ohd_data['phone'] ?? $ohd_data['telephone_no'] ?? '';
        $ohd_tel = $ohd_data['telephone_no'] ?? '097444451';
        
        // Build address from available fields
        $ohd_address_parts = [];
        if (!empty($ohd_data['address'])) $ohd_address_parts[] = $ohd_data['address'];
        if (!empty($ohd_data['state'])) $ohd_address_parts[] = $ohd_data['state'];
        if (!empty($ohd_data['district'])) $ohd_address_parts[] = $ohd_data['district'];
        if (!empty($ohd_data['postcode'])) $ohd_address_parts[] = $ohd_data['postcode'];
        $built_address = implode(', ', $ohd_address_parts);
        if (!empty($built_address)) {
            $ohd_address = $built_address;
        }
    } else {
        // Fallback to saved data if logged-in user data not available
        if ($removal_data) {
            $ohd_name = $removal_data['ohd_name'] ?? '';
            $ohd_email = $removal_data['ohd_email'] ?? '';
            $ohd_address = $removal_data['ohd_address'] ?? $ohd_address;
            $ohd_hp = $removal_data['ohd_hp'] ?? '';
            $ohd_tel = $removal_data['ohd_tel'] ?? $ohd_tel;
            $ohd_fax = $removal_data['ohd_fax'] ?? '';
        }
    }
    
    $html .= '
                <table class="grid-table">
                    <tr>
                        <td>
                            <div class="form-label">Name of OHD:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_name) . '</div>
                        </td>
                        <td>
                            <div class="form-label">Email Address:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_email) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="form-label">Address of Practice:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_address) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="form-label">H/P:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_hp) . '</div>
                        </td>
                        <td>
                            <div class="form-label">Tel:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_tel) . '</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="form-label">Fax:</div>
                            <div class="form-control-plaintext">' . htmlspecialchars($ohd_fax) . '</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Signature and Date -->
        <div class="section">
            <div class="form-section">
                <div class="signature-section">
                    <div class="signature-block">
                        <div class="form-label">OHD signature</div>
                        ' . (!empty($doctor_signature_html) ? $doctor_signature_html : '<div class="signature-line"></div>') . '
                    </div>
                    <div class="signature-block">
                        <div class="form-label">Date</div>
                        <div class="form-control-plaintext">' . date('d F Y', strtotime($removal_data['ohd_signature_date'])) . '</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Note/Disclaimer -->
        <div class="section">
            <div class="note-section">
                <p><strong>Note:</strong> This certificate should be completed in triplicate and the original copy forwarded to the director General. department of Occupational Safety and Health. Putrajaya and must include the actual results of the relevant examination/tests. The quantitive results (e.g. blood lead) the exact Diagrams and measurements units must be clearly stated. Also include a copy of qualitative results (e.g Chest X-ray). Incomplete form will not be accepted.</p>
            </div>
        </div>';
    } else {
        $html .= '
        <!-- No Data Available -->
        <div class="section">
            <div class="assessment-box">
                <div class="assessment-title">No Medical Removal Protection Data</div>
                <div class="assessment-content">
                    No medical removal protection data has been submitted for this patient yet. 
                    Please complete the medical removal protection form to generate a proper report.
                </div>
            </div>
        </div>';
    }
    
    $html .= '
        <!-- Assessment Summary -->
        <div class="section">
            <div class="assessment-box">
                <div class="assessment-title">Medical Removal Protection Summary</div>
                <div class="assessment-content">
                    This medical removal protection report has been generated for ' . htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']) . '. 
                    ' . ($removal_data ? 'The report contains all relevant medical removal protection information and recommendations.' : 'No medical removal protection data is available for this patient.') . '
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
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Generate filename
$filename = 'Medical_Removal_Protection_' . $patient_data['first_name'] . '_' . $patient_data['last_name'] . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>
