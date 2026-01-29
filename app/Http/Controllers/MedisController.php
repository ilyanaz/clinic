<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class MedisController extends Controller
{
    /**
     * Serve PHP files from resources/medis/
     * Supports subdirectories: company/, patient/, medical/, generate/, report/, setting/
     */
    public function serve($path)
    {
        // Normalize path - remove leading slash and ensure .php extension
        $path = ltrim($path, '/');
        if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            $path .= '.php';
        }
        
        // Security: Prevent directory traversal
        if (strpos($path, '..') !== false) {
            abort(404);
        }
        
        // Try different possible locations
        $possiblePaths = [
            resource_path('medis/' . $path), // Direct path (for login.php, logout.php, index.php)
            resource_path('medis/company/' . basename($path)), // company/
            resource_path('medis/patient/' . basename($path)), // patient/
            resource_path('medis/medical/' . basename($path)), // medical/
            resource_path('medis/generate/' . basename($path)), // generate/
            resource_path('medis/report/' . basename($path)), // report/
            resource_path('medis/setting/' . basename($path)), // setting/
        ];
        
        $medisPath = null;
        foreach ($possiblePaths as $possiblePath) {
            if (File::exists($possiblePath) && pathinfo($possiblePath, PATHINFO_EXTENSION) === 'php') {
                $medisPath = $possiblePath;
                break;
            }
        }
        
        if (!$medisPath) {
            abort(404);
        }
        
        // Load database configuration first (creates $clinic_pdo)
        $configPath = base_path('config/clinic_database.php');
        if (file_exists($configPath)) {
            require_once $configPath;
        }
        
        // Make sure $clinic_pdo is available globally
        if (isset($GLOBALS['clinic_pdo'])) {
            global $clinic_pdo;
            $clinic_pdo = $GLOBALS['clinic_pdo'];
        }
        
        // Load service functions after database connection is established
        $services = [
            base_path('app/Services/clinic_functions.php'),
            base_path('app/Services/company_functions.php'),
        ];
        
        foreach ($services as $service) {
            if (file_exists($service)) {
                require_once $service;
            }
        }
        
        // Save original working directory and change to public for context
        $originalDir = getcwd();
        $publicPath = base_path('public');
        chdir($publicPath);
        
        // Save original $_SERVER values
        $originalScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        $originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $originalDocumentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        
        // Set up $_SERVER variables for the medis file
        // Calculate the base path from the request URI
        $requestUri = request()->getRequestUri();
        $parsedUri = parse_url($requestUri);
        $pathInfo = $parsedUri['path'] ?? '/';
        
        // Remove .php extension from path for clean URLs
        $cleanPath = preg_replace('/\.php$/', '', $pathInfo);
        
        // Extract base path (e.g., /clinic/public from /clinic/public/company)
        $basePath = dirname($cleanPath);
        $basePath = ($basePath === '/' || $basePath === '\\' || $basePath === '.') ? '' : $basePath;
        
        // Set SCRIPT_NAME to match the clean path (without .php)
        $scriptName = $basePath . '/' . basename($cleanPath);
        $_SERVER['SCRIPT_NAME'] = $scriptName;
        $_SERVER['REQUEST_URI'] = $requestUri;
        $_SERVER['DOCUMENT_ROOT'] = base_path('public');
        
        // Start output buffering
        ob_start();
        
        try {
            // Make sure $clinic_pdo is available in global scope for included files
            if (isset($GLOBALS['clinic_pdo'])) {
                $GLOBALS['clinic_pdo'] = $GLOBALS['clinic_pdo']; // Ensure it's set
            }
            
            // Include the medis PHP file
            require $medisPath;
            
            // Get the output
            $output = ob_get_clean();
            
            // Restore original values
            if ($originalScriptName !== null) {
                $_SERVER['SCRIPT_NAME'] = $originalScriptName;
            }
            if ($originalRequestUri !== null) {
                $_SERVER['REQUEST_URI'] = $originalRequestUri;
            }
            if ($originalDocumentRoot !== null) {
                $_SERVER['DOCUMENT_ROOT'] = $originalDocumentRoot;
            }
            
            // Restore directory
            chdir($originalDir);
            
            return response($output);
        } catch (\Exception $e) {
            ob_end_clean();
            
            // Restore original values
            if ($originalScriptName !== null) {
                $_SERVER['SCRIPT_NAME'] = $originalScriptName;
            }
            if ($originalRequestUri !== null) {
                $_SERVER['REQUEST_URI'] = $originalRequestUri;
            }
            if ($originalDocumentRoot !== null) {
                $_SERVER['DOCUMENT_ROOT'] = $originalDocumentRoot;
            }
            
            chdir($originalDir);
            
            // Log error and show user-friendly message
            \Log::error('Medis file error: ' . $e->getMessage(), [
                'file' => $path,
                'trace' => $e->getTraceAsString()
            ]);
            
            abort(500, 'Error loading page: ' . $e->getMessage());
        }
    }
}
