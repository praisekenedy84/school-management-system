<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central sessions table. The central schema has no `users` table at all
 * (every user lives in a tenant schema, see database/migrations/tenant) —
 * this only exists so any central-context 'web' request (SESSION_DRIVER is
 * 'database') has somewhere to write its session row. `user_id` stays
 * nullable/unused (no central authentication in Phase 0) — but the column
 * must exist regardless: Illuminate\Session\DatabaseSessionHandler always
 * writes a user_id value when it has a container, column or not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
