<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'request_number',
        'requested_by',
        'title',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'amended_by',
        'amended_at',
        'amendment_notes',
        'fulfilled_by',
        'fulfilled_at',
        'store_requisition_id',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'amended_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by');
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(PurchaseFulfillment::class);
    }

    public function storeRequisition(): BelongsTo
    {
        return $this->belongsTo(StoreRequisition::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFulfillable(): bool
    {
        return in_array($this->status, ['approved', 'amended'], true);
    }
}
