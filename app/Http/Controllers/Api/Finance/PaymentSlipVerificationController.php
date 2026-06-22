<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RejectPaymentSlipRequest;
use App\Http\Requests\Finance\VerifyPaymentSlipRequest;
use App\Http\Resources\PaymentSlipResource;
use App\Models\PaymentSlip;
use App\Services\Finance\PaymentSlipVerificationService;

/**
 * Finance-officer verification workflow (SKILLS Recipe E). Split from
 * PaymentSlipController (submission/read) the same way ResultPublishController
 * is split from ResultController in Phase 2.
 *
 * Authorization (`verify` ability) is enforced in the FormRequests'
 * authorize(); the service enforces the state-machine + idempotency guards
 * (throws ValidationException → 422 on an already-verified / non-pending
 * slip, never a 500).
 */
class PaymentSlipVerificationController extends Controller
{
    public function __construct(private readonly PaymentSlipVerificationService $verification) {}

    public function verify(VerifyPaymentSlipRequest $request, PaymentSlip $paymentSlip)
    {
        $slip = $this->verification->verify(
            $paymentSlip,
            $request->user()->id,
            $request->validated('verification_notes'),
            $request->ip(),
        );

        return PaymentSlipResource::make($slip->load(['student', 'receipt']))
            ->additional(['message' => "Slip verified; receipt {$slip->receipt_number} issued."]);
    }

    public function reject(RejectPaymentSlipRequest $request, PaymentSlip $paymentSlip)
    {
        $slip = $this->verification->reject(
            $paymentSlip,
            $request->user()->id,
            $request->validated('rejection_category'),
            $request->validated('rejection_reason'),
            $request->ip(),
        );

        return PaymentSlipResource::make($slip->load('student'))
            ->additional(['message' => 'Slip rejected; the parent will be notified.']);
    }
}
