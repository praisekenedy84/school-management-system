<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\TeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TeacherAssignment::class);

        $request->validate([
            'teacher_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'class_id' => ['nullable', 'uuid', Rule::exists('classes', 'id')],
        ]);

        $assignments = TeacherAssignment::query()
            ->with(['teacher', 'classRoom', 'subject', 'academicSession'])
            ->when($request->filled('teacher_id'), fn ($query) => $query->where('teacher_id', $request->string('teacher_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->string('class_id')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return TeacherAssignmentResource::collection($assignments);
    }

    public function store(TeacherAssignmentRequest $request)
    {
        $assignment = TeacherAssignment::create($request->validated());

        return TeacherAssignmentResource::make(
            $assignment->load(['teacher', 'classRoom', 'subject', 'academicSession'])
        )->response()->setStatusCode(201);
    }

    public function destroy(TeacherAssignment $teacherAssignment)
    {
        $this->authorize('delete', $teacherAssignment);

        $teacherAssignment->delete();

        return response()->noContent();
    }
}
