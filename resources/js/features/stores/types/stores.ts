/** Mirrors App\Http\Resources\Stores\InventoryItemResource. */
export interface InventoryItem {
    id: string;
    school_id: string;
    name: string;
    sku: string | null;
    category: string | null;
    unit: string;
    current_quantity: string;
    reorder_level: string;
    unit_cost: string;
    line_value: string;
    restock_value: string;
    currency: string;
    is_active: boolean;
    is_low_stock: boolean;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Body for POST/PUT /api/v1/inventory-items. */
export interface InventoryItemRequest {
    name: string;
    sku?: string | null;
    category?: string | null;
    unit: string;
    reorder_level?: number | string | null;
    unit_cost?: number | string | null;
    currency?: string | null;
    is_active?: boolean | null;
    notes?: string | null;
    school_id?: string;
}

/** GET /api/v1/inventory-items/valuation */
export interface InventoryValuation {
    item_count: number;
    total_valuation: string;
    currency: string;
}

export type StoreRequisitionStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'partially_issued'
    | 'issued'
    | 'rejected'
    | 'cancelled';

/** Mirrors App\Http\Resources\Stores\StoreRequisitionLineResource. */
export interface StoreRequisitionLine {
    id: string;
    inventory_item_id: string;
    inventory_item?: InventoryItem;
    requested_quantity: string;
    issued_quantity: string;
    remaining_quantity: string;
    estimated_line_value: string | null;
    unit: string;
    line_notes: string | null;
    is_closed: boolean;
}

/** Mirrors App\Http\Resources\Stores\StoreRequisitionResource. */
export interface StoreRequisition {
    id: string;
    school_id: string;
    requisition_number: string;
    requested_by: string;
    purpose: string | null;
    needed_by: string | null;
    status: StoreRequisitionStatus;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
    rejection_reason: string | null;
    issued_by: string | null;
    issued_at: string | null;
    estimated_total?: string;
    lines?: StoreRequisitionLine[];
    created_at: string | null;
    updated_at: string | null;
}

export interface StoreRequisitionLineInput {
    inventory_item_id: string;
    requested_quantity: number | string;
    line_notes?: string | null;
}

/** Body for POST/PUT /api/v1/store-requisitions. */
export interface StoreRequisitionRequest {
    purpose?: string | null;
    needed_by?: string | null;
    lines: StoreRequisitionLineInput[];
    school_id?: string;
}

export interface IssueRequisitionLineInput {
    line_id: string;
    quantity: number | string;
}

export interface IssueStoreRequisitionRequest {
    lines: IssueRequisitionLineInput[];
}

export interface CloseRequisitionLineRequest {
    line_id: string;
    line_notes?: string | null;
}

export interface RejectStoreRequisitionRequest {
    rejection_reason: string;
}

export interface ApproveStoreRequisitionRequest {
    review_notes?: string | null;
}

export interface AddRequisitionToPurchaseRequest {
    mode: 'shortfall' | 'all';
    purchase_request_id?: string | null;
}

export type PurchaseRequestStatus =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'approved'
    | 'amended'
    | 'rejected'
    | 'fulfilled'
    | 'cancelled';

/** Mirrors App\Http\Resources\Stores\PurchaseRequestLineResource. */
export interface PurchaseRequestLine {
    id: string;
    inventory_item_id: string | null;
    inventory_item?: InventoryItem;
    item_name: string;
    unit: string;
    requested_quantity: string;
    estimated_unit_cost: string;
    amended_quantity: string | null;
    amended_unit_cost: string | null;
    effective_quantity: string;
    effective_unit_cost: string;
    estimated_line_total: string;
    effective_line_total: string;
    line_notes: string | null;
}

/** Mirrors App\Http\Resources\Stores\PurchaseRequestResource. */
export interface PurchaseRequest {
    id: string;
    school_id: string;
    request_number: string;
    requested_by: string;
    store_requisition_id: string | null;
    title: string | null;
    notes: string | null;
    status: PurchaseRequestStatus;
    reviewed_by: string | null;
    reviewed_at: string | null;
    review_notes: string | null;
    rejection_reason: string | null;
    amended_by: string | null;
    amended_at: string | null;
    amendment_notes: string | null;
    fulfilled_by: string | null;
    fulfilled_at: string | null;
    estimated_total?: string;
    effective_total?: string;
    lines?: PurchaseRequestLine[];
    fulfillment?: PurchaseFulfillment;
    created_at: string | null;
    updated_at: string | null;
}

export interface PurchaseRequestLineInput {
    inventory_item_id?: string | null;
    item_name: string;
    unit: string;
    requested_quantity: number | string;
    estimated_unit_cost?: number | string | null;
    line_notes?: string | null;
}

/** Body for POST/PUT /api/v1/purchase-requests. */
export interface PurchaseRequestForm {
    title?: string | null;
    notes?: string | null;
    lines: PurchaseRequestLineInput[];
    school_id?: string;
}

export interface AmendPurchaseRequestLineInput {
    line_id: string;
    amended_quantity?: number | string | null;
    amended_unit_cost?: number | string | null;
}

export interface AmendPurchaseRequestRequest {
    amendment_notes?: string | null;
    lines: AmendPurchaseRequestLineInput[];
}

export interface ApprovePurchaseRequestRequest {
    review_notes?: string | null;
}

export interface RejectPurchaseRequestRequest {
    rejection_reason: string;
}

export interface FulfillPurchaseRequestLineInput {
    purchase_request_line_id: string;
    received_quantity: number | string;
    actual_unit_cost: number | string;
    line_notes?: string | null;
}

/** Body for POST /api/v1/purchase-requests/{id}/fulfill. */
export interface FulfillPurchaseRequestRequest {
    supplier_name?: string | null;
    supplier_reference?: string | null;
    fulfillment_date: string;
    notes?: string | null;
    lines: FulfillPurchaseRequestLineInput[];
}

/** Mirrors App\Http\Resources\Stores\PurchaseFulfillmentLineResource. */
export interface PurchaseFulfillmentLine {
    id: string;
    purchase_request_line_id: string;
    inventory_item_id: string;
    inventory_item?: InventoryItem;
    requested_quantity: string;
    received_quantity: string;
    requested_unit_cost: string;
    actual_unit_cost: string;
    line_notes: string | null;
}

/** Mirrors App\Http\Resources\Stores\PurchaseFulfillmentResource. */
export interface PurchaseFulfillment {
    id: string;
    school_id: string;
    purchase_request_id: string;
    fulfillment_number: string;
    fulfilled_by: string;
    supplier_name: string | null;
    supplier_reference: string | null;
    fulfillment_date: string;
    notes: string | null;
    attachments: unknown[];
    total_cost: string;
    lines?: PurchaseFulfillmentLine[];
    created_at: string | null;
}

export type StockMovementDirection = 'in' | 'out';

export type StockMovementReason = 'requisition_issue' | 'purchase_receipt' | 'adjustment' | 'reversal';

/** Mirrors App\Http\Resources\Stores\StockMovementResource. */
export interface StockMovement {
    id: string;
    school_id: string;
    inventory_item_id: string;
    inventory_item?: InventoryItem;
    direction: StockMovementDirection;
    quantity: string;
    unit_cost: string;
    line_value: string;
    balance_after: string;
    reason: StockMovementReason;
    reference_type: string | null;
    reference_id: string | null;
    notes: string | null;
    performed_by: string;
    performed_at: string | null;
    created_at: string | null;
}

export interface InventoryItemQuery {
    category?: string;
    active_only?: boolean;
}

export interface StoreRequisitionQuery {
    status?: StoreRequisitionStatus;
}

export interface PurchaseRequestQuery {
    status?: PurchaseRequestStatus;
}

export interface StockMovementQuery {
    inventory_item_id?: string;
    direction?: StockMovementDirection;
    from_date?: string;
    to_date?: string;
}
