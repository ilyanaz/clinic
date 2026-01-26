<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Surveillance System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
</head>
<body>
    @include('includes.navigation')

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="{{ url('patients.php?action=add') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Add Patient
                            </a>
                            @if($userRole == 'Admin')
                            <a href="{{ url('doctors.php') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user-md"></i> Manage Doctors
                            </a>
                            @endif
                            <a href="{{ url('appointments.php?action=add') }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-plus"></i> New Appointment
                            </a>
                            <a href="{{ url('surveillance.php?action=add') }}" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-clipboard-list"></i> New Surveillance
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title">Today's Overview</h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="border-end">
                                    <h4 class="text-primary">{{ $stats['total_patients'] }}</h4>
                                    <small class="text-muted">Patients</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h4 class="text-info">{{ $stats['total_staff'] }}</h4>
                                    <small class="text-muted">Doctors</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success">{{ $stats['appointments_today'] }}</h4>
                                <small class="text-muted">Appointments</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="col-md-9 col-lg-10">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card welcome-card">
                            <div class="card-body text-center py-5">
                                <h2 class="card-title text-primary mb-3">Medical Surveillance System</h2>
                                <p class="card-text text-muted fs-6">Professional medical surveillance and patient management for occupational health monitoring.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Cards -->
                <div class="row">
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Patients</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['total_patients'] }}</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card border-left-success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Appointments</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['appointments_today'] }}</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Surveillance Records</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['surveillance_records'] }}</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clipboard-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="card border-left-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reviews</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $stats['pending_reviews'] }}</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Appointments</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(count($recentAppointments) > 0)
                                                @foreach($recentAppointments as $appointment)
                                                <tr>
                                                    <td>{{ htmlspecialchars($appointment->patient_name ?? 'N/A') }}</td>
                                                    <td>{{ $appointment->appointment_date ? date('M d, Y', strtotime($appointment->appointment_date)) : 'N/A' }}</td>
                                                    <td>{{ htmlspecialchars($appointment->appointment_type ?? 'N/A') }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ ($appointment->status ?? '') == 'Scheduled' ? 'primary' : (($appointment->status ?? '') == 'Completed' ? 'success' : 'danger') }}">
                                                            {{ $appointment->status ?? 'N/A' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No recent appointments</td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold text-primary">Quick Stats</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Completed This Month</span>
                                        <span class="font-weight-bold">{{ $stats['completed_this_month'] }}</span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: {{ min(100, ($stats['completed_this_month'] / max(1, $stats['total_appointments'])) * 100) }}%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Abnormal Findings</span>
                                        <span class="font-weight-bold text-warning">{{ $stats['abnormal_findings'] }}</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Fit for Work</span>
                                        <span class="font-weight-bold text-success">{{ $stats['fit_for_work'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script>
        // Simple dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.querySelector('.dropdown-toggle');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            
            if (dropdownToggle && dropdownMenu) {
                console.log('Dropdown elements found');
                
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Dropdown clicked');
                    
                    // Simple toggle
                    if (dropdownMenu.style.display === 'block') {
                        dropdownMenu.style.display = 'none';
                    } else {
                        dropdownMenu.style.display = 'block';
                    }
                });
                
                // Close when clicking outside
                document.addEventListener('click', function(e) {
                    if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.style.display = 'none';
                    }
                });
            } else {
                console.log('Dropdown elements not found');
            }
        });
    </script>
</body>
</html>
