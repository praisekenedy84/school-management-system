<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectResource;
use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassSubjectController extends Controller
{
    public function store(Request $request, ClassRoom $classRoom)
    {
        $this->authorize('update', $classRoom);

        $data = $request->validate([
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
        ]);

        // Rule::exists only checks the subject exists, not that it belongs
        // to this class's school — a tenant_admin (unscoped) could
        // otherwise attach a subject from a different campus.
        $subject = Subject::withoutGlobalScope(SchoolScope::class)->find($data['subject_id']);

        if ($subject?->school_id !== $classRoom->school_id) {
            throw ValidationException::withMessages([
                'subject_id' => 'The selected subject must belong to this class\'s school.',
            ]);
        }

        $classRoom->subjects()->syncWithoutDetaching([$data['subject_id']]);

        return SubjectResource::collection($classRoom->subjects()->get())
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(ClassRoom $classRoom, Subject $subject)
    {
        $this->authorize('update', $classRoom);

        $classRoom->subjects()->detach($subject->id);

        return response()->noContent();
    }
}
