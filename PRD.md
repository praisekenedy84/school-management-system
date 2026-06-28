# PRD.md — School Management System (Product Requirements)

| Item | Value |
|------|-------|
| Version | 1.0 |
| Date | 2026-06-20 |
| Stack | Laravel 11 (PHP 8.2+) + PostgreSQL 16 |
| Companion | `docs/prd-financial-module.md` (Module 7.6 deep spec) |

## 1. Vision & principles

A multi-tenant platform for day and boarding schools that unifies student records, academics,
attendance, assessment, financial recording, hostel operations, communication, and reporting —
tenant-isolated, role-driven, audit-complete, East-Africa-first (TZS, EN+SW).

Principles: *one platform many schools* · *record don't transact* · *audit what matters* ·
*role drives experience* · *offline-tolerant where it counts*.

## 2. Scope

**In (v1):** multi-tenant core, SIS, academics, attendance, assessment/report cards, financial
recording, hostel/boarding, notifications, reporting, parent/student portals.

**Out (v1):** payment *processing*/gateways, payroll/HR, transport GPS, full LMS content delivery,
library catalogue, biometric hardware integration.

## 3. Personas

Parent/guardian, student, teacher, class teacher, academic director, finance manager, accountant,
hostel manager, kitchen staff, storekeeper, school admin, tenant admin, super admin, auditor.
(Capabilities → `RULES.md` §RBAC.)

## 4. Goals & metrics

1. Single source of truth per school. 2. Cut manual fee reconciliation. 3. Real-time parent
visibility. 4. Zero cross-tenant leakage. 5. Complete financial + result audit trail.

Targets: tenant onboarding < 1 working day · ≥90% fees recorded via portal within a term ·
median slip verification < 24h · attendance captured for ≥95% of sessions · 0 isolation incidents ·
class report cards generated < 30s.

## 5. Modules

### 5.1 Tenant & school management
Super admin provisions tenants; tenant admin configures schools (branding, locale EN/SW, currency
TZS, calendar, grading scale, fee terms, hostel availability).
*Done when:* a tenant + first school can be fully configured with no engineering involvement.

### 5.2 Student Information System (SIS)
Admission/enrolment with **day vs boarding** designation; promotion/transfer across classes &
sessions; status lifecycle; guardian linking (many↔many); bulk CSV/Excel import; document attachments.
*Done when:* a student is admitted, classed, guardian-linked, and promoted retaining history.

### 5.3 Academic management
Subjects per school; subject↔class mapping; teacher↔(class, subject, session) assignments;
timetable with clash detection; assignments/homework with optional submission + feedback.
*Done when:* an assigned teacher publishes an assignment visible to that class + guardians.

### 5.4 Attendance
Session/period attendance (present/absent/late/excused); daily + term summaries; absence
notifications; **offline capture** that queues and syncs on reconnect.
*Done when:* a class period is recorded in < 1 min, reflected to student/guardian, alerts sent.

### 5.5 Assessment, grading & report cards
Assessment definitions with weightings; configurable grading scale; teacher mark entry scoped to
assignments; class teacher assembly. **Publishing is gated** by academic-director approval and is
**append-only/audited**; corrections create versioned records. Report card PDFs with letterhead.
Optional fee-status gate (withhold results on balance — configurable, integrates with 5.6).
*Done when:* multi-teacher marks → approved, published, versioned report card PDF.

### 5.6 Financial recording & tracking → see `docs/prd-financial-module.md`
Records externally-made payments as **payment slips** (image evidence + allocation across fee types),
runs verify/clarify/reject workflow, generates sequential **receipts** (PDF + QR), maintains per-student
**fee ledgers** with stored balance, discounts/scholarships, installments, reconciliation, and reports.
Boarding-fee payments sync hostel status (5.7). **Records, never transacts.**

### 5.7 Hostel & boarding management
Hostels → rooms (capacity, gender, type) → allocations (student↔room↔session); boarding fees feed the
ledger; allocation can be gated on hostel-fee status; meal plans; leave/exeat approval.
*Done when:* a boarding student with verified hostel fees can be allocated a room; partial pay flags review.

### 5.8 Communication & notifications
Email + SMS (TZ-network aware) + in-app; templated, locale-aware (EN/SW) for slip lifecycle, receipts,
fee/installment reminders, absence alerts, result publication, announcements; per-user preferences.
*Done when:* each lifecycle event dispatches the right template on the right channel + locale.

### 5.9 Reporting & analytics
Per-module dashboards (finance, academics, attendance, hostel occupancy); PDF/Excel export; scope +
date-range filters; cross-module school-admin overview.
*Done when:* a school admin produces a termly summary spanning enrolment, attendance, results, collections.

### 5.10 Parent & student portals
**Parent:** per-child fees + slip submission/receipts, attendance, results, announcements, pending actions.
**Student:** timetable, assignments, own results, own fee status (read-only, no guardian payment detail).
*Done when:* a multi-child parent switches children, each correctly scoped, no cross-family data.

### 5.11 Stores & kitchen inventory → see `docs/prd-stores-inventory-module.md`
School-store catalog with weighted-average **cost-per-item**; cook **requisitions** (Option A — no
separate usage log) approved and **partially issued** by the storekeeper (stock decreases on each
handover); **purchase requests** to Finance for approve/reject/amend and fulfillment with
requested-vs-actual comparison (stock increases on receipt); **low-stock alerts** to the storekeeper.
*Done when:* a cook's requisition is partially issued across two handovers with correct stock and cost
ledger entries; a purchase request is amended by Finance, fulfilled with different received quantities,
and inventory cost updates; low-stock alert fires after an issue drops qty to reorder level.

## 6. Cross-cutting workflows

- **Session rollover:** archive session, create next, promote students (with hold-backs), carry
  balances + hostel allocations, roll fee structures.
- **Result↔finance gate:** when enabled, report card access checks ledger balance vs configurable threshold.
- **Event side effects:** e.g. `PaymentSlipVerified` → update ledger, generate receipt, notify parent,
  sync hostel; `ResultsPublished` → notify guardians, audit, snapshot version.

## 7. Non-functional requirements

- **Security:** HTTPS; finance data encrypted at rest; uploads virus-scanned; rate limiting on
  submit/auth; ORM-only queries; tenant isolation proven by automated tests.
- **Scale:** 10,000+ students/tenant; 1,000+ daily slip submissions; queues for heavy work.
- **Performance:** queues/lists < 2s; receipt PDF < 5s; class report cards < 30s.
- **Availability:** 99.9% for finance + results; daily backups w/ PITR.
- **Localization:** EN/SW UI + templates; TZS formatting; mobile-first portals.

## 8. Success criteria (platform)

- [ ] Tenant isolation verified (no cross-tenant/school query returns).
- [ ] Full student lifecycle: admission → class → promotion across sessions.
- [ ] Attendance, marks, report cards end-to-end with publishing gated + audited.
- [ ] Finance integrates: ledger updates on verification, receipts generated, hostel synced.
- [ ] RBAC enforced at tenant/school/class/personal for every module.
- [ ] Parent/student portals correctly scoped.
- [ ] Notifications per event, correct channel + locale.
- [ ] NFR targets met under load.

## 9. Open questions

**Resolved (see ARCHITECTURE ADR log):**
1. Tenancy — **`stancl/tenancy` PostgreSQL schema-per-tenant**, subdomain identification (ADR-0001).
2. Frontend — **React SPA + Vite + Material UI**, Sanctum SPA auth (ADR-0002).

**Still open:**
3. SMS provider + whether mobile-money references auto-match (ADR-0007) — needed by Phase 4.
4. Default result-withholding behaviour on outstanding balance.
5. Single vs multi-school per tenant at launch.
