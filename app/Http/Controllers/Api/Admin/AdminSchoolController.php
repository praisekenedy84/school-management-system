<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Events\Tenant\SchoolChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolRequest;
use App\Http\Requests\Admin\UpdateSchoolBillingRequest;
use App\Http\Requests\Admin\UpdateSchoolBrandingRequest;
use App\Http\Requests\Admin\UpdateSchoolSettingsRequest;
use App\Http\Resources\SchoolAdminResource;
use App\Models\School;
use Illuminate\Support\Facades\Auth;

class AdminSchoolController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', School::class);

        return SchoolAdminResource::collection(School::orderBy('name')->get());
    }

    public function store(SchoolRequest $request)
    {
        $school = School::create($request->validated());

        SchoolChanged::dispatch($school, 'created', Auth::user());

        return SchoolAdminResource::make($school)->response()->setStatusCode(201);
    }

    public function show(School $school)
    {
        $this->authorize('view', $school);

        return SchoolAdminResource::make($school);
    }

    public function update(SchoolRequest $request, School $school)
    {
        $school->update($request->validated());

        SchoolChanged::dispatch($school, 'updated', Auth::user());

        return SchoolAdminResource::make($school->fresh());
    }

    public function destroy(School $school)
    {
        $this->authorize('delete', $school);

        $school->delete();

        SchoolChanged::dispatch($school, 'deleted', Auth::user());

        return response()->noContent();
    }

    public function updateSettings(UpdateSchoolSettingsRequest $request, School $school)
    {
        $school->update($request->validated());

        SchoolChanged::dispatch($school->fresh(), 'settings_updated', Auth::user());

        return SchoolAdminResource::make($school->fresh());
    }

    public function updateBranding(UpdateSchoolBrandingRequest $request, School $school)
    {
        $school->update(['branding' => $request->validated('branding')]);

        SchoolChanged::dispatch($school->fresh(), 'branding_updated', Auth::user());

        return SchoolAdminResource::make($school->fresh());
    }

    public function updateBilling(UpdateSchoolBillingRequest $request, School $school)
    {
        $school->update(['billing' => $request->validated('billing')]);

        SchoolChanged::dispatch($school->fresh(), 'billing_updated', Auth::user());

        return SchoolAdminResource::make($school->fresh());
    }
}
