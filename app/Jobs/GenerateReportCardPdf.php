<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AcademicSession;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\StudentFeeLedger;
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
        // This job runs on a queue with NO authenticated user, so global
        // SchoolScope is inert here (SchoolScope returns early when
        // auth()->user() is null). All lookups are explicitly keyed by
        // student/session ids, so cross-school leakage is not a concern in
        // this context — but we read the ledger without the scope explicitly
        // to make that intent obvious.
        $student = Student::withoutGlobalScope(SchoolScope::class)->with('school')->findOrFail($this->studentId);
        $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->findOrFail($this->academicSessionId);

        // Optional fee-status gate (PRD §5.5 / PROJECT-PLAN Phase 3 hook):
        // OPT-IN per school via School.fee_terms->results_gate_enabled
        // (defaults OFF, so Phase 2's tests are unaffected). When enabled and
        // the student's ledger for this session has an outstanding balance
        // (> 0), withhold the PDF: record a ReportCard row with a
        // withheld_reason and NO file_path, so the controller can return a
        // clear message rather than a confusing 404. "Record, don't transact"
        // — this only reads the ledger balance; it never touches money.
        if ($this->resultsGateEnabled($student) && $this->hasOutstandingBalance()) {
            ReportCard::query()->updateOrCreate(
                [
                    'student_id' => $this->studentId,
                    'academic_session_id' => $this->academicSessionId,
                ],
                [
                    'school_id' => $student->school_id,
                    'file_path' => null,
                    'withheld_reason' => 'Outstanding fee balance for this academic session.',
                    'generated_by' => $this->generatedBy,
                    'generated_at' => now(),
                ]
            );

            return;
        }

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
                // Clear any prior withheld marker (e.g. the balance has since
                // been cleared and the card is now generated normally).
                'withheld_reason' => null,
                'generated_by' => $this->generatedBy,
                'generated_at' => now(),
            ]
        );
    }

    private function resultsGateEnabled(Student $student): bool
    {
        return (bool) ($student->school?->fee_terms['results_gate_enabled'] ?? false);
    }

    private function hasOutstandingBalance(): bool
    {
        $ledger = StudentFeeLedger::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $this->studentId)
            ->where('academic_session_id', $this->academicSessionId)
            ->first();

        // No ledger yet → nothing recorded as assessed/paid → treat as no
        // outstanding balance (don't withhold on absence of data).
        if ($ledger === null) {
            return false;
        }

        return bccomp((string) $ledger->balance, '0', 2) > 0;
    }
}
