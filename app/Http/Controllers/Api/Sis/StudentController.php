<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sis\AdmitStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\Sis\StudentAdmissionService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(private readonly StudentAdmissionService $admissionService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $students = Student::query()
            ->with(['enrolments' => fn ($query) => $query->latest('enrolled_at')])
            ->latest('admitted_at')
            ->paginate($request->integer('per_page', 15));

        return StudentResource::collection($students);
    }

    public function store(AdmitStudentRequest $request)
    {
        $student = $this->admissionService->admit($request->validated());

        return StudentResource::make($student)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Student $student)
    {
        $this->authorize('view', $student);

        $student->load(['enrolments.classRoom', 'enrolments.academicSession', 'guardians']);

        return StudentResource::make($student);
    }
}
