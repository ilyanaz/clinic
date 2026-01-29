<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';
require_once __DIR__ . '/../../../app/Services/company_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . app_url("login"));
    exit();
}

// Clear any previous error message
if (isset($_SESSION['error_message'])) {
    $error_msg = $_SESSION['error_message'];
    error_log("Audio page: Clearing error message: " . $error_msg);
    unset($_SESSION['error_message']);
}

// Handle form submissions identical to company.php
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
                        'mykpp_registration_no' => sanitizeInput($_POST['mykpp_registration_no'])
                    ];
                    $companyId = addCompany($companyData);
                    if ($companyId) { $_SESSION['success_message'] = "Company added successfully!"; }
                    else { $_SESSION['error_message'] = "Failed to add company. Company name or MyKPP number may already exist."; }
                } catch (Exception $e) { $_SESSION['error_message'] = "Error adding company: " . $e->getMessage(); }
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
                        'mykpp_registration_no' => sanitizeInput($_POST['mykpp_registration_no'])
                    ];
                    $result = updateCompany($companyId, $companyData);
                    if ($result) { $_SESSION['success_message'] = "Company updated successfully!"; }
                    else { $_SESSION['error_message'] = "Failed to update company."; }
                } catch (Exception $e) { $_SESSION['error_message'] = "Error updating company: " . $e->getMessage(); }
                break;
            case 'delete_company':
                try {
                    $companyId = $_POST['company_id'];
                    $result = deleteCompany($companyId);
                    if ($result) { $_SESSION['success_message'] = "Company deleted successfully!"; }
                    else { $_SESSION['error_message'] = "Failed to delete company."; }
                } catch (Exception $e) { $_SESSION['error_message'] = "Error deleting company: " . $e->getMessage(); }
                break;
        }
    }
    header("Location: " . app_url("audio"));
    exit();
}

// Get all companies
$companies = getAllCompanies();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Management - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        .worker-count-badge { background: linear-gradient(135deg, #389B5B 0%, #319755 100%); color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem; }
        .company-id-badge { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-weight: 600; font-size: 0.8rem; font-family: monospace; }
        .action-buttons { display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: center; }
        .action-buttons .btn { min-width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; }
        .contact-info-cell { min-width: 200px; }
        .contact-item { display: flex; align-items: center; padding: 0.25rem 0; }
        .contact-icon { width: 16px; height: 16px; margin-right: 0.5rem; color: #389B5B; font-size: 0.875rem; flex-shrink: 0; }
        .contact-text { font-size: 0.875rem; color: #495057; font-weight: 500; }
        .contact-email { font-size: 0.875rem; color: #389B5B; text-decoration: none; font-weight: 500; transition: color 0.2s ease; }
        .contact-email:hover { color: #319755; text-decoration: underline; }
        .no-contact-info { display: flex; align-items: center; padding: 0.5rem 0; font-style: italic; }
        .no-contact-info i { margin-right: 0.5rem; font-size: 0.875rem; }
        .no-contact-info span { font-size: 0.875rem; }
        #searchInput { border-radius: 10px !important; }
        .input-group #searchInput { border-top-right-radius: 10px !important; border-bottom-right-radius: 10px !important; }
        .input-group .btn { border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; border-top-right-radius: 10px !important; border-bottom-right-radius: 10px !important; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
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
                        <h5 class="mb-0"><i class="fas fa-headphones"></i> Audio Management</h5>
                        <a href="<?php echo app_url('company_form'); ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Company
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search company...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="text-muted"><small id="resultsCounter">Showing all companies</small></div>
                            </div>
                        </div>

                        <?php if (empty($companies)): ?>
                            <div class="text-center py-5" id="emptyState">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No companies found</h5>
                                <p class="text-muted">Add your first company using the button above.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr style="text-align: center;">
                                            <th>NO</th>
                                            <th>COMPANY NAME</th>
                                            <th>LOCATION</th>
                                            <th>CONTACT INFO</th>
                                            <th>MYKPP NO</th>
                                            <th>WORKERS</th>
                                            <th>ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="companyTableBody">
                                        <?php foreach ($companies as $index => $company): ?>
                                            <tr data-name="<?php echo strtolower(htmlspecialchars($company['company_name'])); ?>" data-address="<?php echo strtolower(htmlspecialchars($company['address'])); ?>" data-mykpp="<?php echo strtolower(htmlspecialchars($company['mykpp_registration_no'])); ?>">
                                                <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars(ucwords(strtolower($company['company_name']))); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($company['address'], 0, 50)) . (strlen($company['address']) > 50 ? '...' : ''); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($company['district']); ?>, <?php echo htmlspecialchars($company['state']); ?><br><small class="text-muted"><?php echo htmlspecialchars($company['postcode']); ?></small></td>
                                                <td>
                                                    <div class="contact-info-cell">
                                                        <?php if ($company['telephone']): ?>
                                                        <div class="contact-item mb-1"><i class="fas fa-phone contact-icon"></i><span class="contact-text"><?php echo htmlspecialchars($company['telephone']); ?></span></div>
                                                        <?php endif; ?>
                                                        <?php if ($company['fax']): ?>
                                                        <div class="contact-item mb-1"><i class="fas fa-fax contact-icon"></i><span class="contact-text"><?php echo htmlspecialchars($company['fax']); ?></span></div>
                                                        <?php endif; ?>
                                                        <?php if ($company['email']): ?>
                                                        <div class="contact-item"><i class="fas fa-envelope contact-icon"></i><a href="mailto:<?php echo htmlspecialchars($company['email']); ?>" class="contact-email"><?php echo htmlspecialchars($company['email']); ?></a></div>
                                                        <?php endif; ?>
                                                        <?php if (!$company['telephone'] && !$company['fax'] && !$company['email']): ?>
                                                        <div class="no-contact-info"><i class="fas fa-minus-circle text-muted"></i><span class="text-muted">No contact information</span></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($company['mykpp_registration_no']); ?></code></td>
                                                <td style="text-align: center;"><?php echo $company['total_workers']; ?></td>
                                                <td style="text-align: center;">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo app_url('audiometric_list'); ?>?company_id=<?php echo $company['id']; ?>" class="btn btn-outline-primary" title="View List of Patients"><i class="fas fa-clipboard-check"></i></a>
                                                        <a href="<?php echo app_url('company_form'); ?>?edit=<?php echo $company['id']; ?>" class="btn btn-outline-warning" title="Edit Company Details"><i class="fas fa-edit"></i></a>
                                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars(ucwords(strtolower($company['company_name']))); ?>')" title="Delete Company Details"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the company <strong id="companyNameToDelete"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_company">
                        <input type="hidden" name="company_id" id="companyIdToDelete">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Company</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(companyId, companyName) {
            document.getElementById('companyIdToDelete').value = companyId;
            document.getElementById('companyNameToDelete').textContent = companyName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        function searchCompanies() { filterCompanies(); }
        function filterCompanies() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#companyTableBody tr');
            let visibleCount = 0;
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const address = row.getAttribute('data-address');
                const mykpp = row.getAttribute('data-mykpp');
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || address.includes(searchTerm) || mykpp.includes(searchTerm);
                if (matchesSearch) { row.style.display = ''; visibleCount++; } else { row.style.display = 'none'; }
            });
            updateResultsCount(visibleCount);
        }
        function clearSearch() { document.getElementById('searchInput').value = ''; filterCompanies(); }
        function updateResultsCount(count) {
            const counter = document.getElementById('resultsCounter');
            const totalCompanies = document.querySelectorAll('#companyTableBody tr').length;
            if (count === totalCompanies) { counter.textContent = 'Showing all companies'; }
            else { counter.textContent = `Showing ${count} of ${totalCompanies} companies`; }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(event) { if (event.key === 'Enter') { searchCompanies(); } else { filterCompanies(); } });
                updateResultsCount(document.querySelectorAll('#companyTableBody tr').length);
            }
        });
    </script>
</body>
</html>
