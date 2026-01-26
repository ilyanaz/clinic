@php
// Get user role and name for navigation
$user_role = session('role') ?? 'Guest';
$user_name = session('username') ?? 'Guest';
$user_first_name = session('first_name') ?? $user_name;
$current_page = request()->route()->getName() ?? basename(request()->path(), '.php');
@endphp

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('dashboard') }}">
            <div class="logo-small">
                <img src="{{ asset('medical_surveillance_logo.png') }}" alt="Medical Surveillance Logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
                    <a class="nav-link {{ $current_page == 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                
                <!-- Medical Surveillance Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ in_array($current_page, ['surveillance', 'surveillance_list', 'company', 'patients']) ? 'active' : '' }}" href="#" id="medicalSurveillanceDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-clipboard-check"></i> Medical Surveillance
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('company.php') }}"><i class="fas fa-building"></i> List of Companies</a></li>
                        <li><a class="dropdown-item" href="{{ url('patients.php') }}"><i class="fas fa-users"></i> List of Patients</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('medical.php') }}"><i class="fas fa-notes-medical"></i> Examination</a></li>
                    </ul>
                </li>
                
                <!-- Audiometry Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="audiometryDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-headphones"></i> Audiometry
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('company.php?type=audiometry') }}"><i class="fas fa-building"></i> List of Companies</a></li>
                        <li><a class="dropdown-item" href="{{ url('patients.php?type=audiometry') }}"><i class="fas fa-users"></i> List of Patients</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ url('audio.php') }}"><i class="fas fa-headphones"></i> Examination</a></li>
                    </ul>
                </li>
                
                <!-- Reports Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ in_array($current_page, ['reports', 'medical_report', 'employee_report', 'ms_report', 'abnormal_workers_report']) ? 'active' : '' }}" href="#" id="reportsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="dropdown-menu">
                        @if($user_role == 'Admin' || $user_role == 'Doctor')
                        <li><a class="dropdown-item" href="{{ url('reports.php') }}"><i class="fas fa-chart-line"></i> General Reports</a></li>
                        @endif
                        
                        @if($user_role == 'Doctor')
                        <li><a class="dropdown-item" href="{{ url('medical_report.php') }}"><i class="fas fa-clipboard-list"></i> Medical Reports</a></li>
                        @else
                        <li><a class="dropdown-item" href="{{ url('employee_report.php') }}"><i class="fas fa-user-md"></i> Employee Report</a></li>
                        <li><a class="dropdown-item" href="{{ url('ms_report.php') }}"><i class="fas fa-file-medical-alt"></i> MS Summary Report</a></li>
                        <li><a class="dropdown-item" href="{{ url('abnormal_workers_report.php') }}"><i class="fas fa-exclamation-triangle"></i> Abnormal Workers Report</a></li>
                        @endif
                    </ul>
                </li>
                
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> {{ htmlspecialchars($user_first_name) }}
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('profile.php') }}"><i class="fas fa-user-circle"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="{{ url('setting.php') }}"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('logout') }}"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
