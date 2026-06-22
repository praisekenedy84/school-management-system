<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Events\Assessment\ReportCardGenerated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReportCardResource;
use App\Jobs\GenerateReportCardPdf;
use App\Models\ReportCard;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        ReportCardGenerated::dispatch($student->id, $validated['academic_session_id'], Auth::user());

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

        // Fee-status gate (PRD §5.5): a withheld card exists as a row but has
        // no PDF. Return 403 with the reason rather than serving a null path
        // or a confusing 404 — the request was authorized, the result is
        // deliberately withheld.
        if ($reportCard->withheld_reason !== null) {
            return response()->json([
                'message' => 'This report card is withheld.',
                'reason' => $reportCard->withheld_reason,
                'withheld' => true,
            ], 403);
        }

        return ReportCardResource::make($reportCard);
    }
}
