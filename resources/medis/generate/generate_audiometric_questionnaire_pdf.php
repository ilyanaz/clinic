<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';
if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}
require_once __DIR__ . '/../setting/get_header_document.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . app_url("login"));
    exit();
}

// Get questionnaire ID from URL
$questionnaire_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$questionnaire_id) {
    header("Location: " . app_url("audiometric_list") . "?error=Questionnaire ID is required");
    exit();
}

// Fetch questionnaire data
try {
    $stmt = $clinic_pdo->prepare("SELECT * FROM audiometric_questionnaire WHERE id = ?");
    $stmt->execute([$questionnaire_id]);
    $questionnaire = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$questionnaire) {
        header("Location: " . app_url("audiometric_list") . "?error=Questionnaire not found");
        exit();
    }
    
    // Fetch patient data
    $patient_data = null;
    if ($questionnaire['patient_id']) {
        $patient_data = getClinicPatientById($questionnaire['patient_id']);
    }
} catch (Exception $e) {
    header("Location: " . app_url("audiometric_list") . "?error=" . urlencode($e->getMessage()));
    exit();
}

// Function to generate HTML for PDF
function generateQuestionnairePDFHTML($questionnaire, $patient_data) {
    // Get uploaded header document
    $headerDocumentPath = getHeaderDocument();
    $headerHtml = '';
    
    if ($headerDocumentPath && file_exists($headerDocumentPath)) {
        $fileExtension = strtolower(pathinfo($headerDocumentPath, PATHINFO_EXTENSION));
        $fileContent = file_get_contents($headerDocumentPath);
        $base64Content = base64_encode($fileContent);
        
        if ($fileExtension === 'pdf') {
            $headerHtml = '<div class="page-header">
                <div class="header-document">
                    <object data="data:application/pdf;base64,' . $base64Content . '" type="application/pdf" width="100%" height="150px">
                        <p>Header document could not be displayed</p>
                    </object>
                </div>
            </div>';
        } elseif (in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
            $headerHtml = '<div class="page-header">
                <div class="header-document">
                    <img src="data:image/' . $fileExtension . ';base64,' . $base64Content . '" alt="Header Document" style="width: 100%; max-height: 150px; object-fit: contain;">
                </div>
            </div>';
        }
    }

    // Helper function to format question answers
    function formatAnswer($value) {
        if (empty($value)) return '-';
        return htmlspecialchars($value);
    }
    
    // Helper function to format YES/NO answers
    function formatYesNo($value) {
        if (empty($value)) return '-';
        return htmlspecialchars($value);
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Questionnaire Form for Audiometric Testing</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 8.5pt;
                line-height: 1.2;
                margin: 0;
                padding: 0;
                color: #000;
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
                max-height: 120px;
                object-fit: contain;
                display: block;
                margin: 0;
                padding: 0;
            }
            
            .content-wrapper {
                padding: 8px 12px;
            }
            
            .report-title {
                text-align: center;
                font-size: 12pt;
                font-weight: bold;
                margin: 5px 0 10px 0;
                text-transform: uppercase;
                color: #000;
            }
            
            .section {
                margin-bottom: 8px;
                page-break-inside: avoid;
            }
            
            .section-title {
                font-size: 9pt;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 5px;
                padding-bottom: 2px;
                border-bottom: 1px solid #ccc;
            }
            
            .info-grid {
                display: table;
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 5px;
            }
            
            .info-row {
                display: table-row;
            }
            
            .info-label {
                display: table-cell;
                font-weight: bold;
                font-size: 8pt;
                padding: 2px 6px 2px 0;
                width: 35%;
                vertical-align: top;
            }
            
            .info-value {
                display: table-cell;
                font-size: 8pt;
                padding: 2px 0;
                vertical-align: top;
            }
            
            .question-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 5px;
                font-size: 7.5pt;
            }
            
            .question-table td {
                padding: 3px 5px;
                border: 1px solid #ddd;
                vertical-align: top;
            }
            
            .question-table .question-label {
                font-weight: bold;
                width: 70%;
                background: #f5f5f5;
            }
            
            .question-table .question-answer {
                width: 30%;
                text-align: center;
            }
            
            .footer {
                margin-top: 10px;
                text-align: center;
                font-size: 7.5pt;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 5px;
            }
            
            @page {
                margin: 0.6cm;
                size: A4;
            }
        </style>
    </head>
    <body>
        ' . $headerHtml . '
        <div class="content-wrapper">
            <div class="report-title">Questionnaire Form for Audiometric Testing</div>
            
            <!-- Patient Information -->
            <div class="section">
                <div class="section-title">Personal Information</div>
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Name of Person examined:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['patient_name']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Age:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['age']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IC/Passport No:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['ic_passport_no']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Gender:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['gender']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Test Date:</div>
                        <div class="info-value">' . ($questionnaire['test_date'] ? date('d/m/Y', strtotime($questionnaire['test_date'])) : '-') . '</div>
                    </div>
                </div>
            </div>
            
            <!-- Employment Information -->
            <div class="section">
                <div class="section-title">Employment Information</div>
                <div class="info-grid">
                    <div class="info-row">
                        <div class="info-label">Name & Address of Employer:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['company']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Department:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['department']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Job Title:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['job']) . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Years of Service:</div>
                        <div class="info-value">' . formatAnswer($questionnaire['years_of_service']) . '</div>
                    </div>
                </div>
            </div>
            
            <!-- Questionnaire Questions -->
            <div class="section">
                <div class="section-title">Questionnaire</div>
                <table class="question-table">
                    <tr>
                        <td class="question-label">1. Were you exposed to loud noise within 14 hours prior to today\'s test?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q1_noise_14hours'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">2. Have you suffered any illness that has affected your hearing?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q2_illness_hearing'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">3. Have you ever had an ear operation or any other major operation that affected your hearing?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q3_ear_operation'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">4. Have you ever taken any medication (tablets or injection) that affected your hearing?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q4_medication_hearing'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">5. Have you been exposed to loud noise (e.g.: chainsaw, firecrackers, explosion, gunfire, motorcycles?)?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q5_exposed_loud_noise'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">6. Any family history of hearing loss/disorders?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q6_family_hearing_loss'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">7. Do you attend night clubs\'/pubs/ discotheques or pop/rock concerts?</td>
                        <td class="question-answer">' . formatAnswer($questionnaire['q7_night_clubs'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">8. Do you use a personal stereo?</td>
                        <td class="question-answer">' . formatAnswer($questionnaire['q8_personal_stereo'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">9. Do you play loud music instruments?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q9_loud_music_instruments'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">10. Have you worked in noisy jobs in the past?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q10_noisy_jobs_past'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">11. Were you wearing personal hearing protectors at that time?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q11_hearing_protectors'] ?? '') . '</td>
                    </tr>
                    <tr>
                        <td class="question-label">12. Have you had an audiometric test before?</td>
                        <td class="question-answer">' . formatYesNo($questionnaire['q12_audiometric_test_before'] ?? '') . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div>This report was generated on ' . date('d F Y') . ' at ' . date('H:i:s') . '</div>
                <div>Professional audiometric testing and occupational health monitoring</div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Generate PDF content
$html = generateQuestionnairePDFHTML($questionnaire, $patient_data);

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
$filename = 'Audiometric_Questionnaire_' . ($questionnaire['patient_name'] ? str_replace(' ', '_', $questionnaire['patient_name']) : 'Patient') . '_' . date('Y-m-d') . '.pdf';

// Output the generated PDF
$dompdf->stream($filename, [
    'Attachment' => 0  // 0 = inline display, 1 = download
]);
?>
