<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StartImpersonationRequest;
use App\Http\Resources\PlatformAdminResource;
use App\Http\Resources\UserResource;
use App\Services\Platform\ImpersonationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonationController extends Controller
{
    public function start(StartImpersonationRequest $request, ImpersonationService $service)
    {
        $target = $service->start(
            $request->string('tenant_id')->toString(),
            $request->string('user_id')->toString(),
            Auth::guard('platform')->user(),
        );

        return (new UserResource($target))->additional(['impersonation' => Session::get('impersonation')]);
    }

    public function stop(ImpersonationService $service)
    {
        return new PlatformAdminResource($service->stop());
    }
}
