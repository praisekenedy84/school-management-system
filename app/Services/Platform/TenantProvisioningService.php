<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Events\Platform\TenantProvisioned;
use App\Models\PlatformAdmin;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

/**
 * Platform Admin provisioning a brand-new tenant. Schema creation
 * (`Tenant::create()`, step 1) is not transactional with RBAC/school/admin
 * creation (steps 2-3) — Postgres schema creation is DDL, stancl runs it via
 * a synchronous job pipeline (App\Providers\TenancyServiceProvider), not
 * inside our own DB transaction. If step 3 fails after the schema already
 * exists, we drop the schema again (`$tenant->delete()`, the same
 * `DROP SCHEMA ... CASCADE` path stancl uses everywhere else) rather than
 * leave a half-provisioned tenant — seeded roles but no admin user — sitting
 * around reachable by id.
 */
class TenantProvisioningService
{
    public function create(array $data, ?PlatformAdmin $admin): Tenant
    {
        $tenant = Tenant::create(['id' => $data['tenant_id']]);
        $tenant->domains()->create(['domain' => $data['tenant_id']]);

        tenancy()->initialize($tenant);

        try {
            (new RoleAndPermissionSeeder)->run();
            (new NavigationSeeder)->run();

            School::create([
                'name' => $data['school_name'],
                'code' => $data['school_code'],
            ]);

            User::create([
                'school_id' => null,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'is_active' => true,
            ])->assignRole('tenant_admin');
            tenancy()->end();
        } catch (\Throwable $e) {
            tenancy()->end();
            $tenant->delete();

            throw $e;
        }

        TenantProvisioned::dispatch($tenant->getTenantKey(), $admin);

        return $tenant;
    }
}
