<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Finance;

use App\Events\Finance\PaymentMethodChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Services\Reporting\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plain CRUD for payment-method configuration — no service layer (mirrors
 * AssessmentController). Authorization runs via PaymentMethodPolicy.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private readonly ExportService $exportService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $paymentMethods = $this->scopedQuery($request)
            ->paginate($request->integer('per_page', 20));

        return PaymentMethodResource::collection($paymentMethods);
    }

    /** GET /api/v1/payment-methods/export?format=xlsx|pdf */
    public function export(Request $request)
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $rows = $this->scopedQuery($request)->get();
        $columns = [
            'name' => 'Name',
            'type' => 'Type',
            'bank_name' => 'Bank',
            'account_number' => 'Account No',
            'is_active' => 'Active',
        ];

        return $request->query('format', 'xlsx') === 'pdf'
            ? $this->exportService->pdf($rows, $columns, 'payment-methods', 'Payment Methods')
            : $this->exportService->excel($rows, $columns, 'payment-methods');
    }

    private function scopedQuery(Request $request)
    {
        return PaymentMethod::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest();
    }

    public function store(PaymentMethodRequest $request)
    {
        $paymentMethod = PaymentMethod::create($request->validated());

        PaymentMethodChanged::dispatch($paymentMethod, 'created', Auth::user());

        return PaymentMethodResource::make($paymentMethod)
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentMethod $paymentMethod)
    {
        $this->authorize('view', $paymentMethod);

        return PaymentMethodResource::make($paymentMethod);
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($request->validated());

        PaymentMethodChanged::dispatch($paymentMethod, 'updated', Auth::user());

        return PaymentMethodResource::make($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);

        $paymentMethod->delete();

        PaymentMethodChanged::dispatch($paymentMethod, 'deleted', Auth::user());

        return response()->noContent();
    }
}
