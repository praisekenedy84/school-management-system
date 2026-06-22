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
        Schema::create('hostels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->index('school_id');

            $table->string('name');
            $table->string('gender', 10); // male|female|mixed
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        DB::statement(
            "ALTER TABLE hostels ADD CONSTRAINT hostels_gender_check CHECK (gender IN ('male', 'female', 'mixed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('hostels');
    }
};
