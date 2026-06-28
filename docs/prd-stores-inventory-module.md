# Stores & Kitchen Inventory Module — Deep Spec (Module 5.11)

> Companion to `PRD.md` §5.11. Detailed specification for school-store inventory: kitchen
> requisitions, stock movements, procurement requests to Finance, and low-stock alerts.
>
> **ADR-0001:** tenant tables live in `database/migrations/tenant` with **no `tenant_id`**
> column. **Keep `school_id`** for campus scoping within a tenant.
>
> **Product decisions (locked):**
> - **Option A** — requisition-only cook workflow (no separate after-the-fact usage log).
> - **Partial issue allowed** — a requisition line may be issued in one or more handovers until
>   `issued_quantity` reaches `requested_quantity` or the storekeeper closes the remainder.
> - **Cost-per-item tracked** — catalog carries a weighted-average unit cost; every stock-in/out
>   movement records the unit cost at transaction time for valuation and audit.

## 1. Core principle

**"Track stock, record procurement — don't pay."** The module tracks physical inventory, governs
who may take items from the store, and routes purchase lists through Finance for approval. Finance
records what was bought externally and received into the store; it does **not** process supplier
payments (same "record, don't transact" principle as the finance module).

## 2. Personas & roles

| Role | Responsibilities |
|------|------------------|
| **kitchen_staff** | Create/submit store requisitions; view own requisitions |
| **storekeeper** | Manage item catalog; approve/reject/issue requisitions; create purchase requests; view stock & movements; receive low-stock alerts |
| **finance_manager / accountant** | Review, approve, reject, or amend purchase requests; record fulfillment (actual items bought + costs) |
| **school_admin** | Full oversight, reports, export |
| **hostel_manager** | Read-only stock summary (optional v1 — defer unless requested) |

New permissions (seeded in `RoleAndPermissionSeeder`):

```
stores.manage_catalog          — storekeeper, school_admin
stores.approve_requisitions    — storekeeper, school_admin
stores.issue_requisitions      — storekeeper, school_admin
stores.create_requisitions     — kitchen_staff, storekeeper, school_admin
stores.view_requisitions       — kitchen_staff (own), storekeeper, school_admin
stores.create_purchase_requests  — storekeeper, school_admin
stores.approve_purchases       — finance_manager, accountant, school_admin
stores.fulfill_purchases       — finance_manager, accountant, school_admin
stores.view_stock              — storekeeper, kitchen_staff (read-only qty), school_admin
stores.view_movements          — storekeeper, school_admin, auditor (read-only)
```

New roles: `kitchen_staff`, `storekeeper`.

## 3. Schemas (PostgreSQL 16 — tenant migrations)

### 3.1 inventory_items (catalog)

```sql
CREATE TABLE inventory_items (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL REFERENCES schools(id),
  name VARCHAR(200) NOT NULL,
  sku VARCHAR(50),                              -- optional; auto-generated SKU-YYYYMMDD-NNNN when blank on create
  category VARCHAR(100),                        -- e.g. grains, vegetables, cleaning
  unit VARCHAR(30) NOT NULL,                  -- kg, L, pcs, bag, crate
  current_quantity DECIMAL(15,3) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(15,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,   -- weighted-average cost (TZS)
  currency VARCHAR(10) NOT NULL DEFAULT 'TZS',
  is_active BOOLEAN NOT NULL DEFAULT true,
  notes TEXT,
  created_by UUID REFERENCES users(id),
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
  deleted_at TIMESTAMP,
  UNIQUE (school_id, name)
);
CREATE INDEX idx_inventory_items_school ON inventory_items (school_id);
CREATE INDEX idx_inventory_items_low_stock ON inventory_items (school_id)
  WHERE is_active = true AND current_quantity <= reorder_level;
```

Quantities use `DECIMAL(15,3)` to support fractional units (e.g. 2.5 kg). Money uses `DECIMAL(15,2)`.

### 3.2 stock_movements (append-only ledger)

```sql
CREATE TABLE stock_movements (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL REFERENCES schools(id),
  inventory_item_id UUID NOT NULL REFERENCES inventory_items(id),
  direction VARCHAR(10) NOT NULL,               -- in | out
  quantity DECIMAL(15,3) NOT NULL CHECK (quantity > 0),
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,   -- cost at time of movement
  balance_after DECIMAL(15,3) NOT NULL,         -- item qty after this movement
  reason VARCHAR(50) NOT NULL,                  -- requisition_issue | purchase_receipt | adjustment | reversal
  reference_type VARCHAR(100),                -- morph: StoreRequisition, PurchaseFulfillment, ...
  reference_id UUID,
  notes TEXT,
  performed_by UUID NOT NULL REFERENCES users(id),
  performed_at TIMESTAMP NOT NULL DEFAULT NOW(),
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_stock_movements_item ON stock_movements (inventory_item_id, performed_at);
CREATE INDEX idx_stock_movements_school ON stock_movements (school_id, performed_at);
```

**Never update or hard-delete** stock movements. Corrections create a reversing `in`/`out` pair with
`reason = reversal` linked to the original movement id.

### 3.3 store_requisitions + store_requisition_lines

```sql
CREATE TABLE store_requisitions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL REFERENCES schools(id),
  requisition_number VARCHAR(50) NOT NULL,      -- REQ-YYYYMMDD-NNNN per school per year
  requested_by UUID NOT NULL REFERENCES users(id),
  purpose TEXT,                                 -- e.g. "Monday lunch — 400 students"
  needed_by DATE,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  -- draft | submitted | approved | partially_issued | issued | rejected | cancelled
  reviewed_by UUID REFERENCES users(id),
  reviewed_at TIMESTAMP,
  review_notes TEXT,
  rejection_reason TEXT,
  issued_by UUID REFERENCES users(id),          -- last issuer (each issue action also logged)
  issued_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
  deleted_at TIMESTAMP,
  UNIQUE (school_id, requisition_number)
);
CREATE INDEX idx_store_requisitions_status ON store_requisitions (school_id, status);

CREATE TABLE store_requisition_lines (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  store_requisition_id UUID NOT NULL REFERENCES store_requisitions(id) ON DELETE CASCADE,
  inventory_item_id UUID NOT NULL REFERENCES inventory_items(id),
  requested_quantity DECIMAL(15,3) NOT NULL CHECK (requested_quantity > 0),
  issued_quantity DECIMAL(15,3) NOT NULL DEFAULT 0 CHECK (issued_quantity >= 0),
  unit VARCHAR(30) NOT NULL,                    -- denormalized from item at submit time
  line_notes TEXT,
  is_closed BOOLEAN NOT NULL DEFAULT false,     -- storekeeper closed remainder without full issue
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (store_requisition_id, inventory_item_id)
);
```

**Partial issue rules:**
- `issued_quantity` may be incremented across multiple issue actions until it equals
  `requested_quantity` or the line is `is_closed = true`.
- Each issue action: `issue_qty = min(remaining_requested, available_stock)` unless storekeeper
  explicitly enters a lower qty; cannot issue more than remaining requested on the line.
- Requisition status transitions:
  - `approved` → first issue with partial lines → `partially_issued`
  - `partially_issued` → all open lines fully issued or closed → `issued`
- Stock check: issue blocked (422) if `issue_qty > inventory_item.current_quantity` unless
  `school.settings` allows negative stock (default: **block**).

### 3.4 purchase_requests + purchase_request_lines

```sql
CREATE TABLE purchase_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL REFERENCES schools(id),
  request_number VARCHAR(50) NOT NULL,            -- PUR-YYYYMMDD-NNNN
  requested_by UUID NOT NULL REFERENCES users(id),
  title VARCHAR(200),
  notes TEXT,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  -- draft | submitted | under_review | approved | amended | rejected | fulfilled | cancelled
  reviewed_by UUID REFERENCES users(id),
  reviewed_at TIMESTAMP,
  review_notes TEXT,
  rejection_reason TEXT,
  amended_by UUID REFERENCES users(id),
  amended_at TIMESTAMP,
  amendment_notes TEXT,
  fulfilled_by UUID REFERENCES users(id),
  fulfilled_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
  deleted_at TIMESTAMP,
  UNIQUE (school_id, request_number)
);

CREATE TABLE purchase_request_lines (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  purchase_request_id UUID NOT NULL REFERENCES purchase_requests(id) ON DELETE CASCADE,
  inventory_item_id UUID REFERENCES inventory_items(id),  -- null = new item to create on fulfill
  item_name VARCHAR(200) NOT NULL,                -- snapshot; used when inventory_item_id null
  unit VARCHAR(30) NOT NULL,
  requested_quantity DECIMAL(15,3) NOT NULL CHECK (requested_quantity > 0),
  estimated_unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  estimated_total DECIMAL(15,2) GENERATED ALWAYS AS (requested_quantity * estimated_unit_cost) STORED,
  line_notes TEXT,
  -- Finance amendment fields (populated on amend; original preserved via audit event)
  amended_quantity DECIMAL(15,3),
  amended_unit_cost DECIMAL(15,2),
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

**Effective quantity/cost for fulfillment** = `amended_quantity ?? requested_quantity` and
`amended_unit_cost ?? estimated_unit_cost`.

### 3.5 purchase_fulfillments + purchase_fulfillment_lines

```sql
CREATE TABLE purchase_fulfillments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL REFERENCES schools(id),
  purchase_request_id UUID NOT NULL REFERENCES purchase_requests(id),
  fulfillment_number VARCHAR(50) NOT NULL,        -- PRC-YYYYMMDD-NNNN
  fulfilled_by UUID NOT NULL REFERENCES users(id),
  supplier_name VARCHAR(200),
  supplier_reference VARCHAR(200),                -- invoice / receipt number
  fulfillment_date DATE NOT NULL,
  notes TEXT,
  attachments JSONB NOT NULL DEFAULT '[]',        -- [{file_path, file_name, mime, size, uploaded_at}]
  total_cost DECIMAL(15,2) NOT NULL DEFAULT 0,    -- sum of line actual totals
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (school_id, fulfillment_number)
);

CREATE TABLE purchase_fulfillment_lines (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  purchase_fulfillment_id UUID NOT NULL REFERENCES purchase_fulfillments(id) ON DELETE CASCADE,
  purchase_request_line_id UUID NOT NULL REFERENCES purchase_request_lines(id),
  inventory_item_id UUID NOT NULL REFERENCES inventory_items(id),
  requested_quantity DECIMAL(15,3) NOT NULL,      -- snapshot at fulfill time
  received_quantity DECIMAL(15,3) NOT NULL CHECK (received_quantity >= 0),
  requested_unit_cost DECIMAL(15,2) NOT NULL,
  actual_unit_cost DECIMAL(15,2) NOT NULL,
  actual_total DECIMAL(15,2) GENERATED ALWAYS AS (received_quantity * actual_unit_cost) STORED,
  line_notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

Fulfillment is **append-only** once created. Corrections require a new reversal fulfillment (v2 —
defer; v1 treat as support-only manual adjustment via stock adjustment with audit).

## 4. Workflows

### 4.1 Store requisition (cook → storekeeper) — Option A

```
draft → submitted → approved → partially_issued* → issued
                  ↘ rejected
                  ↘ cancelled
```

1. **kitchen_staff** builds requisition (lines: item + requested qty + optional purpose/needed_by).
2. **Submit** → status `submitted`; event `StoreRequisitionSubmitted`.
3. **storekeeper** approves or rejects.
   - Reject: reason required (≥ 20 chars); cook notified (in-app; SMS/email when engine exists).
   - Approve: status `approved`; no stock change yet.
4. **Issue (handover)** — may happen one or more times (partial issue):
   - Storekeeper selects lines + quantities to issue this round.
   - Service validates: approved/partially_issued status; qty ≤ remaining on line; qty ≤ stock.
   - In one DB transaction per issue action:
     - Increment `issued_quantity` on each line.
     - Create `stock_movement` (direction `out`, reason `requisition_issue`).
     - Decrement `inventory_item.current_quantity`.
     - Update requisition status (`partially_issued` or `issued`).
   - Event `StoreRequisitionIssued` (includes partial flag + line snapshot).
5. **Close line** — storekeeper may mark a line `is_closed` with a note when remaining qty will not
   be issued (e.g. menu change). Contributes to reaching `issued` status on the header.

**Link to procurement:** when approving, if stock is insufficient, storekeeper can one-click "add to
purchase request" (creates or appends lines on a draft `purchase_request`).

### 4.2 Purchase request (storekeeper → finance)

```
draft → submitted → under_review → approved | amended | rejected
approved | amended → fulfilled
```

1. **storekeeper** creates purchase request with lines (existing catalog item or free-text new item).
2. **Submit** → `submitted`; event `PurchaseRequestSubmitted`.
3. **finance_manager / accountant**:
   - **Approve** as-is → `approved`.
   - **Amend** → set `amended_quantity` / `amended_unit_cost` per line + notes → `amended`.
   - **Reject** → reason required → `rejected`.
4. **Fulfill** (finance, after physical purchase made outside the system):
   - Create `purchase_fulfillment` with side-by-side lines: requested vs received qty/cost.
   - For lines with no `inventory_item_id`, create catalog item on the fly (storekeeper notified).
   - In one DB transaction:
     - Create fulfillment + lines.
     - For each line with `received_quantity > 0`:
       - Create `stock_movement` (direction `in`, reason `purchase_receipt`).
       - Increment `current_quantity`.
       - Recalculate **weighted-average** `unit_cost` on the item:
         `new_cost = (old_qty × old_cost + in_qty × in_cost) / (old_qty + in_qty)` (BCMath).
     - Set purchase_request status `fulfilled`.
   - Event `PurchaseRequestFulfilled`.
5. Finance may attach supplier invoice/receipt images (same upload pattern as payment slips).

### 4.3 Low stock alert

After any stock **out** movement, if `current_quantity <= reorder_level`:
- Emit `InventoryLowStock` (implements `AuditableEvent`).
- In-app notification to users with `stores.manage_catalog` on that school.
- Dashboard widget: count of items at/below reorder level.
- Email/SMS when notification engine (ADR-0007) is available.

## 5. Cost tracking

| Field | Meaning |
|-------|---------|
| `inventory_items.unit_cost` | Current weighted-average unit cost (TZS) |
| `stock_movements.unit_cost` | Cost frozen at transaction time (audit / valuation) |
| `purchase_request_lines.estimated_unit_cost` | Storekeeper's estimate when requesting |
| `purchase_fulfillment_lines.actual_unit_cost` | Finance records what was actually paid per unit |

**Reports (v1):**
- Stock valuation = Σ (`current_quantity × unit_cost`) per school.
- Purchase variance = requested vs received qty and estimated vs actual cost per fulfillment.

All money: `DECIMAL(15,2)`, BCMath in PHP, never float.

## 6. API surface (tenant `/api/v1`)

| Method | Path | Role |
|--------|------|------|
| GET/POST | `/inventory-items` | storekeeper CRUD |
| GET/PATCH/DELETE | `/inventory-items/{id}` | storekeeper |
| GET | `/inventory-items/low-stock` | storekeeper |
| GET | `/stock-movements` | storekeeper, admin (filter by item, date) |
| GET/POST | `/store-requisitions` | kitchen_staff create; storekeeper list all |
| GET/PATCH | `/store-requisitions/{id}` | owner or storekeeper |
| POST | `/store-requisitions/{id}/submit` | kitchen_staff |
| POST | `/store-requisitions/{id}/approve` | storekeeper |
| POST | `/store-requisitions/{id}/reject` | storekeeper |
| POST | `/store-requisitions/{id}/issue` | storekeeper — body: `[{line_id, quantity}]` |
| POST | `/store-requisitions/{id}/close-line` | storekeeper |
| POST | `/store-requisitions/{id}/add-to-purchase` | storekeeper — body: `{ mode: shortfall|all, purchase_request_id? }` |
| POST | `/store-requisitions/{id}/cancel` | kitchen_staff (own draft/submitted) or storekeeper |
| GET | `/inventory-items/valuation` | storekeeper — stock valuation summary (PRD §5) |
| GET/POST | `/purchase-requests` | storekeeper |
| POST | `/purchase-requests/{id}/submit` | storekeeper |
| POST | `/purchase-requests/{id}/approve` | finance |
| POST | `/purchase-requests/{id}/amend` | finance |
| POST | `/purchase-requests/{id}/reject` | finance |
| POST | `/purchase-requests/{id}/fulfill` | finance |
| GET | `/purchase-requests/{id}/fulfillment` | storekeeper, finance |

Controllers under `App\Http\Controllers\Api\Stores\`. Services:
- `Stores\StoreRequisitionService`
- `Stores\PurchaseRequestService`
- `Stores\StockMovementService`
- `Stores\InventoryCostService` (weighted-average helper)

Split approve/issue/fulfill controllers mirror `PaymentSlipVerificationController` pattern.

## 7. Frontend (`resources/js/features/stores/`)

| Page | Primary role |
|------|--------------|
| `InventoryItemsPage` | storekeeper — catalog, reorder levels, unit cost, current qty |
| `LowStockPage` | storekeeper — items at/below reorder |
| `MyRequisitionsPage` | kitchen_staff — create + track own requests |
| `RequisitionQueuePage` | storekeeper — approve, issue (partial qty inputs), close lines |
| `PurchaseRequestsPage` | storekeeper — create/submit lists |
| `ProcurementQueuePage` | finance — approve/amend/reject |
| `FulfillmentPage` | finance — side-by-side requested vs received |
| `StockMovementsPage` | storekeeper/admin — movement history + export |

Nav: new **Stores** section in `AppLayout.tsx` (mirrors Finance / Hostel gating).

## 8. Events & audit

All implement `App\Contracts\AuditableEvent`:

| Event | When |
|-------|------|
| `InventoryItemChanged` | catalog create/update/deactivate |
| `StoreRequisitionSubmitted` | cook submits |
| `StoreRequisitionApproved` / `StoreRequisitionRejected` | storekeeper decision |
| `StoreRequisitionIssued` | each issue action (partial or final) |
| `PurchaseRequestSubmitted` | storekeeper submits |
| `PurchaseRequestApproved` / `PurchaseRequestAmended` / `PurchaseRequestRejected` | finance decision |
| `PurchaseRequestFulfilled` | goods received into stock |
| `InventoryLowStock` | qty crosses at/below reorder_level |
| `StoreRequisitionAddedToPurchase` | requisition lines copied to draft purchase request |

## 9. Validation (enforced)

- Requisition line: `requested_quantity > 0`; item must belong to same `school_id`.
- Issue: `quantity > 0`; `issued_quantity + quantity <= requested_quantity` (unless line closed).
- Issue: `quantity <= inventory_item.current_quantity` (default; configurable per school).
- Reject requisition / purchase: reason ≥ 20 chars.
- Purchase fulfill: `received_quantity` may be 0 (not bought) or differ from requested (partial delivery).
- Fulfillment: at least one line with `received_quantity > 0` unless explicit "nothing received" with notes ≥ 20 chars.
- Sequential numbers: advisory lock per school per year (same pattern as `SLP-` / `RCP-`).
- Cross-school: every `Rule::exists` on item/requisition/request ids must constrain `school_id`.

## 10. Security

- Policies on every model; FormRequest `authorize()` on every mutating endpoint.
- `kitchen_staff` sees only own requisitions (`requested_by = auth id`).
- Stock movements and fulfillments are append-only; soft-delete on requisition/request headers only.
- Upload attachments on fulfillments: same size/type scanning as payment slips (≤ 5 MB, image/pdf).
- Tenant + school isolation tests mandatory for every new model.

## 11. What the system DOES / DOES NOT do

### DOES
- Track physical inventory per school store with reorder levels and weighted-average cost.
- Govern cook access via approved requisitions with partial multi-step issue.
- Route purchase lists through Finance approve/amend/reject/fulfill with requested-vs-actual comparison.
- Maintain an append-only stock movement ledger.
- Alert storekeeper when stock is low.
- Export listings (Excel/PDF) via existing `ExportService` pattern.

### DOES NOT
- Process supplier payments or integrate payment gateways.
- Auto-deduct stock without an approved issue action.
- Allow cooks to bypass the storekeeper.
- Hard-delete movement or fulfillment records.

## 12. Acceptance criteria ("done when")

1. A **kitchen_staff** user submits a requisition; **storekeeper** approves and issues 60% of one line
   now and the rest later; stock decreases correctly after each issue; requisition ends `issued`.
2. A **storekeeper** creates a purchase request; **finance** amends one line's quantity; on fulfillment
   records different received qty and actual unit cost; stock increases; weighted-average cost updates.
3. When stock falls to reorder level after an issue, storekeeper sees a low-stock alert.
4. Fulfillment UI shows requested vs received quantities and costs side by side.
5. Cross-school item/requisition leakage blocked by tests; unauthorized role gets 403.

## 13. Implementation guidance

- Wrap every issue and fulfill action in `DB::transaction()` with `lockForUpdate()` on
  `inventory_items` row (same concurrency pattern as payment slip verification).
- Issue idempotency: guard against double-submit of the same issue payload (optional client token v2).
- Delegate finance fulfillment to `finance-specialist`; rest to `api-builder` + `frontend-builder`.
- Require `security-auditor` sign-off before production (tenancy + approval workflows + uploads).
