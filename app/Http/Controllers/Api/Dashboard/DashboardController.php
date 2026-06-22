<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\HostelRoom;
use App\Models\PaymentSlip;
use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Read-only cross-module summaries (PRD §5.9/§5.10). Plain aggregation
 * queries — no service layer needed, nothing here mutates state. Caching
 * is explicitly deferred (Phase 5's "performance pass" task, not this one).
 */
class DashboardController extends Controller
{
    /**
     * School-staff summary: enrolment, today's attendance, finance,
     * hostel occupancy in one call (PRD §5.9 "termly summary").
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        if (! $user->hasRole(['tenant_admin', 'school_admin', 'finance_manager', 'accountant', 'academic_director'])) {
            abort(403, 'You do not have access to this dashboard.');
        }

        $today = now()->toDateString();

        $currentSession = AcademicSession::where('is_current', true)->first();

        return response()->json(['data' => [
            'active_students' => Student::where('status', 'active')->count(),
            'attendance_today' => [
                'present' => AttendanceRecord::where('attendance_date', $today)->where('status', 'present')->count(),
                'absent' => AttendanceRecord::where('attendance_date', $today)->where('status', 'absent')->count(),
            ],
            'payment_slips' => [
                'pending' => PaymentSlip::where('status', 'pending')->count(),
                'verified_today_total' => (float) PaymentSlip::where('status', 'verified')
                    ->whereDate('verified_at', $today)
                    ->sum('total_amount'),
            ],
            'hostel_occupancy' => [
                'capacity' => (int) HostelRoom::sum('capacity'),
                'rooms' => HostelRoom::count(),
            ],
            'current_academic_session' => $currentSession?->name,
        ]]);
    }

    /**
     * Parent dashboard: per-child summary (PRD §5.10) — reuses the same
     * wards() scoping already enforced on the underlying endpoints
     * (PaymentSlipPolicy, etc.); this just aggregates counts so the SPA
     * doesn't need N round-trips to build one screen.
     */
    public function wards(Request $request)
    {
        $user = $request->user();

        $wards = $user->wards()->with(['enrolments' => fn ($q) => $q->latest('enrolled_at')->limit(1), 'enrolments.classRoom'])->get();

        $data = $wards->map(function (Student $student) {
            $currentEnrolment = $student->enrolments->first();
            $ledger = $student->feeLedgers()->latest('created_at')->first();

            return [
                'student_id' => $student->id,
                'name' => $student->full_name,
                'current_class' => $currentEnrolment?->classRoom?->name,
                'fee_balance' => $ledger ? (float) $ledger->refresh()->balance : null,
                'payment_status' => $ledger?->payment_status,
                'pending_payment_slips' => $student->paymentSlips()->where('status', 'pending')->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
