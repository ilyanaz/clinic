<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$signatureId = $data['id'] ?? null;

if (!$signatureId) {
    echo json_encode(['success' => false, 'message' => 'Signature ID is required']);
    exit();
}

try {
    // Check if table exists first
    $tableCheck = $clinic_pdo->query("SHOW TABLES LIKE 'user_signatures'")->fetch();
    
    if (!$tableCheck) {
        echo json_encode(['success' => false, 'message' => 'Signatures table does not exist']);
        exit();
    }
    
    // Verify that the signature belongs to the current user
    $stmt = $clinic_pdo->prepare("SELECT id, file_path FROM user_signatures WHERE id = ? AND user_id = ?");
    $stmt->execute([$signatureId, $_SESSION['user_id']]);
    $signature = $stmt->fetch();
    
    if (!$signature) {
        echo json_encode(['success' => false, 'message' => 'Signature not found or you do not have permission to delete it']);
        exit();
    }
    
    // Delete the file if it exists
    if ($signature['file_path'] && file_exists($signature['file_path'])) {
        unlink($signature['file_path']);
    }
    
    // Delete the database record
    $stmt = $clinic_pdo->prepare("DELETE FROM user_signatures WHERE id = ? AND user_id = ?");
    $stmt->execute([$signatureId, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Signature deleted successfully'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>














