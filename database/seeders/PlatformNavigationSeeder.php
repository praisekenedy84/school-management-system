<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformNavigationItem;
use App\Models\PlatformNavigationSection;
use Illuminate\Database\Seeder;

/** Idempotent default sidebar for platform admins (central schema). */
class PlatformNavigationSeeder extends Seeder
{
    public function run(): void
    {
        if (PlatformNavigationSection::query()->exists()) {
            return;
        }

        foreach (config('navigation-defaults.platform', []) as $sectionIndex => $sectionData) {
            $section = PlatformNavigationSection::create([
                'label' => $sectionData['label'],
                'sort_order' => $sectionIndex,
                'is_active' => true,
            ]);

            foreach ($sectionData['items'] as $itemIndex => $itemData) {
                PlatformNavigationItem::create([
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
