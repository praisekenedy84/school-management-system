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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('name', 200);
            $table->string('sku', 50)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('unit', 30);
            $table->decimal('current_quantity', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('currency', 10)->default('TZS');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'name']);
        });

        DB::statement(
            'CREATE INDEX idx_inventory_items_low_stock ON inventory_items (school_id) '
            .'WHERE is_active = true AND current_quantity <= reorder_level'
        );

        DB::statement(
            'ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_current_quantity_non_negative '
            .'CHECK (current_quantity >= 0)'
        );
        DB::statement(
            'ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reorder_level_non_negative '
            .'CHECK (reorder_level >= 0)'
        );
        DB::statement(
            'ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_unit_cost_non_negative '
            .'CHECK (unit_cost >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
