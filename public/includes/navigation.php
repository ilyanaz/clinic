<?php
// Get user role and name for navigation
$user_role = $_SESSION['role'] ?? 'Guest';
$user_name = $_SESSION['username'] ?? 'Guest';
$user_first_name = $_SESSION['first_name'] ?? $user_name;
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <div class="logo-small">
                <img src="medical_surveillance_logo.png" alt="Medical Surveillance Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <i class="fas fa-hospital" style="display: none; font-size: 1.2rem; color: white;"></i>
            </div>
            <div class="brand-text">
                <div class="brand-title">Medical Surveillance System</div>
                <div class="brand-subtitle">Healthcare Management</div>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <!-- Medical Surveillance Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['surveillance', 'surveillance_list', 'company', 'patients']) ? 'active' : ''; ?>" href="#" id="medicalSurveillanceDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i> Medical Surveillance
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="company.php"><i class="fas fa-building"></i> List of Companies</a></li>
                        <li><a class="dropdown-item" href="patients.php"><i class="fas fa-users"></i> List of Patients</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="medical.php"><i class="fas fa-notes-medical"></i> Examination</a></li>
                    </ul>
                </li>
                
                <!-- Audiometry Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="audiometryDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-headphones"></i> Audiometry
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="company.php?type=audiometry"><i class="fas fa-building"></i> List of Companies</a></li>
                        <li><a class="dropdown-item" href="patients.php?type=audiometry"><i class="fas fa-users"></i> List of Patients</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="audio.php"><i class="fas fa-headphones"></i> Examination</a></li>
                    </ul>
                </li>
                
                <!-- Reports Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($current_page, ['reports', 'medical_report', 'employee_report', 'ms_report', 'abnormal_workers_report']) ? 'active' : ''; ?>" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($user_role == 'Admin' || $user_role == 'Doctor'): ?>
                        <li><a class="dropdown-item" href="reports.php"><i class="fas fa-chart-line"></i> General Reports</a></li>
                        <?php endif; ?>
                        
                        <?php if ($user_role == 'Doctor'): ?>
                        <li><a class="dropdown-item" href="medical_report.php"><i class="fas fa-clipboard-list"></i> Medical Reports</a></li>
                        <?php else: ?>
                        <li><a class="dropdown-item" href="employee_report.php"><i class="fas fa-user-md"></i> Employee Report</a></li>
                        <li><a class="dropdown-item" href="ms_report.php"><i class="fas fa-file-medical-alt"></i> MS Summary Report</a></li>
                        <li><a class="dropdown-item" href="abnormal_workers_report.php"><i class="fas fa-exclamation-triangle"></i> Abnormal Workers Report</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($user_first_name); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="setting.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>




