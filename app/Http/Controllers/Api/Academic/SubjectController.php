<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\SubjectChanged;
use App\Http\Controllers\Concerns\ResolvesImportSchoolId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Imports\SubjectsImport;
use App\Models\Subject;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    use ResolvesImportSchoolId;

    public function __construct(
        private readonly ExportService $exportService,
        private readonly ImportService $importService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Subject::class);

        $subjects = Subject::query()
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return SubjectResource::collection($subjects);
    }

    /** GET /api/v1/subjects/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Subject::class);

        $rows = Subject::query()->orderBy('name')->get();
        $columns = ['name' => 'Name', 'code' => 'Code'];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'subjects', 'Subjects')
            : $this->exportService->excel($rows, $columns, 'subjects');
    }

    /** GET /api/v1/subjects/import-template */
    public function importTemplate()
    {
        $this->authorize('create', Subject::class);

        return $this->exportService->template(
            ['name', 'code'],
            ['Mathematics', 'MATH'],
            'subjects'
        );
    }

    /** POST /api/v1/subjects/import (multipart: file, [school_id]) */
    public function import(Request $request)
    {
        $this->authorize('create', Subject::class);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = $this->resolveImportSchoolId($request);
        $importer = new SubjectsImport($schoolId);
        $result = $this->importService->run($importer, $request->file('file'));

        return response()->json($result->toArray());
    }

    public function store(SubjectRequest $request)
    {
        $subject = Subject::create($request->validated());

        SubjectChanged::dispatch($subject, 'created', Auth::user());

        return SubjectResource::make($subject)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);

        return SubjectResource::make($subject);
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        SubjectChanged::dispatch($subject, 'updated', Auth::user());

        return SubjectResource::make($subject);
    }

    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        SubjectChanged::dispatch($subject, 'deleted', Auth::user());

        return response()->noContent();
    }
}
