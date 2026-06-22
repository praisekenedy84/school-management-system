<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Implemented by every domain event that should produce an `audit_logs`
 * row (RULES.md §1 — "every state-changing action emits a domain event
 * that feeds audit"). A listener (built separately) reads `toAuditLog()`,
 * adds `ip_address` from the current request, and inserts into the
 * central `AuditLog` model.
 */
interface AuditableEvent
{
    /**
     * Must return every `audit_logs` column EXCEPT `id`, `created_at`, and
     * `ip_address`: `tenant_id`, `actor_type`, `actor_id`, `actor_name`,
     * `actor_email`, `action`, `subject_type`, `subject_id`, `changes`.
     *
     * @return array<string, mixed>
     */
    public function toAuditLog(): array;
}
