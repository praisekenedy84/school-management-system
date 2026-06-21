<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Events\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records attendance for a whole class/period/date in one call. Idempotent
 * by design (SKILLS Recipe G / PRD §5.4 offline tolerance): re-submitting
 * the same (student_id, attendance_date, period) updates that row via
 * `updateOrCreate` rather than colliding with the unique DB constraint.
 */
class AttendanceService
{
    public function record(array $data, string $recordedBy): Collection
    {
        return DB::transaction(function () use ($data, $recordedBy) {
            // The class's OWN school_id is the source of truth for the rows
            // we write — not the acting user's school_id, which is null for
            // a tenant_admin and would otherwise null out a school-owned
            // column. RecordAttendanceRequest::withValidator already proved
            // the class belongs to the acting user's school (when they have
            // one), so re-reading it here is safe and always correct.
            $schoolId = ClassRoom::withoutGlobalScope(SchoolScope::class)
                ->whereKey($data['class_id'])
                ->value('school_id');

            $records = collect($data['records'])->map(function (array $record) use ($data, $schoolId, $recordedBy) {
                return AttendanceRecord::query()->updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'attendance_date' => $data['attendance_date'],
                        'period' => $data['period'] ?? null,
                    ],
                    [
                        'school_id' => $schoolId,
                        'class_id' => $data['class_id'],
                        'academic_session_id' => $data['academic_session_id'],
                        'status' => $record['status'],
                        'note' => $record['note'] ?? null,
                        'recorded_by' => $recordedBy,
                    ]
                );
            });

            $records = new Collection($records->all());

            AttendanceRecorded::dispatch(
                $records,
                $data['class_id'],
                $data['academic_session_id'],
                $data['attendance_date'],
                $data['period'] ?? null,
                $recordedBy,
            );

            return $records;
        });
    }
}
