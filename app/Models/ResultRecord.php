<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ResultRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. A student's score/grade against an
 * assessment.
 *
 * APPEND-ONLY & VERSIONED per RULES.md §1/§3 — same family as finance
 * records. Publishing or correcting a result must NEVER `update()` an
 * existing row's `score`/`grade`/`version` in place. A correction inserts
 * a brand-new row with `version` incremented and the prior row left
 * untouched; that's a Service's job — this model only provides
 * persistence + relationships + the lookup helper below.
 */
class ResultRecord extends Model
{
    /** @use HasFactory<ResultRecordFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * Structural backstop for the append-only invariant: today only
     * MarkEntryService/ResultPublishingService write this model and both
     * already respect it, but RULES.md §1/§3 treats results like finance
     * records — "never overwrite in place" should not depend solely on
     * every future caller remembering that. Block any attempt to change
     * score/grade/version once a row is (already) published; allow the
     * publish transition itself (is_published false → true).
     */
    public static function booted(): void
    {
        static::updating(function (self $resultRecord) {
            if (! $resultRecord->getOriginal('is_published')) {
                return;
            }

            if ($resultRecord->isDirty(['score', 'grade', 'version'])) {
                throw new \RuntimeException(
                    'A published result record cannot be modified in place. Insert a new versioned row instead.'
                );
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'subject_id',
        'assessment_id',
        'score',
        'grade',
        'version',
        'is_published',
        'published_by',
        'published_at',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_published' => 'bool',
            'published_at' => 'datetime',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * Scope to the highest `version` row for a given student + assessment.
     * Services use this to find what to version off of when entering a
     * correction (new row = this row's version + 1) — never to mutate the
     * row returned.
     */
    public function scopeLatestVersionFor(Builder $query, string $studentId, string $assessmentId): Builder
    {
        return $query
            ->where('student_id', $studentId)
            ->where('assessment_id', $assessmentId)
            ->orderByDesc('version');
    }
}
