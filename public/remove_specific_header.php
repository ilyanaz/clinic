<?php
session_start();
require_once 'config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user has permission to remove documents
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$headerId = $input['id'] ?? null;

if (!$headerId) {
    echo json_encode(['success' => false, 'message' => 'Header ID is required']);
    exit();
}

try {
    // Get the header document details
    $stmt = $clinic_pdo->prepare("SELECT id, file_path FROM header_documents WHERE id = ?");
    $stmt->execute([$headerId]);
    $document = $stmt->fetch();
    
    if ($document) {
        // Remove file from filesystem
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        // Remove record from database
        $stmt = $clinic_pdo->prepare("DELETE FROM header_documents WHERE id = ?");
        $stmt->execute([$headerId]);
        
        echo json_encode(['success' => true, 'message' => 'Header document removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Header document not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
































