<?php

namespace Database\Seeders;

use App\Models\School;
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
    }
}
