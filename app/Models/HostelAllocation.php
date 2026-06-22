<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\HostelAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant model, school-owned. RULES.md §3: soft-delete only, never hard
 * deleted. Ending an allocation sets `status=ended` + `ended_at`; it is
 * never deleted outright — history (who lived where, when) must persist.
 */
class HostelAllocation extends Model
{
    /** @use HasFactory<HostelAllocationFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'hostel_room_id',
        'meal_plan_id',
        'academic_session_id',
        'status',
        'allocated_at',
        'ended_at',
        'allocated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostelRoom(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class);
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
