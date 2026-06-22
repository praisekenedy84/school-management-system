<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MealPlan;
use App\Models\User;

class MealPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MealPlan $mealPlan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function update(User $user, MealPlan $mealPlan): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }

    public function delete(User $user, MealPlan $mealPlan): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }
}
