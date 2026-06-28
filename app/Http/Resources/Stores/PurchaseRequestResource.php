<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'request_number' => $this->request_number,
            'requested_by' => $this->requested_by,
            'store_requisition_id' => $this->store_requisition_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'rejection_reason' => $this->rejection_reason,
            'amended_by' => $this->amended_by,
            'amended_at' => $this->amended_at?->toIso8601String(),
            'amendment_notes' => $this->amendment_notes,
            'fulfilled_by' => $this->fulfilled_by,
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'estimated_total' => $this->whenLoaded('lines', fn () => $this->sumPurchaseLineTotals('estimated')),
            'effective_total' => $this->whenLoaded('lines', fn () => $this->sumPurchaseLineTotals('effective')),
            'lines' => PurchaseRequestLineResource::collection($this->whenLoaded('lines')),
            'fulfillment' => PurchaseFulfillmentResource::make($this->whenLoaded('fulfillment')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function sumPurchaseLineTotals(string $mode): string
    {
        $total = '0.00';

        foreach ($this->lines as $line) {
            $lineTotal = $mode === 'effective'
                ? $line->effectiveLineTotal()
                : $line->estimatedLineTotal();
            $total = bcadd($total, $lineTotal, 2);
        }

        return $total;
    }
}
