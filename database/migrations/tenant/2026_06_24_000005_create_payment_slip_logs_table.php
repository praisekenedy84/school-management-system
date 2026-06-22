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
     * Append-only audit trail of every payment_slip state transition
     * (RULES.md §1/§5: every state change emits a domain event whose
     * listener writes a log row here). This table is INSERT-only — never
     * updated, never deleted — so it intentionally has no `updated_at` and
     * no soft deletes; there is nothing to "undo" on a pure log.
     */
    public function up(): void
    {
        Schema::create('payment_slip_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('payment_slip_id');
            $table->foreign('payment_slip_id')->references('id')->on('payment_slips')->onDelete('cascade');
            $table->index('payment_slip_id');

            $table->string('action', 50);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();

            $table->uuid('performed_by')->nullable();
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
            $table->string('performer_role', 100)->nullable();

            $table->jsonb('changes')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_slip_id', 'created_at'], 'payment_slip_logs_slip_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_slip_logs');
    }
};
