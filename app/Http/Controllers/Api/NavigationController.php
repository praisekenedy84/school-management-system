<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NavigationSectionResource;
use App\Models\NavigationSection;
use App\Services\Admin\NavigationService;

/** Read-only navigation tree for authenticated tenant users. */
class NavigationController extends Controller
{
    public function index(NavigationService $service)
    {
        $this->authorize('viewAny', NavigationSection::class);

        return NavigationSectionResource::collection(
            $service->tenantNavigation()->load('items')
        );
    }
}
