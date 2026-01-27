<?php
session_start();
require_once 'config/clinic_database.php';
require_once 'includes/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url('login.php'));
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $user_name;

// Get user details
try {
    $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_details = $stmt->fetch();
    
    if (!$user_details) {
        throw new Exception("User not found in database");
    }
} catch (Exception $e) {
    $user_details = null;
}

// Handle settings updates
$update_message = '';
if ($_POST && isset($_POST['update_settings'])) {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    try {
        $stmt = $clinic_pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$first_name, $last_name, $email, $_SESSION['user_id']])) {
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            
            $update_message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Settings updated successfully!</div>';
            
            // Reload user details
            $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user_details = $stmt->fetch();
        } else {
            $update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to update settings!</div>';
        }
    } catch (Exception $e) {
        $update_message = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <style>
        .signature-pad {
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            margin: 10px 0;
        }
        
        .signature-pad canvas {
            border-radius: 6px;
            width: 100%;
        }
        
        .signature-controls {
            margin-top: 10px;
        }
        
        .signature-controls button {
            margin-right: 10px;
            padding: 5px 15px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-cog text-primary"></i> Settings</h2>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        

        <!-- Header Upload and E-Signature Section -->
        <div class="row mb-4">
            <!-- Header Upload -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Upload Header Document</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Upload a header document (JPG or PDF) that will appear on all generated reports.
                            <br><small><strong>Recommended: A4 letterhead format (2480x300-400px for JPG)</strong></small>
                        </div>
                        
                        <form id="headerUploadForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="headerDocument" class="form-label fw-bold">Header Document</label>
                                <input type="file" class="form-control" id="headerDocument" name="headerDocument" accept=".jpg,.jpeg,.pdf" required>
                                <div class="form-text">JPG, JPEG, or PDF files allowed. Max 5MB</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="documentDescription" class="form-label fw-bold">Description (Optional)</label>
                                <input type="text" class="form-control" id="documentDescription" name="documentDescription" placeholder="Brief description of this header document">
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Header
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearHeaderForm()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </form>
                        
                        <div id="headerUploadStatus" class="mt-3" style="display: none;"></div>
                    </div>
                </div>
            </div>
            
            <!-- E-Signature Upload -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-signature"></i> Upload E-Signature</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Create your digital signature by drawing it in the canvas below.
                            <br><small><strong>Or upload a signature image (JPG, JPEG, PNG)</strong></small>
                        </div>
                        
                        <div id="signatureBox">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Draw Signature</label>
                                <div class="signature-pad">
                                    <canvas id="signatureCanvas" width="500" height="200" style="border:1px solid #ccc;"></canvas>
                                </div>
                                <div class="signature-controls">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSignature()">
                                        <i class="fas fa-eraser"></i> Clear
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Or Upload Signature Image</label>
                                <input type="file" class="form-control signature-file" id="signatureFileInput" accept=".jpg,.jpeg,.png">
                                <div class="form-text">JPG, JPEG, or PNG files. Max 2MB</div>
                            </div>
                            
                            <div class="signature-preview" id="signaturePreview"></div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="uploadSignature()">
                                    <i class="fas fa-upload"></i> Upload Signature
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSignatureForm()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                            
                            <div class="signature-status mt-3" id="signatureStatus" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header History Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Header Upload History</h5>
                    </div>
                    <div class="card-body">
                        <div id="headerHistory">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Loading history...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- E-Signature History Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> E-Signature History</h5>
                    </div>
                    <div class="card-body">
                        <div id="signatureHistory">
                            <div class="text-center">
                                <i class="fas fa-spinner fa-spin"></i> Loading signatures...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize signature pad on page load
        let signaturePad;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadHeaderHistory();
            loadSignatureHistory();
            
            // Initialize SignaturePad
            const canvas = document.getElementById('signatureCanvas');
            if (canvas) {
                signaturePad = new SignaturePad(canvas);
                signaturePad.penColor = '#000000';
            }
            
            // Add file change listener for preview
            const fileInput = document.getElementById('signatureFileInput');
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    previewSignatureImage(e.target);
                });
            }
        });

        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                location.reload();
            }
        }

        // Handle header upload form submission
        document.getElementById('headerUploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const statusDiv = document.getElementById('headerUploadStatus');
            
            // Show loading
            statusDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Uploading header document...</div>';
            statusDiv.style.display = 'block';
            
            fetch('upload_header_document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    // Clear form
                    this.reset();
                    // Reload header history
                    loadHeaderHistory();
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Upload Error:', error);
                statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error uploading header document: ' + error.message + '</div>';
            });
        });

        // Clear header upload form
        function clearHeaderForm() {
            document.getElementById('headerUploadForm').reset();
            document.getElementById('headerUploadStatus').style.display = 'none';
        }

        // Load header history
        function loadHeaderHistory() {
            fetch('get_header_history.php')
            .then(response => response.json())
            .then(data => {
                const historyDiv = document.getElementById('headerHistory');
                if (data.success && data.history.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-hover">';
                    html += '<thead><tr><th>File Name</th><th>Upload Date</th><th>Actions</th></tr></thead><tbody>';
                    
                    data.history.forEach(header => {
                        // Format the date
                        const uploadDate = new Date(header.uploaded_at);
                        const formattedDate = uploadDate.toLocaleDateString() + ' ' + uploadDate.toLocaleTimeString();
                        
                        html += `<tr>
                            <td><i class="fas fa-file"></i> ${header.original_name}</td>
                            <td><small class="text-muted">${formattedDate}</small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="previewHeader('${header.id}')">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeHeader('${header.id}')">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    historyDiv.innerHTML = html;
                } else {
                    historyDiv.innerHTML = '<div class="text-center text-muted"><i class="fas fa-inbox"></i> No header documents uploaded yet.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading header history:', error);
                document.getElementById('headerHistory').innerHTML = '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading header history.</div>';
            });
        }

        // Preview specific header
        function previewHeader(headerId) {
            window.open('preview_header_document.php?id=' + headerId, '_blank');
        }

        // Remove specific header
        function removeHeader(headerId) {
            if (confirm('Are you sure you want to remove this header document?')) {
                fetch('remove_specific_header.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: headerId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Header document removed successfully!');
                        loadHeaderHistory();
                    } else {
                        alert('Error removing header document: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error removing header:', error);
                    alert('Error removing header document: ' + error.message);
                });
            }
        }

        // Clear signature canvas
        function clearSignature() {
            if (signaturePad) {
                signaturePad.clear();
            }
        }

        // Clear entire signature form
        function clearSignatureForm() {
            if (signaturePad) {
                signaturePad.clear();
            }
            const fileInput = document.getElementById('signatureFileInput');
            if (fileInput) {
                fileInput.value = '';
            }
            const previewDiv = document.getElementById('signaturePreview');
            if (previewDiv) {
                previewDiv.innerHTML = '';
            }
            const statusDiv = document.getElementById('signatureStatus');
            if (statusDiv) {
                statusDiv.style.display = 'none';
                statusDiv.innerHTML = '';
            }
        }

        // Preview signature image before upload
        function previewSignatureImage(input) {
            const previewDiv = document.getElementById('signaturePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewDiv.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 150px;" alt="Signature Preview">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Upload signature
        function uploadSignature() {
            const fileInput = document.getElementById('signatureFileInput');
            const statusDiv = document.getElementById('signatureStatus');
            
            const formData = new FormData();
            
            // Check if user drew a signature or uploaded a file
            if (fileInput.files[0]) {
                // User uploaded a file
                formData.append('signature_file', fileInput.files[0]);
                formData.append('upload_type', 'file');
            } else if (signaturePad && !signaturePad.isEmpty()) {
                // User drew a signature
                const signatureData = signaturePad.toDataURL();
                // Convert data URL to blob
                const blob = dataURLtoBlob(signatureData);
                const filename = 'signature_' + Date.now() + '.png';
                formData.append('signature_file', blob, filename);
                formData.append('upload_type', 'canvas');
            } else {
                alert('Please either draw a signature or upload an image!');
                return;
            }
            
            statusDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Uploading signature...</div>';
            statusDiv.style.display = 'block';
            
            fetch('upload_signature.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    // Reload signature history
                    loadSignatureHistory();
                    // Clear the form after successful upload
                    setTimeout(() => {
                        clearSignatureForm();
                        statusDiv.style.display = 'none';
                    }, 1500);
                } else {
                    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Upload Error:', error);
                statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error uploading signature: ' + error.message + '</div>';
            });
        }

        // Convert data URL to blob for file upload
        function dataURLtoBlob(dataurl) {
            const arr = dataurl.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);
            while(n--){
                u8arr[n] = bstr.charCodeAt(n);
            }
            return new Blob([u8arr], {type:mime});
        }

        // Load signature history
        function loadSignatureHistory() {
            fetch('get_signature_history.php')
            .then(response => response.json())
            .then(data => {
                const historyDiv = document.getElementById('signatureHistory');
                if (data.success && data.signatures.length > 0) {
                    let html = '<div class="row">';
                    
                    data.signatures.forEach(signature => {
                        const uploadDate = new Date(signature.uploaded_at);
                        const formattedDate = uploadDate.toLocaleDateString() + ' ' + uploadDate.toLocaleTimeString();
                        
                        html += `<div class="col-md-3 mb-3">
                            <div class="card">
                                <div class="card-body text-center">
                                    <img src="${signature.file_path}" class="img-fluid mb-2" style="max-height: 100px; border: 1px solid #ddd; padding: 5px;" alt="Signature">
                                    <p class="small text-muted">${formattedDate}</p>
                                    <button class="btn btn-sm btn-outline-danger" onclick="removeSignature(${signature.id})">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>`;
                    });
                    
                    html += '</div>';
                    historyDiv.innerHTML = html;
                } else {
                    historyDiv.innerHTML = '<div class="text-center text-muted"><i class="fas fa-inbox"></i> No signatures uploaded yet.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading signature history:', error);
                document.getElementById('signatureHistory').innerHTML = '<div class="text-center text-danger"><i class="fas fa-exclamation-triangle"></i> Error loading signatures.</div>';
            });
        }

        // Remove signature
        function removeSignature(signatureId) {
            if (confirm('Are you sure you want to delete this signature?')) {
                fetch('remove_signature.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: signatureId})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Signature deleted successfully!');
                        loadSignatureHistory();
                    } else {
                        alert('Error deleting signature: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error removing signature:', error);
                    alert('Error deleting signature: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>

