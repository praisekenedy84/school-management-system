<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * "Did I already take attendance today" lookup for a given class + date
     * (optionally narrowed by period).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $request->validate([
            'class_id' => ['required', 'uuid'],
            'attendance_date' => ['required', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);

        $records = AttendanceRecord::query()
            ->with('student')
            ->where('class_id', $request->input('class_id'))
            ->where('attendance_date', $request->input('attendance_date'))
            ->when(
                $request->filled('period'),
                fn ($query) => $query->where('period', $request->input('period')),
                fn ($query) => $query->whereNull('period')
            )
            ->get();

        return AttendanceRecordResource::collection($records);
    }

    public function store(RecordAttendanceRequest $request)
    {
        $records = $this->attendance->record(
            $request->validated(),
            $request->user()->id,
        );

        return AttendanceRecordResource::collection($records->load('student'))
            ->response()
            ->setStatusCode(201);
    }
}
