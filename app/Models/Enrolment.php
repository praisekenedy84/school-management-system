<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\EnrolmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. Links a student to a class for one academic
 * session. Append-only history per RULES.md: never overwrite or delete a
 * past enrolment. A promotion creates a NEW Enrolment row for the next
 * session and flips the OLD row's `status` to `promoted` (a Service's
 * job — this model only provides the persistence + relationships).
 */
class Enrolment extends Model
{
    /** @use HasFactory<EnrolmentFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'academic_session_id',
        'residence_type',
        'status',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
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
}
