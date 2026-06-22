<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StartImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // EnsurePlatformAdmin already gates the route — this is a second,
        // belt-and-suspenders line of defense (RULES §7) in case a future
        // route ever forgets that middleware.
        return Auth::guard('platform')->check();
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string'],
            'user_id' => ['required', 'string'],
        ];
    }
}
