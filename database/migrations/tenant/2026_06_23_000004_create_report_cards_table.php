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
     * A cache pointer to the most recently generated report-card PDF for a
     * (student, academic_session) pair — NOT an append-only/versioned
     * record like `result_records`. Regenerating a report card updates this
     * row's `file_path`/`generated_at` IN PLACE; the underlying
     * `result_records` remain the append-only source of truth.
     */
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
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

            $table->string('file_path', 500);

            $table->uuid('generated_by')->nullable();
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('generated_at');

            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
