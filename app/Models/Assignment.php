<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned. A homework/assignment a teacher publishes to
 * a class. The class, subject, session, and teacher are all derived via
 * `teacher_assignment_id`.
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'teacher_assignment_id',
        'title',
        'description',
        'due_at',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function teacherAssignment(): BelongsTo
    {
        return $this->belongsTo(TeacherAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
