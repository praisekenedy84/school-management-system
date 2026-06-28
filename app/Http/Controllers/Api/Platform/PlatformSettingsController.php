<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Events\Platform\PlatformSettingsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingsRequest;
use App\Http\Resources\PlatformSettingsResource;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Auth;

class PlatformSettingsController extends Controller
{
    public function show()
    {
        return PlatformSettingsResource::make(PlatformSetting::current())
            ->response()
            ->setStatusCode(200);
    }

    public function update(UpdatePlatformSettingsRequest $request)
    {
        $settings = PlatformSetting::current();
        $settings->update($request->validated());

        PlatformSettingsChanged::dispatch($settings->fresh(), Auth::guard('platform')->user());

        return PlatformSettingsResource::make($settings->fresh());
    }
}
