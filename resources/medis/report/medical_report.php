<?php
session_start();
require_once __DIR__ . '/../../../config/clinic_database.php';
require_once __DIR__ . '/../../../app/Services/clinic_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

// Check if user has permission (Admin or Doctor)
if ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Doctor') {
    header('Location: ' . app_url('index.php'));
    exit();
}

// Get parameters
$report_type = $_GET['report_type'] ?? '';
$patient_id = $_GET['patient_id'] ?? null;
$company_id = $_GET['company_id'] ?? null;

// Initialize variables
$all_companies = [];
$all_patients = [];
$company_patients = [];
$error_message = '';

// Get all companies for dropdown
try {
    $stmt = $clinic_pdo->query("SELECT id, company_name FROM company ORDER BY company_name");
    $all_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error retrieving companies: " . $e->getMessage();
}

// Get company-specific patients if company is selected
if ($company_id) {
    try {
        $stmt = $clinic_pdo->prepare("
            SELECT p.id, p.first_name, p.last_name, p.patient_id, p.NRIC, p.passport_no
            FROM patient_information p
            INNER JOIN occupational_history oh ON p.id = oh.patient_id
            INNER JOIN company c ON oh.company_name = c.company_name
            WHERE c.id = ?
            ORDER BY p.first_name, p.last_name
        ");
        $stmt->execute([$company_id]);
        $company_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "Error retrieving company patients: " . $e->getMessage();
    }
}

// Get all patients for dropdown (fallback)
try {
    $stmt = $clinic_pdo->query("SELECT id, first_name, last_name, patient_id FROM patient_information ORDER BY first_name, last_name");
    $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error retrieving patients: " . $e->getMessage();
}

// Get all chemicals from surveillance metadata
$all_chemicals = [];
try {
    $stmt = $clinic_pdo->query("
        SELECT DISTINCT chemical 
        FROM chemical_information 
        WHERE chemical IS NOT NULL AND chemical != '' AND chemical != 'NA'
        ORDER BY chemical
    ");
    $all_chemicals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Error retrieving chemicals: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo asset('assets/css/style.css'); ?>" rel="stylesheet">
    <style>
        .report-card {
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
        }
        
        .report-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .report-card.selected {
            border-color: var(--primary-color);
            background-color: #f8fff9;
        }
        
        .report-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .selection-section {
            background: #f8fff9;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--label-color);
        }
        
        .btn-generate {
            background: linear-gradient(135deg, var(--primary-color), #2d5a3d);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(56, 155, 91, 0.3);
        }
        
        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 155, 91, 0.4);
        }
        
        .btn-generate:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .selection-summary {
            background: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../views/includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">
                    <i class="fas fa-file-medical-alt"></i> Medical Report
                </h3>
                
            </div>
            <div class="card-body">
                
                <!-- Report Type Selection -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <h4 class="mb-4">
                            <i class="fas fa-chart-bar"></i> Select Report
                        </h4>
                    </div>
                    
                    <!-- Employee Medical Report -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card report-card h-100" onclick="selectReport('employee')" data-report="employee">
                            <div class="card-body text-center">
                                <div class="report-icon text-primary">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <h5 class="card-title">Employee Medical Report</h5>
                                <p class="card-text text-muted">
                                    Individual employee medical surveillance report with comprehensive health assessment
                                </p>
                                <div class="badge bg-primary">USECHH 2</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MS Summary Report -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card report-card h-100" onclick="selectReport('ms')" data-report="ms">
                            <div class="card-body text-center">
                                <div class="report-icon text-success">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h5 class="card-title">MS Summary Report</h5>
                                <p class="card-text text-muted">
                                    Medical surveillance summary for entire company workforce. Requires chemical selection.
                                </p>
                                <div class="badge bg-success">USECHH 4</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Abnormal Workers Report -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card report-card h-100" onclick="selectReport('abnormal')" data-report="abnormal">
                            <div class="card-body text-center">
                                <div class="report-icon text-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h5 class="card-title">Abnormal Workers Report</h5>
                                <p class="card-text text-muted">
                                    Report of workers with abnormal medical findings requiring attention
                                </p>
                                <div class="badge bg-warning">USECHH 5</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Removal Protection -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card report-card h-100" onclick="selectReport('medical-removal')" data-report="medical-removal">
                            <div class="card-body text-center">
                                <div class="report-icon text-danger">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h5 class="card-title">Medical Removal Protection</h5>
                                <p class="card-text text-muted">
                                    Medical removal protection form for occupational health hazards
                                </p>
                                <div class="badge bg-danger">USECHH 5i</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Declaration Form -->
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card report-card h-100" onclick="selectReport('declaration')" data-report="declaration">
                            <div class="card-body text-center">
                                <div class="report-icon text-info">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <h5 class="card-title">Medical Declaration Form</h5>
                                <p class="card-text text-muted">
                                    Digital declaration form with e-signature functionality for patient consent
                                </p>
                                <div class="badge bg-info">USECHH 1</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Selection Section - Hidden until report type is selected -->
                <div class="selection-section" id="selectionSection" style="display: none;">
                    
                    <div class="row">
                        <!-- Company Selection -->
                        <div class="col-md-6 mb-3">
                            <label for="companySelect" class="form-label">
                                <i class="fas fa-building"></i> Select Company
                            </label>
                            <select class="form-select" id="companySelect" onchange="updateEmployeeDropdown()">
                                <option value="">-- Select Company --</option>
                                <?php foreach ($all_companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" 
                                            <?php echo $company_id == $company['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucwords(strtolower($company['company_name']))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Employee/Patient Selection -->
                        <div class="col-md-6 mb-3">
                            <label for="patientSelect" class="form-label">
                                <i class="fas fa-user-md"></i> Select Employee (Optional)
                            </label>
                            <select class="form-select" id="patientSelect" onchange="updateSelection()">
                                <option value="">-- All Employees --</option>
                                <?php if (!empty($company_patients)): ?>
                                    <?php foreach ($company_patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" 
                                                <?php echo $patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucwords(strtolower($patient['first_name'] . ' ' . $patient['last_name']))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted">
                                <?php if (!empty($company_patients)): ?>
                                    Found <?php echo count($company_patients); ?> employees
                                <?php else: ?>
                                    Select a company first to see employees
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- Chemical Selection (for MS Summary Report) -->
                    <div class="row" id="chemicalSelection" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label for="chemicalSelect" class="form-label">
                                <i class="fas fa-flask"></i> Select Chemical of Examination
                            </label>
                            <select class="form-select" id="chemicalSelect" onchange="updateSelection()">
                                <option value="">-- Select Chemical --</option>
                                <?php if (!empty($all_chemicals)): ?>
                                    <?php foreach ($all_chemicals as $chemical): ?>
                                        <option value="<?php echo htmlspecialchars($chemical['chemical']); ?>">
                                            <?php echo htmlspecialchars(ucwords(strtolower($chemical['chemical']))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <option value="Other">Other (Specify)</option>
                            </select>
                            <small class="text-muted">Required for MS Summary Report - Select company first to see available chemicals</small>
                        </div>
                        <div class="col-md-6 mb-3" id="otherChemicalInput" style="display: none;">
                            <label for="otherChemical" class="form-label">
                                <i class="fas fa-edit"></i> Specify Other Chemical
                            </label>
                            <input type="text" class="form-control" id="otherChemical" placeholder="Enter chemical name" onchange="updateSelection()">
                        </div>
                    </div>
                    
                    <!-- Selection Summary -->
                    <div id="selectionSummary" class="selection-summary" style="display: none;">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Company:</strong> <span id="selectedCompany">-</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Employee:</strong> <span id="selectedEmployee">All Employees</span>
                            </div>
                            <div class="col-md-4" id="chemicalSummary" style="display: none;">
                                <strong>Chemical:</strong> <span id="selectedChemical">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Generate Button -->
                <div class="text-center mt-4">
                    <button class="btn btn-generate btn-lg me-3" id="generateBtn" onclick="generateReport()" disabled>
                        <i class="fas fa-file-export"></i> Generate Report
                    </button>
                </div>
                
            </div>
        </div>
    </div>

        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedReport = '';
        
        function updateEmployeeDropdown() {
            const companyId = document.getElementById('companySelect').value;
            const patientSelect = document.getElementById('patientSelect');
            const chemicalSelect = document.getElementById('chemicalSelect');
            
            // Clear current options except the first one
            patientSelect.innerHTML = '<option value="">-- All Employees --</option>';
            chemicalSelect.innerHTML = '<option value="">-- Select Chemical --</option>';
            
            if (companyId) {
                // Get company name from the selected option
                const companySelect = document.getElementById('companySelect');
                const companyName = companySelect.options[companySelect.selectedIndex].text;
                
                // Fetch employees for selected company
                fetch(`get_company_employees.php?company_name=${encodeURIComponent(companyName)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Populate employees
                            data.employees.forEach(employee => {
                                const option = document.createElement('option');
                                option.value = employee.id;
                                option.textContent = `${employee.first_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')} ${employee.last_name.split(' ').map(name => name.charAt(0).toUpperCase() + name.slice(1).toLowerCase()).join(' ')}`;
                                patientSelect.appendChild(option);
                            });
                            
                            // Update selection summary
                            updateSelection();
                        } else {
                            console.error('Error fetching data:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
            
            // Populate chemicals from the original PHP data
            const allChemicals = <?php echo json_encode($all_chemicals); ?>;
            allChemicals.forEach(chemical => {
                const option = document.createElement('option');
                option.value = chemical.chemical;
                option.textContent = chemical.chemical.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
                chemicalSelect.appendChild(option);
            });
            
            // Add "Other" option for chemicals
            const otherOption = document.createElement('option');
            otherOption.value = 'Other';
            otherOption.textContent = 'Other (Specify)';
            chemicalSelect.appendChild(otherOption);
            
            updateSelection();
        }
        
        function updateSelection() {
            const companySelect = document.getElementById('companySelect');
            const patientSelect = document.getElementById('patientSelect');
            const chemicalSelect = document.getElementById('chemicalSelect');
            const otherChemical = document.getElementById('otherChemical');
            const summaryDiv = document.getElementById('selectionSummary');
            const selectedCompanySpan = document.getElementById('selectedCompany');
            const selectedEmployeeSpan = document.getElementById('selectedEmployee');
            const selectedChemicalSpan = document.getElementById('selectedChemical');
            
            const companyText = companySelect.options[companySelect.selectedIndex].text;
            const employeeText = patientSelect.value ? patientSelect.options[patientSelect.selectedIndex].text : 'All Employees';
            
            // Handle chemical selection
            let chemicalText = '';
            if (chemicalSelect.value) {
                if (chemicalSelect.value === 'Other') {
                    chemicalText = otherChemical.value || 'Other (not specified)';
                } else {
                    chemicalText = chemicalSelect.value;
                }
                selectedChemicalSpan.textContent = chemicalText;
            }
            
            // Show/hide "Other" input based on chemical selection
            const otherChemicalInput = document.getElementById('otherChemicalInput');
            if (chemicalSelect.value === 'Other') {
                otherChemicalInput.style.display = 'block';
            } else {
                otherChemicalInput.style.display = 'none';
            }
            
            if (companySelect.value) {
                selectedCompanySpan.textContent = companyText;
                selectedEmployeeSpan.textContent = employeeText;
                summaryDiv.style.display = 'block';
                
                // Check if all required fields are filled
                let canGenerate = true;
                if (selectedReport === 'ms' && !chemicalSelect.value) {
                    canGenerate = false;
                } else if (selectedReport === 'employee' && !patientSelect.value) {
                    canGenerate = false;
                } else if (selectedReport === 'medical-removal' && !patientSelect.value) {
                    canGenerate = false;
                }
                
                document.getElementById('generateBtn').disabled = !canGenerate;
                document.getElementById('downloadPdfBtn').disabled = !canGenerate;
            } else {
                summaryDiv.style.display = 'none';
                document.getElementById('generateBtn').disabled = true;
                document.getElementById('downloadPdfBtn').disabled = true;
            }
        }
        
        function selectReport(reportType) {
            // Remove previous selection
            document.querySelectorAll('.report-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            document.querySelector(`[data-report="${reportType}"]`).classList.add('selected');
            selectedReport = reportType;
            
            // Show the selection section now that a report type is selected
            document.getElementById('selectionSection').style.display = 'block';
            
            // Show/hide chemical selection based on report type
            const chemicalSelection = document.getElementById('chemicalSelection');
            const chemicalSummary = document.getElementById('chemicalSummary');
            
            if (reportType === 'ms') {
                chemicalSelection.style.display = 'block';
                chemicalSummary.style.display = 'block';
            } else {
                chemicalSelection.style.display = 'none';
                chemicalSummary.style.display = 'none';
                // Clear chemical selection when not needed
                document.getElementById('chemicalSelect').value = '';
                document.getElementById('otherChemical').value = '';
                document.getElementById('otherChemicalInput').style.display = 'none';
            }
            
            // Update selection summary
            updateSelection();
        }
        
        function downloadPDF() {
            const companyId = document.getElementById('companySelect').value;
            const patientId = document.getElementById('patientSelect').value;
            const chemicalSelect = document.getElementById('chemicalSelect');
            const reportType = document.querySelector('.report-card.selected')?.id;
            
            if (!reportType) {
                alert('Please select a report type first.');
                return;
            }
            
            // Special handling for declaration form
            if (reportType === 'declaration') {
                window.open('declaration.php', '_blank');
                return;
            }
            
            if (!companyId) {
                alert('Please select a company first.');
                return;
            }
            
            // Build URL parameters
            let url = 'generate_report_pdf.php?type=' + reportType + '&company_id=' + companyId;
            
            if (patientId) {
                url += '&patient_id=' + patientId;
            }
            
            if (reportType === 'ms' && chemicalSelect && chemicalSelect.value) {
                url += '&chemical=' + encodeURIComponent(chemicalSelect.value);
            }
            
            // Open PDF in new tab
            window.open(url, '_blank');
        }
        
        function generateReport() {
            const companyId = document.getElementById('companySelect').value;
            const patientId = document.getElementById('patientSelect').value;
            const chemicalSelect = document.getElementById('chemicalSelect');
            const otherChemical = document.getElementById('otherChemical');
            
            if (!selectedReport) {
                alert('Please select a report type.');
                return;
            }
            
            // Declaration form doesn't require company/employee selection
            if (selectedReport === 'declaration') {
                // Declaration can work without company/employee selection
                // We'll handle the URL building in the switch statement
            } else if (!companyId) {
                alert('Please select a company.');
                return;
            }
            
            // Validate employee selection for reports that require it
            if (selectedReport === 'employee' || selectedReport === 'medical-removal') {
                if (!patientId) {
                    alert('Please select an employee for this report type.');
                    return;
                }
            }
            
            // Validate chemical selection for MS reports
            if (selectedReport === 'ms') {
                if (!chemicalSelect.value) {
                    alert('Please select a chemical of examination for MS Summary Report.');
                    return;
                }
                if (chemicalSelect.value === 'Other' && !otherChemical.value.trim()) {
                    alert('Please specify the chemical name.');
                    return;
                }
            }
            
            let url = '';
            
            switch (selectedReport) {
                case 'employee':
                    url = `employee_report.php?patient_id=${patientId}`;
                    break;
                case 'ms':
                    let chemical = chemicalSelect.value;
                    if (chemical === 'Other') {
                        chemical = otherChemical.value.trim();
                    }
                    url = `ms_report.php?company_id=${companyId}&chemical=${encodeURIComponent(chemical)}`;
                    break;
                case 'abnormal':
                    url = `abnormal_workers_report.php?company_id=${companyId}`;
                    break;
                case 'medical-removal':
                    url = `medical_removal_protection.php?patient_id=${patientId}`;
                    break;
                case 'declaration':
                    // For declaration, we can pass patient info if available
                    let declarationUrl = 'declaration.php';
                    if (patientId) {
                        // Get patient name from the selected patient
                        const patientSelect = document.getElementById('patientSelect');
                        const selectedOption = patientSelect.options[patientSelect.selectedIndex];
                        const patientName = selectedOption.text;
                        declarationUrl += `?patient_name=${encodeURIComponent(patientName)}`;
                    }
                    if (companyId) {
                        // Get company name from the selected company
                        const companySelect = document.getElementById('companySelect');
                        const selectedCompanyOption = companySelect.options[companySelect.selectedIndex];
                        const companyName = selectedCompanyOption.text;
                        declarationUrl += (declarationUrl.includes('?') ? '&' : '?') + `employer=${encodeURIComponent(companyName)}`;
                    }
                    url = declarationUrl;
                    break;
                default:
                    alert('Invalid report type selected.');
                    return;
            }
            
            // Open report in new tab
            window.open(url, '_blank');
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelection();
        });
    </script>
</body>
</html>