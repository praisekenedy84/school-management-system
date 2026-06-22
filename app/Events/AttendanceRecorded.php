<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\AuditableEvent;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per batch submission (one class/period/date attendance taking),
 * not once per student row — keeps the event volume sane while still giving
 * listeners (e.g. a future Phase 4 absence notifier, and now LogAudit via
 * the `AuditableEvent` contract) everything they need.
 */
class AttendanceRecorded implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     */
    public function __construct(
        public readonly Collection $records,
        public readonly string $classId,
        public readonly string $academicSessionId,
        public readonly string $attendanceDate,
        public readonly ?string $period,
        public readonly string $recordedBy,
    ) {}

    public function toAuditLog(): array
    {
        $actor = User::find($this->recordedBy);

        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'action' => 'attendance.recorded',
            'subject_type' => ClassRoom::class,
            'subject_id' => $this->classId,
            'changes' => [
                'attendance_date' => $this->attendanceDate,
                'period' => $this->period,
                'record_count' => $this->records->count(),
            ],
        ];
    }
}
