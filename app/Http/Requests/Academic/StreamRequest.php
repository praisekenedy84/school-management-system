<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\ClassRoom;
use App\Models\Stream;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ClassRoom $classRoom */
        $classRoom = $this->route('classRoom');

        return $this->isMethod('POST')
            ? ($this->user()?->can('update', $classRoom) ?? false)
            : ($this->user()?->can('update', $classRoom) ?? false);
    }

    public function rules(): array
    {
        /** @var ClassRoom $classRoom */
        $classRoom = $this->route('classRoom');
        /** @var Stream|null $stream */
        $stream = $this->route('stream');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('streams', 'name')
                    ->where('class_id', $classRoom->id)
                    ->ignore($stream?->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
