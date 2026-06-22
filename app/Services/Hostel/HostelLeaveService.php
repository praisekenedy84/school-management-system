<?php

declare(strict_types=1);

namespace App\Services\Hostel;

use App\Events\Hostel\HostelLeaveRequestChanged;
use App\Models\HostelAllocation;
use App\Models\HostelLeaveRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class HostelLeaveService
{
    public function request(array $data, ?string $requestedBy): HostelLeaveRequest
    {
        $allocation = HostelAllocation::findOrFail($data['hostel_allocation_id']);

        $leaveRequest = HostelLeaveRequest::create([
            'school_id' => $allocation->school_id,
            'student_id' => $allocation->student_id,
            'hostel_allocation_id' => $allocation->id,
            'reason' => $data['reason'],
            'depart_at' => $data['depart_at'],
            'return_at' => $data['return_at'],
            'status' => 'pending',
            'requested_by' => $requestedBy,
        ]);

        HostelLeaveRequestChanged::dispatch($leaveRequest, 'requested', Auth::user());

        return $leaveRequest;
    }

    public function approve(HostelLeaveRequest $leaveRequest, ?string $decidedBy, ?string $notes): HostelLeaveRequest
    {
        $this->assertPending($leaveRequest);

        $leaveRequest->update([
            'status' => 'approved',
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);

        HostelLeaveRequestChanged::dispatch($leaveRequest, 'approved', Auth::user());

        return $leaveRequest;
    }

    public function reject(HostelLeaveRequest $leaveRequest, ?string $decidedBy, ?string $notes): HostelLeaveRequest
    {
        $this->assertPending($leaveRequest);

        $leaveRequest->update([
            'status' => 'rejected',
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);

        HostelLeaveRequestChanged::dispatch($leaveRequest, 'rejected', Auth::user());

        return $leaveRequest;
    }

    private function assertPending(HostelLeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'This leave request has already been decided.',
            ]);
        }
    }
}
