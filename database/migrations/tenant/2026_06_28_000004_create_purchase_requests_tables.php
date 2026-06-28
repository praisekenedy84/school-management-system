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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('request_number', 50);

            $table->uuid('requested_by');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');

            $table->string('title', 200)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft');

            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->uuid('amended_by')->nullable();
            $table->foreign('amended_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('amended_at')->nullable();
            $table->text('amendment_notes')->nullable();

            $table->uuid('fulfilled_by')->nullable();
            $table->foreign('fulfilled_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('fulfilled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'request_number']);
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('purchase_request_id');
            $table->foreign('purchase_request_id')->references('id')->on('purchase_requests')->onDelete('cascade');
            $table->index('purchase_request_id');

            $table->uuid('inventory_item_id')->nullable();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('set null');

            $table->string('item_name', 200);
            $table->string('unit', 30);
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->text('line_notes')->nullable();

            $table->decimal('amended_quantity', 15, 3)->nullable();
            $table->decimal('amended_unit_cost', 15, 2)->nullable();

            $table->timestamps();
        });

        DB::statement(
            'ALTER TABLE purchase_requests ADD CONSTRAINT purchase_requests_status_check '
            ."CHECK (status IN ('draft', 'submitted', 'under_review', 'approved', 'amended', 'rejected', 'fulfilled', 'cancelled'))"
        );

        DB::statement(
            'ALTER TABLE purchase_request_lines ADD CONSTRAINT purchase_request_lines_requested_quantity_positive '
            .'CHECK (requested_quantity > 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_request_lines ADD CONSTRAINT purchase_request_lines_estimated_unit_cost_non_negative '
            .'CHECK (estimated_unit_cost >= 0)'
        );
        DB::statement(
            'ALTER TABLE purchase_request_lines ADD CONSTRAINT purchase_request_lines_estimated_total_non_negative '
            .'CHECK (estimated_total >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};
