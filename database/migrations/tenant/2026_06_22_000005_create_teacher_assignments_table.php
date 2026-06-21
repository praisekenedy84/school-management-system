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
     * Links a teacher (a `users` row) to a (class, subject, academic_session)
     * tuple they teach.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('teacher_id');

            $table->uuid('class_id');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->index('class_id');

            $table->uuid('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->index('subject_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->timestamps();

            $table->unique(
                ['teacher_id', 'class_id', 'subject_id', 'academic_session_id'],
                'teacher_assignments_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
