<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RULES.md §3: hostel_allocations is soft-delete only, never hard-deleted.
     * A student may have only one ACTIVE allocation per session — enforced
     * via a partial unique index (Postgres) rather than a plain unique
     * constraint, since past (ended) allocations for the same session must
     * remain in history.
     */
    public function up(): void
    {
        Schema::create('hostel_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('hostel_room_id');
            $table->foreign('hostel_room_id')->references('id')->on('hostel_rooms')->onDelete('cascade');
            $table->index('hostel_room_id');

            $table->uuid('academic_session_id');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            $table->index('academic_session_id');

            $table->string('status', 20)->default('active'); // active|ended
            $table->date('allocated_at');
            $table->date('ended_at')->nullable();
            $table->uuid('allocated_by')->nullable();
            $table->foreign('allocated_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            "ALTER TABLE hostel_allocations ADD CONSTRAINT hostel_allocations_status_check CHECK (status IN ('active', 'ended'))"
        );

        DB::statement(
            'CREATE UNIQUE INDEX hostel_allocations_one_active_per_session '
            .'ON hostel_allocations (student_id, academic_session_id) '
            ."WHERE status = 'active' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_allocations');
    }
};
