<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Events\Assessment\AssessmentChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssessmentController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Assessment::class);

        $assessments = $this->scopedQuery($request)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AssessmentResource::collection($assessments);
    }

    /** GET /api/v1/assessments/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Assessment::class);

        $rows = $this->scopedQuery($request)->latest()->get();
        $columns = [
            'name' => 'Name',
            'subject.name' => 'Subject',
            'academicSession.name' => 'Session',
            'weight' => 'Weight',
            'max_score' => 'Max Score',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'assessments', 'Assessments')
            : $this->exportService->excel($rows, $columns, 'assessments');
    }

    private function scopedQuery(Request $request)
    {
        return Assessment::query()
            ->with(['subject', 'academicSession'])
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('academic_session_id', $request->input('academic_session_id')));
    }

    public function store(AssessmentRequest $request)
    {
        $assessment = Assessment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        AssessmentChanged::dispatch($assessment, 'created', Auth::user());

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Assessment $assessment)
    {
        $this->authorize('view', $assessment);

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']));
    }

    public function update(AssessmentRequest $request, Assessment $assessment)
    {
        $assessment->update($request->validated());

        AssessmentChanged::dispatch($assessment, 'updated', Auth::user());

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']));
    }

    public function destroy(Assessment $assessment)
    {
        $this->authorize('delete', $assessment);

        $assessment->delete();

        AssessmentChanged::dispatch($assessment, 'deleted', Auth::user());

        return response()->noContent();
    }
}
