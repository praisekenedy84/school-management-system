<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Enrolment;
use App\Models\ReportCard;
use App\Models\ResultRecord;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared report-card data gathering, fee-gate checks, and PDF rendering.
 * Used by GenerateReportCardPdf (single student) and class bulk generation.
 */
class ReportCardService
{
    /**
     * @return Collection<int, array{subject_name: string, weighted_score: float, assessments: mixed}>
     */
    public function gatherSubjectResults(Student $student, string $academicSessionId): Collection
    {
        $resultRecords = ResultRecord::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $academicSessionId)
            ->where('is_published', true)
            ->whereIn('id', function ($sub) use ($student, $academicSessionId) {
                $sub->selectRaw('DISTINCT ON (student_id, assessment_id) id')
                    ->from('result_records')
                    ->where('student_id', $student->id)
                    ->where('academic_session_id', $academicSessionId)
                    ->where('is_published', true)
                    ->orderBy('student_id')
                    ->orderBy('assessment_id')
                    ->orderByDesc('version');
            })
            ->with(['subject', 'assessment'])
            ->get();

        return $resultRecords
            ->groupBy('subject_id')
            ->map(function ($records) {
                $subjectName = $records->first()->subject?->name ?? 'Unknown subject';

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
    }

    public function hasPublishedResults(string $studentId, string $academicSessionId): bool
    {
        return ResultRecord::query()
            ->where('student_id', $studentId)
            ->where('academic_session_id', $academicSessionId)
            ->where('is_published', true)
            ->exists();
    }

    public function resultsGateEnabled(Student $student): bool
    {
        return (bool) ($student->school?->fee_terms['results_gate_enabled'] ?? false);
    }

    public function feeGateThreshold(School $school): string
    {
        $threshold = $school->fee_terms['results_gate_threshold'] ?? '0';

        return number_format((float) $threshold, 2, '.', '');
    }

    /**
     * Returns a parent-facing withheld reason when the fee gate blocks access,
     * or null when the student may receive a report card.
     */
    public function feeGateWithheldReason(Student $student, string $academicSessionId): ?string
    {
        if (! $this->resultsGateEnabled($student)) {
            return null;
        }

        $ledger = StudentFeeLedger::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $student->id)
            ->where('academic_session_id', $academicSessionId)
            ->first();

        if ($ledger === null) {
            return null;
        }

        $threshold = $this->feeGateThreshold($student->school);

        if (bccomp((string) $ledger->balance, $threshold, 2) > 0) {
            return 'Report card is currently unavailable. Please ensure outstanding fees are settled.';
        }

        return null;
    }

    public function generateAndStore(
        Student $student,
        string $academicSessionId,
        ?string $generatedBy,
        string $tenantKey,
        bool $applyFeeGate = true,
    ): ReportCard {
        $student->loadMissing('school');
        $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->findOrFail($academicSessionId);

        if ($applyFeeGate && ($reason = $this->feeGateWithheldReason($student, $academicSessionId)) !== null) {
            return ReportCard::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_session_id' => $academicSessionId,
                ],
                [
                    'school_id' => $student->school_id,
                    'file_path' => null,
                    'withheld_reason' => $reason,
                    'generated_by' => $generatedBy,
                    'generated_at' => now(),
                ]
            );
        }

        $subjects = $this->gatherSubjectResults($student, $academicSessionId);

        $pdf = Pdf::loadView('pdf.report-card', [
            'student' => $student,
            'academicSession' => $academicSession,
            'subjects' => $subjects,
            'generatedAt' => now(),
        ]);

        $directory = "report-cards/{$tenantKey}/{$student->school_id}/{$academicSessionId}";
        $filename = Str::slug($student->admission_number).'-'.Str::random(8).'.pdf';
        $relativePath = "{$directory}/{$filename}";

        Storage::makeDirectory($directory);
        Storage::put($relativePath, $pdf->output());

        return ReportCard::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_session_id' => $academicSessionId,
            ],
            [
                'school_id' => $student->school_id,
                'file_path' => $relativePath,
                'withheld_reason' => null,
                'generated_by' => $generatedBy,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * @return array{
     *     file_path: string,
     *     filename: string,
     *     included_count: int,
     *     excluded_students: list<array{student_id: string, student_name: string, reason: string}>
     * }
     */
    public function generateClassPdf(
        ClassRoom $classRoom,
        string $academicSessionId,
        ?string $generatedBy,
        string $tenantKey,
    ): array {
        $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->findOrFail($academicSessionId);

        if ($academicSession->school_id !== $classRoom->school_id) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'The academic session must belong to the same school as the class.',
            ]);
        }

        $classRoom->load('school');

        $students = Student::withoutGlobalScope(SchoolScope::class)
            ->with('school')
            ->whereIn('id', Enrolment::query()
                ->where('class_id', $classRoom->id)
                ->where('academic_session_id', $academicSessionId)
                ->where('status', 'active')
                ->pluck('student_id'))
            ->orderBy('admission_number')
            ->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'class_id' => 'No actively enrolled students were found for this class and academic session.',
            ]);
        }

        $excludedStudents = [];
        $reportCards = [];

        foreach ($students as $student) {
            if (! $this->hasPublishedResults($student->id, $academicSessionId)) {
                continue;
            }

            if (($reason = $this->feeGateWithheldReason($student, $academicSessionId)) !== null) {
                $excludedStudents[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'reason' => $reason,
                ];

                ReportCard::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_session_id' => $academicSessionId,
                    ],
                    [
                        'school_id' => $student->school_id,
                        'file_path' => null,
                        'withheld_reason' => $reason,
                        'generated_by' => $generatedBy,
                        'generated_at' => now(),
                    ]
                );

                continue;
            }

            $subjects = $this->gatherSubjectResults($student, $academicSessionId);
            $reportCards[] = [
                'student' => $student,
                'subjects' => $subjects,
            ];

            $this->generateAndStore($student, $academicSessionId, $generatedBy, $tenantKey, applyFeeGate: false);
        }

        if ($reportCards === []) {
            throw ValidationException::withMessages([
                'class_id' => 'No students with published results are eligible for report card generation in this class.',
            ]);
        }

        $pdf = Pdf::loadView('pdf.class-report-card', [
            'classRoom' => $classRoom,
            'academicSession' => $academicSession,
            'reportCards' => $reportCards,
            'generatedAt' => now(),
        ]);

        $directory = "report-cards/{$tenantKey}/{$classRoom->school_id}/{$academicSessionId}/classes";
        $filename = Str::slug($classRoom->name).'-'.Str::random(8).'.pdf';
        $relativePath = "{$directory}/{$filename}";

        Storage::makeDirectory($directory);
        Storage::put($relativePath, $pdf->output());

        return [
            'file_path' => $relativePath,
            'filename' => $filename,
            'included_count' => count($reportCards),
            'excluded_students' => $excludedStudents,
        ];
    }
}
