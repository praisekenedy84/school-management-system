<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central model (ADR-0008). Maps a login email to the tenant schema it
 * belongs to, so /login can find the right schema before any tenant
 * connection exists. Kept in sync by SyncTenantUserDirectoryObserver on the
 * tenant-scoped User model — nothing else should write to this table.
 */
class TenantUserDirectory extends Model
{
    use HasUuids;

    protected $table = 'tenant_user_directory';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'tenant_id',
        'user_id',
    ];

    /**
     * Always the central connection, even while a tenant is initialized
     * (the observer writes here from inside tenant-active requests) —
     * otherwise this would resolve against whatever connection
     * `database.default` currently points at and hit the wrong schema.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
