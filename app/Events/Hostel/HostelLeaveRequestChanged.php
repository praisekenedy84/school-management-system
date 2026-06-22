<?php

declare(strict_types=1);

namespace App\Events\Hostel;

use App\Contracts\AuditableEvent;
use App\Models\HostelLeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HostelLeaveRequestChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'requested'|'approved'|'rejected'  $action
     */
    public function __construct(
        public readonly HostelLeaveRequest $hostelLeaveRequest,
        public readonly string $action,
        public readonly ?User $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => "hostel_leave_request.{$this->action}",
            'subject_type' => HostelLeaveRequest::class,
            'subject_id' => $this->hostelLeaveRequest->id,
            'changes' => [
                'student_id' => $this->hostelLeaveRequest->student_id,
            ],
        ];
    }
}
