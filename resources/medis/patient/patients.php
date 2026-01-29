<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

$action = $_GET['action'] ?? 'list';
$search = $_GET['search'] ?? '';
$company = $_GET['company'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$message = '';
$messageType = '';

// Handle messages from URL parameters (after redirect)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $patient_id = $_POST['patient_id'];
        try {
            $clinic_pdo->beginTransaction();
            
            // Delete from all related tables first (due to foreign key constraints)
            $tables = ['medical_history', 'personal_social_history', 'occupational_history', 
                      'training_history', 'history_of_health', 'clinical_findings', 
                      'physical_examination', 'chemical_information'];
            
            foreach ($tables as $table) {
                $stmt = $clinic_pdo->prepare("DELETE FROM $table WHERE patient_id = ?");
                $stmt->execute([$patient_id]);
            }
            
            // Finally delete from patient_information
            $stmt = $clinic_pdo->prepare("DELETE FROM patient_information WHERE id = ?");
            $stmt->execute([$patient_id]);
            
            $clinic_pdo->commit();
            $message = "Patient deleted successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $clinic_pdo->rollBack();
            $message = "Error deleting patient: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($action == 'add') {
        $data = [
            'first_name' => sanitizeInput($_POST['firstName']),
            'last_name' => sanitizeInput($_POST['lastName']),
            'NRIC' => sanitizeInput($_POST['NRIC']),
            'passport_no' => sanitizeInput($_POST['passportNo']),
            'date_of_birth' => $_POST['dateOfBirth'],
            'gender' => $_POST['gender'],
            'address' => sanitizeInput($_POST['address']),
            'state' => 'Kelantan', // Default state
            'district' => 'Kota Bharu', // Default district
            'postcode' => '15000', // Default postcode
            'telephone_no' => sanitizeInput($_POST['phone']),
            'email' => sanitizeInput($_POST['email']),
            'ethnicity' => $_POST['ethnic'],
            'citizenship' => $_POST['citizenship'],
            'martial_status' => $_POST['status'],
            'no_of_children' => (int)$_POST['no_of_children'],
            'years_married' => $_POST['years_married'] ? (int)$_POST['years_married'] : null
        ];
        
        $result = addPatientToClinic($data);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            
            // Redirect to prevent form resubmission
            header('Location: patients.php?action=list&message=' . urlencode($message) . '&type=success');
            exit();
        } else {
            $message = $result['message'];
            $messageType = 'danger';
            
            // Redirect to prevent form resubmission
            header('Location: patients.php?action=list&message=' . urlencode($message) . '&type=danger');
            exit();
        }
    }
}

// Get patients based on company filter
if ($company) {
    // Get patients for specific company
    $stmt = $clinic_pdo->prepare("
        SELECT DISTINCT pi.* 
        FROM patient_information pi
        INNER JOIN occupational_history oh ON pi.id = oh.patient_id
        WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(?))
        ORDER BY pi.first_name, pi.last_name
    ");
    $stmt->execute([$company]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get all patients
    $patients = getAllClinicPatients();
}

$totalPatients = count($patients);
$totalPages = ceil($totalPatients / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($action == 'list'): ?>
        <!-- Patient List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> 
                                <?php if ($company): ?>
                                    Patients - <?php echo htmlspecialchars($company); ?>
                                <?php else: ?>
                                    Patient Management
                                <?php endif; ?>
                            </h5>
                            <?php if ($company): ?>
                                <small class="text-muted">
                                    <a href="<?php echo app_url('patients.php'); ?>" class="text-decoration-none">
                                        <i class="fas fa-arrow-left"></i> Back to All Patients
                                    </a>
                                </small>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo app_url('patient_form.php'); ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Patient
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchInput" 
                                           placeholder="Search patients...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Results Counter -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="text-muted">
                                    <small id="resultsCounter">Showing all patients</small>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>NRIC</th>
                                        <th>Gender</th>
                                        <th>Date of Birth</th>
                                        <th>Phone</th>
                                        <th>Last Appointment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                    <tr data-patient-id="<?php echo htmlspecialchars(strtolower($patient['patient_id'])); ?>"
                                        data-patient-name="<?php echo htmlspecialchars(strtolower(ucwords(strtolower($patient['first_name'] . ' ' . $patient['last_name'])))); ?>"
                                        data-nric="<?php echo htmlspecialchars(strtolower($patient['NRIC'])); ?>"
                                        data-gender="<?php echo htmlspecialchars(strtolower($patient['gender'])); ?>"
                                        data-dob="<?php echo date('d/m/Y', strtotime($patient['date_of_birth'])); ?>"
                                        data-phone="<?php echo htmlspecialchars(strtolower($patient['telephone_no'])); ?>">
                                        <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars(ucwords(strtolower($patient['first_name'] . ' ' . $patient['last_name']))); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['NRIC']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $patient['gender'] == 'Male' ? 'primary' : 'pink'; ?>">
                                                <?php echo $patient['gender']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($patient['date_of_birth'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['telephone_no']); ?></td>
                                        <td>
                                            <span class="text-muted">No appointments</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo app_url('patient_view.php'); ?>?id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo app_url('patient_edit.php'); ?>?id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deletePatient(<?php echo $patient['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <a href="<?php echo app_url('appointments.php'); ?>?action=add&patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-success" title="New Appointment">
                                                    <i class="fas fa-calendar-plus"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Patient pagination">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $company ? '&company=' . urlencode($company) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($action == 'add'): ?>
        <!-- Add Patient Form -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus"></i> Add New Patient</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="firstName" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lastName" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="NRIC" class="form-label">NRIC *</label>
                                    <input type="text" class="form-control" id="NRIC" name="NRIC" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="passportNo" class="form-label">Passport No</label>
                                    <input type="text" class="form-control" id="passportNo" name="passportNo">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="dateOfBirth" class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" id="dateOfBirth" name="dateOfBirth" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="gender" class="form-label">Gender *</label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ethnic" class="form-label">Ethnicity</label>
                                    <select class="form-select" id="ethnic" name="ethnic">
                                        <option value="">Select Ethnicity</option>
                                        <option value="Malay">Malay</option>
                                        <option value="Chinese">Chinese</option>
                                        <option value="Indian">Indian</option>
                                        <option value="Orang Asli">Orang Asli</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Marital Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Select Status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="no_of_children" class="form-label">Number of Children</label>
                                    <input type="number" class="form-control" id="no_of_children" name="no_of_children" min="0" value="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="years_married" class="form-label">Years Married</label>
                                    <input type="number" class="form-control" id="years_married" name="years_married" min="0">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="citizenship" class="form-label">Citizenship</label>
                                    <select class="form-select" id="citizenship" name="citizenship">
                                        <option value="">Select Citizenship</option>
                                        <option value="Malaysian Citizen">Malaysian Citizen</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="patient@example.com">
                                <small class="form-text text-muted">Enter a valid email address</small>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?php echo app_url('patients.php'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Patients
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Patient
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('assets/js/email-validation.js'); ?>"></script>
    <script src="<?php echo asset('assets/js/main.js'); ?>"></script>
    <script>
        function deletePatient(id) {
            if (confirm('Are you sure you want to delete this patient? This action cannot be undone and will also delete all related records.')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'patients.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'patient_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        function searchPatients() {
            filterPatients();
        }

        function filterPatients() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const patientId = row.getAttribute('data-patient-id') || '';
                const patientName = row.getAttribute('data-patient-name') || '';
                const nric = row.getAttribute('data-nric') || '';
                const gender = row.getAttribute('data-gender') || '';
                const dob = row.getAttribute('data-dob') || '';
                const phone = row.getAttribute('data-phone') || '';

                const matchesSearch = searchTerm === '' || 
                                    patientId.includes(searchTerm) || 
                                    patientName.includes(searchTerm) ||
                                    nric.includes(searchTerm) ||
                                    gender.includes(searchTerm) ||
                                    dob.includes(searchTerm) ||
                                    phone.includes(searchTerm);

                if (matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            updateResultsCount(visibleCount);
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            filterPatients();
        }

        function updateResultsCount(count) {
            const counter = document.getElementById('resultsCounter');
            const totalPatients = document.querySelectorAll('tbody tr').length;
            
            if (count === totalPatients) {
                counter.textContent = 'Showing all patients';
            } else {
                counter.textContent = `Showing ${count} of ${totalPatients} patients`;
            }
        }

        // Initialize search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(event) {
                    if (event.key === 'Enter') {
                        searchPatients();
                    } else {
                        filterPatients(); // Real-time search
                    }
                });
                
                // Initialize counter
                updateResultsCount(document.querySelectorAll('tbody tr').length);
            }
        });
    </script>
</body>
</html>
