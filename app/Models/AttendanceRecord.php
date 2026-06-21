<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. A per-(class, period) attendance mark for a
 * student (PRD §5.4 / SKILLS Recipe G). Offline-tolerant capture syncs
 * idempotently against the unique (student_id, attendance_date, period)
 * composite at the DB level — this model only provides persistence +
 * relationships, not the sync logic.
 *
 * `status` is a plain string column (no PHP enum, matching the
 * `Enrolment`/`Student` precedent) and must be one of:
 * present | absent | late | excused.
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'academic_session_id',
        'attendance_date',
        'period',
        'status',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
