<?php
/**
 * Fix Admin Password Script
 * This script ensures the admin user has the correct password
 */

require_once __DIR__ . '/config/clinic_database.php';

echo "<h2>Fixing Admin Password</h2>";

try {
    // Check if admin user exists
    $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p>Found admin user (ID: {$admin['id']})</p>";
        echo "<p>Current password in database: " . htmlspecialchars($admin['password']) . "</p>";
        
        // Update password to plain text 'admin123'
        $stmt = $clinic_pdo->prepare("UPDATE users SET password = 'admin123' WHERE username = 'admin'");
        $stmt->execute();
        
        echo "<p style='color: green;'><strong>✓ Password updated to: admin123</strong></p>";
        
        // Verify the update
        $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
        $stmt->execute();
        $updated = $stmt->fetch();
        
        echo "<p>New password in database: " . htmlspecialchars($updated['password']) . "</p>";
        echo "<p style='color: green;'><strong>✓ Admin password fixed! You can now login with:</strong></p>";
        echo "<ul>";
        echo "<li>Username: <strong>admin</strong></li>";
        echo "<li>Password: <strong>admin123</strong></li>";
        echo "</ul>";
        
    } else {
        // Create admin user if it doesn't exist
        echo "<p>Admin user not found. Creating...</p>";
        $stmt = $clinic_pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@system.com', 'admin123', 'Admin', 'System', 'Administrator']);
        echo "<p style='color: green;'><strong>✓ Admin user created!</strong></p>";
    }
    
    // Also check and fix doctor user
    $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE username = 'doctor'");
    $stmt->execute();
    $doctor = $stmt->fetch();
    
    if ($doctor) {
        $stmt = $clinic_pdo->prepare("UPDATE users SET password = 'doctor123' WHERE username = 'doctor'");
        $stmt->execute();
        echo "<p style='color: green;'><strong>✓ Doctor password also fixed!</strong></p>";
    }
    
    echo "<hr>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error: " . htmlspecialchars($e->getMessage()) . "</strong></p>";
}
?>
