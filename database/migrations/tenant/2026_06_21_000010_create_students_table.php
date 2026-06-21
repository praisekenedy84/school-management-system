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
     * `students` is a critical table per RULES.md — soft deletes only,
     * never hard delete.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('admission_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('residence_type', 10)->default('day'); // day|boarding
            $table->string('status', 20)->default('active'); // active|inactive|transferred|graduated|withdrawn
            $table->date('admitted_at')->nullable();
            $table->string('photo_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'admission_number']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
