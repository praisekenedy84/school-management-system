<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/**
 * School administration — tenant-wide configuration (PRD §5.1).
 * Mutations are gated by Spatie permissions, not role names alone.
 */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, School $school): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tenant.manage_schools');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasPermissionTo('tenant.manage_schools');
    }

    public function delete(User $user, School $school): bool
    {
        return $user->hasPermissionTo('tenant.manage_schools');
    }

    public function updateSettings(User $user, School $school): bool
    {
        return $user->hasPermissionTo('tenant.manage_settings');
    }

    public function updateBranding(User $user, School $school): bool
    {
        return $user->hasPermissionTo('tenant.manage_branding');
    }

    public function updateBilling(User $user, School $school): bool
    {
        return $user->hasPermissionTo('tenant.manage_billing');
    }
}
