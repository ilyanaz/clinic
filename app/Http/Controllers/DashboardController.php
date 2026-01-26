<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Show dashboard
     */
    public function index()
    {
        $userRole = session('role');
        $userName = session('username');
        
        $today = date('Y-m-d');
        $stats = $this->getDashboardStats($today);
        $recentAppointments = $this->getRecentAppointments(5);

        return view('dashboard.index', compact('userRole', 'userName', 'stats', 'recentAppointments'));
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats($today)
    {
        try {
            $totalPatients = DB::table('patient_information')->count();
            $totalStaff = DB::table('medical_staff')->count();
            $appointmentsToday = DB::table('appointments')
                ->whereDate('appointment_date', $today)
                ->count();
            $surveillanceRecords = DB::table('surveillance_metadata')->count();
            $pendingReviews = DB::table('surveillance_metadata')
                ->whereNull('examination_date')
                ->count();
            $completedThisMonth = DB::table('appointments')
                ->whereMonth('appointment_date', date('m'))
                ->whereYear('appointment_date', date('Y'))
                ->where('status', 'Completed')
                ->count();
            $totalAppointments = DB::table('appointments')->count();
            $abnormalFindings = DB::table('conclusion_ms_finding')
                ->where('fitness_status', '!=', 'Fit')
                ->count();
            $fitForWork = DB::table('conclusion_ms_finding')
                ->where('fitness_status', 'Fit')
                ->count();

            return [
                'total_patients' => $totalPatients,
                'total_staff' => $totalStaff,
                'appointments_today' => $appointmentsToday,
                'surveillance_records' => $surveillanceRecords,
                'pending_reviews' => $pendingReviews,
                'completed_this_month' => $completedThisMonth,
                'total_appointments' => $totalAppointments,
                'abnormal_findings' => $abnormalFindings,
                'fit_for_work' => $fitForWork,
            ];
        } catch (\Exception $e) {
            return [
                'total_patients' => 0,
                'total_staff' => 0,
                'appointments_today' => 0,
                'surveillance_records' => 0,
                'pending_reviews' => 0,
                'completed_this_month' => 0,
                'total_appointments' => 0,
                'abnormal_findings' => 0,
                'fit_for_work' => 0,
            ];
        }
    }

    /**
     * Get recent appointments
     */
    private function getRecentAppointments($limit = 5)
    {
        try {
            // Try appointments table first
            $appointments = DB::table('appointments')
                ->join('patient_information', 'appointments.patient_id', '=', 'patient_information.id')
                ->select(
                    'appointments.*',
                    DB::raw("CONCAT(patient_information.first_name, ' ', patient_information.last_name) as patient_name")
                )
                ->orderBy('appointments.appointment_date', 'desc')
                ->limit($limit)
                ->get();
            
            if ($appointments->count() > 0) {
                return $appointments;
            }
            
            // Fallback to chemical_information (surveillance records) if appointments table is empty
            return DB::table('chemical_information')
                ->join('patient_information', 'chemical_information.patient_id', '=', 'patient_information.id')
                ->select(
                    'chemical_information.surveillance_id as id',
                    DB::raw("CONCAT(patient_information.first_name, ' ', patient_information.last_name) as patient_name"),
                    'chemical_information.examination_date as appointment_date',
                    'chemical_information.examination_type as appointment_type',
                    DB::raw("CASE 
                        WHEN chemical_information.final_assessment IS NULL OR chemical_information.final_assessment = '' THEN 'Scheduled'
                        WHEN chemical_information.final_assessment LIKE '%fit%' OR chemical_information.final_assessment LIKE '%normal%' THEN 'Completed'
                        ELSE 'Pending Review'
                    END as status")
                )
                ->orderBy('chemical_information.examination_date', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
