<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\School;
use App\Models\Student;
use App\Models\Tenant;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * The tenant boundary (RULES.md §9 / §1): tenant tables have NO `tenant_id`
 * column. Isolation is the Postgres schema itself, switched by stancl's
 * DatabaseTenancyBootstrapper. We prove this by creating rows while
 * initialized on Tenant A, switching to a completely different Tenant B,
 * and asserting the rows are simply absent — not filtered out by a query
 * scope, but physically in a different schema.
 *
 * NOT using RefreshDatabase here: see CreatesTenant / MigratesCentralDatabase
 * docblocks — wrapping the test in a transaction breaks cross-connection
 * tenant schema creation (Postgres MVCC visibility). Tenant lifecycle
 * (create/migrate/drop schema) is handled explicitly by CreatesTenant, and
 * central tables are migrated for real (once per process) instead.
 */
class SchemaIsolationTest extends TestCase
{
    use CreatesTenant;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();

        parent::tearDown();
    }

    public function test_data_created_in_tenant_a_is_invisible_from_tenant_b(): void
    {
        $tenantA = $this->createAndInitializeTenant();

        $school = School::factory()->create(['name' => 'Tenant A School']);
        Student::factory()->create(['school_id' => $school->id]);

        $this->assertSame(1, School::count());
        $this->assertSame(1, Student::count());

        tenancy()->end();

        $tenantB = $this->createAndInitializeTenant();

        // Same Eloquent models, same table names — but a different Postgres
        // schema is now bound to the default connection. If this returns
        // anything other than 0, tenant isolation is broken.
        $this->assertSame(0, School::count());
        $this->assertSame(0, Student::count());

        tenancy()->end();
    }

    public function test_tenancy_end_cleanly_reverts_to_central_context(): void
    {
        $tenant = $this->createAndInitializeTenant();

        School::factory()->create();
        $this->assertSame(1, School::count());

        tenancy()->end();

        // Back in central context: the central `tenants` table is reachable...
        $this->assertTrue(Tenant::where('id', $tenant->getTenantKey())->exists());

        // ...and tenant tables are not on this connection at all (the
        // `schools` table lives only inside each tenant schema's
        // search_path, not in `public`).
        $this->expectExceptionMessageMatches('/relation "schools" does not exist/i');
        School::count();
    }

    public function test_each_tenant_gets_its_own_independent_schema(): void
    {
        $tenantA = $this->createAndInitializeTenant();
        School::factory()->count(2)->create();
        tenancy()->end();

        $tenantB = $this->createAndInitializeTenant();
        School::factory()->count(3)->create();
        tenancy()->end();

        tenancy()->initialize($tenantA);
        $this->assertSame(2, School::count());
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(3, School::count());
        tenancy()->end();
    }

    /**
     * RULES.md §7: tenant routes must be unreachable from a central domain.
     * PreventAccessFromCentralDomains runs at the highest middleware
     * priority (App\Providers\TenancyServiceProvider::makeTenancyMiddlewareHighestPriority())
     * and aborts with 404 before InitializeTenancyBySubdomain even runs.
     */
    public function test_tenant_api_route_is_unreachable_from_a_central_domain(): void
    {
        $response = $this->getJson('http://central.sms.test/api/v1/me');

        $response->assertNotFound();
    }
}
