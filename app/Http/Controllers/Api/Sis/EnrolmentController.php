<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sis\PromoteEnrolmentRequest;
use App\Http\Resources\EnrolmentResource;
use App\Models\Enrolment;
use App\Services\Sis\PromotionService;

class EnrolmentController extends Controller
{
    public function __construct(private readonly PromotionService $promotionService) {}

    public function promote(PromoteEnrolmentRequest $request, Enrolment $enrolment)
    {
        $newEnrolment = $this->promotionService->promote($enrolment, $request->validated());

        return EnrolmentResource::make($newEnrolment)
            ->response()
            ->setStatusCode(201);
    }
}
