<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Central model (ADR-0008). Cross-tenant activity log queried by Platform
 * Admin to see activity from every tenant in one place. Always writes to
 * the central schema regardless of whatever tenant is currently
 * initialized — mirrors the connection-override pattern in PlatformAdmin.
 *
 * Append-only: rows are only ever inserted, never updated or deleted (no
 * `updated_at`/`deleted_at` column exists on `audit_logs`).
 *
 * `tenant_id` is intentionally a denormalized string, not a relation —
 * some actions are platform-level and have no tenant at all.
 */
class AuditLog extends Model
{
    use HasUuids;

    /**
     * No `updated_at` column on this table — append-only.
     */
    const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * Always the central connection. AuditLog never lives in a tenant
     * schema, regardless of what `database.default` currently points at.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
