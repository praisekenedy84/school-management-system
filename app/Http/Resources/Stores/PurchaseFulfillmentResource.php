<?php

declare(strict_types=1);

namespace App\Http\Resources\Stores;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseFulfillmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'purchase_request_id' => $this->purchase_request_id,
            'fulfillment_number' => $this->fulfillment_number,
            'fulfilled_by' => $this->fulfilled_by,
            'supplier_name' => $this->supplier_name,
            'supplier_reference' => $this->supplier_reference,
            'fulfillment_date' => $this->fulfillment_date?->toDateString(),
            'notes' => $this->notes,
            'attachments' => $this->attachments,
            'total_cost' => (string) $this->total_cost,
            'lines' => PurchaseFulfillmentLineResource::collection($this->whenLoaded('lines')),
            'purchase_request' => PurchaseRequestResource::make($this->whenLoaded('purchaseRequest')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
