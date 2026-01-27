<?php
session_start();
require_once 'config/clinic_database.php';
require_once 'includes/clinic_functions.php';
require_once 'includes/company_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url("login.php"));
    exit();
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company':
                try {
                    $companyData = [
                        'company_name' => sanitizeInput($_POST['company_name']),
                        'address' => sanitizeInput($_POST['address']),
                        'district' => sanitizeInput($_POST['district']),
                        'state' => sanitizeInput($_POST['state']),
                        'postcode' => sanitizeInput($_POST['postcode']),
                        'telephone' => sanitizeInput($_POST['telephone']),
                        'fax' => sanitizeInput($_POST['fax']),
                        'email' => sanitizeInput($_POST['email']),
                        'mykpp_registration_no' => sanitizeInput($_POST['mykpp_registration_no'])
                    ];
                    
                    $companyId = addCompany($companyData);
                    
                    if ($companyId) {
                        $_SESSION['success_message'] = "Company added successfully!";
                        header("Location: " . url("company.php"));
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Failed to add company. Company name or MyKPP number may already exist.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Error adding company: " . $e->getMessage();
                }
                break;
                
            case 'update_company':
                try {
                    $companyId = $_POST['company_id'];
                    $companyData = [
                        'company_name' => sanitizeInput($_POST['company_name']),
                        'address' => sanitizeInput($_POST['address']),
                        'district' => sanitizeInput($_POST['district']),
                        'state' => sanitizeInput($_POST['state']),
                        'postcode' => sanitizeInput($_POST['postcode']),
                        'telephone' => sanitizeInput($_POST['telephone']),
                        'fax' => sanitizeInput($_POST['fax']),
                        'email' => sanitizeInput($_POST['email']),
                        'mykpp_registration_no' => sanitizeInput($_POST['mykpp_registration_no'])
                    ];
                    
                    $result = updateCompany($companyId, $companyData);
                    
                    if ($result) {
                        $_SESSION['success_message'] = "Company updated successfully!";
                        header("Location: " . url("company.php"));
                        exit();
                    } else {
                        $_SESSION['error_message'] = "Failed to update company.";
                    }
                } catch (Exception $e) {
                    $_SESSION['error_message'] = "Error updating company: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get edit data if editing
$editMode = false;
$editData = null;
if (isset($_GET['edit']) && $_GET['edit']) {
    $editMode = true;
    $editData = getCompanyById($_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $editMode ? 'Edit Company' : 'Add New Company'; ?> - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .required {
            color: #dc3545;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #389B5B;
        }
        .form-section h5 {
            color: #389B5B;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        /* Auto-capitalization styling */
        input[type="text"]:not([name="email"]), 
        textarea:not([name="email"]) {
            text-transform: uppercase;
        }
        
        /* Keep placeholders in proper case */
        input[type="text"]:not([name="email"])::placeholder,
        textarea:not([name="email"])::placeholder {
            text-transform: none;
        }
        
        /* Ensure email field is not affected */
        input[type="email"] {
            text-transform: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container-fluid py-4">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $editMode ? 'edit' : 'plus'; ?>"></i> 
                            <?php echo $editMode ? 'Edit Company' : 'Add New Company'; ?>
                        </h5>
                        <div>
                            <?php if ($editMode): ?>
                                <a href="company_form.php" class="btn btn-secondary me-2">
                                    <i class="fas fa-plus"></i> Add New
                                </a>
                            <?php endif; ?>
                            <a href="company.php" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> View All Companies
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Company Information Section -->
                        <div class="form-section">
                            <h5><i class="fas fa-building"></i> Company Information</h5>

            <form method="POST" action="company_form.php<?php echo $editMode ? '?edit=' . $editData['id'] : ''; ?>">
                <input type="hidden" name="action" value="<?php echo $editMode ? 'update_company' : 'add_company'; ?>">
                <?php if ($editMode): ?>
                    <input type="hidden" name="company_id" value="<?php echo $editData['id']; ?>">
                <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Company Name <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['company_name']) : ''; ?>" 
                                               placeholder="Enter company name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="mykpp_registration_no" class="form-label">MyKPP Registration No <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="mykpp_registration_no" name="mykpp_registration_no" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['mykpp_registration_no']) : ''; ?>" 
                                               placeholder="Enter MyKPP registration number" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address <span class="required">*</span></label>
                                        <textarea class="form-control" id="address" name="address" rows="3" 
                                                  placeholder="Enter complete address" required><?php echo $editMode ? htmlspecialchars($editData['address']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="state" class="form-label">State (Negeri) <span class="required">*</span></label>
                                        <select class="form-select" id="state" name="state" required onchange="updateDistricts()">
                                            <option value="">Select State</option>
                                            <option value="Johor" <?php echo ($editMode && $editData['state'] == 'Johor') ? 'selected' : ''; ?>>Johor</option>
                                            <option value="Kedah" <?php echo ($editMode && $editData['state'] == 'Kedah') ? 'selected' : ''; ?>>Kedah</option>
                                            <option value="Kelantan" <?php echo ($editMode && $editData['state'] == 'Kelantan') ? 'selected' : ''; ?>>Kelantan</option>
                                            <option value="Melaka" <?php echo ($editMode && $editData['state'] == 'Melaka') ? 'selected' : ''; ?>>Melaka (Malacca)</option>
                                            <option value="Negeri Sembilan" <?php echo ($editMode && $editData['state'] == 'Negeri Sembilan') ? 'selected' : ''; ?>>Negeri Sembilan</option>
                                            <option value="Pahang" <?php echo ($editMode && $editData['state'] == 'Pahang') ? 'selected' : ''; ?>>Pahang</option>
                                            <option value="Pulau Pinang" <?php echo ($editMode && $editData['state'] == 'Pulau Pinang') ? 'selected' : ''; ?>>Pulau Pinang (Penang)</option>
                                            <option value="Perak" <?php echo ($editMode && $editData['state'] == 'Perak') ? 'selected' : ''; ?>>Perak</option>
                                            <option value="Perlis" <?php echo ($editMode && $editData['state'] == 'Perlis') ? 'selected' : ''; ?>>Perlis</option>
                                            <option value="Selangor" <?php echo ($editMode && $editData['state'] == 'Selangor') ? 'selected' : ''; ?>>Selangor</option>
                                            <option value="Terengganu" <?php echo ($editMode && $editData['state'] == 'Terengganu') ? 'selected' : ''; ?>>Terengganu</option>
                                            <option value="Sabah" <?php echo ($editMode && $editData['state'] == 'Sabah') ? 'selected' : ''; ?>>Sabah</option>
                                            <option value="Sarawak" <?php echo ($editMode && $editData['state'] == 'Sarawak') ? 'selected' : ''; ?>>Sarawak</option>
                                            <option value="Kuala Lumpur" <?php echo ($editMode && $editData['state'] == 'Kuala Lumpur') ? 'selected' : ''; ?>>Kuala Lumpur</option>
                                            <option value="Labuan" <?php echo ($editMode && $editData['state'] == 'Labuan') ? 'selected' : ''; ?>>Labuan</option>
                                            <option value="Putrajaya" <?php echo ($editMode && $editData['state'] == 'Putrajaya') ? 'selected' : ''; ?>>Putrajaya</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="district" class="form-label">District (Daerah) <span class="required">*</span></label>
                                        <select class="form-select" id="district" name="district" required>
                                            <option value="">Select District</option>
                                            <?php if ($editMode && !empty($editData['district'])): ?>
                                                <option value="<?php echo htmlspecialchars($editData['district']); ?>" selected>
                                                    <?php echo htmlspecialchars($editData['district']); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="postcode" class="form-label">Post-code <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="postcode" name="postcode" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['postcode']) : ''; ?>" 
                                               placeholder="Enter postcode" pattern="[0-9]{5}" maxlength="5" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="telephone" class="form-label">Telephone <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="telephone" name="telephone" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['telephone']) : ''; ?>" 
                                               placeholder="Enter telephone number" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="fax" class="form-label">Fax <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="fax" name="fax" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['fax']) : ''; ?>" 
                                               placeholder="Enter fax number" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $editMode ? htmlspecialchars($editData['email']) : ''; ?>" 
                                               placeholder="company@example.com" required>
                                        <small class="form-text text-muted">Enter a valid email address</small>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?php echo $editMode ? 'Update Company' : 'Add Company'; ?>
                                </button>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/email-validation.js"></script>
    <script>
        // Postcode validation
        document.getElementById('postcode').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // Phone number validation (telephone and fax)
        document.getElementById('telephone').addEventListener('input', function() {
            // Allow numbers, spaces, hyphens, and plus sign
            this.value = this.value.replace(/[^0-9\s\-\+]/g, '');
        });

        document.getElementById('fax').addEventListener('input', function() {
            // Allow numbers, spaces, hyphens, and plus sign
            this.value = this.value.replace(/[^0-9\s\-\+]/g, '');
        });

        // Email validation is handled by the universal email-validation.js script
        
        // State and District functionality (same as patient_form.php)
        function updateDistricts() {
            const stateSelect = document.getElementById('state');
            const districtSelect = document.getElementById('district');
            const selectedState = stateSelect.value;
            
            // Clear existing options
            districtSelect.innerHTML = '<option value="">Select District</option>';
            
            if (!selectedState) return;
            
            const districts = {
                'Johor': [
                    'Johor Bahru', 'Batu Pahat', 'Kluang', 'Kota Tinggi', 'Kulai', 
                    'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak'
                ],
                'Kedah': [
                    'Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu',
                    'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan'
                ],
                'Kelantan': [
                    'Bachok', 'Gua Musang', 'Jeli', 'Kota Bharu', 'Kuala Krai',
                    'Machang', 'Pasir Mas', 'Pasir Puteh', 'Tanah Merah', 'Tumpat'
                ],
                'Melaka': [
                    'Melaka Tengah', 'Alor Gajah', 'Jasin'
                ],
                'Negeri Sembilan': [
                    'Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau', 'Seremban', 'Tampin'
                ],
                'Pahang': [
                    'Bentong', 'Bera', 'Cameron Highlands', 'Jerantut', 'Kuantan',
                    'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh'
                ],
                'Perak': [
                    'Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar', 'Kerian',
                    'Kuala Kangsar', 'Kinta', 'Larut, Matang dan Selama', 'Manjung', 'Muallim', 'Perak Tengah', 'Bagans Serai (Kerian)'
                ],
                'Perlis': [
                    'Kangar'
                ],
                'Pulau Pinang': [
                    'Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan',
                    'Timur Laut (George Town)', 'Barat Daya'
                ],
                'Selangor': [
                    'Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang', 'Kuala Langat',
                    'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang'
                ],
                'Terengganu': [
                    'Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Terengganu',
                    'Marang', 'Setiu', 'Kuala Nerus'
                ],
                'Sabah': [
                    'Kota Kinabalu', 'Penampang', 'Putatan', 'Papar', 'Tuaran', 'Kota Belud', 'Ranau',
                    'Beaufort', 'Kuala Penyu', 'Tenom', 'Keningau', 'Tambunan', 'Nabawan',
                    'Sandakan', 'Beluran', 'Kinabatangan', 'Telupid', 'Tongod',
                    'Tawau', 'Lahad Datu', 'Kunak', 'Semporna',
                    'Kudat', 'Kota Marudu', 'Pitas'
                ],
                'Sarawak': [
                    'Kuching', 'Bau', 'Lundu', 'Samarahan', 'Asajaya', 'Simunjan',
                    'Sri Aman', 'Lubok Antu', 'Betong', 'Saratok', 'Sibu', 'Selangau', 'Kanowit',
                    'Mukah', 'Dalat', 'Miri', 'Marudi', 'Subis', 'Beluru', 'Telang Usan',
                    'Limbang', 'Lawas', 'Bintulu', 'Tatau', 'Kapit', 'Song', 'Belaga',
                    'Sarikei', 'Meradong', 'Julau', 'Pakan', 'Serian', 'Tebedu', 'Siburan'
                ],
                'Kuala Lumpur': [
                    'Kuala Lumpur'
                ],
                'Labuan': [
                    'Labuan'
                ],
                'Putrajaya': [
                    'Putrajaya'
                ]
            };
            
            if (districts[selectedState]) {
                districts[selectedState].forEach(district => {
                    const option = document.createElement('option');
                    option.value = district;
                    option.textContent = district;
                    districtSelect.appendChild(option);
                });
            }
        }
        
        // Initialize district options on page load if editing
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($editMode && !empty($editData['state'])): ?>
                // Trigger updateDistricts to populate district options for edit mode
                updateDistricts();
                // Set the selected district value
                <?php if (!empty($editData['district'])): ?>
                    setTimeout(function() {
                        document.getElementById('district').value = '<?php echo addslashes($editData['district']); ?>';
                    }, 100);
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>
