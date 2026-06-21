<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Spins up a real tenant (own Postgres schema) for tests that need genuine
 * schema isolation — RefreshDatabase only resets the CENTRAL connection and
 * has no concept of tenant schemas, so it cannot be relied on here.
 *
 * `Tenant::create()` fires `TenantCreated`, which (per
 * App\Providers\TenancyServiceProvider) synchronously runs CreateDatabase +
 * MigrateDatabase (shouldBeQueued(false) — no queue worker needed in tests).
 * `Tenant::delete()` fires `TenantDeleted` -> DeleteDatabase, which runs
 * `DROP SCHEMA ... CASCADE` (confirmed against
 * vendor/stancl/tenancy/src/TenantDatabaseManagers/PostgreSQLSchemaManager.php),
 * so tearing down a tenant created this way leaves no orphan schema behind.
 *
 * Domain rows store the bare subdomain fragment (e.g. "demo"), matching
 * InitializeTenancyBySubdomain's resolution — see CLAUDE.md / the
 * orchestrator's manual verification notes.
 */
trait CreatesTenant
{
    use MigratesCentralDatabase;

    /**
     * @var list<Tenant>
     */
    private array $createdTenants = [];

    protected function createTenant(?string $id = null): Tenant
    {
        $this->migrateCentralDatabaseOnce();

        $id ??= 'test'.Str::lower(Str::random(10));

        $tenant = Tenant::create(['id' => $id]);
        $tenant->domains()->create(['domain' => $id]);

        $this->createdTenants[] = $tenant;

        return $tenant;
    }

    /**
     * Create a tenant and immediately initialize tenancy on it.
     */
    protected function createAndInitializeTenant(?string $id = null): Tenant
    {
        $tenant = $this->createTenant($id);

        tenancy()->initialize($tenant);

        return $tenant;
    }

    /**
     * End tenancy (if active) and drop every tenant schema created during
     * the test. Call from tearDown(), or rely on
     * EnsureTenantCleanup::tearDownTenants() if the test uses that trait.
     */
    protected function cleanUpTenants(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->createdTenants as $tenant) {
            // Refresh so we don't act on a stale in-memory copy, and guard
            // against a tenant already deleted earlier in the test.
            if (Tenant::find($tenant->getTenantKey())) {
                $tenant->delete();
            }
        }

        $this->createdTenants = [];
    }
}
