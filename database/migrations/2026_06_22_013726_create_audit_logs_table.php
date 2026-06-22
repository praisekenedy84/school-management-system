<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central table: cross-tenant activity log queried by Platform Admin to see
 * activity from every tenant in one place (ADR-0008). Append-only — rows are
 * only ever inserted, never updated or soft-deleted.
 *
 * `tenant_id` is a plain string (the tenants table's primary key is a slug,
 * not a UUID) and intentionally has no foreign key: some actions are
 * platform-level and have no tenant at all (e.g. "tenant created",
 * "platform admin logged in").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('tenant_id')->nullable();
            $table->index('tenant_id');

            $table->string('actor_type');
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();

            $table->string('action');
            $table->index('action');

            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);

            $table->jsonb('changes')->nullable();
            $table->string('ip_address')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
