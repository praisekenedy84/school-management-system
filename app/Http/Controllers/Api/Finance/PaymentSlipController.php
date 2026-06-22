<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SubmitPaymentSlipRequest;
use App\Http\Resources\PaymentSlipResource;
use App\Models\PaymentSlip;
use App\Services\Finance\PaymentSlipSubmissionService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

/**
 * Submission + read side of the payment-slip lifecycle (SKILLS Recipe D).
 * Verification/rejection live in PaymentSlipVerificationController, mirroring
 * the Phase 2 ResultController vs ResultPublishController split.
 *
 * Controllers stay thin: `store` hands off to PaymentSlipSubmissionService;
 * `index`/`show` apply policy-driven scoping only.
 */
class PaymentSlipController extends Controller
{
    public function __construct(
        private readonly PaymentSlipSubmissionService $submission,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentSlip::class);

        $slips = $this->scopedQuery($request)
            ->paginate($request->integer('per_page', 20));

        return PaymentSlipResource::collection($slips);
    }

    /** GET /api/v1/payment-slips/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', PaymentSlip::class);

        $rows = $this->scopedQuery($request)->get();
        $columns = [
            'slip_number' => 'Slip No',
            'student.full_name' => 'Student',
            'total_amount' => 'Amount',
            'status' => 'Status',
            'deposit_date' => 'Deposit Date',
            'verified_at' => 'Verified At',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'payment-slips', 'Payment Slips')
            : $this->exportService->excel($rows, $columns, 'payment-slips');
    }

    /**
     * Shared by index() and export() so both apply the identical role-based
     * scoping. Parents see ONLY their wards' slips (defence-in-depth on top of
     * the policy; SchoolScope alone doesn't constrain a parent to their own
     * family). Finance staff / admins see the school's queue (SchoolScope
     * confines them to their campus; tenant_admin sees all schools).
     */
    private function scopedQuery(Request $request)
    {
        $user = $request->user();

        $query = PaymentSlip::query()
            ->with(['student', 'receipt'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->input('student_id')))
            ->latest();

        if ($user->hasRole('parent') && ! $user->hasRole(['finance_manager', 'accountant', 'school_admin', 'tenant_admin'])) {
            $wardIds = $user->wards()->pluck('students.id');
            $query->whereIn('student_id', $wardIds);
        }

        return $query;
    }

    public function store(SubmitPaymentSlipRequest $request)
    {
        $slip = $this->submission->submit(
            $request->safe()->except('slip_attachments'),
            $request->file('slip_attachments', []),
            $request->user()->id,
            $request->ip(),
        );

        return PaymentSlipResource::make($slip->load('student'))
            ->additional([
                'message' => 'Payment slip submitted and is pending verification. No money has been moved.',
                'eta' => 'Slips are typically verified within 24 hours.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentSlip $paymentSlip)
    {
        $this->authorize('view', $paymentSlip);

        return PaymentSlipResource::make($paymentSlip->load(['student', 'receipt', 'logs']));
    }
}
