<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('updateRoles', $target) ?? false);
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($this->assignableRoleNames())],
        ];
    }

    /**
     * @return list<string>
     */
    private function assignableRoleNames(): array
    {
        $actor = $this->user();
        $all = Role::query()->where('guard_name', 'web')->pluck('name')->all();

        if ($actor?->hasRole(['tenant_admin', 'super_admin'])) {
            return array_values(array_diff($all, ['super_admin']));
        }

        return array_values(array_diff($all, ['tenant_admin', 'super_admin']));
    }
}
