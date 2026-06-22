<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Academic;

use App\Events\Academic\AcademicSessionChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicSessionRequest;
use App\Http\Resources\AcademicSessionResource;
use App\Models\AcademicSession;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index()
    {
        $this->authorize('viewAny', AcademicSession::class);

        $sessions = AcademicSession::query()->orderBy('start_date', 'desc')->get();

        return AcademicSessionResource::collection($sessions);
    }

    /** GET /api/v1/academic-sessions/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', AcademicSession::class);

        $rows = AcademicSession::query()->orderBy('start_date', 'desc')->get();
        $columns = [
            'name' => 'Name',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'is_current' => 'Current',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'academic-sessions', 'Academic Sessions')
            : $this->exportService->excel($rows, $columns, 'academic-sessions');
    }

    public function show(AcademicSession $academicSession)
    {
        $this->authorize('view', $academicSession);

        return AcademicSessionResource::make($academicSession);
    }

    public function store(AcademicSessionRequest $request)
    {
        $academicSession = DB::transaction(function () use ($request) {
            $session = AcademicSession::create($request->validated());

            $this->demoteOtherCurrentSessions($session);

            return $session;
        });

        AcademicSessionChanged::dispatch($academicSession, 'created', Auth::user());

        return AcademicSessionResource::make($academicSession)
            ->response()
            ->setStatusCode(201);
    }

    public function update(AcademicSessionRequest $request, AcademicSession $academicSession)
    {
        DB::transaction(function () use ($request, $academicSession) {
            $academicSession->update($request->validated());

            $this->demoteOtherCurrentSessions($academicSession);
        });

        AcademicSessionChanged::dispatch($academicSession, 'updated', Auth::user());

        return AcademicSessionResource::make($academicSession);
    }

    public function destroy(AcademicSession $academicSession)
    {
        $this->authorize('delete', $academicSession);

        $academicSession->delete();

        AcademicSessionChanged::dispatch($academicSession, 'deleted', Auth::user());

        return response()->noContent();
    }

    /**
     * Only one session may be `is_current` per school (DashboardController
     * and report-card generation both rely on there being exactly one) —
     * no DB constraint enforces this, so flipping a session current here
     * demotes any other current session in the same school.
     */
    private function demoteOtherCurrentSessions(AcademicSession $session): void
    {
        if (! $session->is_current) {
            return;
        }

        AcademicSession::where('school_id', $session->school_id)
            ->whereKeyNot($session->id)
            ->update(['is_current' => false]);
    }
}
