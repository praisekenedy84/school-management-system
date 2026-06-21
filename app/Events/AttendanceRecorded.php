<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per batch submission (one class/period/date attendance taking),
 * not once per student row — keeps the event volume sane while still giving
 * listeners (e.g. a future Phase 4 absence notifier) everything they need.
 */
class AttendanceRecorded
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
}
