<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned. An assessment definition (e.g. "Midterm
 * Exam", "Quiz 1") scoped to a subject within an academic session,
 * carrying a weighting (%) toward the final grade.
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'subject_id',
        'name',
        'weight',
        'max_score',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'max_score' => 'decimal:2',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resultRecords(): HasMany
    {
        return $this->hasMany(ResultRecord::class);
    }
}
