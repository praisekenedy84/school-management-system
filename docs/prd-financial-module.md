# Financial Recording & Tracking Module — Deep Spec (Module 7.6)

> Companion to `PRD.md`. This is the detailed specification for the finance module. It restores and
> completes the section that was truncated in the original document (§14 "What the System DOES").
>
> **ADR-0001 conversion note:** under `stancl/tenancy` schema-per-tenant, these are **tenant tables** —
> their migrations go in `database/migrations/tenant` and they **drop the `tenant_id` column** shown
> below (tenant isolation is the Postgres schema). **Keep `school_id`** for campus scoping within a
> tenant. Where a schema block lists `tenant_id UUID NOT NULL`, omit it when implementing.

## 1. Core principle

**"Record, don't transact."** The system records payment evidence (bank slips, mobile money, cash),
manages verification, and tracks financial status. It does **NOT** process or move money.

## 2. Schemas (PostgreSQL 16)

### 2.1 fee_structures
```sql
CREATE TABLE fee_structures (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL, school_id UUID NOT NULL,
  academic_session_id UUID NOT NULL, class_id UUID NOT NULL REFERENCES classes(id),
  fee_type VARCHAR(100) NOT NULL, amount DECIMAL(15,2) NOT NULL,
  is_mandatory BOOLEAN DEFAULT true,
  applicable_to VARCHAR(20) NOT NULL,           -- all | day_only | boarding_only
  installment_allowed BOOLEAN DEFAULT false, installment_count INTEGER, due_date DATE,
  created_by UUID REFERENCES users(id), is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMP DEFAULT NOW(),
  UNIQUE(tenant_id, school_id, academic_session_id, class_id, fee_type)
);
```

### 2.2 student_fee_ledgers
```sql
CREATE TABLE student_fee_ledgers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL, school_id UUID NOT NULL,
  student_id UUID NOT NULL REFERENCES students(id), academic_session_id UUID NOT NULL,
  fee_details JSONB NOT NULL DEFAULT '[]',      -- [{fee_type, amount, is_paid}]
  total_assessed DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_discounts DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_paid DECIMAL(15,2) NOT NULL DEFAULT 0,
  balance DECIMAL(15,2) GENERATED ALWAYS AS (total_assessed - total_discounts - total_paid) STORED,
  payment_status VARCHAR(50) DEFAULT 'unpaid',  -- unpaid|partially_paid|fully_paid|overpaid|waived
  last_payment_date DATE,
  created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_ledger_student ON student_fee_ledgers(student_id, academic_session_id);
CREATE INDEX idx_ledger_status ON student_fee_ledgers(payment_status);
```

### 2.3 payment_methods
```sql
CREATE TABLE payment_methods (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL, school_id UUID NOT NULL,
  name VARCHAR(200) NOT NULL,
  type VARCHAR(50) NOT NULL,                     -- bank_transfer|cash_deposit|mobile_money|cheque|direct_cash
  bank_name VARCHAR(200), account_number VARCHAR(100), account_name VARCHAR(200),
  branch_code VARCHAR(50), swift_code VARCHAR(50), payment_instructions TEXT,
  is_active BOOLEAN DEFAULT true, created_at TIMESTAMP DEFAULT NOW()
);
```

### 2.4 payment_slips (core)
```sql
CREATE TABLE payment_slips (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id UUID NOT NULL, school_id UUID NOT NULL,
  slip_number VARCHAR(50) UNIQUE NOT NULL,        -- SLP-YYYYMMDD-XXXX
  student_id UUID NOT NULL REFERENCES students(id),
  submitted_by UUID NOT NULL REFERENCES users(id),
  payment_method_id UUID REFERENCES payment_methods(id),
  bank_name VARCHAR(200), branch_name VARCHAR(200),
  teller_number VARCHAR(100), transaction_reference VARCHAR(200),
  depositor_name VARCHAR(300) NOT NULL,
  deposit_date DATE NOT NULL, total_amount DECIMAL(15,2) NOT NULL, currency VARCHAR(10) DEFAULT 'TZS',
  allocation JSONB NOT NULL,                      -- [{fee_type, amount, academic_session_id}]
  slip_attachments JSONB NOT NULL DEFAULT '[]',   -- [{file_path, thumbnail_path, file_name, size, mime, uploaded_at}]
  status VARCHAR(50) DEFAULT 'pending',           -- pending|under_review|verified|approved|rejected|disputed
  verified_by UUID REFERENCES users(id), verified_at TIMESTAMP, verification_notes TEXT,
  approved_by UUID REFERENCES users(id), approved_at TIMESTAMP, approval_notes TEXT,
  rejected_by UUID REFERENCES users(id), rejected_at TIMESTAMP, rejection_reason TEXT,
  rejection_category VARCHAR(100),                -- incorrect_amount|unclear_image|wrong_details|duplicate|other
  receipt_number VARCHAR(50) UNIQUE, receipt_generated_at TIMESTAMP,
  receipt_generated_by UUID REFERENCES users(id), receipt_file_path VARCHAR(500),
  submission_ip VARCHAR(45), device_info JSONB, notes TEXT,
  created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW(), deleted_at TIMESTAMP
);
CREATE INDEX idx_slips_student ON payment_slips(student_id);
CREATE INDEX idx_slips_status ON payment_slips(status);
CREATE INDEX idx_slips_date ON payment_slips(created_at);
CREATE INDEX idx_slips_teller ON payment_slips(teller_number);
```

### 2.5 payment_slip_logs (audit), payment_receipts, fee_payments, fee_discounts, fee_installments
Schemas as in the source PRD: append-only `payment_slip_logs` (action, from/to status, performer + role,
JSON changes, IP); `payment_receipts` (RCP-YYYYMMDD-XXXX, amount_in_words, payment_details JSONB, QR);
`fee_payments` (per-fee-type recorded amounts linked to slip + receipt); `fee_discounts`
(type, %, amount, applies_to JSONB, validity, approver); `fee_installments` (number, due amount/date,
paid amount/date, status, reminders).

## 3. Workflows

- **Submission (parent):** Recipe D in `SKILLS.md`. Allocation must sum to total; duplicate teller per
  bank per date rejected; slip number generated; status `pending`; log + event emitted.
- **Verification (finance):** Recipe E. verify → update ledger + generate receipt (PDF + QR); or
  request-clarification; or reject (category + reason). Receipts immutable once generated.
- **Receipt rules:** auto-generated on verification; sequential per school per year; cannot generate
  without verification; cannot modify after generation; original slip image attached.

## 4. Dashboards

- **Parent:** per-student fee summary (assessed/paid/balance/status/next due), recent submissions +
  statuses, pending actions (clarifications), paid/unpaid breakdown.
- **Finance:** quick stats (pending, overdue >48h, today verified, today/monthly collections),
  verification queue (urgent/standard/bulk), alerts (potential duplicate, amount mismatch),
  reconciliation status (submitted/verified/pending/rejected; expected vs verified totals).
- **Student:** read-only fee status; restrictions flags (exams/results/hostel) per fee status; no
  guardian payment-method detail.

## 5. Reports

daily_collection · student_ledger (statement) · outstanding_balances · verification_performance ·
payment_method_analysis · reconciliation_summary. Filters per report; export PDF/Excel.

## 6. Hostel integration

On verifying a slip containing hostel fees: update boarding financial status; if fully paid enable room
allocation; if partial flag hostel-manager review; if deposit paid record for refund tracking; notify
hostel manager with student/room, amount, outstanding balance, date.

## 7. Validation (enforced)

See `RULES.md` §6. Key invariants: allocation total == total_amount; teller unique per bank per date;
slip image ≤ 5MB image/pdf; verification notes ≥ 10 chars; rejection reason ≥ 20 chars.

## 8. Security & audit

Financial data encrypted at rest; HTTPS; upload scanning; rate limiting on submission; CSRF on web;
ORM-only queries. Every status change logged with user, timestamp, IP. Original values preserved on
update; soft deletes only — **never hard delete** financial records; receipt generation append-only;
verification actions non-repudiable.

---

## 9. §14 completion — "Clarifications for Claude Code" (restores the truncated section)

### What the system DOES
- ✅ Record payment evidence (bank slips, mobile money references, cash deposits) as structured slips.
- ✅ Track each payment through its lifecycle: pending → under_review → verified/approved → receipt issued
  (or clarification_needed / rejected / disputed).
- ✅ Maintain a per-student fee **ledger** with assessed, discounts, paid, and a computed balance.
- ✅ Run a human **verification** workflow (verify / request clarification / reject) with full audit.
- ✅ Generate sequential, immutable **receipts** (PDF + QR) only after verification.
- ✅ Allocate a single payment across multiple fee types, enforcing that allocations sum to the total.
- ✅ Detect likely problems (duplicate teller numbers, amount mismatches) and surface them to finance.
- ✅ Provide dashboards and reports for parents, finance officers, students, and auditors.
- ✅ Integrate boarding-fee status with hostel allocation.
- ✅ Notify the right people (parent, finance, hostel manager) on each lifecycle event, in EN/SW.

### What the system DOES NOT do
- ❌ Process, collect, hold, or disburse money. No payment gateway, no card processing, no wallet.
- ❌ Auto-confirm payments. A human verifies every slip; nothing is "verified" automatically.
- ❌ Initiate refunds (it can *track* refundable deposits, but disbursement happens outside the system).
- ❌ Modify or delete a verified slip or generated receipt. Corrections are new, versioned, audited records.
- ❌ Expose one tenant's, school's, or family's financial data to another.

### Implementation guidance for Claude Code
- Treat the ledger `balance` as derived (generated column) — never write it directly.
- All money in `DECIMAL(15,2)`; format as TZS; compute with decimal/BCMath, never float.
- Wrap verify-and-generate-receipt in a single DB transaction; emit `PaymentSlipVerified` on commit.
- Make submission and receipt generation idempotent (guard against double-submit / double-generate).
- Build the finance module behind the `finance-specialist` subagent; require `security-auditor` sign-off.
