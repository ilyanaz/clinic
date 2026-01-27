<?php
session_start();
require_once __DIR__ . '/config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized access';
    exit();
}

try {
    // Get header document by ID or latest
    $headerId = $_GET['id'] ?? null;
    
    if ($headerId) {
        $stmt = $clinic_pdo->prepare("SELECT filename, original_name, file_path FROM header_documents WHERE id = ?");
        $stmt->execute([$headerId]);
    } else {
        $stmt = $clinic_pdo->prepare("SELECT filename, original_name, file_path FROM header_documents ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute();
    }
    
    $document = $stmt->fetch();
    
    if ($document && file_exists($document['file_path'])) {
        // Set headers for PDF display
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $document['original_name'] . '"');
        header('Content-Length: ' . filesize($document['file_path']));
        
        // Output the PDF file
        readfile($document['file_path']);
    } else {
        echo 'No header document found';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
