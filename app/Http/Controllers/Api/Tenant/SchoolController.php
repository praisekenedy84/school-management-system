<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Models\School;

/**
 * Read-only lookup (id/name/code) — feeds the tenant-admin "which school"
 * picker on Subjects/Classes/Academic Sessions create forms (a school_admin
 * never needs this; their own school is implicit). No CRUD here yet; School
 * management itself is out of scope for this pass.
 */
class SchoolController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', School::class);

        return SchoolResource::collection(School::orderBy('name')->get());
    }
}
