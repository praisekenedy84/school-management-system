<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sis;

use App\Http\Controllers\Concerns\ResolvesImportSchoolId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sis\AdmitStudentRequest;
use App\Http\Resources\StudentResource;
use App\Imports\StudentsImport;
use App\Models\Student;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ImportService;
use App\Services\Sis\StudentAdmissionService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use ResolvesImportSchoolId;

    public function __construct(
        private readonly StudentAdmissionService $admissionService,
        private readonly ExportService $exportService,
        private readonly ImportService $importService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $students = $this->scopedQuery($request)->paginate($request->integer('per_page', 15));

        return StudentResource::collection($students);
    }

    /** GET /api/v1/students/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $rows = $this->scopedQuery($request)->get();
        $columns = [
            'admission_number' => 'Admission No',
            'full_name' => 'Name',
            'gender' => 'Gender',
            'enrolments.0.classRoom.name' => 'Class',
            'enrolments.0.academicSession.name' => 'Academic Session',
            'residence_type' => 'Residence',
            'status' => 'Status',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'students', 'Students')
            : $this->exportService->excel($rows, $columns, 'students');
    }

    /** GET /api/v1/students/import-template */
    public function importTemplate()
    {
        $this->authorize('create', Student::class);

        return $this->exportService->template(
            ['admission_number', 'first_name', 'last_name', 'date_of_birth', 'gender', 'residence_type', 'class', 'academic_session'],
            ['ADM-0001', 'Jane', 'Doe', '2012-04-15', 'female', 'day', 'Form 1', '2026/2027'],
            'students'
        );
    }

    /** POST /api/v1/students/import (multipart: file, [school_id]) */
    public function import(Request $request)
    {
        $this->authorize('create', Student::class);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = $this->resolveImportSchoolId($request);
        $importer = new StudentsImport($this->admissionService, $schoolId);
        $result = $this->importService->run($importer, $request->file('file'));

        return response()->json($result->toArray());
    }

    private function scopedQuery(Request $request)
    {
        $query = Student::query()
            ->with(['enrolments' => fn ($query) => $query->latest('enrolled_at')->with(['classRoom', 'academicSession'])])
            ->latest('admitted_at');

        if ($request->user()->hasRole('parent')) {
            $query->whereIn('id', $request->user()->wards()->pluck('students.id'));
        }

        return $query;
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
