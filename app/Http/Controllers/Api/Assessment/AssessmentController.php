<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Assessment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\AssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Assessment::class);

        $assessments = Assessment::query()
            ->with(['subject', 'academicSession'])
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->input('subject_id')))
            ->when($request->filled('academic_session_id'), fn ($query) => $query->where('academic_session_id', $request->input('academic_session_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return AssessmentResource::collection($assessments);
    }

    public function store(AssessmentRequest $request)
    {
        $assessment = Assessment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Assessment $assessment)
    {
        $this->authorize('view', $assessment);

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']));
    }

    public function update(AssessmentRequest $request, Assessment $assessment)
    {
        $assessment->update($request->validated());

        return AssessmentResource::make($assessment->load(['subject', 'academicSession']));
    }

    public function destroy(Assessment $assessment)
    {
        $this->authorize('delete', $assessment);

        $assessment->delete();

        return response()->noContent();
    }
}
