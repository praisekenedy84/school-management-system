<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUserDirectory;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * ADR-0008 recovery/maintenance tool: rebuilds the central
 * tenant_user_directory from each tenant's own `users` table. Needed once
 * for tenants/users that existed before the observer did; also useful if
 * the directory ever drifts from reality.
 */
class BackfillTenantUserDirectory extends Command
{
    protected $signature = 'tenancy:backfill-directory {tenant? : Only backfill this tenant id}';

    protected $description = 'Rebuild the central tenant_user_directory from each tenant\'s users table';

    public function handle(): int
    {
        $tenants = $this->argument('tenant')
            ? Tenant::where('id', $this->argument('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No matching tenant(s) found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $count = 0;
            foreach (User::all() as $user) {
                TenantUserDirectory::updateOrCreate(
                    ['tenant_id' => $tenant->getTenantKey(), 'user_id' => $user->id],
                    ['email' => $user->email],
                );
                $count++;
            }

            tenancy()->end();

            $this->info("Tenant {$tenant->getTenantKey()}: synced {$count} user(s).");
        }

        return self::SUCCESS;
    }
}
