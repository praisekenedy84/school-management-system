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
     * A student's submission against an assignment, with optional teacher
     * feedback/grade.
     */
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('assignment_id');
            $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
            $table->index('assignment_id');

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->timestamp('submitted_at')->nullable();
            $table->text('content')->nullable();
            $table->string('file_path')->nullable();

            $table->text('feedback')->nullable();
            $table->string('grade')->nullable();

            $table->uuid('graded_by')->nullable();
            $table->foreign('graded_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('graded_at')->nullable();

            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
