<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant model, school-owned.
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToSchool, HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'code',
    ];

    public function classRooms(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_subjects', 'subject_id', 'class_id');
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
