<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\AuditableEvent;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Generic listener for every event implementing `AuditableEvent` — registered
 * once against the interface (see AppServiceProvider::boot()), not once per
 * event class. Deliberately NOT queued: this project has no Horizon/Redis
 * running locally yet (database-driver queue stopgap, see SETUP.md), and an
 * audit trail that silently never appears because a worker isn't running
 * would defeat the entire point of giving Platform Admin cross-tenant
 * visibility. Side-effect listeners (notify, generate receipt, etc.) can
 * still be queued separately if/when they're built.
 */
class LogAudit
{
    public function __construct(private readonly Request $request) {}

    public function handle(AuditableEvent $event): void
    {
        AuditLog::create($event->toAuditLog() + [
            'ip_address' => $this->request->ip(),
        ]);
    }
}
