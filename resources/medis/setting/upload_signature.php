<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['signature_file']) || $_FILES['signature_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['signature_file'];
$signature_name = $_POST['signature_name'] ?? 'Signature';

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG files are allowed']);
    exit();
}

// Validate file size (2MB max)
$maxSize = 2 * 1024 * 1024; // 2MB in bytes
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 2MB limit']);
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../../storage/app/uploads/signatures/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'signature_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
$filepath = $uploadDir . $filename;
$relativePath = 'storage/uploads/signatures/' . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    try {
        // Check if user_signatures table exists, if not create it
        $clinic_pdo->exec("CREATE TABLE IF NOT EXISTS user_signatures (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            signature_name VARCHAR(255) DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        // Store signature info in database
        $stmt = $clinic_pdo->prepare("INSERT INTO user_signatures (user_id, filename, original_name, file_path, signature_name, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            $filename,
            $file['name'],
            $relativePath,
            $signature_name
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Signature uploaded successfully!',
            'filename' => $filename
        ]);
    } catch (Exception $e) {
        // Remove uploaded file if database insert fails
        unlink($filepath);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>














