<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant model. Lives in the tenant schema — there is no central `users`
 * table; every user belongs to exactly one tenant schema. `school_id` is
 * nullable: tenant_admin / tenant-wide roles have it null.
 *
 * Deliberately does NOT use `BelongsToSchool`: that trait's global scope
 * calls auth()->user(), and resolving the authenticated user queries this
 * very table — applying the scope here recurses until memory exhausts.
 * Scope user listings explicitly (e.g. `User::where('school_id', ...)`)
 * in controllers/services instead.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'phone',
        'locale',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'bool',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The students this user (as a guardian) is responsible for. Named
     * `wards()` rather than `students()` to avoid ambiguity with a
     * school-wide student listing.
     */
    public function wards(): BelongsToMany
    {
        return $this->belongsToMany(
            Student::class,
            'student_guardians',
            'guardian_id',
            'student_id'
        )->using(StudentGuardian::class)->withPivot('relationship', 'is_primary');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }
}
