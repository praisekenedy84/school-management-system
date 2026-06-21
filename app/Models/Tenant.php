<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Central model. Adds database-per-schema support (ADR-0001) and the
 * domains() relation on top of stancl's base Tenant, which only implements
 * the bare Contracts\Tenant.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;
}
