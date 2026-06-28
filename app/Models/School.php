<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model. Lives in the tenant schema (default connection while
 * tenancy is initialized). A school is the root campus entity within a
 * tenant; it is NOT school-owned (it IS the school), so it does not use
 * `BelongsToSchool` itself.
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'locale',
        'currency',
        'timezone',
        'branding',
        'calendar_type',
        'grading_scale',
        'fee_terms',
        'billing',
        'hostel_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'grading_scale' => 'array',
            'fee_terms' => 'array',
            'billing' => 'array',
            'hostel_available' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function classRooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
