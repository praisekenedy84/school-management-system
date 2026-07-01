<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHostelAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('hostelAllocation')) ?? false;
    }

    public function rules(): array
    {
        return [
            'meal_plan_id' => ['nullable', 'uuid', Rule::exists('meal_plans', 'id')],
        ];
    }
}
