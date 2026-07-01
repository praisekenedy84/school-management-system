<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\EnterMarkRequest;
use App\Http\Resources\ResultRecordResource;
use App\Models\ResultRecord;
use App\Services\Assessment\MarkEntryService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(
        private readonly MarkEntryService $markEntry,
        private readonly ExportService $exportService,
    ) {}

    /**
     * Filterable by student_id / assessment_id / academic_session_id.
     * Returns latest-version-only rows unless `?all_versions=1` is passed.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ResultRecord::class);

        $results = $this->scopedQuery($request)->latest('version')->paginate($request->integer('per_page', 20));

        return ResultRecordResource::collection($results);
    }

    /** GET /api/v1/results/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', ResultRecord::class);

        $rows = $this->scopedQuery($request)->latest('version')->get();
        $columns = [
            'student.admission_number' => 'Admission No',
            'student.full_name' => 'Student',
            'subject.name' => 'Subject',
            'assessment.name' => 'Assessment',
            'score' => 'Score',
            'grade' => 'Grade',
            'is_published' => 'Published',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'results', 'Results')
            : $this->exportService->excel($rows, $columns, 'results');
    }

    private function scopedQuery(Request $request)
    {
        $query = ResultRecord::query()
            ->with(['student', 'subject', 'assessment'])
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->input('student_id')))
            ->when($request->filled('assessment_id'), fn ($q) => $q->where('assessment_id', $request->input('assessment_id')))
            ->when($request->filled('academic_session_id'), fn ($q) => $q->where('academic_session_id', $request->input('academic_session_id')));

        // A parent only ever sees PUBLISHED results for their own wards —
        // `viewAny`/`view` are open placeholders (see ResultRecordPolicy),
        // so without this, passing any student_id would otherwise leak
        // another family's (and unpublished/draft) results.
        if ($request->user()->hasRole('parent')) {
            $query->where('is_published', true)
                ->whereIn('student_id', $request->user()->wards()->pluck('students.id'));
        }

        if (! $request->boolean('all_versions')) {
            $query->whereIn('id', function ($sub) {
                $sub->selectRaw('DISTINCT ON (student_id, assessment_id) id')
                    ->from('result_records')
                    ->orderBy('student_id')
                    ->orderBy('assessment_id')
                    ->orderByDesc('version');
            });
        }

        return $query;
    }

    public function store(EnterMarkRequest $request)
    {
        $result = $this->markEntry->enter($request->validated(), $request->user()->id);

        return ResultRecordResource::make($result->load(['student', 'subject', 'assessment']))
            ->response()
            ->setStatusCode(201);
    }
}
