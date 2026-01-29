<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}


$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $user_name;

// Get user details with all medical_staff information
try {
    // Debug: Check if user_id exists
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        throw new Exception("User ID not found in session");
    }
    
    // Get user details from users table
    $stmt = $clinic_pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_details = $stmt->fetch();
    
    // If user found, try to get medical_staff details if user is a doctor
    if ($user_details && $user_details['role'] === 'Doctor') {
        // Try to find medical_staff record by matching first_name and last_name
        $stmt = $clinic_pdo->prepare("SELECT * FROM medical_staff WHERE first_name = ? AND last_name = ?");
        $stmt->execute([$user_details['first_name'], $user_details['last_name']]);
        $medical_staff = $stmt->fetch();
        
        if ($medical_staff) {
            // Merge medical_staff data with user_details
            $user_details = array_merge($user_details, $medical_staff);
        }
    }
    
    // Debug: Check if user was found
    if (!$user_details) {
        throw new Exception("User not found in database");
    }
    
} catch (Exception $e) {
    $user_details = null;
}

// Handle password change
$password_message = '';
if ($_POST && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $password_message = '<div class="alert alert-danger">New passwords do not match!</div>';
    } elseif (strlen($new_password) < 6) {
        $password_message = '<div class="alert alert-danger">New password must be at least 6 characters long!</div>';
    } else {
        // Verify current password
        $stmt = $clinic_pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['password'] === $current_password) {
            // Update password
            $stmt = $clinic_pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$new_password, $_SESSION['user_id']])) {
                $password_message = '<div class="alert alert-success">Password changed successfully!</div>';
            } else {
                $password_message = '<div class="alert alert-danger">Failed to update password!</div>';
            }
        } else {
            $password_message = '<div class="alert alert-danger">Current password is incorrect!</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-user-circle text-primary"></i> User Profile</h2>
                    <a href="<?php echo app_url('index.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($user_details): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <p class="form-control-plaintext">
                                        <?php echo htmlspecialchars(trim(($user_details['first_name'] ?? '') . ' ' . ($user_details['last_name'] ?? ''))); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user_details['phone'] ?? $user_details['telephone_no'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user_details['email'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">License Number</label>
                                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user_details['license_number'] ?? 'Not provided'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary" onclick="viewFullProfile()">
                                        <i class="fas fa-eye"></i> View Full Profile
                                    </button>
                                    <button type="button" class="btn btn-outline-success" onclick="editProfile()">
                                        <i class="fas fa-edit"></i> Edit Information
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Unable to load user details.
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <br><small>User ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?> | 
                                Role: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Not set'); ?> | 
                                Username: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Not set'); ?></small>
                            <?php else: ?>
                                <br><small>Session user_id is not set. Please log in again.</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
            
                
        <!-- Header Upload and Change Password Section -->
        <div class="row mb-4">
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
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lock"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <?php echo $password_message; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-bold">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-bold">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label fw-bold">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearPasswordForm()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </form>
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
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check if header document exists on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadHeaderHistory();
        });

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

        // Clear password form
        function clearPasswordForm() {
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        }

        // View full profile
        function viewFullProfile() {
            // Create a modal or redirect to a detailed view
            alert('Full profile view functionality will be implemented. This will show all detailed information.');
        }

        // Edit profile
        function editProfile() {
            // Create a modal or redirect to edit page
            alert('Edit profile functionality will be implemented. This will allow editing of personal information.');
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
    </script>
</body>
</html>
