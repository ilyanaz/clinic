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

// Check if user has permission to upload documents
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['headerDocument']) || $_FILES['headerDocument']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['headerDocument'];
$description = $_POST['documentDescription'] ?? '';

// Validate file type
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only PDF and JPG files are allowed']);
    exit();
}

// Validate file size (5MB max)
$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit();
}

// Validate image dimensions for JPG files
if (in_array($fileType, ['image/jpeg', 'image/jpg'])) {
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo) {
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Check if dimensions are exactly A4 size for letterhead
        if ($width < 2000 || $height < 250) {
            echo json_encode(['success' => false, 'message' => 'Image too small for A4 letterhead. Required: Width 2480px, Height 300-400px']);
            exit();
        }
        
        if ($width > 3000 || $height > 500) {
            echo json_encode(['success' => false, 'message' => 'Image too large for A4 letterhead. Required: Width 2480px, Height 300-400px']);
            exit();
        }
        
        // Check aspect ratio for A4 letterhead (should be wide, not tall)
        $aspectRatio = $width / $height;
        if ($aspectRatio < 5) {
            echo json_encode(['success' => false, 'message' => 'Image should be wider for A4 letterhead format. Current ratio: ' . round($aspectRatio, 1) . ':1. Required: 6:1 to 8:1']);
            exit();
        }
        
        // Store dimension info for later use
        $dimensionInfo = [
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => round($width / $height, 1)
        ];
    }
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../../storage/app/uploads/headers/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'header_document_' . time() . '.' . $fileExtension;
$filepath = $uploadDir . $filename;
$relativePath = 'storage/uploads/headers/' . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    try {
        // Store document info in database
        $stmt = $clinic_pdo->prepare("INSERT INTO header_documents (filename, original_name, file_path, description, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $filename,
            $file['name'],
            $relativePath,
            $description,
            $_SESSION['user_id']
        ]);
        
        $message = 'Header document uploaded successfully!';
        if (isset($dimensionInfo)) {
            if ($dimensionInfo['width'] >= 2000 && $dimensionInfo['width'] <= 3000 && $dimensionInfo['height'] >= 250 && $dimensionInfo['height'] <= 450) {
                $message = 'Perfect A4 letterhead dimensions! Header uploaded successfully.';
            } else {
                $message = 'Header uploaded successfully. Dimensions: ' . $dimensionInfo['width'] . 'x' . $dimensionInfo['height'] . 'px';
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
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
