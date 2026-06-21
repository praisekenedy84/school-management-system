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
     * Per-(class, period) attendance mark for a student. Offline-tolerant
     * capture (PRD §5.4 / SKILLS Recipe G) syncs idempotently: a retried or
     * duplicate sync of the same mark must not create a second row, hence
     * the unique composite on (student_id, attendance_date, period).
     */
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
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

            $table->date('attendance_date');
            $table->string('period', 50)->nullable();
            $table->string('status', 20); // present|absent|late|excused
            $table->text('note')->nullable();

            $table->uuid('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            $table->unique(['student_id', 'attendance_date', 'period'], 'attendance_records_student_date_period_unique');
            $table->index(['school_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
