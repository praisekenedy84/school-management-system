<?php

declare(strict_types=1);

namespace App\Events\Finance;

use App\Contracts\AuditableEvent;
use App\Models\FeeStructure;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeeStructureChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deleted'  $action
     */
    public function __construct(
        public readonly FeeStructure $feeStructure,
        public readonly string $action,
        public readonly ?User $actor,
    ) {}

    public function toAuditLog(): array
    {
        return [
            'tenant_id' => tenant('id'),
            'actor_type' => 'user',
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
            'actor_email' => $this->actor?->email,
            'action' => "fee_structure.{$this->action}",
            'subject_type' => FeeStructure::class,
            'subject_id' => $this->feeStructure->id,
            'changes' => null,
        ];
    }
}
