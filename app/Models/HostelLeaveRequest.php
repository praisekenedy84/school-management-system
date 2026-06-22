<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\HostelLeaveRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelLeaveRequest extends Model
{
    /** @use HasFactory<HostelLeaveRequestFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'hostel_allocation_id',
        'reason',
        'depart_at',
        'return_at',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'decision_notes',
    ];

    protected function casts(): array
    {
        return [
            'depart_at' => 'date',
            'return_at' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostelAllocation(): BelongsTo
    {
        return $this->belongsTo(HostelAllocation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
