<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = Role::findByName($this->route('role'), 'web');

        return $this->user()?->can('update', $role) ?? false;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Permission::query()->where('guard_name', 'web')->pluck('name'))],
        ];
    }
}
