<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\AmendPurchaseRequestRequest;
use App\Http\Requests\Stores\ApprovePurchaseRequestRequest;
use App\Http\Requests\Stores\FulfillPurchaseRequestRequest;
use App\Http\Requests\Stores\PurchaseRequestFormRequest;
use App\Http\Requests\Stores\RejectPurchaseRequestRequest;
use App\Http\Requests\Stores\SubmitPurchaseRequestRequest;
use App\Http\Resources\Stores\PurchaseFulfillmentResource;
use App\Http\Resources\Stores\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\Reporting\ExportService;
use App\Services\Stores\PurchaseRequestService;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchases,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $query = PurchaseRequest::query()
            ->with('lines.inventoryItem')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return PurchaseRequestResource::collection($query->get());
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $rows = PurchaseRequest::query()->latest()->get();
        $columns = [
            'request_number' => 'Number',
            'title' => 'Title',
            'status' => 'Status',
            'created_at' => 'Created',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'purchase-requests', 'Purchase Requests')
            : $this->exportService->excel($rows, $columns, 'purchase-requests');
    }

    public function store(PurchaseRequestFormRequest $request)
    {
        $purchaseRequest = $this->purchases->createDraft(
            $request->validated(),
            $request->user()->id,
        );

        return PurchaseRequestResource::make($purchaseRequest)->response()->setStatusCode(201);
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        return PurchaseRequestResource::make(
            $purchaseRequest->load(['lines.inventoryItem', 'fulfillment.lines.inventoryItem'])
        );
    }

    public function update(PurchaseRequestFormRequest $request, PurchaseRequest $purchaseRequest)
    {
        $updated = $this->purchases->updateDraft(
            $purchaseRequest,
            $request->validated(),
        );

        return PurchaseRequestResource::make($updated);
    }

    public function submit(SubmitPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest)
    {
        $updated = $this->purchases->submit($purchaseRequest);

        return PurchaseRequestResource::make($updated)
            ->additional(['message' => 'Purchase request submitted to Finance.']);
    }

    public function approve(ApprovePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest)
    {
        $updated = $this->purchases->approve(
            $purchaseRequest,
            $request->user()->id,
            $request->input('review_notes'),
        );

        return PurchaseRequestResource::make($updated)
            ->additional(['message' => 'Purchase request approved.']);
    }

    public function amend(AmendPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest)
    {
        $updated = $this->purchases->amend(
            $purchaseRequest,
            $request->validated('lines'),
            $request->user()->id,
            $request->input('amendment_notes'),
        );

        return PurchaseRequestResource::make($updated)
            ->additional(['message' => 'Purchase request amended.']);
    }

    public function reject(RejectPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest)
    {
        $updated = $this->purchases->reject(
            $purchaseRequest,
            $request->user()->id,
            $request->validated('rejection_reason'),
        );

        return PurchaseRequestResource::make($updated)
            ->additional(['message' => 'Purchase request rejected.']);
    }

    public function fulfill(FulfillPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest)
    {
        $fulfillment = $this->purchases->fulfill(
            $purchaseRequest,
            $request->safe()->except('attachments'),
            $request->file('attachments', []),
            $request->user()->id,
        );

        return PurchaseFulfillmentResource::make($fulfillment)
            ->additional(['message' => 'Purchase fulfilled; stock updated.'])
            ->response()
            ->setStatusCode(201);
    }

    public function showFulfillment(PurchaseRequest $purchaseRequest)
    {
        $this->authorize('view', $purchaseRequest);

        $fulfillment = $purchaseRequest->fulfillment()
            ->with(['lines.inventoryItem', 'purchaseRequest'])
            ->firstOrFail();

        return PurchaseFulfillmentResource::make($fulfillment);
    }
}
