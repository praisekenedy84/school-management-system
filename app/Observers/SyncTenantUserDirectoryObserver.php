<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TenantUserDirectory;
use App\Models\User;

/**
 * Keeps the central tenant_user_directory table in sync with each tenant's
 * own `users` table (ADR-0008). This is the only place directory rows are
 * written — no service should write to TenantUserDirectory directly.
 *
 * Runs while tenancy is still initialized (the request/command that
 * created/changed the user), so tenant()->id reflects the correct tenant.
 */
class SyncTenantUserDirectoryObserver
{
    public function created(User $user): void
    {
        TenantUserDirectory::create([
            'email' => $user->email,
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
        ]);
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('email')) {
            return;
        }

        TenantUserDirectory::where('tenant_id', tenant('id'))
            ->where('user_id', $user->id)
            ->update(['email' => $user->email]);
    }

    public function deleted(User $user): void
    {
        TenantUserDirectory::where('tenant_id', tenant('id'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
