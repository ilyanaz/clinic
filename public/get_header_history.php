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
    // Get all header documents ordered by upload date
    $stmt = $clinic_pdo->prepare("SELECT id, filename, original_name, uploaded_at FROM header_documents ORDER BY uploaded_at DESC");
    $stmt->execute();
    $history = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
































