<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ClassRoomFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned. Table is `classes`; named `ClassRoom` in PHP
 * to avoid colliding with the reserved word `class`.
 */
class ClassRoom extends Model
{
    /** @use HasFactory<ClassRoomFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    protected $table = 'classes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'level',
    ];

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class, 'class_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects', 'class_id', 'subject_id');
    }

    public function enrolments(): HasMany
    {
        return $this->hasMany(Enrolment::class, 'class_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'class_id');
    }
}
