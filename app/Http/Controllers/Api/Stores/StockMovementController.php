<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stores;

use App\Http\Controllers\Controller;
use App\Http\Resources\Stores\StockMovementResource;
use App\Models\StockMovement;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = StockMovement::query()
            ->with('inventoryItem')
            ->orderByDesc('performed_at');

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', $request->query('inventory_item_id'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('performed_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('performed_at', '<=', $request->query('to_date'));
        }

        return StockMovementResource::collection($query->get());
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', StockMovement::class);

        $rows = StockMovement::query()
            ->with('inventoryItem')
            ->orderByDesc('performed_at')
            ->get();

        $columns = [
            'inventoryItem.name' => 'Item',
            'direction' => 'Direction',
            'quantity' => 'Quantity',
            'unit_cost' => 'Unit Cost',
            'balance_after' => 'Balance After',
            'reason' => 'Reason',
            'performed_at' => 'Performed At',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'stock-movements', 'Stock Movements')
            : $this->exportService->excel($rows, $columns, 'stock-movements');
    }
}
