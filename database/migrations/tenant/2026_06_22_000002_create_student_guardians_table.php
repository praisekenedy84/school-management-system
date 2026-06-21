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
     * Many-to-many pivot between `students` and `users`. Guardians are just
     * `users` with the 'parent' role — there is no separate Guardian model.
     * No `school_id` here: ownership is derived through the student/guardian
     * relationship, not stored redundantly on the pivot.
     */
    public function up(): void
    {
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('student_id');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index('student_id');

            $table->uuid('guardian_id');
            $table->foreign('guardian_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('guardian_id');

            $table->string('relationship', 50)->nullable(); // e.g. mother|father|guardian
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
