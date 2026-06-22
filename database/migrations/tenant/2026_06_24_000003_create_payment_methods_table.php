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
     * Per-school configuration of accepted payment channels (bank transfer,
     * mobile money, cash, etc.) referenced by payment_slips.payment_method_id.
     * Per ADR-0001, `tenant_id` is dropped; `school_id` retained.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('name', 200);
            $table->string('type', 50); // bank_transfer|cash_deposit|mobile_money|cheque|direct_cash

            $table->string('bank_name', 200)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('account_name', 200)->nullable();
            $table->string('branch_code', 50)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->text('payment_instructions')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['school_id', 'is_active'], 'payment_methods_school_active_index');
        });

        DB::statement(
            'ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type_check '
            ."CHECK (type IN ('bank_transfer', 'cash_deposit', 'mobile_money', 'cheque', 'direct_cash'))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
