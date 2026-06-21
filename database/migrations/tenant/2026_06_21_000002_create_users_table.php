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
     * Tenant-scoped users table. Replaces the default Laravel `users` table —
     * this one lives in the tenant schema only (no central users table).
     * `school_id` is nullable because a tenant_admin operates across every
     * school in the tenant; school-scoped roles (school_admin, teacher, …)
     * will have it set.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->uuid('school_id')->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
            $table->index('school_id');

            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('locale')->default('en');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
