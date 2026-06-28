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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index(['school_id', 'performed_at']);

            $table->uuid('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->index(['inventory_item_id', 'performed_at']);

            $table->string('direction', 10);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 3);
            $table->string('reason', 50);
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('performed_by');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');
            $table->timestamp('performed_at')->useCurrent();

            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_direction_check '
            ."CHECK (direction IN ('in', 'out'))"
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_quantity_positive '
            .'CHECK (quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason_check '
            ."CHECK (reason IN ('requisition_issue', 'purchase_receipt', 'adjustment', 'reversal'))"
        );
        DB::statement(
            'ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_unit_cost_non_negative '
            .'CHECK (unit_cost >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
