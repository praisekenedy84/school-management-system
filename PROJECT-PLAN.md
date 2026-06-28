# PROJECT-PLAN.md — Phased Roadmap & Task Checklist

Work top to bottom. Check off tasks and keep this in sync with `CHANGELOG.md`. Each phase ends with a
green suite, passing isolation tests, and updated docs.

**Locked stack:** Laravel 11 + PostgreSQL 16 · `stancl/tenancy` schema-per-tenant (subdomain) ·
React + Vite + MUI SPA · Sanctum SPA auth. (ADR-0007 SMS still open — needed by Phase 4.)

---

## Phase 0 — Foundations (Weeks 1–2)

- [x] Bootstrap Laravel 11 + PostgreSQL 16 (SETUP.md). Redis/Horizon **deferred** — not installed in this
      local environment; `cache`/`queue`/`session` use the `database` driver as a stopgap (central AND
      per-tenant tables, since `DatabaseTenancyBootstrapper` repoints the default connection's
      search_path per tenant). Swap to Redis/Horizon when available; no app code should need to change.
- [x] Install & configure **`stancl/tenancy` with `PostgreSQLSchemaManager`**; central tables (tenants, domains).
- [x] Central vs tenant split: `routes/tenant.php` behind `InitializeTenancyBySubdomain` +
      `PreventAccessFromCentralDomains`; tenant migrations under `database/migrations/tenant`.
- [x] Tenant provisioning pipeline (create tenant → create schema → `tenants:migrate` → seed roles) —
      verified manually (`App\Models\Tenant::create()` → schema created + migrated synchronously via
      `TenancyServiceProvider`'s job pipeline; `tenants:seed` runs `RoleAndPermissionSeeder` + `DatabaseSeeder`).
- [x] Core tenant migrations: schools, users, roles, permissions, academic_sessions, classes, students
      (**no `tenant_id`**; `school_id` where school-owned). Also added: streams, password_reset_tokens,
      sessions, personal_access_tokens (Sanctum), cache/jobs (database-driver stopgap).
- [x] RBAC: roles + scoped permissions seeded (RULES §5); `BelongsToSchool` scope + base policies
      (`SchoolPolicy`, `StudentPolicy`, `ClassRoomPolicy` — Phase 0 placeholders, to be tightened once
      real CRUD endpoints land in Phase 1).
- [x] Sanctum SPA auth configured (stateful subdomains, CSRF); `/api/v1/me`. Login rate-limited
      (`throttle:5,1`) and rejects deactivated users.
- [x] **React + Vite + MUI SPA shell**: providers (QueryClient, Theme, Router), auth context, layout,
      `<RequireAuth>` / `<RequirePermission>` guards, axios client with CSRF (FRONTEND.md).
- [x] **Isolation harness**: `tenancy()->initialize()` test helpers (`tests/Concerns/CreatesTenant.php`) +
      passing schema-isolation tests and a `school_id` scope test (27 tests total, all green).
- [ ] CI: `pint` + `php artisan test` on every push — **not set up**; no git repository/remote exists yet
      for this project, so there's no CI host to wire up. Revisit once the repo is pushed somewhere.

**Exit:** a tenant (own schema) + school + user exist; login works on a tenant subdomain (verified both
manually via curl against `php artisan serve` and via the automated suite); cross-tenant data is
physically isolated and campus isolation is tested; SPA shell renders and the auth round-trip works
end-to-end. `./vendor/bin/pint` clean, `php artisan test` green (27 passed).

---

## Phase 1 — SIS & Academics (Weeks 3–4)

- [x] Student admission/enrolment (day vs boarding); lifecycle; promotion/transfer across sessions.
      `StudentAdmissionService` (atomic Student+Enrolment), `PromotionService` (append-only — old
      enrolment flips to `status=promoted`, new row carries the new class/session/residence forward).
- [x] Guardian linking (many↔many). **Documents and bulk CSV/Excel import are deferred** — not part
      of this phase's exit criteria; pick up in a follow-up increment.
- [x] Subjects; subject↔class mapping; teacher↔(class, subject, session) assignments.
- [ ] **Timetable with clash detection — deferred** (not part of the exit criteria below; revisit
      alongside the document-attachment/bulk-import follow-up).
- [x] Assignments/homework with real visibility rules (`AssignmentVisibilityService`: owning teacher,
      school_admin/tenant_admin, and — only once published — guardians of actively-enrolled students).
      **Submission/feedback is schema-only this phase** (`assignment_submissions` table + model exist,
      no controller/endpoint yet — deferred).
- [x] React/MUI features: students list/detail/admission, subjects admin, assignments (list/create/publish).
- [x] Feature + isolation tests per model/endpoint (93 tests passing total).

**Exit:** student admitted → classed → guardian-linked → promoted retaining history (verified via
automated tests — old enrolment row preserved with `status=promoted`, new row created, never updated
in place); teacher publishes an assignment visible to class + guardians, and NOT visible while still a
draft (verified — this was a real bug caught by security review and fixed before sign-off). SPA pages
exist for all of the above. `./vendor/bin/pint` clean, `php artisan test` green (93 passed).

**Security note:** code-reviewer + security-auditor passes caught and fixed 2 critical findings (draft
assignments leaking to guardians; arbitrary users linkable as student guardians with no role/school
check) and 3 cross-school integrity gaps (teacher assignments, student admission, promotion, and
class↔subject mapping all now validate same-school membership, not just record existence) before this
phase was marked done.

---

## Phase 2 — Attendance & Assessment (Weeks 5–6)

- [x] Attendance per (class, period) with idempotent batch capture; per-teacher class-ownership
      enforced. **Absence alerts deferred** — notification engine is Phase 4, not built yet.
      (`AttendanceService`, `tests/Feature/Attendance/AttendanceTest.php`).
- [x] Assessment definitions + weightings. **Configurable grading scale**: `schools.grading_scale`
      (JSONB) already exists from Phase 0 — not re-validated/consumed by report cards this phase, flag
      as a follow-up if grade letters (not just weighted %) are needed on the PDF.
      (`tests/Feature/Assessment/AssessmentTest.php`).
- [x] Mark entry scoped to a teacher's own `TeacherAssignment`; versioned/append-only, structurally
      enforced at the model layer (not just by service discipline).
      (`MarkEntryService`, `tests/Feature/Assessment/MarkEntryTest.php`).
- [x] **Gated publishing** (academic_director/school_admin/tenant_admin only) + versioned/append-only
      results. **Audit log persistence deferred** (no audit-log table/listener exists yet in this
      project — same known gap as Phases 0/1; `ResultsPublished` event fires and is available for a
      future listener to consume). (`ResultPublishingService`, `tests/Feature/Assessment/ResultPublishTest.php`).
- [x] Report card PDF (queued, DomPDF) with weighted score per subject. **Letterhead is placeholder
      text, not real branding assets. Fee-status gate hook is explicitly deferred** (Phase 3 finance
      doesn't exist yet — wire this when it does). (`tests/Feature/Assessment/ReportCardTest.php`);
      fixed a real bug where the job's `queue(): string` method silently prevented it from ever running
      in any environment (see CHANGELOG).
- [x] React/MUI: attendance taker, assessments CRUD + publish, mark-entry grid, report-card
      generation/status page.

**Test status:** 149 tests passing suite-wide, `pint` clean.

**Exit:** multi-teacher marks → approved → published → versioned report card PDF, viewable in the SPA.
Verified via automated tests, including the append-only guarantee at both the service layer (never
mutates a published row) and now the model layer (a direct `update()` call on a published row throws).

**Security note:** security-auditor pass caught and fixed 2 critical findings (cross-school `student_id`
in mark entry and attendance — the same `Rule::exists`-doesn't-constrain-`school_id` bug class Phase 1
already paid for once, regressed on a new field) and 2 important findings (any teacher could overwrite
another teacher's attendance for a class they don't teach; `ResultRecord`'s append-only invariant had no
structural enforcement beyond service-layer discipline) before this phase was marked done. A separate
code-reviewer pass was **not** run this phase (time-boxed) — worth doing before Phase 3 if a dedicated
review session is available.

---

## Phase 3 — Financial Module (Weeks 7–9) — see `docs/prd-financial-module.md`

- [x] Fee structures; payment methods config.
- [x] Student fee ledgers with assessed/discount/paid + stored `balance` + payment_status
      (ledger upsert happens on verification, not as a standalone endpoint).
- [x] Payment slip submission (Recipe D): images, allocation, dup-teller check, `SLP-` numbering, logs.
- [x] Verification workflow (Recipe E): verify/reject; ledger update; `RCP-` receipts + PDF.
      **Clarify path + QR codes deferred** (no QR package installed; `qr_code_path` left null).
- [ ] Discounts/scholarships; installment tracking. **Deferred** — `fee_discounts`/`fee_installments`
      tables not yet created.
- [ ] Reconciliation + finance reports. **Deferred.**
- [x] Wire fee-status gate into report cards (Phase 2 hook) — opt-in per school, defaults OFF.
- [x] React/MUI: parent submit-slip (with allocation editor + file upload) + my-slips view; finance
      verification queue + review drawer; fee-structure/payment-method admin. **A dedicated student
      fee-ledger view is deferred** — no ledger-by-itself endpoint exists yet (ledger state is only
      visible indirectly via a verified slip's effect); add a `GET /api/v1/students/{id}/fee-ledger`
      endpoint in a follow-up.
- [x] End-to-end finance tests: submit → verify → receipt → correct ledger balance
      (`tests/Feature/Finance/PaymentSlipFlowTest.php`, `ReportCardFeeGateTest.php`, 13 tests).

**Exit:** parent submits slip (SPA) → finance verifies → receipt issued → ledger correct; full audit
trail via `PaymentSlipLog`. **Hostel status sync deferred** — the hostel module doesn't exist yet (Phase 4).

**Security note:** a full `security-auditor` pass was started but interrupted (session limit) partway
through Phase 3. A targeted manual spot-check (the recurring cross-school `Rule::exists` bug class that
hit Phases 1 and 2; the `PaymentSlipPolicy::verify()` gate; parent `wards()` scoping) found the
established patterns correctly applied: `SubmitPaymentSlipRequest`/`FeeStructureRequest` both validate
same-school membership via `withoutGlobalScope(SchoolScope::class)`, and `verify`/`reject` are gated
server-side through `PaymentSlipPolicy::verify()` (parent role excluded). **A full audit (file uploads,
sequential-numbering race safety, receipt immutability, idempotency under real concurrency) was not
completed** — recommend running `/security-review` or the `security-auditor` subagent properly before
this module reaches production, especially given it handles money-adjacent data. Manual UAT (real
browser walkthrough of submit→verify→receipt) is intentionally left for a follow-up session.

---

## Phase 4 — Hostel & Communication (Weeks 10–11)

- [x] Hostels → rooms → allocations (session-scoped); gender/capacity constraints enforced in
      `HostelAllocationService` (capacity, gender match, one-active-per-session). `room_type` not
      modeled yet — deferred, not needed for the core allocate flow.
- [x] Boarding fees feed ledger; allocation gated on hostel-fee status — opt-in per school via
      `School.fee_terms->hostel_gate_enabled` (defaults OFF), mirrors the Phase 2/3 report-card gate
      pattern exactly (`HostelAllocationService::hostelGateEnabled()`/`hasOutstandingBalance()`).
      **Partial-pay-flags-review is not built** — the gate is binary (blocked/allowed on balance > 0),
      no separate "flag for review" state.
- [x] Meal plans (per-hostel, optional `meal_plan_id` on an allocation); leave/exeat approval
      (`HostelLeaveService` — parent requests for their own ward via `wards()`, hostel_manager
      approves/rejects, re-deciding an already-decided request is a clean 422). **Hostel-manager
      notifications deferred** — depends on ADR-0007.
- [ ] **Decide ADR-0007 (SMS provider)**; notification engine: email + SMS + in-app; EN/SW templates;
      preferences. **Still open / not built** — product decision, not mine to make.
- [x] React/MUI: hostel rooms/allocations, meal plans, leave approval. `features/hostel/` —
      `HostelsPage`/`HostelRoomsPage`/`HostelAllocationsPage`/`MealPlansPage`/
      `HostelLeaveRequestsPage`, staff CRUD + parent-facing leave-request form and ward-scoped
      allocation view (reuses the now-fixed server-side ward scoping on
      `HostelAllocationController`/`HostelLeaveRequestController`). **Notification preferences UI
      still deferred** — no notification engine exists yet (ADR-0007 still open).

**Exit:** a student can be allocated a room with capacity/gender/fee-status enforced; ending an
allocation preserves history (soft-delete only, RULES.md §3). The full CRUD + leave-approval
workflow is now usable end-to-end via the SPA, not just the API. **Not yet done:** lifecycle
notifications (depends on ADR-0007). A full `security-auditor` pass was not run for this slice —
recommend auditing hostel + finance together before production use.

---

## Phase 5 — Reporting, Portals & Hardening (Weeks 12–13)

- [x] **Head start (pulled forward from Phase 4):** app-shell visual refresh — `AppLayout.tsx` rebuilt as
      a collapsible, section-grouped sidebar (Overview/Academics/Finance) + a quiet topbar with a
      page-title and an avatar menu (replacing the plain logout button), `theme/index.ts` retuned
      (soft neutral background, low-elevation cards, quiet AppBar/Drawer chrome) — inspired by the MUI
      "Minimal Dashboard" template. Chrome only: no new data, no stat cards, no charts — those remain
      full Phase 5 scope below.
- [x] **Cross-module dashboard backend (core slice):** `GET /api/v1/dashboard/summary` (school staff —
      active students, today's attendance, pending/verified-today payment slips, hostel room/capacity
      counts, current academic session) and `GET /api/v1/dashboard/wards` (parent — per-child class,
      fee balance + status, pending slip count via the existing `wards()` relation). Plain aggregation
      queries in a thin controller (no service layer — nothing here mutates state); 3 feature tests
      (role-gating + ward-scoping) passing, `pint` clean.
- [x] **Cross-module dashboard UI:** `DashboardPage.tsx` now renders the staff summary (stat cards:
      active students, today's attendance, pending/verified slips, hostel occupancy, current session)
      or the parent's per-child cards (class, fee balance via the shared `formatMoney`, payment status,
      pending-slip count) depending on `user.roles` — a role with neither (teacher) sees a plain
      landing message, since teachers already land on attendance/mark-entry as their real workflow.
      New `features/dashboard/{api,types}` mirroring the existing feature-folder convention. Verified
      with `tsc --noEmit` (no type errors) and `npm run build` (clean production bundle). **PDF/Excel
      export is still deferred** — this is the on-screen dashboard only, not the reporting/export
      endpoints from PRD §5.9.
- [x] **Demo tenant sample data:** `DemoDataSeeder` (called from `DatabaseSeeder`, gated to the literal
      `demo` tenant id so `preview` and every test tenant are untouched) builds a full walk-through
      dataset across every module — classes/subjects/teachers, students with parents, attendance,
      published results, fee structures + payment slips in pending/verified/rejected states (verified
      ones go through the real verification service, so receipts + ledgers are genuine), hostel
      allocations + a leave request — so the dashboards built above actually have something to show.
      Applied via `tenants:migrate-fresh --tenants=demo` + `tenants:seed --tenants=demo` (confirmed with
      the user first since the fresh-migrate step is destructive to that one schema).
- [x] **Academic Administration UI (RBAC/UI audit gap close).** Four endpoints existed fully
      authorized server-side with zero frontend: classes, academic sessions, class↔subject mapping,
      teacher assignments. Built `features/academics/pages/{ClassesPage,AcademicSessionsPage,
      TeacherAssignmentsPage}.tsx` + a `ClassSubjectsDrawer` component (attach/detach subjects per
      class), mirroring `SubjectsPage.tsx`'s CRUD list + dialog pattern exactly; new
      `api/{useClassRoomMutations,useAcademicSessionMutations,useClassSubjects}.ts` hooks, new
      `ClassRoomRequest`/`AcademicSessionRequest` TS types. Role gating gets it right per-policy:
      Classes mutate-gated to `tenant_admin`/`school_admin`/`academic_director`
      (`ClassRoomPolicy`); Academic Sessions and Teacher Assignments to `tenant_admin`/`school_admin`
      only (`AcademicSessionPolicy`/`TeacherAssignmentPolicy` — deliberately narrower, checked
      against the actual policy files rather than assumed) — all three view-open per their
      `viewAny()`. Nav: Classes added unconditionally (view-open); Academic Sessions/Teacher
      Assignments restricted from the sidebar for non-admin-ish roles (new `ACADEMIC_ADMIN_ROLES`
      in `AppLayout.tsx`, mirroring `FINANCE_STAFF_ROLES`) since a teacher/parent has no real
      workflow use for them even though the API permits viewing.
      One contract gap found and fixed immediately after (not left as a workaround): added
      `GET /api/v1/classes/{classRoom}/subjects` (`ClassSubjectController::index`) +
      `ClassRoomResource` stays as-is, so `ClassSubjectsDrawer` now fetches a class's real
      currently-attached subjects instead of showing an "unknown until you attach one" banner. The
      other gap remains genuinely open: no `/users` or teacher-listing endpoint exists anywhere
      (confirmed against `routes/tenant.php`), so the teacher picker on `TeacherAssignmentsPage` is
      a free-text user-id field — the same gap `GuardianList.tsx` already lives with for guardian
      linking. Verified with `tsc --noEmit` (clean) and `npm run build` (clean production bundle);
      no live walkthrough (no `.env`/DB wired in this environment, consistent with the rest of Phase 5).
- [x] **Parent portal per-child drill-down.** `dashboard/wards` gave the summary card only; clicking a
      ward card now goes to `WardDetailPage.tsx` (`/my-children/:studentId`) with three
      independently-loading sections — payment slips/receipts (`usePaymentSlips({student_id})`,
      already ward-scoped), attendance history (new `useAttendanceForStudent` hook +
      `AttendanceController::index`'s new `student_id` mode), and published results
      (`useResults({student_id})`). Deliberately a new page, not the staff `StudentDetailPage`
      (which exposes guardian-linking/promote controls inappropriate for a parent). Required two
      security fixes first — see CHANGELOG.md Security: `ResultController`/`AttendanceController`
      had no parent ward-ownership scoping at all before this pass.
- [x] **Reporting: Excel/PDF export on every listing + Excel bulk-import with downloadable templates**
      (explicit user request, closes the PDF/Excel-export half of PRD §5.9 the dashboard UI above
      deliberately deferred). New `maatwebsite/excel` (PhpSpreadsheet-based — required enabling PHP's
      `gd` extension, declared explicitly as `ext-gd` in `composer.json` rather than just patched
      locally, so any environment — CI, staging, production — fails fast with a clear message instead
      of silently breaking, per explicit user instruction to avoid a local-only hack).
      - **Export** (`App\Services\Reporting\ExportService` + `App\Exports\GenericListExport` +
        `resources/views/exports/list.blade.php`): one reusable mechanism for every module — a
        controller's `export()` action supplies its rows (same query/scoping as `index()`, unpaginated)
        and a `data_get()` path → heading column map; no per-module Export class needed. Wired into
        18 listing endpoints: Students, Subjects, Classes, Academic Sessions, Teacher Assignments,
        Assignments, Attendance, Assessments, Results, Fee Structures, Payment Methods, Payment Slips,
        Hostels, Hostel Rooms, Hostel Allocations, Meal Plans, Hostel Leave Requests, and the
        Platform Admin Audit Log — every one preserves its `index()`'s exact authorization/ward-scoping
        (parents still only see their own children's records; finance/hostel staff scoping unchanged).
      - **Import** (`App\Imports\{Students,Subjects,Classes}Import` + `App\Support\Import\ImportResult`
        + `App\Services\Reporting\ImportService`): template-download (`GET .../import-template`) then
        upload (`POST .../import`) for the 3 modules where bulk-create is safe and well-defined —
        Students (PRD §5.2's explicitly-flagged "bulk CSV/Excel import", deferred since Phase 1, built
        now; resolves `class`/`academic_session` by name, reuses `StudentAdmissionService` so an
        imported row is admitted exactly like one entered through the form), Subjects, Classes. A bad
        row never aborts the batch — every valid row still imports, with a per-row `{row, message}`
        error reported back. Deliberately did NOT add import for Payment Slips (financial evidence
        needs a real human verification workflow, not bulk-creation — contradicts "record, don't
        transact"), Attendance/Results (date/session-specific, error-prone to genericize), Hostel
        allocations (real-time capacity/gender constraint checking suits one-at-a-time, not bulk), or
        Teacher Assignments/Fee Structures/Academic Sessions (no clear bulk-creation need expressed).
      - **Frontend**: two new shared components — `components/ExportButtons.tsx` ("Excel"/"PDF"
        buttons, blob-download via new `lib/downloadFile.ts`) and `components/ImportDialog.tsx`
        (download-template button, file input, optional "which school" picker for a tenant-wide admin,
        upload, per-row error list) — wired into all 18 export-enabled listing pages and the 3
        import-enabled ones. `api/client.ts`'s response interceptor extended to unwrap a Blob-typed
        error response back into parsed JSON first, so `getErrorMessage()` keeps working transparently
        for blob-download requests without every caller special-casing it.
      - Built backend via `api-builder` (non-finance) + `finance-specialist` (Fee Structures/Payment
        Methods/Payment Slips) running in parallel on disjoint files, then frontend via
        `frontend-builder` — every subagent's output independently re-verified (read the diffs, rather
        than trusting self-reports of `php -l`/`pint`/`tsc`/`npm run build`, since 2 of the 3 had no
        shell access in their sessions and explicitly flagged that in their reports).
      - 16 new backend tests (one export test per new endpoint + the 3 import-flow tests on
        Students/Subjects/Classes, including a tenant-admin-must-choose-a-school case and a
        bad-row-doesn't-abort-the-batch case) — full suite green at 252 tests. `tsc --noEmit` and
        `npm run build` clean.
- [ ] **Student portal — blocked, needs a product decision.** Students do not yet authenticate as
      `User`s (flagged back in Phase 1's `AssignmentVisibilityService`); a real student-facing portal
      needs that decided first. Not building student auth speculatively — out of scope for this pass.
- [ ] Performance pass (queues, caching, indexes, Vite bundle); load test to NFR targets. **Deferred** —
      no caching added to the new dashboard queries; revisit once real load data exists.
- [ ] Security review (security-auditor full pass); UAT; release notes. **Deferred per standing
      instruction** ("leave most of UAT testing for future", "use less tokens") — only a manual
      spot-check of the new dashboard endpoints was done (role check on `summary`, ward-ownership
      scoping on `wards`, no cross-school leakage since both queries run inside the initialized tenant
      schema). A full `security-auditor` pass across Phases 3–5 is still owed before production use.

**Exit:** all PRD §8 success criteria met under load; v1 release tagged.

---

## Phase 6 — Platform Admin & Cross-Tenant Oversight

User-requested: Platform Admin should be able to create tenants, see all activity across every
tenant, and impersonate any user in any role. ADR-0009 (ARCHITECTURE.md §10).

- [x] **Cross-tenant audit log.** Central `audit_logs` table + `App\Models\AuditLog` + a new
      `App\Contracts\AuditableEvent` interface + a single generic `App\Listeners\LogAudit` listener
      registered against that interface (`AppServiceProvider::boot()`). Instrumented every
      state-changing action across every module that didn't already have one: SIS (`StudentAdmitted`,
      `StudentGuardianChanged`, `StudentPromoted`), Academics (`SubjectChanged`, `ClassSubjectChanged`,
      `TeacherAssignmentChanged`, `AssignmentChanged`), Assessment (`AssessmentChanged`, `MarkEntered`,
      `ReportCardGenerated`), Hostel (`HostelChanged`, `HostelRoomChanged`, `HostelAllocationChanged`,
      `MealPlanChanged`, `HostelLeaveRequestChanged`), Finance config (`FeeStructureChanged`,
      `PaymentMethodChanged`) — plus retrofitted the 5 events that already existed
      (`PaymentSlipSubmitted/Verified/Rejected`, `AttendanceRecorded`, `ResultsPublished`) to implement
      the same contract. `LogAudit` runs **synchronously, not queued** (deliberate ADR-0009 deviation —
      no Horizon/Redis locally, audit visibility shouldn't depend on a worker running).
- [x] **Tenant provisioning.** `POST /api/v1/platform/tenants` (`TenantProvisioningService`) creates
      the schema, seeds RBAC, and creates the first school + tenant_admin user — rolls the schema back
      (`$tenant->delete()`) if anything after schema creation fails, rather than leaving a
      half-provisioned tenant reachable. `tenant_id` validated against a tight identifier regex +
      reserved-Postgres-namespace denylist (security-auditor finding) and throttled (`throttle:10,1`).
- [x] **Full read+write impersonation** (confirmed scope with the user — not read-only). Dual-guard
      session design: `Auth::guard('platform')` stays authenticated while `Auth::guard('web')` logs in
      as the target, in the same session — `App\Services\Platform\ImpersonationService`,
      `App\Http\Middleware\EnsurePlatformAdmin` (checks the `platform` guard specifically, immune to
      whatever the `web` guard resolves to mid-impersonation), `AuthController::me()`'s new
      impersonation branch. Throttled (`throttle:20,1`).
- [x] Frontend: `features/platform/` (Tenants page with create-tenant form + impersonation picker,
      Audit Log page with filters), `ImpersonationBanner` ("Return to Platform Admin"), Platform Admin
      gets its own sidebar section + dashboard landing instead of the tenant-scoped nav/summary.
- [x] Tests: `tests/Feature/Platform/{TenantProvisioningTest,ImpersonationTest,AuditLogTest}.php` (10
      tests) — happy path, 403 for non-platform callers, cross-tenant isolation during impersonation.
      Full suite green, `pint` clean, `tsc --noEmit`/`npm run build` clean.
- [x] `security-auditor` + `code-reviewer` passes (mandatory per AGENTS.md — touches tenancy, auth,
      and impersonation). Findings fixed: rate limiting added, `tenant_id` validation tightened,
      provisioning rollback added, `subject_type` no longer leaks a raw FQCN over the API,
      belt-and-suspenders `authorize()` checks added alongside the route middleware.
- [ ] **Not built** (out of scope for this pass, flagged for a follow-up): instrumenting *every*
      remaining write endpoint with its own bespoke event name (the `XChanged` + action-discriminator
      pattern was used instead, to bound the change to ~15 new event classes instead of ~45); a
      platform-level dashboard with cross-tenant aggregate stats (today's landing is just navigation
      links to Tenants/Audit Log); deleting/deactivating a tenant from the UI.

**Exit:** a Platform Admin can create a tenant end-to-end (schema + RBAC + first login), see that
tenant's activity show up in the cross-tenant audit log, and impersonate any user in it with full
read+write access — verified via the automated test suite (not yet manually walked through in a
browser in this environment, consistent with this project's standing frontend-verification note).

---

## Phase 7 — Stores & Kitchen Inventory (Weeks 14–16) — see `docs/prd-stores-inventory-module.md`

**Locked product decisions:** Option A (requisition-only cook workflow), partial issue allowed,
cost-per-item tracked (weighted-average on catalog, frozen per stock movement).

- [x] **7a — Schema & catalog.** Tenant migrations: `inventory_items`, `stock_movements`,
      `store_requisitions` + lines, `purchase_requests` + lines, `purchase_fulfillments` + lines.
      Models, factories, `BelongsToSchool`, policies, RBAC seed (`kitchen_staff`, `storekeeper` +
      `stores.*` permissions). Catalog CRUD + low-stock query. Isolation tests.
- [x] **7b — Requisitions.** `StoreRequisitionService`: submit → approve/reject → issue (partial,
      multi-step) → close line. Stock out + append-only movements in one transaction per issue.
      Events + audit. Feature tests: happy path partial issue, insufficient stock 422, auth failures.
- [x] **7c — Procurement.** `PurchaseRequestService`: submit → finance approve/amend/reject → fulfill.
      Side-by-side requested vs received; weighted-average cost update on stock-in. Fulfillment
      attachments. Finance uses `finance-specialist` subagent patterns.
- [x] **7d — Alerts & dashboard.** `InventoryLowStock` event; in-app alert for storekeeper;
      dashboard summary count (extend `GET /api/v1/dashboard/summary`). Email/SMS deferred (ADR-0007).
- [x] **7e — React/MUI.** `features/stores/`: catalog, low stock, my requisitions, requisition queue
      (partial qty issue UI), purchase requests, procurement queue, fulfillment (side-by-side table),
      stock movements. Nav section + permission guards in `AppLayout`.
- [x] **7f — Reporting & demo.** Excel/PDF export on store listings via `ExportService`; seed demo
      tenant sample items + one requisition + one purchase flow.
- [x] **7h — PRD gaps closed.** Requisition→procurement link (`add-to-purchase`), SKU auto-gen,
      stock valuation API + UI, requisition cancel, `store_requisition_id` on purchase requests.
- [ ] **7g — Review.** `code-reviewer` + `security-auditor` (mandatory — approval workflows, uploads,
      stock concurrency). **Deferred** — implementation complete, review not run this pass.

**Exit:** cook submits requisition → storekeeper partially issues twice → stock and movement ledger
correct; storekeeper purchase request → finance amends → fulfills with different qty/cost → stock and
weighted-average cost update; low-stock alert after issue; full suite green, `pint` clean.

---

## Cross-phase, always-on

- [ ] Keep `CHANGELOG.md` Unreleased current; maintain the ADR log in `ARCHITECTURE.md`.
- [ ] Every new tenant/school-owned model gets an isolation test.
- [ ] `/ship-check` green before any task is marked done.

## Progress log

| Date | Phase | Notes |
|------|-------|-------|
| 2026-06-20 | — | Scaffold created. |
| 2026-06-20 | — | ADR-0001 locked: stancl/tenancy schema-per-tenant. ADR-0002 locked: React+Vite+MUI. ADR-0007 still open. |
| 2026-06-21 | 0 | Phase 0 bootstrap complete: Laravel 11 app, stancl/tenancy (PostgreSQL schema-per-tenant), core tenant migrations/models/policies, RBAC seeding, Sanctum SPA auth, React+Vite+MUI SPA shell, isolation test harness (27 tests passing). Redis/Horizon deferred to a later session (database driver stopgap for cache/queue/session); CI not set up (no git remote yet). |
| 2026-06-21 | 1 | Phase 1 (SIS & Academics) complete: admission/enrolment, guardian linking, promotion (append-only), subjects, class↔subject mapping, teacher assignments, assignments/homework with real visibility rules, React/MUI pages, 93 tests passing. Security review caught and fixed 2 critical (draft-assignment leak to guardians, unvalidated guardian linking) + 3 cross-school integrity findings before sign-off. Deferred to a follow-up: bulk CSV/Excel import, timetable clash detection, student document attachments, assignment submission/grading endpoints. |
| 2026-06-21 | 2 | Phase 2 (Attendance & Assessment) complete: idempotent attendance capture, assessment definitions, versioned/append-only mark entry, gated publishing, queued report-card PDF generation, React/MUI pages, 149 tests passing. Fixed a real bug where the report-card job's `queue()` method silently prevented it from ever running. Security review caught and fixed 2 critical (cross-school `student_id` regressions in mark entry + attendance) + 2 important findings (attendance ownership, append-only structural enforcement) before sign-off. code-reviewer pass skipped this phase (time-boxed) — do before Phase 3. |
| 2026-06-21 | — | **ADR-0008**: replaced subdomain tenant identification with credential-based routing — single domain forever (dev + prod), no per-tenant URLs. New central `tenant_user_directory` (email→tenant, observer-synced) + `platform_admins`/`platform` guard; `InitializeTenancyFromSession` replaces `InitializeTenancyBySubdomain`; sessions pinned to the central `pgsql` connection. Driven by local-preview friction (hosts-file edits per tenant). All 170 tests passing after rewrite. |
| 2026-06-21 | 3 | Phase 3 (Financial Module) backend + frontend complete: fee structures, payment methods, payment slip submission (Recipe D, advisory-lock sequential `SLP-` numbering, allocation-sum BCMath rule, duplicate-teller check), verification workflow (Recipe E, ledger upsert off the generated `balance` column, immutable `RCP-` receipt + DomPDF, idempotent re-verify guard), append-only `PaymentSlipLog` audit trail, opt-in fee-status gate on report cards, React/MUI pages, 163 tests passing. A full `security-auditor` pass was started but interrupted by a session limit — a manual spot-check found the established cross-school/policy-gate patterns correctly applied, but the full audit (uploads, race-safety under real concurrency, receipt immutability) is deferred to a follow-up session before production use. Manual UAT deferred per explicit instruction. |
| 2026-06-21 | 4 | Phase 4 (Hostel) backend complete: hostels → rooms → allocations (capacity/gender/one-active-per-session/fee-status checks), meal plans, leave/exeat approval (parent-requests-for-ward, hostel_manager decides), 13 tests passing. Built directly without subagents to conserve resources, per explicit instruction. Deferred: notifications (ADR-0007 still open — product decision), frontend, full security audit. |
| 2026-06-21 | 5 | Phase 5 head start (visual refresh only, no new data): app shell restyled after the MUI "Minimal Dashboard" template — `AppLayout.tsx` now a collapsible, section-grouped sidebar + topbar with page title and avatar menu; `theme/index.ts` retuned (neutral background, low-elevation cards, quiet AppBar/Drawer). Verified by rendering the shell against a mocked auth context in an isolated temporary Vite preview (no `.env`/DB configured yet in this environment) and screenshotting both the expanded and collapsed sidebar states — no console errors. Stat cards, charts, and real dashboard data remain full Phase 5 scope. |
| 2026-06-22 | 4, 5 | **Full API-to-UI parity pass** (explicit user request to make every role's permitted API actions reachable on the system). An Explore-agent audit enumerated every route/policy/permission/page; scope bounded to closing gaps in *existing* API capability, not building new backend modules. Found and fixed 6 cross-family/cross-school data-leak bugs along the way (parent ward-scoping missing on `StudentController`/`HostelAllocationController`/`HostelLeaveRequestController`/`ResultController`/`AttendanceController` `index()` actions, plus `ReportCardController` inherited the `Student` fix) and 2 permission/policy mismatches (`ClassRoomPolicy`/`SubjectPolicy` rejected `academic_director` despite the permission being seeded) — see CHANGELOG.md Security. Built three frontend passes via `frontend-builder`: the Hostel module (5 pages, previously zero UI despite a fully tested Phase 4 backend), the Academic Administration module (Classes/Academic Sessions/Teacher Assignments/class↔subject mapping — also required adding the missing `ClassRoom`/`AcademicSession` backend CRUD, since only `index` existed), and the parent per-child drill-down (`WardDetailPage`, reached from clickable dashboard ward cards — fees/slips, attendance history, published results, each independently scoped). Every agent's output was independently verified (read the diffs, re-ran `tsc --noEmit`/`npm run build`/`php artisan test` myself rather than trusting self-reports) before being accepted. 205 backend tests passing, `pint` clean. Explicitly NOT built (no API exists for these yet, out of the stated scope): timetable, fee reconciliation/discounts, audit-log persistence, SMS/notifications, student portal auth. |
| 2026-06-22 | 6 | **Phase 6 (Platform Admin & Cross-Tenant Oversight)** — explicit user request: tenant creation, cross-tenant activity visibility, full impersonation. New ADR-0009 (cross-tenant `audit_logs` + generic `AuditableEvent`-contract `LogAudit` listener, synchronous by deliberate deviation from the "queued" convention). Instrumented every previously-uninstrumented state-changing action across SIS/Academics/Assessment/Hostel/Finance-config with a new event (mostly one `XChanged` event per resource with an action discriminator, not one class per CRUD verb, to bound the change), plus retrofitted the contract onto the 5 events that already existed. Built `TenantProvisioningService` (schema + RBAC + first admin, rolls the schema back on partial failure) and `ImpersonationService` (dual-guard session design — `platform` guard stays authenticated while `web` logs in as the target, so `EnsurePlatformAdmin` stays correct even mid-impersonation). Frontend: Tenants page (create + impersonation picker), Audit Log page, an impersonation banner, Platform Admin's own sidebar section/dashboard landing. `security-auditor` + `code-reviewer` passes (mandatory — touches tenancy/auth/impersonation) found and got fixed: missing rate limiting on tenant-creation/impersonation, an overly loose `tenant_id` regex (now denies reserved Postgres namespace names), non-transactional provisioning (now rolls back), a raw FQCN leaking into the audit API response (`subject_type` now uses `class_basename()`), and missing belt-and-suspenders `authorize()` checks alongside the route middleware. Hit the session's usage limit mid-way through a subagent delegation (finance-specialist) — it turned out to have actually completed before the limit message printed, verified by reading its output directly rather than re-running it. 10 new Platform tests + the existing suite stay green throughout (215 tests total), `pint` clean, `tsc --noEmit`/`npm run build` clean. Not built (flagged for follow-up): a platform-level aggregate-stats dashboard (today's landing is just navigation links), tenant deletion/deactivation from the UI, instrumenting the audit log with a bespoke event name per action instead of the `XChanged` pattern. |
| 2026-06-28 | 7 | **Phase 7 spec (Stores & Kitchen Inventory)** — product decisions locked: Option A (requisition-only), partial issue allowed, cost-per-item (weighted-average). Added `docs/prd-stores-inventory-module.md`, PRD §5.11, RBAC entries in RULES.md, Phase 7 checklist in PROJECT-PLAN.md. Implementation not started. |
| 2026-06-28 | 7 | **Phase 7 implementation complete (7a–7f).** Full stores module: catalog + append-only stock movements, cook requisitions with partial multi-step issue, purchase requests through finance approve/amend/fulfill with weighted-average cost, low-stock events + dashboard counts, React/MUI `features/stores/` (8 pages), Excel/PDF export, demo seeder (storekeeper/kitchen_staff + sample items + submitted requisition). 8 new feature tests (`StoreInventoryTest`); full suite 264 passed. `security-auditor`/`code-reviewer` deferred (7g). |
