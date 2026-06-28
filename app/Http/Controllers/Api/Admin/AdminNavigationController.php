<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Events\Tenant\NavigationChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderNavigationRequest;
use App\Http\Requests\Admin\UpdateNavigationItemRequest;
use App\Http\Requests\Admin\UpdateNavigationSectionRequest;
use App\Http\Resources\NavigationItemResource;
use App\Http\Resources\NavigationSectionResource;
use App\Models\NavigationItem;
use App\Models\NavigationSection;
use App\Services\Admin\NavigationService;
use Illuminate\Support\Facades\Auth;

class AdminNavigationController extends Controller
{
    public function index(NavigationService $service)
    {
        $this->authorize('manage', NavigationItem::class);

        return NavigationSectionResource::collection(
            NavigationSection::query()
                ->where('platform_only', false)
                ->with('items')
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function updateSection(UpdateNavigationSectionRequest $request, NavigationSection $section, NavigationService $service)
    {
        $section->update($request->validated());
        NavigationChanged::dispatch('tenant', Auth::user());

        return NavigationSectionResource::make($section->fresh()->load('items'));
    }

    public function updateItem(UpdateNavigationItemRequest $request, NavigationItem $item, NavigationService $service)
    {
        $updated = $service->updateTenantItem($item, $request->validated());
        NavigationChanged::dispatch('tenant', Auth::user());

        return NavigationItemResource::make($updated);
    }

    public function reorder(ReorderNavigationRequest $request, NavigationService $service)
    {
        if ($request->has('sections')) {
            $service->reorderTenantSections($request->validated('sections'));
        }
        if ($request->has('items')) {
            $service->reorderTenantItems($request->validated('items'));
        }

        NavigationChanged::dispatch('tenant', Auth::user());

        return NavigationSectionResource::collection(
            NavigationSection::query()
                ->where('platform_only', false)
                ->with('items')
                ->orderBy('sort_order')
                ->get()
        );
    }
}
