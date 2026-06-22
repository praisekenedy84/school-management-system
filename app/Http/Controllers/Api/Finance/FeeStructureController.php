<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Finance;

use App\Events\Finance\FeeStructureChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plain CRUD for fee-structure configuration — no service layer (mirrors
 * AssessmentController, RULES.md §"controllers are thin"). Authorization runs
 * via FeeStructurePolicy (index/show authorize here; store/update authorize
 * inside FeeStructureRequest::authorize()).
 */
class FeeStructureController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FeeStructure::class);

        $feeStructures = $this->scopedQuery($request)
            ->paginate($request->integer('per_page', 20));

        return FeeStructureResource::collection($feeStructures);
    }

    /** GET /api/v1/fee-structures/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', FeeStructure::class);

        $rows = $this->scopedQuery($request)->get();
        $columns = [
            'classRoom.name' => 'Class',
            'academicSession.name' => 'Session',
            'fee_type' => 'Fee Type',
            'amount' => 'Amount',
            'applicable_to' => 'Applicable To',
            'is_mandatory' => 'Mandatory',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'fee-structures', 'Fee Structures')
            : $this->exportService->excel($rows, $columns, 'fee-structures');
    }

    private function scopedQuery(Request $request)
    {
        return FeeStructure::query()
            ->with(['academicSession', 'classRoom'])
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('academic_session_id', $request->input('academic_session_id')))
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->input('class_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest();
    }

    public function store(FeeStructureRequest $request)
    {
        $feeStructure = FeeStructure::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        FeeStructureChanged::dispatch($feeStructure, 'created', Auth::user());

        return FeeStructureResource::make($feeStructure->load(['academicSession', 'classRoom']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(FeeStructure $feeStructure)
    {
        $this->authorize('view', $feeStructure);

        return FeeStructureResource::make($feeStructure->load(['academicSession', 'classRoom']));
    }

    public function update(FeeStructureRequest $request, FeeStructure $feeStructure)
    {
        $feeStructure->update($request->validated());

        FeeStructureChanged::dispatch($feeStructure, 'updated', Auth::user());

        return FeeStructureResource::make($feeStructure->load(['academicSession', 'classRoom']));
    }

    public function destroy(FeeStructure $feeStructure)
    {
        $this->authorize('delete', $feeStructure);

        $feeStructure->delete();

        FeeStructureChanged::dispatch($feeStructure, 'deleted', Auth::user());

        return response()->noContent();
    }
}
