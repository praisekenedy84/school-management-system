<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-fee-type breakdown of a verified slip's allocation, linked to the
     * receipt that evidences it. This is the normalized counterpart to
     * payment_slips.allocation (JSONB) — used for ledger updates and
     * per-fee-type reporting (docs/prd-financial-module.md §5).
     */
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('payment_slip_id');
            $table->foreign('payment_slip_id')->references('id')->on('payment_slips')->onDelete('cascade');
            $table->index('payment_slip_id');

            $table->uuid('receipt_id');
            $table->foreign('receipt_id')->references('id')->on('payment_receipts')->onDelete('cascade');
            $table->index('receipt_id');

            $table->string('fee_type', 100);
            $table->decimal('amount', 15, 2);

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->timestamps();

            $table->index(['school_id', 'fee_type'], 'fee_payments_school_fee_type_index');
        });

        DB::statement('ALTER TABLE fee_payments ADD CONSTRAINT fee_payments_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
