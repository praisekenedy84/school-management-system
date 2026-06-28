<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformNavigationItemRequest;
use App\Http\Resources\NavigationItemResource;
use App\Http\Resources\NavigationSectionResource;
use App\Models\PlatformNavigationItem;
use App\Models\PlatformNavigationSection;
use App\Services\Admin\NavigationService;
use Illuminate\Http\Request;

class PlatformNavigationController extends Controller
{
    public function index(NavigationService $service)
    {
        return NavigationSectionResource::collection(
            $service->platformNavigation()->load('items')
        );
    }

    public function adminIndex()
    {
        return NavigationSectionResource::collection(
            PlatformNavigationSection::query()
                ->with('items')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function updateItem(UpdatePlatformNavigationItemRequest $request, PlatformNavigationItem $item, NavigationService $service)
    {
        $updated = $service->updatePlatformItem($item, $request->validated());

        return NavigationItemResource::make($updated);
    }

    public function reorder(Request $request, NavigationService $service)
    {
        $validated = $request->validate([
            'sections' => ['sometimes', 'array'],
            'sections.*.id' => ['required', 'uuid'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        if (isset($validated['sections'])) {
            $service->reorderPlatformSections($validated['sections']);
        }
        if (isset($validated['items'])) {
            $service->reorderPlatformItems($validated['items']);
        }

        return NavigationSectionResource::collection(
            PlatformNavigationSection::query()->with('items')->orderBy('sort_order')->get()
        );
    }
}
