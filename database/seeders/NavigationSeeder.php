<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NavigationItem;
use App\Models\NavigationSection;
use Illuminate\Database\Seeder;

/** Idempotent default sidebar for tenant schemas. */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        if (NavigationSection::query()->exists()) {
            return;
        }

        foreach (config('navigation-defaults.tenant', []) as $sectionIndex => $sectionData) {
            $section = NavigationSection::create([
                'label' => $sectionData['label'],
                'sort_order' => $sectionIndex,
                'platform_only' => $sectionData['platform_only'] ?? false,
                'is_active' => true,
            ]);

            foreach ($sectionData['items'] as $itemIndex => $itemData) {
                NavigationItem::create([
                    'section_id' => $section->id,
                    'label' => $itemData['label'],
                    'path' => $itemData['path'],
                    'icon' => $itemData['icon'],
                    'permissions' => $itemData['permissions'],
                    'sort_order' => $itemIndex,
                    'is_active' => true,
                    'is_system' => true,
                ]);
            }
        }
    }
}
