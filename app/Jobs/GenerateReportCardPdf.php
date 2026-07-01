<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AcademicSession;
use App\Models\ReportCard;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Services\Assessment\ReportCardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        // `$queue` property, not a `queue(): string` method — see the
        // comment block in the prior version of this file.
        $this->queue = 'pdf';
    }

    public function handle(ReportCardService $reportCards): void
    {
        $student = Student::withoutGlobalScope(SchoolScope::class)->with('school')->findOrFail($this->studentId);
        AcademicSession::withoutGlobalScope(SchoolScope::class)->findOrFail($this->academicSessionId);

        $reportCards->generateAndStore(
            $student,
            $this->academicSessionId,
            $this->generatedBy,
            $this->tenantKey,
        );
    }
}
