<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportCardResource;
use App\Jobs\GenerateReportCardPdf;
use App\Models\ReportCard;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportCardController extends Controller
{
    /**
     * Enqueues report-card generation; does not render synchronously
     * (ARCHITECTURE.md §9 — PDF rendering runs on the `pdf` queue).
     */
    public function store(Request $request, Student $student)
    {
        $this->authorize('view', $student);
        $this->authorize('create', ReportCard::class);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ]);

        GenerateReportCardPdf::dispatch(
            $student->id,
            $validated['academic_session_id'],
            $request->user()->id,
            (string) tenant()->getTenantKey(),
        );

        return response()->json([
            'message' => 'Report card generation has been queued.',
            'student_id' => $student->id,
            'academic_session_id' => $validated['academic_session_id'],
        ], 202);
    }

    /**
     * Returns the stored ReportCard pointer (file path / generated_at) if
     * one already exists; 404 if not yet generated. Does not stream/serve
     * the file itself — that's a follow-up.
     */
    public function show(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ]);

        $reportCard = ReportCard::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $validated['academic_session_id'])
            ->first();

        if ($reportCard === null) {
            return response()->json(['message' => 'No report card has been generated yet for this academic session.'], 404);
        }

        $this->authorize('view', $reportCard);

        return ReportCardResource::make($reportCard);
    }
}
