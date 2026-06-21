<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned.
 */
class AcademicSession extends Model
{
    /** @use HasFactory<AcademicSessionFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'bool',
        ];
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function resultRecords(): HasMany
    {
        return $this->hasMany(ResultRecord::class);
    }
}
