<?php

namespace App\Providers;

use App\Contracts\AuditableEvent;
use App\Listeners\LogAudit;
use App\Models\User;
use App\Observers\SyncTenantUserDirectoryObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(SyncTenantUserDirectoryObserver::class);

        // One registration covers every event implementing AuditableEvent —
        // see App\Listeners\LogAudit.
        Event::listen(AuditableEvent::class, LogAudit::class);
    }
}
