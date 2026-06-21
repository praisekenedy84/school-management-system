<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\CreateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Services\Academic\AssignmentVisibilityService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentVisibilityService $visibility) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Assignment::class);

        $assignments = $this->visibility
            ->scopeVisible(
                Assignment::query()->with(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher']),
                $request->user()
            )
            ->latest('published_at')
            ->paginate($request->integer('per_page', 20));

        return AssignmentResource::collection($assignments);
    }

    public function store(CreateAssignmentRequest $request)
    {
        $assignment = Assignment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

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

    public function publish(Assignment $assignment)
    {
        $this->authorize('update', $assignment);

        $assignment->update(['published_at' => $assignment->published_at ?? now()]);

        return AssignmentResource::make(
            $assignment->load(['teacherAssignment.classRoom', 'teacherAssignment.subject', 'teacherAssignment.teacher'])
        );
    }
}
