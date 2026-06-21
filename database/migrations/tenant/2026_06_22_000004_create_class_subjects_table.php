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
     * Many-to-many pivot between `classes` and `subjects`. No `school_id`
     * here: ownership is derived through the class/subject relationship.
     */
    public function up(): void
    {
        Schema::create('class_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('class_id');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->index('class_id');

            $table->uuid('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->index('subject_id');

            $table->timestamps();

            $table->unique(['class_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
    }
};
