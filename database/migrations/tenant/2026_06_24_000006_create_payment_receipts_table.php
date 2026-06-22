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
     * Sequential, immutable receipts (RCP-YYYYMMDD-NNNN) generated only
     * after a payment_slip is verified (docs/prd-financial-module.md §3
     * "Receipt rules"). Receipts are immutable by application convention —
     * once a receipt row exists, the app never issues an UPDATE against it;
     * corrections happen by creating a new, separately-numbered receipt
     * tied to a new/corrected slip, never by mutating this row.
     *
     * Deliberately NOT unique on `payment_slip_id`: idempotency of "one
     * receipt per verified slip" is a service-layer guard (RULES.md
     * "Make submission and receipt generation idempotent"), not a DB
     * constraint, because a future correction workflow may legitimately
     * need a second receipt referencing the same original slip. No soft
     * deletes either — there is no delete path for a generated receipt.
     */
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('payment_slip_id');
            $table->foreign('payment_slip_id')->references('id')->on('payment_slips')->onDelete('restrict');
            $table->index('payment_slip_id');

            $table->string('receipt_number', 50)->unique(); // RCP-YYYYMMDD-NNNN
            $table->text('amount_in_words')->nullable();
            $table->jsonb('payment_details')->default('{}');
            $table->string('qr_code_path', 500)->nullable();
            $table->string('file_path', 500)->nullable();

            $table->uuid('generated_by');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('restrict');
            $table->timestamp('generated_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
