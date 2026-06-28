<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Stores\StoreRequisitionApproved;
use App\Events\Stores\StoreRequisitionIssued;
use App\Events\Stores\StoreRequisitionRejected;
use App\Events\Stores\StoreRequisitionSubmitted;
use App\Models\InventoryItem;
use App\Models\Scopes\SchoolScope;
use App\Models\StoreRequisition;
use App\Models\StoreRequisitionLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreRequisitionService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, string $requestedBy): StoreRequisition
    {
        return DB::transaction(function () use ($data, $requestedBy) {
            $requisition = StoreRequisition::create([
                'school_id' => $data['school_id'],
                'requisition_number' => 'DRAFT-'.strtoupper(substr(md5((string) microtime(true)), 0, 8)),
                'requested_by' => $requestedBy,
                'purpose' => $data['purpose'] ?? null,
                'needed_by' => $data['needed_by'] ?? null,
                'status' => 'draft',
            ]);

            $this->syncLines($requisition, $data['lines'] ?? []);

            return $requisition->load('lines.inventoryItem');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(StoreRequisition $requisition, array $data): StoreRequisition
    {
        $this->assertDraft($requisition);

        return DB::transaction(function () use ($requisition, $data) {
            $requisition->update([
                'purpose' => $data['purpose'] ?? $requisition->purpose,
                'needed_by' => $data['needed_by'] ?? $requisition->needed_by,
            ]);

            if (array_key_exists('lines', $data)) {
                $this->syncLines($requisition, $data['lines']);
            }

            return $requisition->fresh()->load('lines.inventoryItem');
        });
    }

    public function submit(StoreRequisition $requisition): StoreRequisition
    {
        $this->assertDraft($requisition);

        $requisition->load('lines');

        if ($requisition->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'At least one line is required to submit a requisition.',
            ]);
        }

        return DB::transaction(function () use ($requisition) {
            $now = now();
            $number = $this->nextRequisitionNumber($requisition->school_id, $now);

            $requisition->update([
                'requisition_number' => $number,
                'status' => 'submitted',
            ]);

            StoreRequisitionSubmitted::dispatch($requisition->fresh(), Auth::user());

            return $requisition->fresh()->load('lines.inventoryItem');
        });
    }

    public function approve(StoreRequisition $requisition, string $reviewedBy, ?string $notes): StoreRequisition
    {
        $this->assertStatus($requisition, ['submitted']);

        return DB::transaction(function () use ($requisition, $reviewedBy, $notes) {
            $requisition->update([
                'status' => 'approved',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            StoreRequisitionApproved::dispatch($requisition->fresh(), Auth::user());

            return $requisition->fresh()->load('lines.inventoryItem');
        });
    }

    public function reject(StoreRequisition $requisition, string $reviewedBy, string $reason): StoreRequisition
    {
        $this->assertStatus($requisition, ['submitted']);

        return DB::transaction(function () use ($requisition, $reviewedBy, $reason) {
            $requisition->update([
                'status' => 'rejected',
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            StoreRequisitionRejected::dispatch($requisition->fresh(), Auth::user());

            return $requisition->fresh()->load('lines.inventoryItem');
        });
    }

    /**
     * @param  list<array{line_id: string, quantity: string|float}>  $issueLines
     */
    public function issue(StoreRequisition $requisition, array $issueLines, string $issuedBy): StoreRequisition
    {
        if (! $requisition->isIssuable()) {
            throw ValidationException::withMessages([
                'status' => "Requisition with status '{$requisition->status}' cannot be issued.",
            ]);
        }

        if ($issueLines === []) {
            throw ValidationException::withMessages([
                'lines' => 'At least one line with a quantity is required.',
            ]);
        }

        return DB::transaction(function () use ($requisition, $issueLines, $issuedBy) {
            $locked = StoreRequisition::withoutGlobalScope(SchoolScope::class)
                ->whereKey($requisition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isIssuable()) {
                throw ValidationException::withMessages([
                    'status' => "Requisition with status '{$locked->status}' cannot be issued.",
                ]);
            }

            $locked->load('lines.inventoryItem');
            $lineMap = $locked->lines->keyBy('id');

            foreach ($issueLines as $payload) {
                $lineId = $payload['line_id'];
                $qty = number_format((float) $payload['quantity'], 3, '.', '');

                /** @var StoreRequisitionLine|null $line */
                $line = $lineMap->get($lineId);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'lines' => "Line {$lineId} does not belong to this requisition.",
                    ]);
                }

                if ($line->is_closed) {
                    throw ValidationException::withMessages([
                        'lines' => "Line {$lineId} is closed and cannot receive further issues.",
                    ]);
                }

                $remaining = $line->remainingQuantity();

                if (bccomp($qty, '0', 3) <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Issue quantity must be greater than zero.',
                    ]);
                }

                if (bccomp($qty, $remaining, 3) > 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "Cannot issue {$qty} — only {$remaining} remaining on this line.",
                    ]);
                }

                $this->stockMovements->recordOut(
                    $line->inventory_item_id,
                    $qty,
                    'requisition_issue',
                    StoreRequisition::class,
                    $locked->id,
                    $issuedBy,
                    "Requisition {$locked->requisition_number}",
                );

                $line->update([
                    'issued_quantity' => bcadd((string) $line->issued_quantity, $qty, 3),
                ]);
            }

            $locked->update([
                'issued_by' => $issuedBy,
                'issued_at' => now(),
                'status' => $this->computeHeaderStatus($locked->fresh()->load('lines')),
            ]);

            $fresh = $locked->fresh()->load('lines.inventoryItem');
            $isPartial = $fresh->status === 'partially_issued';

            StoreRequisitionIssued::dispatch($fresh, $isPartial, Auth::user());

            return $fresh;
        });
    }

    public function closeLine(StoreRequisition $requisition, string $lineId, ?string $note): StoreRequisition
    {
        if (! $requisition->isIssuable()) {
            throw ValidationException::withMessages([
                'status' => "Requisition with status '{$requisition->status}' cannot have lines closed.",
            ]);
        }

        return DB::transaction(function () use ($requisition, $lineId, $note) {
            $line = StoreRequisitionLine::query()
                ->where('store_requisition_id', $requisition->id)
                ->whereKey($lineId)
                ->firstOrFail();

            if ($line->is_closed) {
                throw ValidationException::withMessages([
                    'line_id' => 'This line is already closed.',
                ]);
            }

            $line->update([
                'is_closed' => true,
                'line_notes' => $note ?? $line->line_notes,
            ]);

            $requisition->update([
                'status' => $this->computeHeaderStatus($requisition->fresh()->load('lines')),
            ]);

            return $requisition->fresh()->load('lines.inventoryItem');
        });
    }

    public function cancel(StoreRequisition $requisition): StoreRequisition
    {
        if (! $requisition->isCancellable()) {
            throw ValidationException::withMessages([
                'status' => "Requisition with status '{$requisition->status}' cannot be cancelled.",
            ]);
        }

        $requisition->update(['status' => 'cancelled']);

        return $requisition->fresh()->load('lines.inventoryItem');
    }

    /**
     * @param  list<array{inventory_item_id: string, requested_quantity: string|float, line_notes?: string|null}>  $lines
     */
    private function syncLines(StoreRequisition $requisition, array $lines): void
    {
        $requisition->lines()->delete();

        foreach ($lines as $lineData) {
            $item = InventoryItem::withoutGlobalScope(SchoolScope::class)
                ->where('school_id', $requisition->school_id)
                ->whereKey($lineData['inventory_item_id'])
                ->firstOrFail();

            StoreRequisitionLine::create([
                'store_requisition_id' => $requisition->id,
                'inventory_item_id' => $item->id,
                'requested_quantity' => number_format((float) $lineData['requested_quantity'], 3, '.', ''),
                'issued_quantity' => '0.000',
                'unit' => $item->unit,
                'line_notes' => $lineData['line_notes'] ?? null,
            ]);
        }
    }

    private function computeHeaderStatus(StoreRequisition $requisition): string
    {
        $allComplete = $requisition->lines->every(fn (StoreRequisitionLine $line) => $line->isComplete());

        if ($allComplete) {
            return 'issued';
        }

        $anyIssued = $requisition->lines->contains(
            fn (StoreRequisitionLine $line) => bccomp((string) $line->issued_quantity, '0', 3) > 0
        );

        return $anyIssued ? 'partially_issued' : $requisition->status;
    }

    private function assertDraft(StoreRequisition $requisition): void
    {
        if (! $requisition->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requisitions can be modified.',
            ]);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(StoreRequisition $requisition, array $allowed): void
    {
        if (! in_array($requisition->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Requisition with status '{$requisition->status}' cannot perform this action.",
            ]);
        }
    }

    private function nextRequisitionNumber(string $schoolId, Carbon $now): string
    {
        $year = $now->format('Y');

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["requisition:{$schoolId}:{$year}"]);

        $count = StoreRequisition::withoutGlobalScope(SchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereYear('created_at', $year)
            ->where('requisition_number', 'not like', 'DRAFT-%')
            ->count();

        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return 'REQ-'.$now->format('Ymd')."-{$sequence}";
    }
}
