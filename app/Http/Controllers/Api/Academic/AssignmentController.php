<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\AssignmentChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\CreateAssignmentRequest;
use App\Http\Requests\Academic\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Services\Academic\AssignmentVisibilityService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentVisibilityService $visibility,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Assignment::class);

        $assignments = $this->scopedQuery($request)
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return AssignmentResource::collection($assignments);
    }

    /** GET /api/v1/assignments/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Assignment::class);

        $rows = $this->scopedQuery($request)->latest('created_at')->get();
        $columns = [
            'title' => 'Title',
            'teacherAssignment.classRoom.name' => 'Class',
            'teacherAssignment.subject.name' => 'Subject',
            'due_at' => 'Due',
            'published_at' => 'Published At',
            'archived_at' => 'Archived At',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'assignments', 'Assignments')
            : $this->exportService->excel($rows, $columns, 'assignments');
    }

    private function scopedQuery(Request $request)
    {
        $query = $this->visibility->scopeVisible(
            Assignment::query()->with(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher']),
            $request->user()
        );

        if ($request->filled('class_id')) {
            $query->whereHas(
                'teacherAssignment',
                fn ($q) => $q->where('class_id', $request->string('class_id'))
            );
        }

        if ($request->filled('subject_id')) {
            $query->whereHas(
                'teacherAssignment',
                fn ($q) => $q->where('subject_id', $request->string('subject_id'))
            );
        }

        if ($request->filled('due_from')) {
            $query->whereDate('due_at', '>=', $request->date('due_from'));
        }

        if ($request->filled('due_to')) {
            $query->whereDate('due_at', '<=', $request->date('due_to'));
        }

        $status = $request->string('status')->toString();
        match ($status) {
            'draft' => $query->whereNull('published_at')->whereNull('archived_at'),
            'published' => $query->whereNotNull('published_at')->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            default => $query->whereNull('archived_at'),
        };

        return $query;
    }

    public function store(CreateAssignmentRequest $request)
    {
        $assignment = Assignment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        AssignmentChanged::dispatch($assignment, 'created', Auth::user());

        return AssignmentResource::make(
            $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher'])
        )->response()->setStatusCode(201);
    }

    public function show(Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher']);

        return AssignmentResource::make($assignment);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment)
    {
        $assignment->update($request->validated());

        AssignmentChanged::dispatch($assignment, 'updated', Auth::user());

        return AssignmentResource::make(
            $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher'])
        );
    }

    public function publish(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        abort_if($assignment->isArchived(), 422, 'Archived assignments cannot be published.');

        $assignment->update(['published_at' => $assignment->published_at ?? now()]);

        AssignmentChanged::dispatch($assignment, 'published', Auth::user());

        return AssignmentResource::make(
            $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher'])
        );
    }

    public function archive(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        abort_unless($assignment->isPublished(), 422, 'Only published assignments can be archived.');

        $assignment->update(['archived_at' => now()]);

        AssignmentChanged::dispatch($assignment, 'archived', Auth::user());

        return AssignmentResource::make(
            $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher'])
        );
    }
}
