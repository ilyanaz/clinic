<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MedisController;

// Legacy PHP file routes - handle both GET and POST
$legacyFiles = [
    'login', 'logout', 'index', 'patients', 'patient_list', 'patient_form', 
    'patient_view', 'patient_edit', 'company', 'company_form', 'medical', 
    'medical_list', 'medical_report', 'ms_report', 'employee_report', 
    'abnormal_workers_report', 'reports', 'usechh_1', 'usechh1_view',
    'surveillance_list', 'surveillance_form', 'surveillance_edit',
    'audio', 'audiometric', 'audiometric_edit', 'audiometric_history', 'audiometric_list',
    'audiometric_questionnaire', 'audiometric_report', 'audiometric_summary',
    'audiometric_test', 'audiometric_view',
    'generate_patient_pdf', 'generate_certificate_pdf', 'generate_employee_pdf',
    'generate_msReport_pdf', 'generate_abnormal_pdf', 'generate_declaration_pdf',
    'generate_removal_pdf', 'generate_surveillance_pdf', 'generate_audiometric_questionnaire_pdf',
    'setting', 'profile',
    'upload_signature', 'upload_header_document', 'get_signature_history',
    'get_header_history', 'get_header_document', 'preview_header_document',
    'remove_signature', 'remove_specific_header', 'get_logo'
];

foreach ($legacyFiles as $file) {
    // Route with .php extension (for backward compatibility)
    Route::match(['get', 'post'], '/' . $file . '.php', function() use ($file) {
        $controller = new MedisController();
        return $controller->serve($file . '.php');
    });
    
    // Route without .php extension (clean URLs)
    Route::match(['get', 'post'], '/' . $file, function() use ($file) {
        $controller = new MedisController();
        return $controller->serve($file . '.php');
    });
}

// Original Laravel routes (keep for compatibility)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth.session'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
