<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreRequisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'requisition_number' => $this->requisition_number,
            'requested_by' => $this->requested_by,
            'purpose' => $this->purpose,
            'needed_by' => $this->needed_by?->toDateString(),
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'rejection_reason' => $this->rejection_reason,
            'issued_by' => $this->issued_by,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'estimated_total' => $this->whenLoaded('lines', fn () => $this->sumEstimatedLineValues()),
            'lines' => StoreRequisitionLineResource::collection($this->whenLoaded('lines')),
            'issue_history' => StockMovementResource::collection($this->whenLoaded('issueMovements')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function sumEstimatedLineValues(): string
    {
        $total = '0.00';

        foreach ($this->lines as $line) {
            $lineValue = $line->estimatedLineValue();
            if ($lineValue !== null) {
                $total = bcadd($total, $lineValue, 2);
            }
        }

        return $total;
    }
}
