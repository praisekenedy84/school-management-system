<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hostel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\DecideLeaveRequest;
use App\Http\Requests\Hostel\RequestLeaveRequest;
use App\Http\Resources\HostelLeaveRequestResource;
use App\Models\HostelLeaveRequest;
use App\Services\Hostel\HostelLeaveService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class HostelLeaveRequestController extends Controller
{
    public function __construct(
        private readonly HostelLeaveService $leave,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelLeaveRequest::class);

        return HostelLeaveRequestResource::collection($this->scopedQuery($request)->latest()->get());
    }

    /** GET /api/v1/hostel-leave-requests/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', HostelLeaveRequest::class);

        $rows = $this->scopedQuery($request)->with('student')->latest()->get();
        $columns = [
            'student.full_name' => 'Student',
            'reason' => 'Reason',
            'depart_at' => 'Depart',
            'return_at' => 'Return',
            'status' => 'Status',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'hostel-leave-requests', 'Hostel Leave Requests')
            : $this->exportService->excel($rows, $columns, 'hostel-leave-requests');
    }

    private function scopedQuery(Request $request)
    {
        $query = HostelLeaveRequest::query();

        if ($request->user()->hasRole('parent')) {
            $query->whereIn('student_id', $request->user()->wards()->pluck('students.id'));
        }

        return $query;
    }

    public function store(RequestLeaveRequest $request)
    {
        $leaveRequest = $this->leave->request($request->validated(), $request->user()->id);

        return HostelLeaveRequestResource::make($leaveRequest)->response()->setStatusCode(201);
    }

    public function approve(DecideLeaveRequest $request, HostelLeaveRequest $hostelLeaveRequest)
    {
        $result = $this->leave->approve($hostelLeaveRequest, $request->user()->id, $request->input('decision_notes'));

        return HostelLeaveRequestResource::make($result);
    }

    public function reject(DecideLeaveRequest $request, HostelLeaveRequest $hostelLeaveRequest)
    {
        $result = $this->leave->reject($hostelLeaveRequest, $request->user()->id, $request->input('decision_notes'));

        return HostelLeaveRequestResource::make($result);
    }
}
