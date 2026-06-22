<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the local-dev Platform Admin (ADR-0008) — the one login not scoped
 * to a tenant. Central table, so this runs once via `db:seed`, never via
 * `tenants:seed`. Idempotent: safe to re-run.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformAdmin::query()->firstOrCreate(
            ['email' => 'platform-admin@sms.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
    }
}
