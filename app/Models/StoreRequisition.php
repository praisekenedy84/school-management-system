<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StoreRequisitionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreRequisition extends Model
{
    /** @use HasFactory<StoreRequisitionFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'requisition_number',
        'requested_by',
        'purpose',
        'needed_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'issued_by',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'reviewed_at' => 'datetime',
            'issued_at' => 'datetime',
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

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StoreRequisitionLine::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function issueMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', self::class)
            ->where('reason', 'requisition_issue')
            ->orderBy('performed_at');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssuable(): bool
    {
        return in_array($this->status, ['approved', 'partially_issued'], true);
    }

    public function canAddToPurchase(): bool
    {
        return in_array($this->status, ['submitted', 'approved', 'partially_issued'], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }
}
