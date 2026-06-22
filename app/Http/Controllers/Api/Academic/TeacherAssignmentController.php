<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\TeacherAssignmentChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\TeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\TeacherAssignment;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TeacherAssignment::class);

        $request->validate([
            'teacher_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'class_id' => ['nullable', 'uuid', Rule::exists('classes', 'id')],
        ]);

        $assignments = $this->scopedQuery($request)
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return TeacherAssignmentResource::collection($assignments);
    }

    /** GET /api/v1/teacher-assignments/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', TeacherAssignment::class);

        $rows = $this->scopedQuery($request)->latest()->get();
        $columns = [
            'teacher.name' => 'Teacher',
            'classRoom.name' => 'Class',
            'subject.name' => 'Subject',
            'academicSession.name' => 'Academic Session',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'teacher-assignments', 'Teacher Assignments')
            : $this->exportService->excel($rows, $columns, 'teacher-assignments');
    }

    private function scopedQuery(Request $request)
    {
        return TeacherAssignment::query()
            ->with(['teacher', 'classRoom', 'subject', 'academicSession'])
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->string('teacher_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->string('class_id')));
    }

    public function store(TeacherAssignmentRequest $request)
    {
        $assignment = TeacherAssignment::create($request->validated());

        TeacherAssignmentChanged::dispatch($assignment, 'created', Auth::user());

        return TeacherAssignmentResource::make(
            $assignment->load(['teacher', 'classRoom', 'subject', 'academicSession'])
        )->response()->setStatusCode(201);
    }

    public function destroy(TeacherAssignment $teacherAssignment)
    {
        $this->authorize('delete', $teacherAssignment);

        $teacherAssignment->delete();

        TeacherAssignmentChanged::dispatch($teacherAssignment, 'deleted', Auth::user());

        return response()->noContent();
    }
}
