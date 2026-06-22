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
     * Fee structures define the assessed fee amounts per (school, academic
     * session, class, fee type). Per ADR-0001 / docs/prd-financial-module.php
     * §2.1 conversion note, `tenant_id` is dropped — tenant isolation is the
     * Postgres schema, not a column. `school_id` is retained for campus
     * scoping within a tenant.
     */
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->uuid('class_id');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->index('class_id');

            $table->string('fee_type', 100);
            $table->decimal('amount', 15, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->string('applicable_to', 20); // all|day_only|boarding_only

            $table->boolean('installment_allowed')->default(false);
            $table->integer('installment_count')->nullable();
            $table->date('due_date')->nullable();

            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_session_id', 'class_id', 'fee_type'],
                'fee_structures_school_session_class_type_unique'
            );
        });

        // Laravel's Blueprint has no first-class CHECK constraint builder, so
        // DB-level invariants beyond FK/unique are added via raw SQL.
        DB::statement(
            'ALTER TABLE fee_structures ADD CONSTRAINT fee_structures_applicable_to_check '
            ."CHECK (applicable_to IN ('all', 'day_only', 'boarding_only'))"
        );
        DB::statement('ALTER TABLE fee_structures ADD CONSTRAINT fee_structures_amount_non_negative CHECK (amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
