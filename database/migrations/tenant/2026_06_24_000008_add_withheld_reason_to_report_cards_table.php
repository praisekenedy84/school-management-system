<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds `withheld_reason` so the optional fee-status gate (PRD §5.5,
     * PROJECT-PLAN Phase 3 hook) can record that a report card was NOT
     * generated because the student's StudentFeeLedger balance is
     * outstanding and the school opted into the gate
     * (School.fee_terms->results_gate_enabled). When set, file_path is left
     * null and ReportCardController@show returns a clear "withheld" message
     * instead of a confusing 404.
     *
     * file_path is made nullable: a withheld card has no PDF. Existing rows
     * all have a non-null file_path, so this widening is safe and the gate
     * defaults OFF (so Phase 2's existing tests are unaffected).
     */
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->change();
            $table->string('withheld_reason', 255)->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropColumn('withheld_reason');
            $table->string('file_path', 500)->nullable(false)->change();
        });
    }
};
