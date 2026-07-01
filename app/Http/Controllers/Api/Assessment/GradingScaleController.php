<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\UpdateGradingScaleRequest;
use App\Http\Resources\GradingScaleResource;
use App\Models\School;
use App\Services\Assessment\GradingScaleService;
use Illuminate\Http\Request;

class GradingScaleController extends Controller
{
    public function __construct(private readonly GradingScaleService $gradingScale) {}

    /** GET /api/v1/grading-scale — current user's school bands (defaults if unset). */
    public function show(Request $request)
    {
        $school = $this->resolveSchool($request);

        return GradingScaleResource::make([
            'school_id' => $school->id,
            'bands' => $this->gradingScale->scaleForSchool($school),
        ]);
    }

    /** PUT /api/v1/grading-scale — school/tenant admin only. */
    public function update(UpdateGradingScaleRequest $request)
    {
        $school = $this->resolveSchool($request);
        $school->update(['grading_scale' => $request->validated('bands')]);

        return GradingScaleResource::make([
            'school_id' => $school->id,
            'bands' => $this->gradingScale->scaleForSchool($school->fresh()),
        ]);
    }

    private function resolveSchool(Request $request): School
    {
        $schoolId = $request->user()->school_id ?? $request->input('school_id');

        abort_unless($schoolId !== null, 422, 'A school context is required.');

        return School::findOrFail($schoolId);
    }
}
