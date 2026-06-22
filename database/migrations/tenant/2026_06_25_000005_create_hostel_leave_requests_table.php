<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('hostel_allocation_id');
            $table->foreign('hostel_allocation_id')->references('id')->on('hostel_allocations')->onDelete('cascade');
            $table->index('hostel_allocation_id');

            $table->text('reason');
            $table->date('depart_at');
            $table->date('return_at');
            $table->string('status', 20)->default('pending'); // pending|approved|rejected

            $table->uuid('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');

            $table->uuid('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();

            $table->timestamps();

            $table->index('status');
        });

        DB::statement(
            'ALTER TABLE hostel_leave_requests ADD CONSTRAINT hostel_leave_requests_status_check '
            ."CHECK (status IN ('pending', 'approved', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_leave_requests');
    }
};
