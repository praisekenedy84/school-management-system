<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hostel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\AllocateHostelRequest;
use App\Http\Resources\HostelAllocationResource;
use App\Models\HostelAllocation;
use App\Services\Hostel\HostelAllocationService;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class HostelAllocationController extends Controller
{
    public function __construct(
        private readonly HostelAllocationService $allocations,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelAllocation::class);

        return HostelAllocationResource::collection($this->scopedQuery($request)->latest('allocated_at')->get());
    }

    /** GET /api/v1/hostel-allocations/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', HostelAllocation::class);

        $rows = $this->scopedQuery($request)->with(['student', 'hostelRoom.hostel'])->latest('allocated_at')->get();
        $columns = [
            'student.full_name' => 'Student',
            'hostelRoom.room_number' => 'Room',
            'hostelRoom.hostel.name' => 'Hostel',
            'status' => 'Status',
            'allocated_at' => 'Allocated At',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'hostel-allocations', 'Hostel Allocations')
            : $this->exportService->excel($rows, $columns, 'hostel-allocations');
    }

    private function scopedQuery(Request $request)
    {
        $query = HostelAllocation::query();

        if ($request->user()->hasRole('parent')) {
            $query->whereIn('student_id', $request->user()->wards()->pluck('students.id'));
        }

        if ($studentId = $request->query('student_id')) {
            $query->where('student_id', $studentId);
        }

        return $query;
    }

    public function store(AllocateHostelRequest $request)
    {
        $allocation = $this->allocations->allocate($request->validated(), $request->user()->id);

        return HostelAllocationResource::make($allocation)->response()->setStatusCode(201);
    }

    public function end(HostelAllocation $hostelAllocation)
    {
        $this->authorize('update', $hostelAllocation);

        return HostelAllocationResource::make($this->allocations->end($hostelAllocation));
    }
}
