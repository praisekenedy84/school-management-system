<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Ensures the CENTRAL schema (tenants, domains, central migrations table)
 * exists, without wrapping the test in a transaction.
 *
 * Why not RefreshDatabase: it begins a real DB transaction on the default
 * connection for the duration of each test and rolls it back at the end.
 * Our tests need to CREATE SCHEMA + migrate a brand new Postgres schema via
 * a *separate* `tenant` connection (a different physical session) while the
 * test is running. Postgres MVCC means that second session cannot see the
 * central connection's uncommitted CREATE SCHEMA — every tenant migration
 * then fails with "no schema has been selected to create in" because the
 * schema was never actually visible outside the still-open transaction.
 * (Confirmed empirically: identical tenant-creation code that works fine
 * from `php artisan tinker` failed every time under RefreshDatabase.)
 *
 * So central tables are migrated for real (once per test process, reusing
 * RefreshDatabaseState::$migrated the same way RefreshDatabase does) and
 * each test is responsible for cleaning up the tenant rows/schemas it
 * created — which CreatesTenant::cleanUpTenants() already does by calling
 * Tenant::delete() (DROP SCHEMA ... CASCADE) and removing the central
 * tenants/domains rows.
 */
trait MigratesCentralDatabase
{
    protected function migrateCentralDatabaseOnce(): void
    {
        if (RefreshDatabaseState::$migrated) {
            return;
        }

        $this->artisan('migrate', ['--force' => true]);

        RefreshDatabaseState::$migrated = true;
    }
}
