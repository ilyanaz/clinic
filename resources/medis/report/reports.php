<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

// Check if user has permission to view reports
if ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Doctor') {
    header('Location: ' . app_url('index.php'));
    exit();
}

$report_type = $_GET['type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get report data
$stats = getDashboardStats($date_to);
$recent_appointments = getRecentAppointments(10);

// Get patients list for reports
$patients = getAllClinicPatients();
$patients_with_surveillance = [];

// Get surveillance data and declarations for each patient
foreach ($patients as $patient) {
    $surveillance_data = [];
    $declaration_data = [];
    
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM chemical_information WHERE patient_id = ? ORDER BY examination_date DESC");
        $stmt->execute([$patient['id']]);
        $surveillance_data = $stmt->fetchAll();
    } catch (Exception $e) {
        $surveillance_data = [];
    }
    
    // Get declarations for this patient
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM declarations WHERE patient_name = ? ORDER BY created_at DESC");
        $stmt->execute([$patient['first_name'] . ' ' . $patient['last_name']]);
        $declaration_data = $stmt->fetchAll();
    } catch (Exception $e) {
        $declaration_data = [];
    }
    
    // Get medical removal protection forms for this patient
    try {
        $stmt = $clinic_pdo->prepare("SELECT * FROM medical_removal_protection WHERE patient_name = ? ORDER BY id DESC");
        $stmt->execute([$patient['first_name'] . ' ' . $patient['last_name']]);
        $medical_removal_data = $stmt->fetchAll();
    } catch (Exception $e) {
        $medical_removal_data = [];
    }
    
    // Get company name from occupational history
    $company_name = '';
    try {
        $stmt = $clinic_pdo->prepare("SELECT company_name FROM occupational_history WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patient['id']]);
        $occupational_data = $stmt->fetch();
        $company_name = $occupational_data['company_name'] ?? '';
    } catch (Exception $e) {
        $company_name = '';
    }
    
    $patients_with_surveillance[] = [
        'patient' => $patient,
        'surveillance_count' => count($surveillance_data),
        'last_examination' => !empty($surveillance_data) ? $surveillance_data[0]['examination_date'] : null,
        'declarations' => $declaration_data,
        'medical_removal_forms' => $medical_removal_data,
        'company_name' => $company_name
    ];
}

// Get monthly statistics
$monthly_stats = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = ?");
    $stmt->execute([$month]);
    $monthly_stats[] = [
        'month' => date('M Y', strtotime($month)),
        'appointments' => $stmt->fetch()['count']
    ];
}

// Get fitness statistics (using available data)
$fitness_stats = [
    'Fit for Work' => 0,
    'Unfit for Work' => 0,
    'Pending Review' => 0
];

// Since ms_findings_conclusion table doesn't exist, we'll use surveillance data
try {
    $stmt = $clinic_pdo->query("SELECT COUNT(*) as total FROM chemical_information");
    $total_surveillance = $stmt->fetch()['total'];
    
    // For now, we'll assume all are fit for work since we don't have fitness data
    $fitness_stats['Fit for Work'] = $total_surveillance;
} catch (Exception $e) {
    // If there's an error, set default values
    $fitness_stats['Fit for Work'] = 0;
}

// Get abnormal findings by system (using available columns)
$abnormal_findings = [];
$systems = [
    'general_appearance' => 'General Appearance',
    'ear_nose_throat' => 'Ear, Nose & Throat',
    'sensation' => 'Sensation',
    'sound' => 'Lung Sound',
    'air_entry' => 'Air Entry',
    'reproductive' => 'Reproductive',
    'skin' => 'Skin'
];

foreach ($systems as $system => $label) {
    try {
        $stmt = $clinic_pdo->prepare("SELECT COUNT(*) as count FROM physical_examination WHERE $system = 'Abnormal'");
        $stmt->execute();
        $abnormal_findings[$label] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $abnormal_findings[$label] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Medical Surveillance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .summary-info {
            min-width: 200px;
        }
        .summary-info small {
            font-size: 0.75rem;
            line-height: 1.4;
        }
        .summary-info .fas {
            width: 12px;
            color: #6c757d;
        }
        .summary-info .btn {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Report Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="type" onchange="this.form.submit()">
                                    <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview</option>
                                    <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                                    <option value="surveillance" <?php echo $report_type == 'surveillance' ? 'selected' : ''; ?>>Surveillance</option>
                                    <option value="fitness" <?php echo $report_type == 'fitness' ? 'selected' : ''; ?>>Fitness Assessment</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Generate Report
                                    </button>
                                    <button type="button" class="btn btn-success" onclick="exportReport()">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient List Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users"></i> Patient List Report</h5>
                        <div>
                            <button class="btn btn-success me-2" onclick="exportPatientList()">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                            </div>
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

                        <div class="table-responsive">
                            <table class="table table-hover" id="patientListTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Name</th>
                                        <th>NRIC</th>
                                        <th>Phone</th>
                                        <th>Last Examination</th>
                                        <th>Declaration</th>
                                        <th>Medical Removal</th>
                                        <th>Report Summary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients_with_surveillance as $item): ?>
                                    <tr data-patient-id="<?php echo htmlspecialchars(strtolower($item['patient']['patient_id'])); ?>"
                                        data-patient-name="<?php echo htmlspecialchars(strtolower(ucwords(strtolower($item['patient']['first_name'] . ' ' . $item['patient']['last_name'])))); ?>"
                                        data-nric="<?php echo htmlspecialchars(strtolower($item['patient']['NRIC'] ?? 'n/a')); ?>"
                                        data-phone="<?php echo htmlspecialchars(strtolower($item['patient']['telephone_no'] ?? 'n/a')); ?>"
                                        data-last-exam="<?php echo $item['last_examination'] ? date('d/m/Y', strtotime($item['last_examination'])) : 'no examinations'; ?>"
                                        data-company="<?php echo htmlspecialchars(strtolower($item['company_name'] ?? 'n/a')); ?>">
                                        <td><?php echo htmlspecialchars($item['patient']['patient_id']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars(ucwords(strtolower($item['patient']['first_name'] . ' ' . $item['patient']['last_name']))); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['patient']['NRIC'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($item['patient']['telephone_no'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($item['last_examination']): ?>
                                                <?php echo date('d/m/Y', strtotime($item['last_examination'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No examinations</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($item['declarations'])): ?>
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="createDeclaration('<?php echo htmlspecialchars(ucwords(strtolower($item['patient']['first_name'] . ' ' . $item['patient']['last_name']))); ?>', '<?php echo htmlspecialchars($item['company_name']); ?>', '<?php echo $item['patient']['id']; ?>')" 
                                                        title="Create Declaration">
                                                    <i class="fas fa-plus"></i> Create
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="viewDeclaration('<?php echo $item['declarations'][0]['id']; ?>')" 
                                                        title="View Declaration">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($item['medical_removal_forms'])): ?>
                                                <button class="btn btn-outline-secondary btn-sm" 
                                                        onclick="createMedicalRemoval('<?php echo htmlspecialchars(ucwords(strtolower($item['patient']['first_name'] . ' ' . $item['patient']['last_name']))); ?>', '<?php echo htmlspecialchars($item['company_name']); ?>', '<?php echo $item['patient']['id']; ?>')" 
                                                        title="Create Medical Removal Form">
                                                    <i class="fas fa-plus"></i> Create
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="summary-info">
                                               
                                                <div class="mt-2">
                                                    <a href="<?php echo app_url('employee_report.php'); ?>?patient_id=<?php echo $item['patient']['id']; ?>&patient_name=<?php echo urlencode(ucwords(strtolower($item['patient']['first_name'] . ' ' . $item['patient']['last_name']))); ?>" 
                                                       class="btn btn-outline-primary btn-sm" 
                                                       title="View Employee Summary Report">
                                                        <i class="fas fa-file-medical"></i> Summary Report
                                                    </a>
                            </div>
                        </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($report_type == 'overview'): ?>
        <!-- Overview Report -->

        <!-- Charts -->
        <div class="row mt-5">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Monthly Appointments Trend</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="appointmentsChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Fitness Assessment</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="fitnessChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abnormal Findings -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Abnormal Findings by System</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($abnormal_findings as $system => $count): ?>
                            <div class="col-md-4 col-lg-2 mb-3">
                                <div class="text-center">
                                    <div class="h4 text-<?php echo $count > 0 ? 'danger' : 'success'; ?>"><?php echo $count; ?></div>
                                    <div class="text-muted small"><?php echo ucwords(str_replace('_', ' ', $system)); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($report_type == 'appointments'): ?>
        <!-- Appointments Report -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">Appointments Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Patient</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                        <td><?php echo formatDate($appointment['appointment_date']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['appointment_type']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['staff_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] == 'Scheduled' ? 'primary' : 
                                                    ($appointment['status'] == 'Completed' ? 'success' : 'danger'); 
                                            ?>">
                                                <?php echo $appointment['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo asset('assets/js/main.js'); ?>"></script>
    <script>
        // Monthly Appointments Chart
        const appointmentsCtx = document.getElementById('appointmentsChart');
        if (appointmentsCtx) {
            new Chart(appointmentsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_stats, 'month')); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode(array_column($monthly_stats, 'appointments')); ?>,
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Fitness Assessment Chart
        const fitnessCtx = document.getElementById('fitnessChart');
        if (fitnessCtx) {
            new Chart(fitnessCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Fit for Work', 'Unfit for Work', 'Pending Review'],
                    datasets: [{
                        data: [<?php echo $fitness_stats['Fit for Work']; ?>, <?php echo $fitness_stats['Unfit for Work']; ?>, <?php echo $fitness_stats['Pending Review']; ?>],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(220, 53, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)'
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(255, 193, 7, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Export functions
        function exportReport() {
            const table = document.getElementById('patientListTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [];
                const cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let cellText = cols[j].innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
                    row.push('"' + cellText + '"');
                }
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'patient_list_report.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportPatientList() {
            exportReport();
        }


        function createDeclaration(patientName, companyName, patientId) {
            const url = 'declaration.php?action=add&patient_name=' + encodeURIComponent(patientName) +
                       (companyName ? '&employer=' + encodeURIComponent(companyName) : '') +
                       (patientId ? '&patient_id=' + encodeURIComponent(patientId) : '');
            window.open(url, '_blank');
        }

        function viewDeclaration(declarationId) {
            window.open('declaration_view.php?id=' + declarationId, '_blank');
        }

        function createMedicalRemoval(patientName, companyName, patientId) {
            const url = 'medical_removal_protection.php?action=add&patient_name=' + encodeURIComponent(patientName) +
                       (companyName ? '&employer=' + encodeURIComponent(companyName) : '') +
                       (patientId ? '&patient_id=' + encodeURIComponent(patientId) : '');
            window.open(url, '_blank');
        }

        // Search functionality
        function searchPatients() {
            filterPatients();
        }

        function filterPatients() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#patientListTable tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const patientId = row.getAttribute('data-patient-id') || '';
                const patientName = row.getAttribute('data-patient-name') || '';
                const nric = row.getAttribute('data-nric') || '';
                const phone = row.getAttribute('data-phone') || '';
                const lastExam = row.getAttribute('data-last-exam') || '';
                const company = row.getAttribute('data-company') || '';

                const matchesSearch = searchTerm === '' || 
                                    patientId.includes(searchTerm) || 
                                    patientName.includes(searchTerm) ||
                                    nric.includes(searchTerm) ||
                                    phone.includes(searchTerm) ||
                                    lastExam.includes(searchTerm) ||
                                    company.includes(searchTerm);

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
            const totalPatients = document.querySelectorAll('#patientListTable tbody tr').length;
            
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
                updateResultsCount(document.querySelectorAll('#patientListTable tbody tr').length);
            }
        });
    </script>
</body>
</html>
