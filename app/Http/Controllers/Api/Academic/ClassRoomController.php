<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\ClassRoomChanged;
use App\Http\Controllers\Concerns\ResolvesImportSchoolId;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\ClassRoomRequest;
use App\Http\Resources\ClassRoomResource;
use App\Imports\ClassesImport;
use App\Models\ClassRoom;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRoomController extends Controller
{
    use ResolvesImportSchoolId;

    public function __construct(
        private readonly ExportService $exportService,
        private readonly ImportService $importService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', ClassRoom::class);

        $classes = ClassRoom::query()->orderBy('name')->get();

        return ClassRoomResource::collection($classes);
    }

    /** GET /api/v1/classes/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', ClassRoom::class);

        $rows = ClassRoom::query()->orderBy('name')->get();
        $columns = ['name' => 'Name', 'level' => 'Level'];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'classes', 'Classes')
            : $this->exportService->excel($rows, $columns, 'classes');
    }

    /** GET /api/v1/classes/import-template */
    public function importTemplate()
    {
        $this->authorize('create', ClassRoom::class);

        return $this->exportService->template(['name', 'level'], ['Form 1', '1'], 'classes');
    }

    /** POST /api/v1/classes/import (multipart: file, [school_id]) */
    public function import(Request $request)
    {
        $this->authorize('create', ClassRoom::class);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $schoolId = $this->resolveImportSchoolId($request);
        $importer = new ClassesImport($schoolId);
        $result = $this->importService->run($importer, $request->file('file'));

        return response()->json($result->toArray());
    }

    public function show(ClassRoom $classRoom)
    {
        $this->authorize('view', $classRoom);

        return ClassRoomResource::make($classRoom);
    }

    public function store(ClassRoomRequest $request)
    {
        $classRoom = ClassRoom::create($request->validated());

        ClassRoomChanged::dispatch($classRoom, 'created', Auth::user());

        return ClassRoomResource::make($classRoom)
            ->response()
            ->setStatusCode(201);
    }

    public function update(ClassRoomRequest $request, ClassRoom $classRoom)
    {
        $classRoom->update($request->validated());

        ClassRoomChanged::dispatch($classRoom, 'updated', Auth::user());

        return ClassRoomResource::make($classRoom);
    }

    public function destroy(ClassRoom $classRoom)
    {
        $this->authorize('delete', $classRoom);

        $classRoom->delete();

        ClassRoomChanged::dispatch($classRoom, 'deleted', Auth::user());

        return response()->noContent();
    }
}
