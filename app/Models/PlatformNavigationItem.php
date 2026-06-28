<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformNavigationItem extends Model
{
    use HasUuids;

    protected $table = 'platform_navigation_items';

    protected $fillable = [
        'section_id',
        'label',
        'path',
        'icon',
        'permissions',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'bool',
            'is_system' => 'bool',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PlatformNavigationSection::class, 'section_id');
    }
}
