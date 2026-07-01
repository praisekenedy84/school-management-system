<?php

declare(strict_types=1);

namespace App\Events\Hostel;

use App\Contracts\AuditableEvent;
use App\Models\HostelAllocation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HostelAllocationChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'allocated'|'ended'|'meal_plan_updated'  $action
     */
    public function __construct(
        public readonly HostelAllocation $hostelAllocation,
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
            'action' => "hostel_allocation.{$this->action}",
            'subject_type' => HostelAllocation::class,
            'subject_id' => $this->hostelAllocation->id,
            'changes' => [
                'student_id' => $this->hostelAllocation->student_id,
                'hostel_room_id' => $this->hostelAllocation->hostel_room_id,
                'meal_plan_id' => $this->hostelAllocation->meal_plan_id,
            ],
        ];
    }
}
