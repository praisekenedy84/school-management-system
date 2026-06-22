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
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->uuid('hostel_id');
            $table->foreign('hostel_id')->references('id')->on('hostels')->onDelete('cascade');
            $table->index('hostel_id');

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['hostel_id', 'name']);
        });

        Schema::table('hostel_allocations', function (Blueprint $table) {
            $table->uuid('meal_plan_id')->nullable()->after('hostel_room_id');
            $table->foreign('meal_plan_id')->references('id')->on('meal_plans')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('hostel_allocations', function (Blueprint $table) {
            $table->dropForeign(['meal_plan_id']);
            $table->dropColumn('meal_plan_id');
        });

        Schema::dropIfExists('meal_plans');
    }
};
