<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\InventoryItem;
use App\Models\Scopes\SchoolScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sequential SKU codes per school per year: SKU-YYYYMMDD-NNNN.
 * Auto-assigned when the storekeeper leaves SKU blank on create.
 */
class InventorySkuService
{
    public function generate(string $schoolId, ?Carbon $date = null): string
    {
        $date ??= now();
        $datePart = $date->format('Ymd');
        $year = $date->format('Y');

        return DB::transaction(function () use ($schoolId, $datePart, $year) {
            DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', ["sku:{$schoolId}:{$year}"]);

            $count = InventoryItem::withoutGlobalScope(SchoolScope::class)
                ->where('school_id', $schoolId)
                ->whereYear('created_at', $year)
                ->where('sku', 'like', 'SKU-%')
                ->count();

            $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

            return "SKU-{$datePart}-{$sequence}";
        });
    }
}
