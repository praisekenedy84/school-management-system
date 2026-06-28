<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'label',
        'sort_order',
        'platform_only',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'platform_only' => 'bool',
            'is_active' => 'bool',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'section_id')->orderBy('sort_order');
    }
}
