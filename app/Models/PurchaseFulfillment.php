<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseFulfillment extends Model
{
    use BelongsToSchool, HasUuids;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'purchase_request_id',
        'fulfillment_number',
        'fulfilled_by',
        'supplier_name',
        'supplier_reference',
        'fulfillment_date',
        'notes',
        'attachments',
        'total_cost',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fulfillment_date' => 'date',
            'attachments' => 'array',
            'total_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseFulfillmentLine::class);
    }
}
