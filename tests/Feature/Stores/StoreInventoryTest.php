<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Models\InventoryItem;
use App\Models\School;
use App\Models\StoreRequisition;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTenant;
use Tests\TestCase;

/**
 * Phase 7 Stores module — acceptance criteria from docs/prd-stores-inventory-module.md §12.
 */
class StoreInventoryTest extends TestCase
{
    use CreatesTenant;

    private Tenant $tenant;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createAndInitializeTenant();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->school = School::factory()->create();
    }

    protected function tearDown(): void
    {
        Auth::guard('web')->logout();
        $this->cleanUpTenants();

        parent::tearDown();
    }

    private function asUser(User $user)
    {
        return $this->withSession(['tenant_id' => $this->tenant->getTenantKey()])->actingAs($user);
    }

    private function storekeeper(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('storekeeper');

        return $user;
    }

    private function kitchenStaff(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('kitchen_staff');

        return $user;
    }

    private function financeManager(): User
    {
        $user = User::factory()->create(['school_id' => $this->school->id]);
        $user->assignRole('finance_manager');

        return $user;
    }

    private function createStockedItem(array $overrides = []): InventoryItem
    {
        return InventoryItem::factory()->create(array_merge([
            'school_id' => $this->school->id,
            'name' => 'White Rice',
            'unit' => 'kg',
            'current_quantity' => '100.000',
            'reorder_level' => '10.000',
            'unit_cost' => '1000.00',
            'created_by' => $this->storekeeper()->id,
        ], $overrides));
    }

    public function test_storekeeper_can_crud_inventory_items(): void
    {
        $keeper = $this->storekeeper();

        $create = $this->asUser($keeper)->postJson('/api/v1/inventory-items', [
            'name' => 'Cooking Oil',
            'category' => 'oils',
            'unit' => 'L',
            'reorder_level' => 5,
            'unit_cost' => 3500,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Cooking Oil');
        $create->assertJsonPath('data.current_quantity', '0.000');
        $this->assertNotNull($create->json('data.sku'));
        $this->assertStringStartsWith('SKU-', $create->json('data.sku'));

        $itemId = $create->json('data.id');

        $index = $this->asUser($keeper)->getJson('/api/v1/inventory-items');
        $index->assertOk();
        $index->assertJsonFragment(['id' => $itemId, 'name' => 'Cooking Oil']);

        $update = $this->asUser($keeper)->putJson("/api/v1/inventory-items/{$itemId}", [
            'name' => 'Vegetable Oil',
            'unit' => 'L',
            'reorder_level' => 8,
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.name', 'Vegetable Oil');
        $this->assertDatabaseHas('inventory_items', [
            'id' => $itemId,
            'name' => 'Vegetable Oil',
        ]);

        $delete = $this->asUser($keeper)->deleteJson("/api/v1/inventory-items/{$itemId}");
        $delete->assertNoContent();

        $this->assertSoftDeleted('inventory_items', ['id' => $itemId]);
        $this->assertDatabaseHas('inventory_items', [
            'id' => $itemId,
            'is_active' => false,
        ]);
    }

    public function test_kitchen_staff_partial_requisition_flow_issues_in_two_steps(): void
    {
        $item = $this->createStockedItem();
        $cook = $this->kitchenStaff();
        $keeper = $this->storekeeper();

        $draft = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Monday lunch — 400 students',
            'needed_by' => now()->addDay()->toDateString(),
            'lines' => [
                [
                    'inventory_item_id' => $item->id,
                    'requested_quantity' => 100,
                ],
            ],
        ]);

        $draft->assertCreated();
        $draft->assertJsonPath('data.estimated_total', '100000.00');
        $draft->assertJsonPath('data.lines.0.estimated_line_value', '100000.00');
        $requisitionId = $draft->json('data.id');
        $lineId = $draft->json('data.lines.0.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve", [
            'review_notes' => 'Approved for Monday service.',
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $firstIssue = $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/issue", [
            'lines' => [
                ['line_id' => $lineId, 'quantity' => 60],
            ],
        ]);

        $firstIssue->assertOk();
        $firstIssue->assertJsonPath('data.status', 'partially_issued');
        $firstIssue->assertJsonPath('data.lines.0.issued_quantity', '60.000');

        $item->refresh();
        $this->assertSame('40.000', (string) $item->current_quantity);

        $secondIssue = $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/issue", [
            'lines' => [
                ['line_id' => $lineId, 'quantity' => 40],
            ],
        ]);

        $secondIssue->assertOk();
        $secondIssue->assertJsonPath('data.status', 'issued');
        $secondIssue->assertJsonPath('data.lines.0.issued_quantity', '100.000');

        $item->refresh();
        $this->assertSame('0.000', (string) $item->current_quantity);

        $this->assertDatabaseHas('store_requisitions', [
            'id' => $requisitionId,
            'status' => 'issued',
        ]);
    }

    public function test_insufficient_stock_returns_422_on_issue(): void
    {
        $item = $this->createStockedItem(['current_quantity' => '10.000']);
        $cook = $this->kitchenStaff();
        $keeper = $this->storekeeper();

        $draft = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Emergency lunch prep',
            'lines' => [
                ['inventory_item_id' => $item->id, 'requested_quantity' => 20],
            ],
        ])->assertCreated();

        $requisitionId = $draft->json('data.id');
        $lineId = $draft->json('data.lines.0.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")->assertOk();
        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve")->assertOk();

        $response = $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/issue", [
            'lines' => [
                ['line_id' => $lineId, 'quantity' => 15],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);

        $item->refresh();
        $this->assertSame('10.000', (string) $item->current_quantity);
    }

    public function test_purchase_request_submit_amend_fulfill_updates_stock_and_weighted_cost(): void
    {
        $keeper = $this->storekeeper();
        $finance = $this->financeManager();

        $item = InventoryItem::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'Maize Flour',
            'unit' => 'kg',
            'current_quantity' => '10.000',
            'unit_cost' => '1000.00',
            'created_by' => $keeper->id,
        ]);

        $create = $this->asUser($keeper)->postJson('/api/v1/purchase-requests', [
            'title' => 'Weekly grain restock',
            'lines' => [
                [
                    'inventory_item_id' => $item->id,
                    'item_name' => 'Maize Flour',
                    'unit' => 'kg',
                    'requested_quantity' => 50,
                    'estimated_unit_cost' => 1200,
                ],
            ],
        ])->assertCreated();

        $create->assertJsonPath('data.lines.0.estimated_line_total', '60000.00');
        $create->assertJsonPath('data.estimated_total', '60000.00');
        $create->assertJsonPath('data.effective_total', '60000.00');

        $purchaseRequestId = $create->json('data.id');
        $lineId = $create->json('data.lines.0.id');

        $this->asUser($keeper)->postJson("/api/v1/purchase-requests/{$purchaseRequestId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->asUser($finance)->postJson("/api/v1/purchase-requests/{$purchaseRequestId}/amend", [
            'amendment_notes' => 'Reduced qty after supplier quote.',
            'lines' => [
                ['line_id' => $lineId, 'amended_quantity' => 40],
            ],
        ])->assertOk()->assertJsonPath('data.status', 'amended');
        $this->asUser($finance)->getJson("/api/v1/purchase-requests/{$purchaseRequestId}")
            ->assertOk()
            ->assertJsonPath('data.effective_total', '48000.00');

        $fulfill = $this->asUser($finance)->postJson("/api/v1/purchase-requests/{$purchaseRequestId}/fulfill", [
            'fulfillment_date' => now()->toDateString(),
            'supplier_name' => 'Kariakoo Traders',
            'lines' => [
                [
                    'purchase_request_line_id' => $lineId,
                    'received_quantity' => 35,
                    'actual_unit_cost' => 2000,
                ],
            ],
        ]);

        $fulfill->assertCreated();
        $fulfill->assertJsonPath('data.lines.0.received_quantity', '35.000');
        $fulfill->assertJsonPath('data.lines.0.actual_unit_cost', '2000.00');

        $item->refresh();
        $this->assertSame('45.000', (string) $item->current_quantity);
        // (10 × 1000 + 35 × 2000) / 45 = 80000 / 45; bcdiv truncates to 1777.77
        $this->assertSame('1777.77', (string) $item->unit_cost);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequestId,
            'status' => 'fulfilled',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'direction' => 'in',
            'reason' => 'purchase_receipt',
        ]);
    }

    public function test_low_stock_item_appears_after_issue(): void
    {
        $item = $this->createStockedItem([
            'current_quantity' => '100.000',
            'reorder_level' => '50.000',
        ]);

        $cook = $this->kitchenStaff();
        $keeper = $this->storekeeper();

        $draft = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Weekly kitchen supply',
            'lines' => [
                ['inventory_item_id' => $item->id, 'requested_quantity' => 51],
            ],
        ])->assertCreated();

        $requisitionId = $draft->json('data.id');
        $lineId = $draft->json('data.lines.0.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")->assertOk();
        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve")->assertOk();

        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/issue", [
            'lines' => [['line_id' => $lineId, 'quantity' => 51]],
        ])->assertOk();

        $item->refresh();
        $this->assertTrue($item->isLowStock());
        $this->assertSame('49.000', (string) $item->current_quantity);

        $lowStock = $this->asUser($keeper)->getJson('/api/v1/inventory-items/low-stock');
        $lowStock->assertOk();
        $lowStock->assertJsonFragment(['id' => $item->id, 'name' => 'White Rice']);
    }

    public function test_cannot_use_inventory_item_from_another_school_in_requisition(): void
    {
        $otherSchool = School::factory()->create();
        $otherItem = InventoryItem::factory()->create([
            'school_id' => $otherSchool->id,
            'created_by' => $this->storekeeper()->id,
        ]);

        $cook = $this->kitchenStaff();

        $response = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Cross-campus attempt',
            'lines' => [
                ['inventory_item_id' => $otherItem->id, 'requested_quantity' => 10],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lines.0.inventory_item_id']);
    }

    public function test_kitchen_staff_cannot_approve_requisitions(): void
    {
        $item = $this->createStockedItem();
        $cook = $this->kitchenStaff();
        $keeper = $this->storekeeper();

        $draft = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Daily prep',
            'lines' => [
                ['inventory_item_id' => $item->id, 'requested_quantity' => 5],
            ],
        ])->assertCreated();

        $requisitionId = $draft->json('data.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")->assertOk();

        $response = $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve");

        $response->assertForbidden();

        $this->assertDatabaseHas('store_requisitions', [
            'id' => $requisitionId,
            'status' => 'submitted',
        ]);

        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_dashboard_summary_includes_stores_counts_for_storekeeper(): void
    {
        $keeper = $this->storekeeper();

        InventoryItem::factory()->create([
            'school_id' => $this->school->id,
            'current_quantity' => '5.000',
            'reorder_level' => '10.000',
            'is_active' => true,
            'created_by' => $keeper->id,
        ]);

        StoreRequisition::factory()->create([
            'school_id' => $this->school->id,
            'requested_by' => $keeper->id,
            'status' => 'submitted',
        ]);

        StoreRequisition::factory()->create([
            'school_id' => $this->school->id,
            'requested_by' => $keeper->id,
            'status' => 'approved',
        ]);

        $response = $this->asUser($keeper)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.stores.low_stock_items', 1);
        $response->assertJsonPath('data.stores.pending_requisitions', 2);
    }

    public function test_storekeeper_can_add_requisition_shortfall_to_new_purchase_request(): void
    {
        $keeper = $this->storekeeper();
        $cook = $this->kitchenStaff();

        $rice = $this->createStockedItem([
            'name' => 'Rice',
            'current_quantity' => '10.000',
            'unit_cost' => '2500.00',
        ]);

        $create = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'purpose' => 'Lunch',
            'lines' => [
                ['inventory_item_id' => $rice->id, 'requested_quantity' => 25],
            ],
        ]);
        $requisitionId = $create->json('data.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")->assertOk();
        $this->asUser($keeper)->postJson("/api/v1/store-requisitions/{$requisitionId}/approve")->assertOk();

        $add = $this->asUser($keeper)->postJson(
            "/api/v1/store-requisitions/{$requisitionId}/add-to-purchase",
            ['mode' => 'shortfall'],
        );

        $add->assertOk();
        $add->assertJsonPath('data.status', 'draft');
        $add->assertJsonPath('data.store_requisition_id', $requisitionId);
        $add->assertJsonPath('data.lines.0.requested_quantity', '15.000');

        $this->assertDatabaseHas('purchase_requests', [
            'store_requisition_id' => $requisitionId,
            'status' => 'draft',
        ]);
    }

    public function test_inventory_valuation_endpoint_returns_total(): void
    {
        $keeper = $this->storekeeper();

        $this->createStockedItem([
            'current_quantity' => '10.000',
            'unit_cost' => '1000.00',
        ]);
        $this->createStockedItem([
            'name' => 'Beans',
            'current_quantity' => '5.000',
            'unit_cost' => '2000.00',
        ]);

        $response = $this->asUser($keeper)->getJson('/api/v1/inventory-items/valuation');

        $response->assertOk();
        $response->assertJsonPath('data.item_count', 2);
        $response->assertJsonPath('data.total_valuation', '20000.00');
        $response->assertJsonPath('data.currency', 'TZS');

        $list = $this->asUser($keeper)->getJson('/api/v1/inventory-items');
        $list->assertOk();
        $list->assertJsonPath('data.0.line_value', '10000.00');
    }

    public function test_kitchen_staff_can_cancel_own_submitted_requisition(): void
    {
        $cook = $this->kitchenStaff();
        $rice = $this->createStockedItem();

        $create = $this->asUser($cook)->postJson('/api/v1/store-requisitions', [
            'lines' => [['inventory_item_id' => $rice->id, 'requested_quantity' => 5]],
        ]);
        $requisitionId = $create->json('data.id');

        $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/submit")->assertOk();

        $cancel = $this->asUser($cook)->postJson("/api/v1/store-requisitions/{$requisitionId}/cancel");

        $cancel->assertOk();
        $cancel->assertJsonPath('data.status', 'cancelled');
    }
}
