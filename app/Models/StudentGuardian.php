<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StudentGuardianFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * Tenant model. Pivot model for the `student_guardians` many-to-many between
 * `students` and `users` (guardians are `users` with the 'parent' role).
 *
 * Deliberately does NOT use `BelongsToSchool` — the migration has no
 * `school_id` column. Ownership flows through the student/guardian
 * relationship rather than being stored redundantly on the pivot.
 *
 * Must use `AsPivot`: Student::guardians()/User::wards() declare
 * `->using(StudentGuardian::class)`, and Eloquent's BelongsToMany relies on
 * `Pivot`/`AsPivot`'s `fromRawAttributes()` to hydrate pivot rows — a plain
 * `Model` does not implement it and the relation throws a BadMethodCallException
 * the moment a pivot row is hydrated (e.g. eager-loading `guardians`).
 */
class StudentGuardian extends Model
{
    /** @use HasFactory<StudentGuardianFactory> */
    use AsPivot, HasFactory, HasUuids;

    protected $table = 'student_guardians';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'guardian_id',
        'relationship',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'bool',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }
}
