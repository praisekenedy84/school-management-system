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
     * `enrolments` link a student to a class for one academic session.
     * Append-only history: promoting a student to a new session creates a
     * NEW row here and flips the OLD row's `status` to `promoted` — never
     * overwrite or delete a past enrolment. `residence_type` is tracked per
     * enrolment (not just on `students`) because day/boarding status can
     * change session to session.
     */
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('class_id');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->index('class_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->string('residence_type', 10)->default('day'); // day|boarding
            $table->string('status', 20)->default('active'); // active|promoted|transferred|withdrawn|completed
            $table->date('enrolled_at')->nullable();

            $table->timestamps();

            // One enrolment per student per session — promotion creates a new
            // row in the NEXT session, so this does not block multi-session history.
            $table->unique(['student_id', 'academic_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};
