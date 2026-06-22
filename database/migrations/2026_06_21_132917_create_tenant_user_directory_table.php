<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central table: maps a login email to the tenant schema it belongs to.
 * Kept in sync by App\Observers\SyncTenantUserDirectoryObserver on the
 * tenant-scoped App\Models\User — see ARCHITECTURE.md ADR-0008.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_directory', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('email')->unique();
            $table->string('tenant_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_directory');
    }
};
