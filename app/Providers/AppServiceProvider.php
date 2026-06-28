<?php

namespace App\Providers;

use App\Contracts\AuditableEvent;
use App\Listeners\LogAudit;
use App\Models\NavigationItem;
use App\Models\NavigationSection;
use App\Models\User;
use App\Observers\SyncTenantUserDirectoryObserver;
use App\Policies\NavigationPolicy;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(NavigationSection::class, NavigationPolicy::class);
        Gate::policy(NavigationItem::class, NavigationPolicy::class);

        // One registration covers every event implementing AuditableEvent —
        // see App\Listeners\LogAudit.
        Event::listen(AuditableEvent::class, LogAudit::class);
    }
}
