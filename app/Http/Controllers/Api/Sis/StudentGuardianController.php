<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sis;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sis\LinkGuardianRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;

class StudentGuardianController extends Controller
{
    public function store(LinkGuardianRequest $request, Student $student)
    {
        $data = $request->validated();

        $student->guardians()->syncWithoutDetaching([
            $data['guardian_id'] => [
                'relationship' => $data['relationship'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
            ],
        ]);

        return StudentResource::make($student->load('guardians'));
    }

    public function destroy(Student $student, User $guardian)
    {
        $this->authorize('update', $student);

        $student->guardians()->detach($guardian->id);

        return response()->noContent();
    }
}
