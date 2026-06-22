<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hostel;

use App\Events\Hostel\HostelChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\HostelRequest;
use App\Http\Resources\HostelResource;
use App\Models\Hostel;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HostelController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index()
    {
        $this->authorize('viewAny', Hostel::class);

        return HostelResource::collection(Hostel::query()->orderBy('name')->get());
    }

    /** GET /api/v1/hostels/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Hostel::class);

        $rows = Hostel::query()->orderBy('name')->get();
        $columns = ['name' => 'Name', 'gender' => 'Gender', 'is_active' => 'Active'];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'hostels', 'Hostels')
            : $this->exportService->excel($rows, $columns, 'hostels');
    }

    public function store(HostelRequest $request)
    {
        $hostel = Hostel::create($request->validated());

        HostelChanged::dispatch($hostel, 'created', Auth::user());

        return HostelResource::make($hostel)->response()->setStatusCode(201);
    }

    public function show(Hostel $hostel)
    {
        $this->authorize('view', $hostel);

        return HostelResource::make($hostel);
    }

    public function update(HostelRequest $request, Hostel $hostel)
    {
        $hostel->update($request->validated());

        HostelChanged::dispatch($hostel, 'updated', Auth::user());

        return HostelResource::make($hostel);
    }

    public function destroy(Hostel $hostel)
    {
        $this->authorize('delete', $hostel);

        $hostel->delete();

        HostelChanged::dispatch($hostel, 'deleted', Auth::user());

        return response()->noContent();
    }
}
