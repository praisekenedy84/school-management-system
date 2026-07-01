<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Finance\StudentFeeStatementService;
use Illuminate\Http\Request;

class StudentFeeStatementController extends Controller
{
    public function __construct(private readonly StudentFeeStatementService $statements) {}

    /** GET /api/v1/students/{student}/fee-statement?academic_session_id= */
    public function show(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $validated = $request->validate([
            'academic_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
        ]);

        return response()->json([
            'data' => $this->statements->build($student, $validated['academic_session_id']),
        ]);
    }
}
