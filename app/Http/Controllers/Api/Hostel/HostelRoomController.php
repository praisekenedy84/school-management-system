<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hostel;

use App\Events\Hostel\HostelRoomChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\HostelRoomRequest;
use App\Http\Resources\HostelRoomResource;
use App\Models\HostelRoom;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HostelRoomController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelRoom::class);

        return HostelRoomResource::collection($this->scopedQuery($request)->orderBy('room_number')->get());
    }

    /** GET /api/v1/hostel-rooms/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', HostelRoom::class);

        $rows = $this->scopedQuery($request)->with('hostel')->orderBy('room_number')->get();
        $columns = ['hostel.name' => 'Hostel', 'room_number' => 'Room', 'capacity' => 'Capacity'];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'hostel-rooms', 'Hostel Rooms')
            : $this->exportService->excel($rows, $columns, 'hostel-rooms');
    }

    private function scopedQuery(Request $request)
    {
        $query = HostelRoom::query();

        if ($hostelId = $request->query('hostel_id')) {
            $query->where('hostel_id', $hostelId);
        }

        return $query;
    }

    public function store(HostelRoomRequest $request)
    {
        $room = HostelRoom::create($request->validated());

        HostelRoomChanged::dispatch($room, 'created', Auth::user());

        return HostelRoomResource::make($room)->response()->setStatusCode(201);
    }

    public function show(HostelRoom $hostelRoom)
    {
        $this->authorize('view', $hostelRoom);

        return HostelRoomResource::make($hostelRoom);
    }

    public function update(HostelRoomRequest $request, HostelRoom $hostelRoom)
    {
        $hostelRoom->update($request->validated());

        HostelRoomChanged::dispatch($hostelRoom, 'updated', Auth::user());

        return HostelRoomResource::make($hostelRoom);
    }

    public function destroy(HostelRoom $hostelRoom)
    {
        $this->authorize('delete', $hostelRoom);

        $hostelRoom->delete();

        HostelRoomChanged::dispatch($hostelRoom, 'deleted', Auth::user());

        return response()->noContent();
    }
}
