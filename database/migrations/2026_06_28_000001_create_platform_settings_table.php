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
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('platform_name')->default('School Management System');
            $table->string('support_email')->nullable();
            $table->string('default_locale')->default('en');
            $table->string('default_currency')->default('TZS');
            $table->boolean('maintenance_mode')->default(false);
            $table->unsignedInteger('max_tenants')->nullable();
            $table->jsonb('branding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
