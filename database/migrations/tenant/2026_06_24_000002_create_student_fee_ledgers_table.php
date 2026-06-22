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
     * One ledger per (student, academic_session). `balance` is a STORED
     * generated column (RULES.md §3 / ARCHITECTURE.md §3.1) — never written
     * directly by the app; Postgres recomputes it from
     * total_assessed - total_discounts - total_paid on every write.
     *
     * Laravel 11.x's PostgresGrammar compiles `storedAs()` to
     * `generated always as (...) stored`, so the native Blueprint API is
     * used here instead of a raw DB::statement.
     */
    public function up(): void
    {
        Schema::create('student_fee_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->jsonb('fee_details')->default('[]'); // [{fee_type, amount, is_paid}]

            $table->decimal('total_assessed', 15, 2)->default(0);
            $table->decimal('total_discounts', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);

            // Generated column: never assign this in application code.
            $table->decimal('balance', 15, 2)
                ->storedAs('total_assessed - total_discounts - total_paid');

            $table->string('payment_status', 50)->default('unpaid'); // unpaid|partially_paid|fully_paid|overpaid|waived
            $table->date('last_payment_date')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'student_fee_ledgers_student_session_unique');
            $table->index(['school_id', 'student_id'], 'student_fee_ledgers_school_student_index');
            $table->index('payment_status');
        });

        DB::statement(
            'ALTER TABLE student_fee_ledgers ADD CONSTRAINT student_fee_ledgers_payment_status_check '
            ."CHECK (payment_status IN ('unpaid', 'partially_paid', 'fully_paid', 'overpaid', 'waived'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fee_ledgers');
    }
};
