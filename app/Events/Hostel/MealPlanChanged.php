<?php

declare(strict_types=1);

namespace App\Events\Hostel;

use App\Contracts\AuditableEvent;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MealPlanChanged implements AuditableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  'created'|'updated'|'deleted'  $action
     */
    public function __construct(
        public readonly MealPlan $mealPlan,
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
            'action' => "meal_plan.{$this->action}",
            'subject_type' => MealPlan::class,
            'subject_id' => $this->mealPlan->id,
            'changes' => null,
        ];
    }
}
