<?php
/**
 * Clinic Database Configuration
 * 
 * This connects the system to the medical database (medical.sql)
 * 
 * For XAMPP: Default MySQL root password is usually empty (blank)
 * Update CLINIC_DB_PASS if you've set a different password
 */

// Database connection settings
// Connected to the medical database (medical.sql)
define('CLINIC_DB_HOST', 'localhost');
define('CLINIC_DB_NAME', 'medical');  // Database name (matches medical.sql)
define('CLINIC_DB_USER', 'root');
define('CLINIC_DB_PASS', '');  // XAMPP default: empty password

require_once __DIR__ . '/clinic_schema_updates.php';

// Create clinic database connection
try {
    // First, try to connect to MySQL server (without database) to create it if needed
    $server_pdo = new PDO("mysql:host=" . CLINIC_DB_HOST . ";charset=utf8mb4", CLINIC_DB_USER, CLINIC_DB_PASS);
    $server_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $server_pdo->exec("CREATE DATABASE IF NOT EXISTS `" . CLINIC_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Now connect to the specific database
    $clinic_pdo = new PDO("mysql:host=" . CLINIC_DB_HOST . ";dbname=" . CLINIC_DB_NAME . ";charset=utf8mb4", CLINIC_DB_USER, CLINIC_DB_PASS);
    $clinic_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $clinic_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ensure the database schema matches what the application expects.
    ensureClinicSchema($clinic_pdo);
} catch(PDOException $e) {
    $error_msg = "Clinic database connection failed: " . $e->getMessage();
    
    // Provide helpful error messages for common XAMPP issues
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        $error_msg .= "\n\nCommon XAMPP Solutions:";
        $error_msg .= "\n- XAMPP default MySQL password is usually empty (blank)";
        $error_msg .= "\n- Update CLINIC_DB_PASS to '' (empty string) in this file if using default XAMPP settings";
        $error_msg .= "\n- Or set a password in XAMPP MySQL and update CLINIC_DB_PASS here";
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        $error_msg .= "\n\nSolution:";
        $error_msg .= "\n- Import medical.sql file to create the database structure";
        $error_msg .= "\n- Run import_medical_database.php to import";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'No connection') !== false) {
        $error_msg .= "\n\nSolution:";
        $error_msg .= "\n- Make sure MySQL is running in XAMPP Control Panel";
        $error_msg .= "\n- Check that MySQL service is started (green indicator)";
    }
    
    // In web context, show user-friendly error; in CLI, show full error
    if (php_sapi_name() !== 'cli') {
        die("<html><head><title>Database Connection Error</title></head><body style='font-family: Arial; padding: 20px;'><h2>Database Connection Error</h2><pre>" . htmlspecialchars($error_msg) . "</pre><p><a href='import_medical_database.php'>Import Database</a> | <a href='verify_database_connection.php'>Verify Connection</a> | <a href='connect_database.php'>Test Connection</a></p></body></html>");
    } else {
        die($error_msg . "\n");
    }
}

// Global clinic database connection variable
$GLOBALS['clinic_pdo'] = $clinic_pdo;
?>


