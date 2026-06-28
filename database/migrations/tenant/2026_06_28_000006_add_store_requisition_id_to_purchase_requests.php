<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->uuid('store_requisition_id')->nullable()->after('requested_by');
            $table->foreign('store_requisition_id')
                ->references('id')
                ->on('store_requisitions')
                ->onDelete('set null');
            $table->index('store_requisition_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX inventory_items_school_sku_unique ON inventory_items (school_id, sku) WHERE sku IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_items_school_sku_unique');

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['store_requisition_id']);
            $table->dropColumn('store_requisition_id');
        });
    }
};
