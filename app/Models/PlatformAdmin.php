<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Central model (ADR-0008). The one login type not scoped to a single
 * tenant — authenticates via the separate `platform` guard, never against
 * a tenant's `users` table. No tenancy is ever initialized for this guard.
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'bool',
        ];
    }

    /**
     * Always the central connection. PlatformAdmin never lives in a tenant
     * schema, regardless of what `database.default` currently points at.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
