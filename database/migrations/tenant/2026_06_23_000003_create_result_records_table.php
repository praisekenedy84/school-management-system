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
     * A student's score/grade against an assessment. Append-only and
     * versioned per RULES.md §1/§3 (same family as finance records):
     * publishing or correcting a result NEVER updates an existing row in
     * place — a correction inserts a new row with `version` incremented.
     * There is deliberately NO unique constraint on (student_id,
     * assessment_id) since multiple versions are expected to coexist.
     */
    public function up(): void
    {
        Schema::create('result_records', function (Blueprint $table) {
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

            $table->uuid('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->index('subject_id');

            $table->uuid('assessment_id');
            $table->foreign('assessment_id')->references('id')->on('assessments')->onDelete('cascade');
            $table->index('assessment_id');

            $table->decimal('score', 6, 2)->nullable();
            $table->string('grade', 5)->nullable();

            $table->integer('version')->default(1);
            $table->boolean('is_published')->default(false);

            $table->uuid('published_by')->nullable();
            $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();

            $table->uuid('entered_by');
            $table->foreign('entered_by')->references('id')->on('users')->onDelete('restrict');

            $table->timestamps();

            $table->index(['student_id', 'assessment_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_records');
    }
};
