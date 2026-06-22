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
     * Core finance record: parent-submitted evidence of an externally-made
     * payment. Per RULES.md §1/§3 this table is append-only — verified
     * slips/receipts are never updated in place; corrections are new
     * versioned records. Soft deletes only, never hard delete (RULES.md §3).
     * Per ADR-0001, `tenant_id` is dropped; `school_id` retained.
     */
    public function up(): void
    {
        Schema::create('payment_slips', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('slip_number', 50)->unique(); // SLP-YYYYMMDD-NNNN

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('submitted_by');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('restrict');

            $table->uuid('payment_method_id')->nullable();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');

            $table->string('bank_name', 200)->nullable();
            $table->string('branch_name', 200)->nullable();
            $table->string('teller_number', 100)->nullable();
            $table->string('transaction_reference', 200)->nullable();
            $table->string('depositor_name', 300);

            $table->date('deposit_date');
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 10)->default('TZS');

            $table->jsonb('allocation'); // [{fee_type, amount, academic_session_id}]
            $table->jsonb('slip_attachments')->default('[]'); // [{file_path, thumbnail_path, file_name, size, mime, uploaded_at}]

            $table->string('status', 50)->default('pending');
            // pending|under_review|verified|approved|rejected|disputed|clarification_needed

            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->uuid('rejected_by')->nullable();
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('rejection_category', 100)->nullable();
            // incorrect_amount|unclear_image|wrong_details|duplicate|other

            $table->string('receipt_number', 50)->nullable()->unique();
            $table->timestamp('receipt_generated_at')->nullable();
            $table->uuid('receipt_generated_by')->nullable();
            $table->foreign('receipt_generated_by')->references('id')->on('users')->onDelete('set null');
            $table->string('receipt_file_path', 500)->nullable();

            $table->string('submission_ip', 45)->nullable();
            $table->jsonb('device_info')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('teller_number');
            $table->index('deposit_date');
            $table->index('created_at');
            $table->index(['school_id', 'status'], 'payment_slips_school_status_index');
        });

        DB::statement(
            'ALTER TABLE payment_slips ADD CONSTRAINT payment_slips_status_check '
            ."CHECK (status IN ('pending', 'under_review', 'verified', 'approved', 'rejected', 'disputed', 'clarification_needed'))"
        );
        DB::statement('ALTER TABLE payment_slips ADD CONSTRAINT payment_slips_total_amount_positive CHECK (total_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_slips');
    }
};
