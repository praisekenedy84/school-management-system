<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scopes a query to the authenticated user's school within the current
 * tenant schema. This is a CAMPUS boundary, not a tenant boundary — tenant
 * isolation is handled by the Postgres schema switch (stancl/tenancy), never
 * by this scope.
 *
 * Behaviour:
 * - Authenticated user with a non-null `school_id` (school_admin, teacher, …):
 *   scope to that school.
 * - Authenticated user with a null `school_id` (tenant_admin / tenant-wide
 *   roles): no scope applied — they see every school in the tenant.
 * - No authenticated user (console/queue/job context): no scope applied.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        if ($user->school_id === null) {
            return;
        }

        $builder->where($model->getQualifiedSchoolIdColumn(), $user->school_id);
    }
}
