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
        Schema::create('store_requisitions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index(['school_id', 'status']);

            $table->string('requisition_number', 50);

            $table->uuid('requested_by');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');

            $table->text('purpose')->nullable();
            $table->date('needed_by')->nullable();
            $table->string('status', 30)->default('draft');

            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->uuid('issued_by')->nullable();
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'requisition_number']);
        });

        Schema::create('store_requisition_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('store_requisition_id');
            $table->foreign('store_requisition_id')->references('id')->on('store_requisitions')->onDelete('cascade');
            $table->index('store_requisition_id');

            $table->uuid('inventory_item_id');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('restrict');

            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('issued_quantity', 15, 3)->default(0);
            $table->string('unit', 30);
            $table->text('line_notes')->nullable();
            $table->boolean('is_closed')->default(false);

            $table->timestamps();

            $table->unique(['store_requisition_id', 'inventory_item_id']);
        });

        DB::statement(
            'ALTER TABLE store_requisitions ADD CONSTRAINT store_requisitions_status_check '
            ."CHECK (status IN ('draft', 'submitted', 'approved', 'partially_issued', 'issued', 'rejected', 'cancelled'))"
        );

        DB::statement(
            'ALTER TABLE store_requisition_lines ADD CONSTRAINT store_requisition_lines_requested_quantity_positive '
            .'CHECK (requested_quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE store_requisition_lines ADD CONSTRAINT store_requisition_lines_issued_quantity_non_negative '
            .'CHECK (issued_quantity >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('store_requisition_lines');
        Schema::dropIfExists('store_requisitions');
    }
};
