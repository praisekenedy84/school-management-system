<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Models\ClassRoom;
use App\Models\ReportCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGenerateReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReportCard::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
        ];
    }

    public function classRoom(): ClassRoom
    {
        return ClassRoom::query()->findOrFail($this->validated('class_id'));
    }
}
