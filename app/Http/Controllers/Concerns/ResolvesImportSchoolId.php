<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Every bulk-import endpoint admits/creates rows into exactly ONE school
 * per upload — mirrors the "school_id required only for a tenant-wide
 * admin" rule already established for create forms (SubjectRequest et al.):
 * a school-scoped user's own school always wins, a tenant_admin must say
 * which school via a `school_id` form field alongside the file.
 */
trait ResolvesImportSchoolId
{
    private function resolveImportSchoolId(Request $request): string
    {
        $schoolId = $request->user()?->school_id;

        if ($schoolId !== null) {
            return $schoolId;
        }

        $schoolId = $request->input('school_id');

        if (! $schoolId || ! School::whereKey($schoolId)->exists()) {
            throw ValidationException::withMessages([
                'school_id' => 'Choose which school this import is for.',
            ]);
        }

        return $schoolId;
    }
}
