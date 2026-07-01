<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\RecordAttendanceRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Services\Attendance\AttendanceService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly ExportService $exportService,
    ) {}

    /**
     * Two modes: (1) "did I already take attendance today" roster lookup
     * for a given class + date (optionally narrowed by period), or (2) a
     * single student's attendance history via `student_id` — used by the
     * parent per-child drill-down. A `parent` may only use mode 2, and only
     * for their own ward — `viewAny` is an open placeholder (see
     * AttendanceRecordPolicy), so without this a parent could otherwise
     * pass any class_id and see an entire class roster's attendance.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $request->validate([
            'student_id' => ['required_without:class_id', 'nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['required_without:student_id', 'nullable', 'uuid'],
            'attendance_date' => ['required_with:class_id', 'nullable', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->user()->hasRole('parent')) {
            if (
                ! $request->filled('student_id')
                || ! $request->user()->wards()->whereKey($request->input('student_id'))->exists()
            ) {
                abort(403, 'You may only view attendance for your own ward.');
            }
        }

        if ($request->filled('student_id')) {
            $records = AttendanceRecord::query()
                ->with('student')
                ->where('student_id', $request->input('student_id'))
                ->latest('attendance_date')
                ->paginate($request->integer('per_page', 30));

            return AttendanceRecordResource::collection($records);
        }

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

    /**
     * GET /api/v1/attendance/report — searchable attendance history with summary stats.
     */
    public function report(Request $request)
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        abort_if($request->user()->hasRole('parent'), 403, 'Parents may only view their ward\'s attendance history.');

        $request->validate([
            'class_id' => ['nullable', 'uuid'],
            'attendance_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = AttendanceRecord::query()->with(['student']);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('attendance_date')) {
            $query->whereDate('attendance_date', $request->input('attendance_date'));
        } else {
            if ($request->filled('date_from')) {
                $query->whereDate('attendance_date', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('attendance_date', '<=', $request->input('date_to'));
            }
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->whereHas('student', function ($studentQuery) use ($term) {
                $studentQuery->where('admission_number', 'ilike', $term)
                    ->orWhere('first_name', 'ilike', $term)
                    ->orWhere('last_name', 'ilike', $term)
                    ->orWhereRaw("concat(first_name, ' ', last_name) ilike ?", [$term]);
            });
        }

        $summaryRows = (clone $query)->get(['student_id', 'status']);
        $totalRecords = $summaryRows->count();
        $present = $summaryRows->where('status', 'present')->count();
        $late = $summaryRows->where('status', 'late')->count();

        $summary = [
            'total_students' => $summaryRows->pluck('student_id')->unique()->count(),
            'total_records' => $totalRecords,
            'present' => $present,
            'absent' => $summaryRows->where('status', 'absent')->count(),
            'late' => $late,
            'excused' => $summaryRows->where('status', 'excused')->count(),
            'attendance_percentage' => $totalRecords > 0
                ? round((($present + $late) / $totalRecords) * 100, 1)
                : 0.0,
        ];

        $records = $query->latest('attendance_date')->paginate($request->integer('per_page', 30));

        return AttendanceRecordResource::collection($records)->additional(['summary' => $summary]);
    }

    /**
     * GET /api/v1/attendance/export?format=xlsx|pdf
     * Mirrors index()'s exact two-mode authorization/scoping (including the
     * parent-may-only-use-student_id-for-their-own-ward restriction) — just
     * unpaginated.
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', AttendanceRecord::class);

        $request->validate([
            'student_id' => ['required_without:class_id', 'nullable', 'uuid', 'exists:students,id'],
            'class_id' => ['required_without:student_id', 'nullable', 'uuid'],
            'attendance_date' => ['required_with:class_id', 'nullable', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);

        if ($request->user()->hasRole('parent')) {
            if (
                ! $request->filled('student_id')
                || ! $request->user()->wards()->whereKey($request->input('student_id'))->exists()
            ) {
                abort(403, 'You may only view attendance for your own ward.');
            }
        }

        if ($request->filled('student_id')) {
            $rows = AttendanceRecord::query()
                ->with('student')
                ->where('student_id', $request->input('student_id'))
                ->latest('attendance_date')
                ->get();
        } else {
            $rows = AttendanceRecord::query()
                ->with('student')
                ->where('class_id', $request->input('class_id'))
                ->where('attendance_date', $request->input('attendance_date'))
                ->when(
                    $request->filled('period'),
                    fn ($query) => $query->where('period', $request->input('period')),
                    fn ($query) => $query->whereNull('period')
                )
                ->get();
        }

        $columns = [
            'student.full_name' => 'Student',
            'attendance_date' => 'Date',
            'status' => 'Status',
            'note' => 'Note',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'attendance', 'Attendance')
            : $this->exportService->excel($rows, $columns, 'attendance');
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
