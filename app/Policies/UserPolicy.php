<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * Lookup-only policy for GET /api/v1/users. Full user CRUD is out of scope;
 * this endpoint feeds searchable pickers in admin forms.
 */
class UserPolicy
{
    /**
     * @param  'teacher'|'parent'|null  $role
     */
    public function lookup(User $user, ?string $role = null): bool
    {
        return match ($role) {
            'teacher' => $user->can('create', TeacherAssignment::class),
            'parent' => $user->hasRole(['tenant_admin', 'school_admin']),
            default => false,
        };
    }
}
