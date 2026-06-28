<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NavigationItem;
use App\Models\NavigationSection;
use App\Models\User;

class NavigationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('tenant.manage_navigation');
    }

    public function update(User $user, NavigationItem|NavigationSection $model): bool
    {
        return $this->manage($user);
    }
}
