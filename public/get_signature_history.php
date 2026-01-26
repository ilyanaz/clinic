<?php
session_start();
require_once 'config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Check if table exists first
    $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'user_signatures'")->fetch();
    
    if ($tableCheck) {
        // Get user's signatures
        $stmt = $clinic_pdo->prepare("SELECT * FROM user_signatures WHERE user_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $signatures = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'signatures' => $signatures
        ]);
    } else {
        // Table doesn't exist yet, return empty array
        echo json_encode([
            'success' => true,
            'signatures' => []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>














