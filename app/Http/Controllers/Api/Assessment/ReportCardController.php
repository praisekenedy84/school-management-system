<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Events\Assessment\ReportCardGenerated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\BulkGenerateReportCardRequest;
use App\Http\Resources\ReportCardResource;
use App\Jobs\GenerateReportCardPdf;
use App\Models\ClassRoom;
use App\Models\ReportCard;
use App\Models\Student;
use App\Services\Assessment\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCardController extends Controller
{
    public function __construct(private readonly ReportCardService $reportCards) {}

    /**
     * Generates a report card synchronously so the caller receives immediate
     * feedback (fixes silent failures when the pdf queue has no worker).
     */
    public function store(Request $request, Student $student)
    {
        $this->authorize('view', $student);
        $this->authorize('create', ReportCard::class);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ]);

        if (! $this->reportCards->hasPublishedResults($student->id, $validated['academic_session_id'])) {
            return response()->json([
                'message' => 'Results must be published before report cards can be generated.',
            ], 422);
        }

        GenerateReportCardPdf::dispatchSync(
            $student->id,
            $validated['academic_session_id'],
            $request->user()->id,
            (string) tenant()->getTenantKey(),
        );

        ReportCardGenerated::dispatch($student->id, $validated['academic_session_id'], Auth::user());

        $reportCard = ReportCard::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $validated['academic_session_id'])
            ->firstOrFail();

        if ($reportCard->withheld_reason !== null) {
            return response()->json([
                'message' => $reportCard->withheld_reason,
                'withheld' => true,
                'data' => ReportCardResource::make($reportCard),
            ], 403);
        }

        return ReportCardResource::make($reportCard)
            ->additional(['message' => 'Report card generated successfully.']);
    }

    /**
     * Generates a combined class PDF synchronously and returns metadata plus
     * a download path for the merged file.
     */
    public function bulkStore(BulkGenerateReportCardRequest $request)
    {
        $classRoom = $request->classRoom();
        $this->authorize('view', $classRoom);

        $result = $this->reportCards->generateClassPdf(
            $classRoom,
            $request->validated('academic_session_id'),
            $request->user()->id,
            (string) tenant()->getTenantKey(),
        );

        return response()->json([
            'message' => 'Class report cards generated successfully.',
            'included_count' => $result['included_count'],
            'excluded_students' => $result['excluded_students'],
            'file_path' => $result['file_path'],
            'download_url' => url("/api/v1/report-cards/class-download?class_id={$classRoom->id}&academic_session_id={$request->validated('academic_session_id')}"),
        ]);
    }

    /**
     * Streams the most recently generated class report-card PDF for the given
     * class + session (stored under the classes/ subdirectory).
     */
    public function classDownload(Request $request)
    {
        $this->authorize('create', ReportCard::class);

        $validated = $request->validate([
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ]);

        $classRoom = ClassRoom::query()->findOrFail($validated['class_id']);
        $this->authorize('view', $classRoom);

        $directory = 'report-cards/'.tenant()->getTenantKey()."/{$classRoom->school_id}/{$validated['academic_session_id']}/classes";

        if (! Storage::exists($directory)) {
            return response()->json(['message' => 'No class report card has been generated yet.'], 404);
        }

        $files = collect(Storage::files($directory))
            ->sortByDesc(fn (string $path) => Storage::lastModified($path))
            ->values();

        if ($files->isEmpty()) {
            return response()->json(['message' => 'No class report card has been generated yet.'], 404);
        }

        $path = $files->first();

        return Storage::download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

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

        if ($reportCard->withheld_reason !== null && $request->user()->hasRole('parent')) {
            return response()->json([
                'message' => $reportCard->withheld_reason,
                'withheld' => true,
            ], 403);
        }

        return ReportCardResource::make($reportCard);
    }

    /**
     * Streams the stored PDF for an individual student's report card.
     */
    public function download(Request $request, Student $student): StreamedResponse|JsonResponse
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

        if ($reportCard->withheld_reason !== null) {
            return response()->json([
                'message' => $reportCard->withheld_reason,
                'withheld' => true,
            ], 403);
        }

        if ($reportCard->file_path === null || ! Storage::exists($reportCard->file_path)) {
            return response()->json(['message' => 'The report card file is not available.'], 404);
        }

        return Storage::download(
            $reportCard->file_path,
            basename($reportCard->file_path),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
