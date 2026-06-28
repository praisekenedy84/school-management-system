<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Stores\PurchaseRequestAmended;
use App\Events\Stores\PurchaseRequestApproved;
use App\Events\Stores\PurchaseRequestFulfilled;
use App\Events\Stores\PurchaseRequestRejected;
use App\Events\Stores\PurchaseRequestSubmitted;
use App\Events\Stores\StoreRequisitionAddedToPurchase;
use App\Models\InventoryItem;
use App\Models\PurchaseFulfillment;
use App\Models\PurchaseFulfillmentLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Scopes\SchoolScope;
use App\Models\StoreRequisition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, string $requestedBy): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $requestedBy) {
            $request = PurchaseRequest::create([
                'school_id' => $data['school_id'],
                'request_number' => 'DRAFT-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                'requested_by' => $requestedBy,
                'title' => $data['title'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);

            $this->syncLines($request, $data['lines'] ?? []);

            return $request->load('lines.inventoryItem');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PurchaseRequest $purchaseRequest, array $data): PurchaseRequest
    {
        $this->assertDraft($purchaseRequest);

        return DB::transaction(function () use ($purchaseRequest, $data) {
            $purchaseRequest->update([
                'title' => $data['title'] ?? $purchaseRequest->title,
                'notes' => $data['notes'] ?? $purchaseRequest->notes,
            ]);

            if (array_key_exists('lines', $data)) {
                $this->syncLines($purchaseRequest, $data['lines']);
            }

            return $purchaseRequest->fresh()->load('lines.inventoryItem');
        });
    }

    public function submit(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        $this->assertDraft($purchaseRequest);
        $purchaseRequest->load('lines');

        if ($purchaseRequest->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'At least one line is required to submit a purchase request.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest) {
            $now = now();
            $number = $this->nextPurchaseNumber($purchaseRequest->school_id, $now);

            $purchaseRequest->update([
                'request_number' => $number,
                'status' => 'submitted',
            ]);

            PurchaseRequestSubmitted::dispatch($purchaseRequest->fresh(), Auth::user());

            return $purchaseRequest->fresh()->load('lines.inventoryItem');
        });
    }

    public function approve(PurchaseRequest $purchaseRequest, string $reviewedBy, ?string $notes): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, ['submitted', 'under_review']);

        return DB::transaction(function () use ($purchaseRequest, $reviewedBy, $notes) {
            $purchaseRequest->update([
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            PurchaseRequestApproved::dispatch($purchaseRequest->fresh(), Auth::user());

            return $purchaseRequest->fresh()->load('lines.inventoryItem');
        });
    }

    /**
     * @param  list<array{line_id: string, amended_quantity?: string|float|null, amended_unit_cost?: string|float|null}>  $amendments
     */
    public function amend(PurchaseRequest $purchaseRequest, array $amendments, string $amendedBy, ?string $notes): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, ['submitted', 'under_review', 'approved']);

        return DB::transaction(function () use ($purchaseRequest, $amendments, $amendedBy, $notes) {
            $purchaseRequest->load('lines');
            $lineMap = $purchaseRequest->lines->keyBy('id');

            foreach ($amendments as $amendment) {
                $line = $lineMap->get($amendment['line_id'] ?? null);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'One or more line IDs are invalid for this purchase request.',
                    ]);
                }

                $updates = [];

                if (array_key_exists('amended_quantity', $amendment) && $amendment['amended_quantity'] !== null) {
                    $updates['amended_quantity'] = number_format((float) $amendment['amended_quantity'], 3, '.', '');
                }

                if (array_key_exists('amended_unit_cost', $amendment) && $amendment['amended_unit_cost'] !== null) {
                    $updates['amended_unit_cost'] = number_format((float) $amendment['amended_unit_cost'], 2, '.', '');
                }

                if ($updates !== []) {
                    $line->update($updates);
                }
            }

            $purchaseRequest->update([
                'status' => 'amended',
                'amended_by' => $amendedBy,
                'amended_at' => now(),
                'amendment_notes' => $notes,
            ]);

            PurchaseRequestAmended::dispatch($purchaseRequest->fresh()->load('lines.inventoryItem'), Auth::user());

            return $purchaseRequest->fresh()->load('lines.inventoryItem');
        });
    }

    public function reject(PurchaseRequest $purchaseRequest, string $reviewedBy, string $reason): PurchaseRequest
    {
        $this->assertStatus($purchaseRequest, ['submitted', 'under_review', 'approved', 'amended']);

        return DB::transaction(function () use ($purchaseRequest, $reviewedBy, $reason) {
            $purchaseRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            PurchaseRequestRejected::dispatch($purchaseRequest->fresh(), Auth::user());

            return $purchaseRequest->fresh()->load('lines.inventoryItem');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function fulfill(PurchaseRequest $purchaseRequest, array $data, array $attachments, string $fulfilledBy): PurchaseFulfillment
    {
        if (! $purchaseRequest->isFulfillable()) {
            throw ValidationException::withMessages([
                'status' => "Purchase request with status '{$purchaseRequest->status}' cannot be fulfilled.",
            ]);
        }

        if ($purchaseRequest->fulfillment()->exists()) {
            throw ValidationException::withMessages([
                'purchase_request' => 'This purchase request has already been fulfilled.',
            ]);
        }

        $fulfillmentLines = $data['lines'] ?? [];

        if ($fulfillmentLines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one fulfillment line is required.',
            ]);
        }

        $hasReceived = collect($fulfillmentLines)->contains(
            fn (array $line) => bccomp(number_format((float) ($line['received_quantity'] ?? 0), 3, '.', ''), '0', 3) > 0
        );

        $notes = $data['notes'] ?? null;

        if (! $hasReceived && (strlen(trim((string) $notes)) < 20)) {
            throw ValidationException::withMessages([
                'notes' => 'When nothing was received, notes of at least 20 characters are required.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $data, $attachments, $fulfilledBy, $fulfillmentLines) {
            $locked = PurchaseRequest::withoutGlobalScope(SchoolScope::class)
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isFulfillable()) {
                throw ValidationException::withMessages([
                    'status' => "Purchase request with status '{$locked->status}' cannot be fulfilled.",
                ]);
            }

            if ($locked->fulfillment()->exists()) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'This purchase request has already been fulfilled.',
                ]);
            }

            $locked->load('lines');
            $requestLineMap = $locked->lines->keyBy('id');
            $now = now();
            $fulfillmentNumber = $this->nextFulfillmentNumber($locked->school_id, $now);
            $storedAttachments = $this->storeAttachments($attachments, $locked->school_id, $now);
            $totalCost = '0.00';

            $fulfillment = PurchaseFulfillment::create([
                'school_id' => $locked->school_id,
                'purchase_request_id' => $locked->id,
                'fulfillment_number' => $fulfillmentNumber,
                'fulfilled_by' => $fulfilledBy,
                'supplier_name' => $data['supplier_name'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'fulfillment_date' => $data['fulfillment_date'],
                'notes' => $data['notes'] ?? null,
                'attachments' => $storedAttachments,
                'total_cost' => '0.00',
                'created_at' => $now,
            ]);

            foreach ($fulfillmentLines as $linePayload) {
                /** @var PurchaseRequestLine|null $requestLine */
                $requestLine = $requestLineMap->get($linePayload['purchase_request_line_id'] ?? null);

                if ($requestLine === null) {
                    throw ValidationException::withMessages([
                        'lines' => 'One or more purchase request line IDs are invalid.',
                    ]);
                }

                $receivedQty = number_format((float) ($linePayload['received_quantity'] ?? 0), 3, '.', '');
                $actualCost = number_format((float) ($linePayload['actual_unit_cost'] ?? 0), 2, '.', '');

                $inventoryItemId = $requestLine->inventory_item_id;

                if ($inventoryItemId === null && bccomp($receivedQty, '0', 3) > 0) {
                    $skuService = app(InventorySkuService::class);
                    $newItem = InventoryItem::create([
                        'school_id' => $locked->school_id,
                        'name' => $requestLine->item_name,
                        'sku' => $skuService->generate($locked->school_id),
                        'unit' => $requestLine->unit,
                        'current_quantity' => '0.000',
                        'unit_cost' => $actualCost,
                        'currency' => 'TZS',
                        'created_by' => $fulfilledBy,
                    ]);
                    $inventoryItemId = $newItem->id;
                    $requestLine->update(['inventory_item_id' => $inventoryItemId]);
                }

                if ($inventoryItemId === null) {
                    throw ValidationException::withMessages([
                        'lines' => "Line '{$requestLine->item_name}' has no catalog item and received quantity is zero.",
                    ]);
                }

                PurchaseFulfillmentLine::create([
                    'purchase_fulfillment_id' => $fulfillment->id,
                    'purchase_request_line_id' => $requestLine->id,
                    'inventory_item_id' => $inventoryItemId,
                    'requested_quantity' => $requestLine->effectiveQuantity(),
                    'received_quantity' => $receivedQty,
                    'requested_unit_cost' => $requestLine->effectiveUnitCost(),
                    'actual_unit_cost' => $actualCost,
                    'line_notes' => $linePayload['line_notes'] ?? null,
                    'created_at' => $now,
                ]);

                if (bccomp($receivedQty, '0', 3) > 0) {
                    $this->stockMovements->recordIn(
                        $inventoryItemId,
                        $receivedQty,
                        $actualCost,
                        'purchase_receipt',
                        PurchaseFulfillment::class,
                        $fulfillment->id,
                        $fulfilledBy,
                        "Purchase fulfillment {$fulfillmentNumber}",
                    );

                    $totalCost = bcadd($totalCost, bcmul($receivedQty, $actualCost, 4), 2);
                }
            }

            $fulfillment->update(['total_cost' => $totalCost]);

            $locked->update([
                'status' => 'fulfilled',
                'fulfilled_by' => $fulfilledBy,
                'fulfilled_at' => $now,
            ]);

            $fulfillment = $fulfillment->fresh()->load(['lines.inventoryItem', 'purchaseRequest']);

            PurchaseRequestFulfilled::dispatch($fulfillment, Auth::user());

            return $fulfillment;
        });
    }

    /**
     * Copy requisition lines onto a draft purchase request (PRD §4.1 link to procurement).
     *
     * @param  'shortfall'|'all'  $mode  shortfall = remaining qty minus stock; all = full remaining qty
     */
    public function addFromRequisition(
        StoreRequisition $requisition,
        string $mode,
        ?string $purchaseRequestId,
        string $requestedBy,
    ): PurchaseRequest {
        if (! $requisition->canAddToPurchase()) {
            throw ValidationException::withMessages([
                'status' => "Requisition with status '{$requisition->status}' cannot be added to a purchase list.",
            ]);
        }

        return DB::transaction(function () use ($requisition, $mode, $purchaseRequestId, $requestedBy) {
            $requisition->load('lines.inventoryItem');

            $linesToAdd = $this->buildPurchaseLinesFromRequisition($requisition, $mode);

            if ($linesToAdd === []) {
                throw ValidationException::withMessages([
                    'lines' => 'No quantities to add — stock may already cover this requisition.',
                ]);
            }

            if ($purchaseRequestId !== null) {
                $purchaseRequest = PurchaseRequest::withoutGlobalScope(SchoolScope::class)
                    ->where('school_id', $requisition->school_id)
                    ->whereKey($purchaseRequestId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $purchaseRequest->isDraft()) {
                    throw ValidationException::withMessages([
                        'purchase_request_id' => 'Only a draft purchase request can receive requisition lines.',
                    ]);
                }

                $this->appendLines($purchaseRequest, $linesToAdd);
            } else {
                $purchaseRequest = PurchaseRequest::create([
                    'school_id' => $requisition->school_id,
                    'request_number' => 'DRAFT-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                    'requested_by' => $requestedBy,
                    'store_requisition_id' => $requisition->id,
                    'title' => "Restock for {$requisition->requisition_number}",
                    'notes' => $requisition->purpose,
                    'status' => 'draft',
                ]);

                foreach ($linesToAdd as $lineData) {
                    PurchaseRequestLine::create([
                        'purchase_request_id' => $purchaseRequest->id,
                        ...$lineData,
                        'estimated_total' => bcmul($lineData['requested_quantity'], $lineData['estimated_unit_cost'], 2),
                    ]);
                }
            }

            $fresh = $purchaseRequest->fresh()->load('lines.inventoryItem');

            StoreRequisitionAddedToPurchase::dispatch($requisition, $fresh, $mode, Auth::user());

            return $fresh;
        });
    }

    /**
     * @return list<array{inventory_item_id: string, item_name: string, unit: string, requested_quantity: string, estimated_unit_cost: string, line_notes: string|null}>
     */
    private function buildPurchaseLinesFromRequisition(StoreRequisition $requisition, string $mode): array
    {
        $lines = [];

        foreach ($requisition->lines as $line) {
            if ($line->is_closed) {
                continue;
            }

            $remaining = $line->remainingQuantity();
            if (bccomp($remaining, '0', 3) <= 0) {
                continue;
            }

            $item = $line->inventoryItem;
            $stock = (string) ($item?->current_quantity ?? '0');

            $qty = $mode === 'shortfall'
                ? bcsub($remaining, $stock, 3)
                : $remaining;

            if (bccomp($qty, '0', 3) <= 0) {
                continue;
            }

            $lines[] = [
                'inventory_item_id' => $line->inventory_item_id,
                'item_name' => $item->name,
                'unit' => $line->unit,
                'requested_quantity' => number_format((float) $qty, 3, '.', ''),
                'estimated_unit_cost' => number_format((float) $item->unit_cost, 2, '.', ''),
                'line_notes' => "From requisition {$requisition->requisition_number}",
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{inventory_item_id: string, item_name: string, unit: string, requested_quantity: string, estimated_unit_cost: string, line_notes: string|null}>  $linesToAdd
     */
    private function appendLines(PurchaseRequest $purchaseRequest, array $linesToAdd): void
    {
        $purchaseRequest->load('lines');
        $existing = $purchaseRequest->lines->keyBy('inventory_item_id');

        foreach ($linesToAdd as $lineData) {
            $itemId = $lineData['inventory_item_id'];
            /** @var PurchaseRequestLine|null $existingLine */
            $existingLine = $existing->get($itemId);

            if ($existingLine !== null) {
                $newQty = bcadd(
                    (string) $existingLine->requested_quantity,
                    $lineData['requested_quantity'],
                    3,
                );
                $existingLine->update([
                    'requested_quantity' => $newQty,
                    'estimated_total' => bcmul($newQty, (string) $existingLine->estimated_unit_cost, 2),
                ]);
            } else {
                PurchaseRequestLine::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    ...$lineData,
                    'estimated_total' => bcmul($lineData['requested_quantity'], $lineData['estimated_unit_cost'], 2),
                ]);
            }
        }
    }

    /**
     * @param  list<array{inventory_item_id?: string|null, item_name: string, unit: string, requested_quantity: string|float, estimated_unit_cost?: string|float, line_notes?: string|null}>  $lines
     */
    private function syncLines(PurchaseRequest $purchaseRequest, array $lines): void
    {
        $purchaseRequest->lines()->delete();

        foreach ($lines as $lineData) {
            if (! empty($lineData['inventory_item_id'])) {
                InventoryItem::withoutGlobalScope(SchoolScope::class)
                    ->where('school_id', $purchaseRequest->school_id)
                    ->whereKey($lineData['inventory_item_id'])
                    ->firstOrFail();
            }

            PurchaseRequestLine::create([
                'purchase_request_id' => $purchaseRequest->id,
                'inventory_item_id' => $lineData['inventory_item_id'] ?? null,
                'item_name' => $lineData['item_name'],
                'unit' => $lineData['unit'],
                'requested_quantity' => number_format((float) $lineData['requested_quantity'], 3, '.', ''),
                'estimated_unit_cost' => number_format((float) ($lineData['estimated_unit_cost'] ?? 0), 2, '.', ''),
                'estimated_total' => bcmul(
                    number_format((float) $lineData['requested_quantity'], 3, '.', ''),
                    number_format((float) ($lineData['estimated_unit_cost'] ?? 0), 2, '.', ''),
                    2,
                ),
                'line_notes' => $lineData['line_notes'] ?? null,
            ]);
        }
    }

    private function assertDraft(PurchaseRequest $purchaseRequest): void
    {
        if (! $purchaseRequest->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft purchase requests can be modified.',
            ]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(PurchaseRequest $purchaseRequest, array $allowed): void
    {
        if (! in_array($purchaseRequest->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Purchase request with status '{$purchaseRequest->status}' cannot perform this action.",
            ]);
        }
    }

    private function nextPurchaseNumber(string $schoolId, Carbon $now): string
    {
        $year = $now->format('Y');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["purchase:{$schoolId}:{$year}"]);

        $count = PurchaseRequest::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereYear('created_at', $year)
            ->where('request_number', 'not like', 'DRAFT-%')
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return 'PUR-'.$now->format('Ymd')."-{$sequence}";
    }

    private function nextFulfillmentNumber(string $schoolId, Carbon $now): string
    {
        $year = $now->format('Y');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["fulfillment:{$schoolId}:{$year}"]);

        $count = PurchaseFulfillment::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereYear('created_at', $year)
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return 'PRC-'.$now->format('Ymd')."-{$sequence}";
    }

    /**
     * @param  list<UploadedFile>  $attachments
     * @return list<array<string, mixed>>
     */
    private function storeAttachments(array $attachments, string $schoolId, Carbon $date): array
    {
        $tenantKey = (string) tenant()->getTenantKey();
        $year = $date->format('Y');
        $month = $date->format('m');
        $directory = "purchase-fulfillments/{$tenantKey}/{$schoolId}/{$year}/{$month}";

        $stored = [];

        foreach ($attachments as $file) {
            $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
            $filename = Str::uuid()->toString().'.'.$extension;
            $path = $file->storeAs($directory, $filename);

            $stored[] = [
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        return $stored;
    }
}
