<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. Defines the assessed fee amount per (school,
 * academic session, class, fee type) — configuration consumed by
 * StudentFeeLedger when a ledger is assembled. See
 * docs/prd-financial-module.md §2.1.
 */
class FeeStructure extends Model
{
    /** @use HasFactory<FeeStructureFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'class_id',
        'fee_type',
        'amount',
        'is_mandatory',
        'applicable_to',
        'installment_allowed',
        'installment_count',
        'due_date',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_mandatory' => 'bool',
            'installment_allowed' => 'bool',
            'is_active' => 'bool',
            'due_date' => 'date',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
