<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $logs = $this->scopedQuery($request)
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }

    /** GET /api/v1/platform/audit-logs/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $rows = $this->scopedQuery($request)->orderBy('created_at', 'desc')->get();
        $columns = [
            'created_at' => 'When',
            'actor_name' => 'Actor',
            'action' => 'Action',
            'subject_type' => 'Subject Type',
            'tenant_id' => 'Tenant',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'audit-logs', 'Audit Logs')
            : $this->exportService->excel($rows, $columns, 'audit-logs');
    }

    private function scopedQuery(Request $request)
    {
        return AuditLog::query()
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $request->input('tenant_id')))
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->input('actor_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->input('to')));
    }
}
