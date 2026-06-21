<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. A CACHE POINTER to the most recently generated
 * report-card PDF for a (student, academic_session) pair — not an
 * append-only/versioned record. Regenerating updates `file_path`/
 * `generated_at` in place; `ResultRecord` remains the append-only source of
 * truth this PDF is rendered from.
 */
class ReportCard extends Model
{
    /** @use HasFactory<ReportCardFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'file_path',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
