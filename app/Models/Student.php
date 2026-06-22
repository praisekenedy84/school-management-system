<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant model, school-owned. Critical table per RULES.md — soft delete
 * only, never hard delete.
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, HasUuids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'admission_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'residence_type',
        'status',
        'admitted_at',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admitted_at' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'student_guardians',
            'student_id',
            'guardian_id'
        )->using(StudentGuardian::class)->withPivot('relationship', 'is_primary');
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function resultRecords(): HasMany
    {
        return $this->hasMany(ResultRecord::class);
    }

    public function paymentSlips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }

    /**
     * Plural: one StudentFeeLedger per academic_session, not a single
     * lifetime ledger.
     */
    public function feeLedgers(): HasMany
    {
        return $this->hasMany(StudentFeeLedger::class);
    }

    public function hostelAllocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }
}
