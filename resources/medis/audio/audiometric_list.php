<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login'));
    exit();
}

// Get company ID from URL (optional – allow listing by company like patient_list)
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;

// If company filter provided, fetch company; otherwise allow all
$company = null;
if ($company_id > 0) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM company WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            $_SESSION['error_message'] = 'Company not found.';
            header('Location: ' . app_url('audio'));
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
        header('Location: ' . app_url('audio'));
        exit();
    }
}

// Fetch patients (scoped to company if provided) to start audiometry
try {
    if ($company) {
        $stmt = $clinic_pdo->prepare("
            SELECT DISTINCT pi.id, pi.patient_id, pi.first_name, pi.last_name, pi.NRIC, pi.date_of_birth,
                   pi.gender, pi.telephone_no, oh.job_title
            FROM patient_information pi
            INNER JOIN occupational_history oh ON pi.id = oh.patient_id
            WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(?))
            ORDER BY pi.first_name, pi.last_name
        ");
        $stmt->execute([$company['company_name']]);
    } else {
        $stmt = $clinic_pdo->prepare("
            SELECT pi.id, pi.patient_id, pi.first_name, pi.last_name, pi.NRIC, pi.date_of_birth,
                   pi.gender, pi.telephone_no, oh.job_title, oh.company_name
            FROM patient_information pi
            LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
            ORDER BY pi.first_name, pi.last_name
        ");
        $stmt->execute();
    }
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients = [];
    error_log('Error fetching patients for audiometry: ' . $e->getMessage());
}

$page_title = 'Audiometric List';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        .company-id-badge { background: #389B5B; color: white; padding: 0.4rem 1rem; border-radius: 6px; font-weight: 600; font-family: 'Courier New', monospace; font-size: 0.9rem; display: inline-block; margin-bottom: 0.75rem; letter-spacing: 0.5px; }
        .breadcrumb-custom { background: none; padding: 0; margin: 0; font-size: 0.9rem; }
        .breadcrumb-custom .breadcrumb-item a { color: #389B5B; text-decoration: none; }
        .breadcrumb-custom .breadcrumb-item.active { color: #6c757d; }
        .card-title { color: #2c3e50; font-weight: 600; font-size: 1.25rem; margin: 0; display: flex; align-items: center; }
        .card-title i { color: #389B5B; margin-right: 0.75rem; font-size: 1.1rem; }
        .table th { border-top: none; font-weight: 600; color: #495057; font-size: 0.9rem; padding: 1rem 0.75rem; text-align: center; }
        .table td { padding: 1rem 0.75rem; vertical-align: middle; border-color: #f8f9fa; text-align: center; }
        .table td:nth-child(2) { text-align: left; }
        .table tbody tr:hover { background-color: #f8fff9; }
        #searchInput { border-radius: 15px !important; }
        @media (max-width: 768px) { .table th, .table td { padding: 0.5rem 0.25rem; font-size: 0.85rem; } }
    </style>
    </head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <nav aria-label="breadcrumb" class="mb-2">
                            <ol class="breadcrumb breadcrumb-custom mb-0">
                                <li class="breadcrumb-item"><a href="<?php echo app_url('audio'); ?>">Audio</a></li>
                                <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo $company ? htmlspecialchars($company['company_name']) : 'All Companies'; ?>
                                </li>
                            </ol>
                        </nav>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fas fa-wave-square"></i> Audiometric Candidates - <?php echo $company ? htmlspecialchars($company['company_name']) : 'All'; ?>
                                </h5>
                                <small class="text-muted">Total: <?php echo count($patients); ?> patients</small>
                            </div>
                            <a href="<?php echo app_url('patient_form'); ?><?php echo $company ? '?company=' . urlencode($company['company_name']) : ''; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Patient
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search patients...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="genderFilter" onchange="filterPatients()">
                                    <option value="">All Genders</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>

                        <?php if (!empty($patients)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>NRIC</th>
                                        <th>Gender</th>
                                        <th>Date of Birth</th>
                                        <th>Phone</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="patientTableBody">
                                    <?php foreach ($patients as $index => $patient): ?>
                                    <tr data-name="<?php echo strtolower(htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'])); ?>" 
                                        data-nric="<?php echo strtolower(htmlspecialchars($patient['NRIC'] ?? '')); ?>" 
                                        data-gender="<?php echo htmlspecialchars($patient['gender']); ?>">
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="patient-name-cell">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                            <?php if (!$company && !empty($patient['company_name'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($patient['company_name']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['NRIC'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $patient['gender'] == 'Male' ? 'primary' : 'pink'; ?>">
                                                <?php echo $patient['gender']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $patient['date_of_birth'] ? date('d/m/Y', strtotime($patient['date_of_birth'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($patient['telephone_no'] ?? '-'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo app_url('audiometric_questionnaire'); ?>?patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-secondary" title="Audiometric Questionnaire">
                                                    <i class="fas fa-clipboard-list"></i>
                                                </a>
                                                <a href="<?php echo app_url('audiometric_history'); ?>?patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Audiometric History">
                                                    <i class="fas fa-history"></i>
                                                </a>
                                                <a href="<?php echo app_url('audiometric'); ?>?patient_id=<?php echo $patient['id']; ?>&new=1" 
                                                   class="btn btn-outline-success" title="New Audiometric">
                                                    <i class="fas fa-headphones"></i>
                                                </a>
                                                <a href="<?php echo app_url('patient_view'); ?>?id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Patient Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo app_url('patient_edit'); ?>?id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit Patient Details">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deletePatient(<?php echo $patient['id']; ?>)" title="Delete Patient">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No Patients Found</h6>
                            <p class="text-muted">No patients are available for audiometry<?php echo $company ? ' under this company' : ''; ?>.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var patientsActionUrl = '<?php echo app_url("patients"); ?>';
        function deletePatient(patientId) {
            if (confirm('Are you sure you want to delete this patient?\n\nThis action cannot be undone and will delete all related medical records.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = patientsActionUrl;
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'patient_id';
                idInput.value = patientId;
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function searchPatients() { filterPatients(); }
        function filterPatients() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const genderFilter = document.getElementById('genderFilter').value;
            const rows = document.querySelectorAll('#patientTableBody tr');
            let visibleCount = 0;
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const nric = row.getAttribute('data-nric');
                const gender = row.getAttribute('data-gender');
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || nric.includes(searchTerm);
                const matchesGender = genderFilter === '' || gender === genderFilter;
                if (matchesSearch && matchesGender) { row.style.display = ''; visibleCount++; } else { row.style.display = 'none'; }
            });
            updateResultsCount(visibleCount);
        }
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('genderFilter').value = '';
            filterPatients();
        }
        function updateResultsCount(count) {
            const totalCount = document.querySelectorAll('#patientTableBody tr').length;
            let resultsText = '';
            if (count === totalCount) { resultsText = `Showing all ${totalCount} patients`; } else { resultsText = `Showing ${count} of ${totalCount} patients`; }
            let resultsCounter = document.getElementById('resultsCounter');
            if (!resultsCounter) {
                resultsCounter = document.createElement('div');
                resultsCounter.id = 'resultsCounter';
                resultsCounter.className = 'text-muted small mb-2';
                const tableContainer = document.querySelector('.table-responsive');
                if (tableContainer && tableContainer.parentNode) { tableContainer.parentNode.insertBefore(resultsCounter, tableContainer); }
            }
            resultsCounter.textContent = resultsText;
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(event) { if (event.key === 'Enter') { searchPatients(); } else { filterPatients(); } });
                updateResultsCount(document.querySelectorAll('#patientTableBody tr').length);
            }
        });
    </script>
</body>
</html>
