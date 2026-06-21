<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For school-owned tenant models. Boots the `SchoolScope` global scope (campus
 * isolation within a tenant schema) and stamps `school_id` from the
 * authenticated user on create when it isn't already set.
 *
 * Do NOT confuse this with tenant isolation: tenant tables have no
 * `tenant_id` column and no tenant scope — the Postgres schema switch
 * (stancl/tenancy) is what isolates tenants. This trait only isolates
 * campuses (`school_id`) inside a single tenant schema.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if ($model->school_id === null) {
                $user = auth()->user();

                if ($user !== null && $user->school_id !== null) {
                    $model->school_id = $user->school_id;
                }
            }
        });
    }

    public function getQualifiedSchoolIdColumn(): string
    {
        return $this->qualifyColumn('school_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
