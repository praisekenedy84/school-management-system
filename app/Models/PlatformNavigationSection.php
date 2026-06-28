<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformNavigationSection extends Model
{
    use HasUuids;

    protected $table = 'platform_navigation_sections';

    protected $fillable = [
        'label',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'bool',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlatformNavigationItem::class, 'section_id')->orderBy('sort_order');
    }
}
