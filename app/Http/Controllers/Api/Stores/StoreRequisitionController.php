<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\AddRequisitionToPurchaseRequest;
use App\Http\Requests\Stores\ApproveStoreRequisitionRequest;
use App\Http\Requests\Stores\CancelStoreRequisitionRequest;
use App\Http\Requests\Stores\CloseRequisitionLineRequest;
use App\Http\Requests\Stores\IssueStoreRequisitionRequest;
use App\Http\Requests\Stores\RejectStoreRequisitionRequest;
use App\Http\Requests\Stores\StoreRequisitionRequest;
use App\Http\Requests\Stores\SubmitStoreRequisitionRequest;
use App\Http\Resources\Stores\PurchaseRequestResource;
use App\Http\Resources\Stores\StoreRequisitionResource;
use App\Models\StoreRequisition;
use App\Services\Reporting\ExportService;
use App\Services\Stores\PurchaseRequestService;
use App\Services\Stores\StoreRequisitionService;
use Illuminate\Http\Request;

class StoreRequisitionController extends Controller
{
    public function __construct(
        private readonly StoreRequisitionService $requisitions,
        private readonly PurchaseRequestService $purchases,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', StoreRequisition::class);

        $query = StoreRequisition::query()
            ->with(['lines.inventoryItem', 'issueMovements.inventoryItem'])
            ->latest();

        if ($request->user()->hasRole('kitchen_staff') && ! $request->user()->hasRole(['tenant_admin', 'school_admin', 'storekeeper'])) {
            $query->where('requested_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return StoreRequisitionResource::collection($query->get());
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', StoreRequisition::class);

        $query = StoreRequisition::query()->latest();

        if ($request->user()->hasRole('kitchen_staff') && ! $request->user()->hasRole(['tenant_admin', 'school_admin', 'storekeeper'])) {
            $query->where('requested_by', $request->user()->id);
        }

        $rows = $query->get();
        $columns = [
            'requisition_number' => 'Number',
            'purpose' => 'Purpose',
            'needed_by' => 'Needed By',
            'status' => 'Status',
            'created_at' => 'Created',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'store-requisitions', 'Store Requisitions')
            : $this->exportService->excel($rows, $columns, 'store-requisitions');
    }

    public function store(StoreRequisitionRequest $request)
    {
        $requisition = $this->requisitions->createDraft(
            $request->validated(),
            $request->user()->id,
        );

        return StoreRequisitionResource::make($requisition)->response()->setStatusCode(201);
    }

    public function show(StoreRequisition $storeRequisition)
    {
        $this->authorize('view', $storeRequisition);

        return StoreRequisitionResource::make(
            $storeRequisition->load('lines.inventoryItem')
        );
    }

    public function update(StoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->updateDraft(
            $storeRequisition,
            $request->validated(),
        );

        return StoreRequisitionResource::make($requisition);
    }

    public function submit(SubmitStoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->submit($storeRequisition);

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Requisition submitted for storekeeper review.']);
    }

    public function approve(ApproveStoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->approve(
            $storeRequisition,
            $request->user()->id,
            $request->input('review_notes'),
        );

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Requisition approved.']);
    }

    public function reject(RejectStoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->reject(
            $storeRequisition,
            $request->user()->id,
            $request->validated('rejection_reason'),
        );

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Requisition rejected.']);
    }

    public function issue(IssueStoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->issue(
            $storeRequisition,
            $request->validated('lines'),
            $request->user()->id,
        );

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Items issued from store.']);
    }

    public function closeLine(CloseRequisitionLineRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->closeLine(
            $storeRequisition,
            $request->validated('line_id'),
            $request->input('line_notes'),
        );

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Requisition line closed.']);
    }

    public function addToPurchase(AddRequisitionToPurchaseRequest $request, StoreRequisition $storeRequisition)
    {
        $purchaseRequest = $this->purchases->addFromRequisition(
            $storeRequisition,
            $request->validated('mode'),
            $request->validated('purchase_request_id'),
            $request->user()->id,
        );

        return PurchaseRequestResource::make($purchaseRequest)
            ->additional(['message' => 'Requisition lines added to purchase list.']);
    }

    public function cancel(CancelStoreRequisitionRequest $request, StoreRequisition $storeRequisition)
    {
        $requisition = $this->requisitions->cancel($storeRequisition);

        return StoreRequisitionResource::make($requisition)
            ->additional(['message' => 'Requisition cancelled.']);
    }
}
