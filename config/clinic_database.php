<?php
/**
 * Clinic Database Configuration
 * 
 * This connects the system to the medical database (medical.sql)
 * 
 * For XAMPP: Default MySQL root password is usually empty (blank)
 * Update CLINIC_DB_PASS if you've set a different password
 */

/**
 * Read environment variables safely in both Laravel and plain PHP contexts.
 */
function clinic_env(string $key, $default = null) {
    if (function_exists('env')) {
        $value = env($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

/**
 * Import SQL dump if the schema has not been initialized yet.
 */
function importClinicSqlDump(PDO $pdo, string $dumpPath): void {
    if (!is_file($dumpPath) || !is_readable($dumpPath)) {
        return;
    }

    $sql = file_get_contents($dumpPath);
    if ($sql === false || trim($sql) === '') {
        return;
    }

    // Remove UTF-8 BOM if present.
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

    // Split statements on semicolon followed by line break.
    $statements = preg_split('/;\s*[\r\n]+/', $sql);
    if (!is_array($statements)) {
        return;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        // Skip SQL comments that are split as standalone chunks.
        if (preg_match('/^(--|\/\*|\*|#)/', $statement) === 1) {
            continue;
        }

        $pdo->exec($statement);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

// Database connection settings
// Connected to the medical database (medical.sql)
define('CLINIC_DB_HOST', clinic_env('CLINIC_DB_HOST', clinic_env('DB_HOST', 'localhost')));
define('CLINIC_DB_NAME', clinic_env('CLINIC_DB_NAME', clinic_env('DB_DATABASE', 'medical'))); // Database name (matches medical.sql)
define('CLINIC_DB_USER', clinic_env('CLINIC_DB_USER', clinic_env('DB_USERNAME', 'root')));
define('CLINIC_DB_PASS', clinic_env('CLINIC_DB_PASS', clinic_env('DB_PASSWORD', ''))); // XAMPP default: empty password

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

    // Auto-import the SQL dump on fresh setups when the core table is missing.
    $usersTableExists = false;
    $tableCheckStmt = $clinic_pdo->query("SHOW TABLES LIKE 'users'");
    if ($tableCheckStmt !== false) {
        $usersTableExists = (bool) $tableCheckStmt->fetchColumn();
    }

    if (!$usersTableExists) {
        importClinicSqlDump($clinic_pdo, base_path('database/medical.sql'));
    }

    // Set in global scope - $GLOBALS is the global namespace
    $GLOBALS['clinic_pdo'] = $clinic_pdo;

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

// Global clinic database connection variable is already set above
?>


