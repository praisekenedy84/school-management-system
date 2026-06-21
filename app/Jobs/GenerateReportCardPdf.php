<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AcademicSession;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Queued (Horizon `pdf` queue — ARCHITECTURE.md §9). Gathers every
 * published, latest-version `ResultRecord` for one student in one academic
 * session, computes a weighted score per subject, renders a DomPDF report
 * card, and upserts the `ReportCard` cache-pointer row.
 */
class GenerateReportCardPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentId,
        public readonly string $academicSessionId,
        public readonly ?string $generatedBy,
        public readonly string $tenantKey,
    ) {
        // Horizon queue name. NOTE: this MUST be the `Queueable` trait's
        // `$queue` property, not a `queue(): string` method —
        // `Illuminate\Bus\Dispatcher::dispatchToQueue()` calls
        // `$command->queue($queue, $command)` INSTEAD OF pushing the job
        // onto the queue whenever the job class defines any method
        // literally named `queue` (it's an opt-in hook for jobs that want
        // to push themselves, e.g. unique/batched jobs). A zero-arg
        // `queue(): string` silently satisfies that call (extra args are
        // simply ignored by PHP) and returns the queue name string instead
        // of dispatching — the job never actually runs, with no exception
        // anywhere. Confirmed by reading vendor/laravel/framework/src/
        // Illuminate/Bus/Dispatcher.php::dispatchToQueue().
        $this->queue = 'pdf';
    }

    public function handle(): void
    {
        $student = Student::with('school')->findOrFail($this->studentId);
        $academicSession = AcademicSession::findOrFail($this->academicSessionId);

        // Latest version per (subject, assessment), published only — never
        // pull in an unpublished draft or a superseded version.
        $resultRecords = ResultRecord::query()
            ->where('student_id', $this->studentId)
            ->where('academic_session_id', $this->academicSessionId)
            ->where('is_published', true)
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('DISTINCT ON (student_id, assessment_id) id')
                    ->from('result_records')
                    ->where('student_id', $this->studentId)
                    ->where('academic_session_id', $this->academicSessionId)
                    ->where('is_published', true)
                    ->orderBy('student_id')
                    ->orderBy('assessment_id')
                    ->orderByDesc('version');
            })
            ->with(['subject', 'assessment'])
            ->get();

        $subjects = $resultRecords
            ->groupBy('subject_id')
            ->map(function ($records) {
                $subjectName = $records->first()->subject?->name ?? 'Unknown subject';

                // sum(score/max_score * weight) across this subject's
                // published assessments in the session.
                $weightedScore = $records->reduce(function (float $carry, ResultRecord $record) {
                    $maxScore = (float) ($record->assessment?->max_score ?? 0);
                    $weight = (float) ($record->assessment?->weight ?? 0);

                    if ($maxScore <= 0 || $record->score === null) {
                        return $carry;
                    }

                    return $carry + ((float) $record->score / $maxScore) * $weight;
                }, 0.0);

                return [
                    'subject_name' => $subjectName,
                    'weighted_score' => round($weightedScore, 2),
                    'assessments' => $records->map(fn (ResultRecord $record) => [
                        'name' => $record->assessment?->name,
                        'score' => $record->score,
                        'max_score' => $record->assessment?->max_score,
                        'weight' => $record->assessment?->weight,
                        'grade' => $record->grade,
                    ])->values(),
                ];
            })
            ->values();

        $pdf = Pdf::loadView('pdf.report-card', [
            'student' => $student,
            'academicSession' => $academicSession,
            'subjects' => $subjects,
            'generatedAt' => now(),
        ]);

        $directory = "report-cards/{$this->tenantKey}/{$student->school_id}/{$this->academicSessionId}";
        $filename = Str::slug($student->admission_number).'-'.Str::random(8).'.pdf';
        $relativePath = "{$directory}/{$filename}";

        Storage::makeDirectory($directory);
        Storage::put($relativePath, $pdf->output());

        ReportCard::query()->updateOrCreate(
            [
                'student_id' => $this->studentId,
                'academic_session_id' => $this->academicSessionId,
            ],
            [
                'school_id' => $student->school_id,
                'file_path' => $relativePath,
                'generated_by' => $this->generatedBy,
                'generated_at' => now(),
            ]
        );
    }
}
