<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Subject::class);

        $subjects = Subject::query()
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return SubjectResource::collection($subjects);
    }

    public function store(SubjectRequest $request)
    {
        $subject = Subject::create($request->validated());

        return SubjectResource::make($subject)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);

        return SubjectResource::make($subject);
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        return SubjectResource::make($subject);
    }

    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        return response()->noContent();
    }
}
