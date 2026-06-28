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
        Schema::create('purchase_fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('purchase_request_id');
            $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('restrict');
            $table->index('purchase_request_id');

            $table->string('fulfillment_number', 50);

            $table->uuid('fulfilled_by');
            $table->foreign('fulfilled_by')->references('id')->on('users')->onDelete('restrict');

            $table->string('supplier_name', 200)->nullable();
            $table->string('supplier_reference', 200)->nullable();
            $table->date('fulfillment_date');
            $table->text('notes')->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['school_id', 'fulfillment_number']);
        });

        Schema::create('purchase_fulfillment_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('purchase_fulfillment_id');
            $table->foreign('purchase_fulfillment_id')->references('id')->on('purchase_fulfillments')->onDelete('cascade');
            $table->index('purchase_fulfillment_id');

            $table->uuid('purchase_request_line_id');
            $table->foreign('purchase_request_line_id')->references('id')->on('purchase_request_lines')->onDelete('restrict');

            $table->uuid('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');

            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('received_quantity', 15, 3);
            $table->decimal('requested_unit_cost', 15, 2);
            $table->decimal('actual_unit_cost', 15, 2);
            $table->decimal('actual_total', 15, 2)->default(0);
            $table->text('line_notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement(
            'ALTER TABLE purchase_fulfillments ADD CONSTRAINT purchase_fulfillments_total_cost_non_negative '
            .'CHECK (total_cost >= 0)'
        );

        DB::statement(
            'ALTER TABLE purchase_fulfillment_lines ADD CONSTRAINT purchase_fulfillment_lines_received_quantity_non_negative '
            .'CHECK (received_quantity >= 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_fulfillment_lines ADD CONSTRAINT purchase_fulfillment_lines_requested_unit_cost_non_negative '
            .'CHECK (requested_unit_cost >= 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_fulfillment_lines ADD CONSTRAINT purchase_fulfillment_lines_actual_unit_cost_non_negative '
            .'CHECK (actual_unit_cost >= 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_fulfillment_lines ADD CONSTRAINT purchase_fulfillment_lines_actual_total_non_negative '
            .'CHECK (actual_total >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_fulfillment_lines');
        Schema::dropIfExists('purchase_fulfillments');
    }
};
