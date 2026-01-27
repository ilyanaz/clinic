<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Surveillance System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    @php
    // Get navigation data
    $user_role = session('role') ?? 'Guest';
    $user_name = session('username') ?? 'Guest';
    $user_first_name = session('first_name') ?? $user_name;
    $current_page = 'index';
    @endphp
    @include('includes.navigation')
    
    @php
    // Convert stats array to variables for PHP usage
    $stats = $stats ?? [];
    $recentAppointments = $recentAppointments ?? [];
    @endphp

    <!-- Main Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="patient_form.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Add Patient
                            </a>
                            @if($userRole == 'Admin')
                            <a href="doctors.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user-md"></i> Manage Doctors
                            </a>
                            @endif
                            <a href="appointments.php?action=add" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-plus"></i> New Appointment
                            </a>
                            <a href="usechh_1.php?new_surveillance=1" class="btn btn-outline-info btn-sm">
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
                                    <h4 class="text-primary"><?php echo $stats['total_patients'] ?? 0; ?></h4>
                                    <small class="text-muted">Patients</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border-end">
                                    <h4 class="text-info"><?php echo $stats['total_staff'] ?? 0; ?></h4>
                                    <small class="text-muted">Doctors</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?php echo $stats['appointments_today'] ?? 0; ?></h4>
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
                                <h2 class="card-title text-primary mb-3">Welcome, {{ $userName ?? 'User' }}!</h2>
                                <p class="card-text text-muted fs-6">Medical Surveillance System - Professional medical surveillance and patient management for occupational health monitoring.</p>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_patients'] ?? 0; ?></div>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['appointments_today'] ?? 0; ?></div>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['surveillance_records'] ?? 0; ?></div>
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
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_reviews'] ?? 0; ?></div>
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
                                            <?php
                                            $recent_appointments = $recentAppointments ?? [];
                                            if (!empty($recent_appointments)):
                                                foreach ($recent_appointments as $appointment):
                                                    $appointment = (array)$appointment;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($appointment['patient_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo isset($appointment['appointment_date']) && $appointment['appointment_date'] ? date('M d, Y', strtotime($appointment['appointment_date'])) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($appointment['appointment_type'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php
                                                    $status = $appointment['status'] ?? 'Scheduled';
                                                    $badgeClass = $status == 'Scheduled' ? 'primary' : ($status == 'Completed' ? 'success' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No recent appointments</td>
                                            </tr>
                                            <?php endif; ?>
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
                                        <span class="font-weight-bold"><?php echo $stats['completed_this_month'] ?? 0; ?></span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <?php
                                        $totalAppts = max(1, $stats['total_appointments'] ?? 1);
                                        $completed = $stats['completed_this_month'] ?? 0;
                                        $percentage = min(100, ($completed / $totalAppts) * 100);
                                        ?>
                                        <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Abnormal Findings</span>
                                        <span class="font-weight-bold text-warning"><?php echo $stats['abnormal_findings'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Fit for Work</span>
                                        <span class="font-weight-bold text-success"><?php echo $stats['fit_for_work'] ?? 0; ?></span>
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
    <script src="assets/js/main.js"></script>
</body>
</html>
