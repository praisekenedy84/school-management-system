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
     * An assessment definition (e.g. "Midterm Exam", "Quiz 1") scoped to a
     * subject within an academic session, carrying a weighting (%) toward
     * the final grade.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->uuid('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->index('subject_id');

            $table->string('name');
            $table->decimal('weight', 5, 2); // percentage toward final grade, e.g. 30.00
            $table->decimal('max_score', 6, 2)->default(100);

            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->timestamps();

            $table->unique(['subject_id', 'academic_session_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
