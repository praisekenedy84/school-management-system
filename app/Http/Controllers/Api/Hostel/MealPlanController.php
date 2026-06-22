<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Hostel;

use App\Events\Hostel\MealPlanChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hostel\MealPlanRequest;
use App\Http\Resources\MealPlanResource;
use App\Models\MealPlan;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MealPlanController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', MealPlan::class);

        return MealPlanResource::collection($this->scopedQuery($request)->orderBy('name')->get());
    }

    /** GET /api/v1/meal-plans/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', MealPlan::class);

        $rows = $this->scopedQuery($request)->with('hostel')->orderBy('name')->get();
        $columns = ['hostel.name' => 'Hostel', 'name' => 'Name', 'price' => 'Price'];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'meal-plans', 'Meal Plans')
            : $this->exportService->excel($rows, $columns, 'meal-plans');
    }

    private function scopedQuery(Request $request)
    {
        $query = MealPlan::query();

        if ($hostelId = $request->query('hostel_id')) {
            $query->where('hostel_id', $hostelId);
        }

        return $query;
    }

    public function store(MealPlanRequest $request)
    {
        $mealPlan = MealPlan::create($request->validated());

        MealPlanChanged::dispatch($mealPlan, 'created', Auth::user());

        return MealPlanResource::make($mealPlan)->response()->setStatusCode(201);
    }

    public function update(MealPlanRequest $request, MealPlan $mealPlan)
    {
        $mealPlan->update($request->validated());

        MealPlanChanged::dispatch($mealPlan, 'updated', Auth::user());

        return MealPlanResource::make($mealPlan);
    }

    public function destroy(MealPlan $mealPlan)
    {
        $this->authorize('delete', $mealPlan);

        $mealPlan->delete();

        MealPlanChanged::dispatch($mealPlan, 'deleted', Auth::user());

        return response()->noContent();
    }
}
