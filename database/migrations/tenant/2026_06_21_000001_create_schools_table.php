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
     * Schools are the root campus entity within a tenant schema. A tenant may
     * have one or more schools (multi-campus); a single-school tenant simply
     * has one row here. This table is NOT school-owned (no school_id) — it IS
     * the school.
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('name');
            $table->string('code')->unique();
            $table->string('locale')->default('en');
            $table->string('currency')->default('TZS');
            $table->string('timezone')->default('Africa/Dar_es_Salaam');

            $table->jsonb('branding')->nullable(); // logo_path, colors, etc.
            $table->string('calendar_type')->nullable();
            $table->jsonb('grading_scale')->nullable();
            $table->jsonb('fee_terms')->nullable();

            $table->boolean('hostel_available')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
