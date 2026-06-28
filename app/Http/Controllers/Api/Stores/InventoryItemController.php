<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stores;

use App\Events\Stores\InventoryItemChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\InventoryItemRequest;
use App\Http\Resources\Stores\InventoryItemResource;
use App\Models\InventoryItem;
use App\Services\Reporting\ExportService;
use App\Services\Stores\InventorySkuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryItemController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
        private readonly InventorySkuService $skus,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $query = InventoryItem::query()->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return InventoryItemResource::collection($query->get());
    }

    public function lowStock()
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('current_quantity', '<=', 'reorder_level')
            ->orderBy('name')
            ->get();

        return InventoryItemResource::collection($items);
    }

    /** PRD §5 — stock valuation = Σ(qty × unit_cost) for active items. */
    public function valuation()
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = InventoryItem::query()->where('is_active', true)->get();
        $total = '0.00';

        foreach ($items as $item) {
            $lineValue = bcmul((string) $item->current_quantity, (string) $item->unit_cost, 2);
            $total = bcadd($total, $lineValue, 2);
        }

        return response()->json([
            'data' => [
                'item_count' => $items->count(),
                'total_valuation' => $total,
                'currency' => 'TZS',
            ],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $rows = InventoryItem::query()->orderBy('name')->get();
        $columns = [
            'name' => 'Name',
            'sku' => 'SKU',
            'category' => 'Category',
            'unit' => 'Unit',
            'current_quantity' => 'Quantity',
            'reorder_level' => 'Reorder Level',
            'unit_cost' => 'Unit Cost',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'inventory-items', 'Inventory Items')
            : $this->exportService->excel($rows, $columns, 'inventory-items');
    }

    public function store(InventoryItemRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['current_quantity'] = '0.000';
        $data['currency'] = $data['currency'] ?? 'TZS';

        if (empty($data['sku'])) {
            $data['sku'] = $this->skus->generate($data['school_id']);
        }

        $item = InventoryItem::create($data);

        InventoryItemChanged::dispatch($item, 'created', Auth::user());

        return InventoryItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(InventoryItemRequest $request, InventoryItem $inventoryItem)
    {
        $inventoryItem->update($request->validated());

        InventoryItemChanged::dispatch($inventoryItem, 'updated', Auth::user());

        return InventoryItemResource::make($inventoryItem);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $this->authorize('delete', $inventoryItem);

        $inventoryItem->update(['is_active' => false]);
        $inventoryItem->delete();

        InventoryItemChanged::dispatch($inventoryItem, 'deactivated', Auth::user());

        return response()->noContent();
    }
}
