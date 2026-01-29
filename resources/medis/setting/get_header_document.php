<?php
require_once __DIR__ . '/../../../config/clinic_database.php';

function getHeaderDocument() {
    global $clinic_pdo;
    
    try {
        $stmt = $clinic_pdo->prepare("SELECT file_path FROM header_documents ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute();
        $document = $stmt->fetch();
        
        if ($document && !empty($document['file_path'])) {
            $filePath = $document['file_path'];
            
            // If path is relative, make it relative to public directory
            if (!file_exists($filePath) && !preg_match('/^[a-zA-Z]:\\\\/', $filePath)) {
                // Path is relative, try from public directory
                $publicPath = __DIR__ . '/' . ltrim($filePath, '/');
                if (file_exists($publicPath)) {
                    return $publicPath;
                }
            }
            
            // If absolute path or already exists, return as is
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting header document: " . $e->getMessage());
        return null;
    }
}

function getHeaderDocumentBase64() {
    $filePath = getHeaderDocument();
    
    if ($filePath && file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'pdf') {
            return base64_encode($fileContent);
        } elseif (in_array($fileExtension, ['jpg', 'jpeg'])) {
            return base64_encode($fileContent);
        }
    }
    
    return null;
}

function getHeaderDocumentMimeType() {
    $filePath = getHeaderDocument();
    
    if ($filePath && file_exists($filePath)) {
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'pdf') {
            return 'application/pdf';
        } elseif (in_array($fileExtension, ['jpg', 'jpeg'])) {
            return 'image/jpeg';
        }
    }
    
    return 'application/pdf'; // default
}
?>
