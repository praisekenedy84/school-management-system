<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    /**
     * `tenant_id` becomes a Postgres schema name (PostgreSQLSchemaManager)
     * and a `domains.domain` row — reject anything that isn't a clean
     * identifier (no leading/trailing hyphen, no reserved Postgres
     * schema/namespace names) even though stancl quotes identifiers itself.
     */
    private const RESERVED_TENANT_IDS = ['public', 'pg_catalog', 'information_schema', 'central', 'platform'];

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
            'tenant_id' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z][a-z0-9-]*[a-z0-9]$/',
                Rule::notIn(self::RESERVED_TENANT_IDS),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (str_starts_with($value, 'pg_')) {
                        $fail('The tenant id may not start with "pg_" (reserved by PostgreSQL).');
                    }
                },
                'unique:tenants,id',
            ],
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }
}
