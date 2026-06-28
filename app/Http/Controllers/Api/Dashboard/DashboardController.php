<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Dashboard\DashboardSummaryBuilder;
use Illuminate\Http\Request;

/**
 * Read-only cross-module summaries (PRD §5.9/§5.10). Plain aggregation
 * queries — no service layer needed for wards; summary uses
 * DashboardSummaryBuilder for permission-scoped metrics.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardSummaryBuilder $summaryBuilder) {}

    /**
     * School-staff summary — each metric is included only when the user's
     * Spatie permissions unlock that widget (direct admin grants count).
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        if (! $this->summaryBuilder->canAccessSummary($user)) {
            abort(403, 'You do not have access to this dashboard.');
        }

        return response()->json([
            'data' => $this->summaryBuilder->build($user),
        ]);
    }

    /**
     * Parent dashboard: per-child summary (PRD §5.10) — requires
     * students.view_own_children; scoped through wards().
     */
    public function wards(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('students.view_own_children')) {
            abort(403, 'You do not have access to this dashboard.');
        }

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
