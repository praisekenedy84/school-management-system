<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFeeLedgerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant model, school-owned. One ledger per (student, academic_session),
 * tracking assessed/discount/paid totals. Per
 * database/migrations/tenant/..._create_student_fee_ledgers_table.php,
 * `balance` is a Postgres STORED GENERATED column
 * (total_assessed - total_discounts - total_paid) — it is deliberately
 * absent from $fillable below and must NEVER be assigned by application
 * code. Mass-assignment guarding here relies on the same convention used
 * across this codebase (explicit $fillable allow-list, RULES.md §"never
 * $guarded = []"); omitting `balance` from $fillable is sufficient because
 * Eloquent only allows mass-assignment of listed attributes — there is no
 * separate $guarded list elsewhere to keep in sync. If something attempts
 * `fill(['balance' => ...])` or `create(['balance' => ...])`, Eloquent
 * silently ignores the unlisted key; if something attempts a raw
 * `$ledger->balance = ...; $ledger->save();`, Postgres itself rejects writes
 * to a STORED GENERATED column at the database level — this is a second,
 * stronger backstop beyond the model layer.
 */
class StudentFeeLedger extends Model
{
    /** @use HasFactory<StudentFeeLedgerFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'fee_details',
        'total_assessed',
        'total_discounts',
        'total_paid',
        'payment_status',
        'last_payment_date',
    ];

    protected function casts(): array
    {
        return [
            'fee_details' => 'array',
            'total_assessed' => 'decimal:2',
            'total_discounts' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'last_payment_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }
}
