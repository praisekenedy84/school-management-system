<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton central configuration for the platform operator (ADR-0008).
 * One row — read/update via PlatformSettingsController.
 */
class PlatformSetting extends Model
{
    use HasUuids;

    protected $table = 'platform_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'platform_name',
        'support_email',
        'default_locale',
        'default_currency',
        'maintenance_mode',
        'max_tenants',
        'branding',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'bool',
            'max_tenants' => 'integer',
            'branding' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'platform_name' => 'School Management System',
            'default_locale' => 'en',
            'default_currency' => 'TZS',
            'maintenance_mode' => false,
        ]);
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
