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
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $tenantId = tenant('id') ?? 'tenant';

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

        User::factory()
            ->withoutSchool()
            ->create([
                'name' => 'Tenant Admin',
                'email' => "admin@{$tenantId}.sms.test",
            ])
            ->assignRole('tenant_admin');

        User::factory()
            ->create([
                'school_id' => $school->id,
                'name' => 'School Admin',
                'email' => "school-admin@{$tenantId}.sms.test",
            ])
            ->assignRole('school_admin');

        // Full walk-through fixtures (classes, students, attendance, fees,
        // hostel, …) only for the literal "demo" tenant — other tenants
        // (e.g. a frontend preview tenant) get just the bare RBAC + login
        // above and stay otherwise empty.
        if ($tenantId === 'demo') {
            $this->call(DemoDataSeeder::class);
        }
    }
}
