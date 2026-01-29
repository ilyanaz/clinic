<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login'));
    exit();
}

// Params
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$show_all = isset($_GET['show_all']) ? (bool)$_GET['show_all'] : false;

// Infer company_id from patient if missing
if ($company_id <= 0 && $patient_id > 0) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT c.id AS company_id
                                       FROM company c
                                       INNER JOIN occupational_history oh ON TRIM(LOWER(c.company_name)) = TRIM(LOWER(oh.company_name))
                                       WHERE oh.patient_id = ? LIMIT 1");
        $stmt->execute([$patient_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $company_id = $row ? (int)$row['company_id'] : 0;
    } catch (Exception $e) {
        error_log('audiometric_history infer company error: ' . $e->getMessage());
    }
}

// If still missing and not show_all, redirect to select company
if ($company_id <= 0 && !$show_all) {
    $_SESSION['error_message'] = 'Please select a company to view audiometric history.';
    header('Location: ' . app_url('audio'));
    exit();
}

// Load company if provided
$company = null;
if ($company_id > 0) {
    try {
        $stmt = $clinic_pdo->prepare('SELECT * FROM company WHERE id = ?');
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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_audiometry') {
    $delete_id = (int)($_POST['test_id'] ?? 0);
    if ($delete_id > 0) {
        try {
            $stmt = $clinic_pdo->prepare('DELETE FROM audiometric_tests WHERE id = ?');
            $stmt->execute([$delete_id]);
            $_SESSION['success_message'] = 'Audiometric test deleted successfully.';
            header('Location: ' . app_url('audiometric_history') . '?' . http_build_query($_GET));
            exit();
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error deleting audiometric test: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid audiometric test ID.';
    }
}

// Fetch history
$records = [];
$grouped_by_company = [];
try {
    if ($patient_id > 0) {
        $stmt = $clinic_pdo->prepare("SELECT t.*, pi.first_name, pi.last_name, pi.patient_id AS patient_code,
                                             oh.company_name
                                      FROM audiometric_tests t
                                      LEFT JOIN patient_information pi ON t.patient_id = pi.id
                                      LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                                      WHERE t.patient_id = ?
                                      ORDER BY t.examination_date DESC, t.id DESC");
        $stmt->execute([$patient_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($show_all) {
        $stmt = $clinic_pdo->prepare("SELECT t.*, pi.first_name, pi.last_name, pi.patient_id AS patient_code,
                                             oh.company_name
                                      FROM audiometric_tests t
                                      LEFT JOIN patient_information pi ON t.patient_id = pi.id
                                      LEFT JOIN occupational_history oh ON pi.id = oh.patient_id
                                      ORDER BY oh.company_name ASC, pi.first_name ASC, pi.last_name ASC, t.examination_date DESC, t.id DESC");
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($records as $r) {
            $key = $r['company_name'] ?: 'Unknown Company';
            if (!isset($grouped_by_company[$key])) { $grouped_by_company[$key] = []; }
            $grouped_by_company[$key][] = $r;
        }
    } else {
        $stmt = $clinic_pdo->prepare("SELECT t.*, pi.first_name, pi.last_name, pi.patient_id AS patient_code,
                                             oh.company_name
                                      FROM audiometric_tests t
                                      INNER JOIN patient_information pi ON t.patient_id = pi.id
                                      INNER JOIN occupational_history oh ON pi.id = oh.patient_id
                                      WHERE TRIM(LOWER(oh.company_name)) = TRIM(LOWER(?))
                                      ORDER BY pi.first_name, pi.last_name, t.examination_date DESC, t.id DESC");
        $stmt->execute([$company['company_name']]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $records = [];
    error_log('audiometric_history fetch error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiometric History - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        .breadcrumb-nav { background: #f8fff9; padding: 1rem 0; border-bottom: 1px solid #e9ecef; }
        .breadcrumb-custom { background: none; padding: 0; margin: 0; font-size: 0.9rem; }
        .breadcrumb-custom .breadcrumb-item a { color: #389B5B; text-decoration: none; }
        .breadcrumb-custom .breadcrumb-item.active { color: #6c757d; }
        .table-responsive table { table-layout: auto; width: auto; min-width: 100%; }
        .action-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; margin: 0 2px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

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
                        <h5 class="mb-0"><i class="fas fa-headphones"></i> Audiometric History</h5>
                        <div class="btn-group">
                            <?php if ($show_all): ?>
                                <a href="<?php echo app_url('audiometric_history'); ?>?company_id=<?php echo $company_id; ?><?php echo $patient_id > 0 ? '&patient_id=' . $patient_id : ''; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-filter"></i> Filter by Company
                                </a>
                            <?php endif; ?>
                            <?php if ($patient_id > 0): ?>
                                <a href="<?php echo app_url('audiometric'); ?>?patient_id=<?php echo $patient_id; ?>&new=1" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add New Audiometric
                                </a>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label small">Test Date</label>
                                <input type="date" class="form-control" id="dateFilter">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Otoscopy</label>
                                <select class="form-select" id="otoscopyFilter">
                                    <option value="">All</option>
                                    <option value="normal">Normal</option>
                                    <option value="abnormal">Abnormal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">&nbsp;</label>
                                <div>
                                    <button class="btn btn-outline-secondary" onclick="clearFilters()">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Results Counter -->
                        <div class="row mb-2">
                            <div class="col-12">
                                <div class="text-muted"><small id="resultsCounter">Showing all audiometric tests</small></div>
                            </div>
                        </div>

                        <?php if ($show_all && !empty($grouped_by_company)): ?>
                            <?php foreach ($grouped_by_company as $company_name => $items): ?>
                                <div class="mb-4">
                                    <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-building"></i> <?php echo htmlspecialchars($company_name); ?> <span class="badge bg-secondary ms-2"><?php echo count($items); ?> records</span></h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm">
                                            <thead>
                                                <tr style="text-align:center;">
                                                    <th>NO</th>
                                                    <th>PATIENT NAME</th>
                                                    <th>TEST DATE</th>
                                                    <th>OTOSCOPY</th>
                                                    <th>AUDIOMETER</th>
                                                    <th>ENTERED BY</th>
                                                    <th>ACTIONS</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $index => $r): ?>
                                                <tr data-test-date="<?php echo isset($r['examination_date']) ? date('d/m/Y', strtotime($r['examination_date'])) : 'N/A'; ?>" data-otoscopy="<?php echo strtolower($r['otoscopy'] ?? ''); ?>">
                                                    <td style="text-align:center;"><?php echo $index + 1; ?></td>
                                                    <td><strong><?php echo htmlspecialchars(ucwords(strtolower(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')))); ?></strong></td>
                                                    <td><?php echo isset($r['examination_date']) ? date('d/m/Y', strtotime($r['examination_date'])) : 'N/A'; ?></td>
                                                    <td><?php echo htmlspecialchars(strtoupper($r['otoscopy'] ?? '-')); ?></td>
                                                    <td><?php echo htmlspecialchars($r['audiometer'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($r['created_by'] ?? '-'); ?></td>
                                                    <td style="text-align:center;">
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="<?php echo app_url('audiometric_view'); ?>?test_id=<?php echo $r['id']; ?>&patient_id=<?php echo $r['patient_id']; ?><?php echo !empty($r['examination_date']) ? '&test_date=' . urlencode($r['examination_date']) : ''; ?><?php if (!empty($r['company_name'])): ?><?php 
                                                                try {
                                                                    $compStmt = $clinic_pdo->prepare("SELECT id FROM company WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?)) LIMIT 1");
                                                                    $compStmt->execute([$r['company_name']]);
                                                                    $compRow = $compStmt->fetch(PDO::FETCH_ASSOC);
                                                                    if ($compRow) { echo '&company_id=' . $compRow['id']; }
                                                                } catch (Exception $e) { }
                                                            ?><?php endif; ?>" class="btn btn-outline-primary" title="View Audiometric Results"><i class="fas fa-eye"></i></a>
                                                            <a href="<?php echo app_url('audiometric_edit'); ?>?test_id=<?php echo $r['id']; ?>&patient_id=<?php echo $r['patient_id']; ?><?php echo !empty($r['examination_date']) ? '&test_date=' . urlencode($r['examination_date']) : ''; ?><?php if (!empty($r['company_name'])): ?><?php 
                                                                try {
                                                                    $compStmt = $clinic_pdo->prepare("SELECT id FROM company WHERE TRIM(LOWER(company_name)) = TRIM(LOWER(?)) LIMIT 1");
                                                                    $compStmt->execute([$r['company_name']]);
                                                                    $compRow = $compStmt->fetch(PDO::FETCH_ASSOC);
                                                                    if ($compRow) { echo '&company_id=' . $compRow['id']; }
                                                                } catch (Exception $e) { }
                                                            ?><?php endif; ?>" class="btn btn-outline-warning" title="Edit Audiometric Test"><i class="fas fa-edit"></i></a>
                                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(ucwords(strtolower(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')))); ?>')" title="Delete Test"><i class="fas fa-trash"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr style="text-align:center;">
                                            <th>NO</th>
                                            <th>PATIENT NAME</th>
                                            <th>TEST DATE</th>
                                            <th>OTOSCOPY</th>
                                            <th>AUDIOMETER</th>
                                            <th>ENTERED BY</th>
                                            <th>ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="testsTableBody">
                                        <?php foreach ($records as $index => $r): ?>
                                        <tr data-test-date="<?php echo isset($r['examination_date']) ? date('d/m/Y', strtotime($r['examination_date'])) : 'N/A'; ?>" data-otoscopy="<?php echo strtolower($r['otoscopy'] ?? ''); ?>">
                                            <td style="text-align:center;">&nbsp;<?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars(ucwords(strtolower(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')))); ?></strong></td>
                                            <td><?php echo isset($r['examination_date']) ? date('d/m/Y', strtotime($r['examination_date'])) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars(strtoupper($r['otoscopy'] ?? '-')); ?></td>
                                            <td><?php echo htmlspecialchars($r['audiometer'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($r['created_by'] ?? '-'); ?></td>
                                            <td style="text-align:center;">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo app_url('audiometric_view'); ?>?test_id=<?php echo $r['id']; ?>&patient_id=<?php echo $r['patient_id']; ?><?php echo !empty($r['examination_date']) ? '&test_date=' . urlencode($r['examination_date']) : ''; ?><?php echo !empty($company_id) ? '&company_id=' . $company_id : ''; ?>" class="btn btn-outline-primary" title="View Audiometric Results"><i class="fas fa-eye"></i></a>
                                                    <a href="<?php echo app_url('audiometric_edit'); ?>?test_id=<?php echo $r['id']; ?>&patient_id=<?php echo $r['patient_id']; ?><?php echo !empty($r['examination_date']) ? '&test_date=' . urlencode($r['examination_date']) : ''; ?><?php echo !empty($company_id) ? '&company_id=' . $company_id : ''; ?>" class="btn btn-outline-warning" title="Edit Audiometric Test"><i class="fas fa-edit"></i></a>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(ucwords(strtolower(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')))); ?>')" title="Delete Test"><i class="fas fa-trash"></i></button>
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

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this audiometric test for <strong id="patientNameToDelete"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete_audiometry">
                        <input type="hidden" name="test_id" id="testIdToDelete">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete Test</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(testId, patientName) {
            document.getElementById('testIdToDelete').value = testId;
            document.getElementById('patientNameToDelete').textContent = patientName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function filterTests() {
            const dateVal = document.getElementById('dateFilter').value;
            const otoscopy = document.getElementById('otoscopyFilter').value;
            const rows = document.querySelectorAll('#testsTableBody tr');
            let visibleCount = 0;
            rows.forEach(row => {
                const testDate = row.getAttribute('data-test-date');
                const oto = row.getAttribute('data-otoscopy');
                let matchesDate = true;
                if (dateVal) {
                    const parts = testDate.split('/');
                    if (parts.length === 3) {
                        const ymd = parts[2] + '-' + parts[1].padStart(2,'0') + '-' + parts[0].padStart(2,'0');
                        matchesDate = ymd === dateVal;
                    } else { matchesDate = false; }
                }
                const matchesOto = otoscopy === '' || oto === otoscopy;
                if (matchesDate && matchesOto) { row.style.display=''; visibleCount++; } else { row.style.display='none'; }
            });
            updateResultsCount(visibleCount);
        }

        function clearFilters() {
            document.getElementById('dateFilter').value = '';
            document.getElementById('otoscopyFilter').value = '';
            filterTests();
        }

        function updateResultsCount(count) {
            const total = document.querySelectorAll('#testsTableBody tr').length;
            const text = count === total ? `Showing all ${total} audiometric tests` : `Showing ${count} of ${total} audiometric tests`;
            const el = document.getElementById('resultsCounter');
            if (el) el.textContent = text;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dateFilter = document.getElementById('dateFilter');
            const otoFilter = document.getElementById('otoscopyFilter');
            if (dateFilter) dateFilter.addEventListener('change', filterTests);
            if (otoFilter) otoFilter.addEventListener('change', filterTests);
            updateResultsCount(document.querySelectorAll('#testsTableBody tr').length);
        });
    </script>
</body>
</html>
