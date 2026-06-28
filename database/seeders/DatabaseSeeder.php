<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\TenantUserDirectory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a tenant schema: RBAC matrix + a demo school and admin login.
     *
     * Safe to re-run on an existing tenant (updates roles, skips records that
     * already exist). For a completely empty schema, use tenants:migrate-fresh
     * first — that wipes the tenant's Postgres schema including users.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $tenantId = tenant('id') ?? 'tenant';

        $school = School::query()->where('code', 'DEMO')->first();

        if ($school === null) {
            // `tenants:migrate-fresh` wipes this tenant's schema (its `users`
            // table) but NOT the central `tenant_user_directory` — those rows
            // point at user_ids that no longer exist. Re-seeding would otherwise
            // collide with the directory's unique `email` constraint on the
            // very first User::create() below (SyncTenantUserDirectoryObserver
            // tries to re-insert an email that's still there from last time).
            // Clearing this tenant's own directory rows first makes
            // migrate-fresh + reseed a safe, repeatable local workflow.
            TenantUserDirectory::where('tenant_id', $tenantId)->delete();

            $school = School::factory()->create([
                'name' => 'Demo Secondary School',
                'code' => 'DEMO',
            ]);
        }

        $this->ensureBootstrapUser(
            $tenantId,
            "admin@{$tenantId}.sms.test",
            'Tenant Admin',
            null,
            'tenant_admin',
        );

        $this->ensureBootstrapUser(
            $tenantId,
            "school-admin@{$tenantId}.sms.test",
            'School Admin',
            $school->id,
            'school_admin',
        );

        // Full walk-through fixtures (classes, students, attendance, fees,
        // hostel, stores, …) only for the literal "demo" tenant — other tenants
        // (e.g. a frontend preview tenant) get just the bare RBAC + login
        // above and stay otherwise empty.
        if ($tenantId === 'demo') {
            $this->call(DemoDataSeeder::class);
        }
    }

    private function ensureBootstrapUser(
        string $tenantId,
        string $email,
        string $name,
        ?string $schoolId,
        string $role,
    ): void {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $factory = User::factory();

            if ($schoolId === null) {
                $factory = $factory->withoutSchool();
            }

            $user = $factory->create([
                'school_id' => $schoolId,
                'name' => $name,
                'email' => $email,
            ]);
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
