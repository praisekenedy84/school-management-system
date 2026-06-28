<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\NavigationItem;
use App\Models\NavigationSection;
use App\Models\PlatformNavigationItem;
use App\Models\PlatformNavigationSection;
use Illuminate\Support\Collection;

class NavigationService
{
    /**
     * @return Collection<int, NavigationSection>
     */
    public function tenantNavigation(): Collection
    {
        return NavigationSection::query()
            ->where('is_active', true)
            ->where('platform_only', false)
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, PlatformNavigationSection>
     */
    public function platformNavigation(): Collection
    {
        return PlatformNavigationSection::query()
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function allowedPaths(bool $platform = false): array
    {
        $key = $platform ? 'platform' : 'tenant';
        $paths = [];

        foreach (config("navigation-defaults.{$key}", []) as $section) {
            foreach ($section['items'] as $item) {
                $paths[] = $item['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    public function updateTenantItem(NavigationItem $item, array $data): NavigationItem
    {
        if (isset($data['path']) && $data['path'] !== $item->path) {
            abort_if(
                ! in_array($data['path'], $this->allowedPaths(), true),
                422,
                'Path must match an existing application route.'
            );
        }

        $item->update($data);

        return $item->fresh();
    }

    public function updatePlatformItem(PlatformNavigationItem $item, array $data): PlatformNavigationItem
    {
        if (isset($data['path']) && $data['path'] !== $item->path) {
            abort_if(
                ! in_array($data['path'], $this->allowedPaths(true), true),
                422,
                'Path must match an existing application route.'
            );
        }

        $item->update($data);

        return $item->fresh();
    }

    /**
     * @param  list<array{id: string, sort_order: int}>  $sections
     */
    public function reorderTenantSections(array $sections): void
    {
        foreach ($sections as $row) {
            NavigationSection::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
        }
    }

    /**
     * @param  list<array{id: string, sort_order: int, section_id?: string}>  $items
     */
    public function reorderTenantItems(array $items): void
    {
        foreach ($items as $row) {
            $update = ['sort_order' => $row['sort_order']];
            if (isset($row['section_id'])) {
                $update['section_id'] = $row['section_id'];
            }
            NavigationItem::where('id', $row['id'])->update($update);
        }
    }

    /**
     * @param  list<array{id: string, sort_order: int}>  $sections
     */
    public function reorderPlatformSections(array $sections): void
    {
        foreach ($sections as $row) {
            PlatformNavigationSection::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
        }
    }

    /**
     * @param  list<array{id: string, sort_order: int, section_id?: string}>  $items
     */
    public function reorderPlatformItems(array $items): void
    {
        foreach ($items as $row) {
            $update = ['sort_order' => $row['sort_order']];
            if (isset($row['section_id'])) {
                $update['section_id'] = $row['section_id'];
            }
            PlatformNavigationItem::where('id', $row['id'])->update($update);
        }
    }
}
