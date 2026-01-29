<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.session' => \App\Http\Middleware\AuthenticateMiddleware::class,
        ]);
        
        // Exclude all .php files from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'login.php',
            'logout.php',
            'index.php',
            'patients.php',
            'patient_list.php',
            'patient_form.php',
            'patient_view.php',
            'patient_edit.php',
            'company.php',
            'company_form.php',
            'medical.php',
            'medical_list.php',
            'medical_report.php',
            'ms_report.php',
            'employee_report.php',
            'abnormal_workers_report.php',
            'reports.php',
            'usechh_1.php',
            'usechh1_view.php',
            'surveillance_list.php',
            'surveillance_form.php',
            'surveillance_edit.php',
            'generate_patient_pdf.php',
            'generate_certificate_pdf.php',
            'generate_employee_pdf.php',
            'generate_msReport_pdf.php',
            'generate_abnormal_pdf.php',
            'generate_declaration_pdf.php',
            'generate_removal_pdf.php',
            'generate_surveillance_pdf.php',
            'setting.php',
            'profile.php',
            'upload_signature.php',
            'upload_header_document.php',
            'get_signature_history.php',
            'get_header_history.php',
            'get_header_document.php',
            'preview_header_document.php',
            'remove_signature.php',
            'remove_specific_header.php',
        ]);
    })
    ->withProviders([
        \App\Providers\HelperServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
