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
     * A homework/assignment a teacher publishes to a class. The class,
     * subject, session, and teacher are all derived via `teacher_assignment_id`.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('teacher_assignment_id');
            $table->foreign('teacher_assignment_id')->references('id')->on('teacher_assignments')->onDelete('cascade');
            $table->index('teacher_assignment_id');

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->uuid('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
