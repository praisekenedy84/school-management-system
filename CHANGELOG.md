# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Append to **[Unreleased]** as you work. Move entries under a version heading on release.

## [Unreleased]

### Added
- **Issue 21 — Submit slip form layout.** Total Amount is a full-width standalone field above
  Fee Allocation with dividers; mismatch validation only shows after the parent enters a total
  and allocation amounts.
- **Issue 22 — Structured fee type allocation.** Fee Type is a dropdown populated from the
  student's assigned fee structure; server validates fee types and rejects duplicates; per-item
  balances shown in options. Tests: `StudentFeeStatementTest`.
- **Issue 23 — Parent fee statement.** `GET /api/v1/students/{id}/fee-statement` returns
  per-item charged/paid/balance; panel on ward profile and Submit Payment Slip form; ledger
  `fee_details` updated per item on verification.
- **Issue 16 — Meal plan persistence on room allocation.** `meal_plan_id` is saved when
  allocating a boarding student; `PUT /api/v1/hostel-allocations/{id}` updates the plan on
  active allocations; list/export include meal plan; UI dropdown + filter on Hostel Allocations
  page. Tests: `HostelAllocationTest`.
- **Issue 17 — Boarding-only meal plan assignment.** Day students are blocked from hostel room
  allocation (and therefore meal plans); student picker lists boarding students only. Tests:
  `HostelAllocationTest::test_allocate_rejects_day_student`.
- **Issue 11 — Class report card PDF generation.** `POST /api/v1/report-cards/bulk` generates a
  combined class PDF synchronously; `GET .../report-card/download` and
  `GET /api/v1/report-cards/class-download` stream PDFs; Report Cards UI supports whole-class and
  single-student modes with download. Tests: `ReportCardBulkTest`.
- **Issue 12 — Report card fee gate.** Configurable `results_gate_threshold` in school settings;
  parent portal blocks withheld cards with a clear message; class bulk PDF excludes fee-blocked
  students with a warning list; staff retain internal visibility. Tests: extended
  `ReportCardFeeGateTest`.
- **Issue 13 — Payment slip split validation.** Client blocks mismatched/zero allocation lines
  before submit; server rule unchanged (`AllocationSumMatchesTotal`). Tests: zero-line rejection in
  `PaymentSlipFlowTest`.
- **Issue 14 — Mandatory slip rejection reason.** Finance reject drawer shows inline validation;
  server already requires min 20 chars. Test: `reject_without_reason_returns_422`.
- **Issue 15 — Partial requisition fulfilment.** Issue history from stock movements on requisition
  detail; client validates issue qty against remaining balance. Backend partial flow already covered
  by `StoreInventoryTest`.
- **Issue 6 — Teacher picker preloads registered teachers.** `UserSearchSelect` loads all active
  teachers on open (no typing required); inactive users excluded; limit raised to 200. Tests:
  `UserLookupTest`.
- **Issue 7 — Assignment lifecycle.** Draft edit (`PUT /assignments/{id}`), publish, archive
  (`PATCH .../archive`), status filters (draft/published/archived), class/subject/due-date filters,
  and UI actions on Assignments page. Tests: `AssignmentLifecycleTest`.
- **Issue 8 — Attendance reports.** `GET /api/v1/attendance/report` with summary stats (present,
  absent, late, excused, attendance %) and searchable/filterable records; new Attendance Reports page
  at `/attendance/reports`.
- **Issue 9 — School-style assessment categories.** `assessments.category` with presets (CA, weekly/
  monthly tests, mid-term/terminal exams, practical, custom); category picker on Assessments admin UI.
- **Issue 10 — Grading schemes & mark export.** `GradingScaleService` auto-calculates letter grades
  from configurable school bands (`GET/PUT /api/v1/grading-scale`); Mark Entry auto-grades on score
  entry; Excel/PDF export for published marks per assessment. Tests: `GradingScaleServiceTest`.
- **Academic streams (Classes & Students).** Tenant migrations add `streams.is_active`
  and `enrolments.stream_id`. CRUD via `GET/POST/PUT/DELETE /api/v1/classes/{class}/streams`
  (delete deactivates); stream assignment on student admission and promotion;
  `StreamsDrawer` on the Classes page; stream column on the student list and exports.
  Auditable `StreamChanged` event. Tests: `StreamTest`, stream cases in `StudentFilterTest`.
- **Student list search & filters.** `GET /api/v1/students` accepts `search`, `class_id`,
  `stream_id`, `gender`, `residence_type`, `academic_session_id`, and `status` (combined,
  server-side, paginated). Students list UI filter panel with debounced search; export
  respects active filters.
- **Academic terms.** New `academic_terms` table and nested CRUD under
  `/api/v1/academic-sessions/{session}/terms` with overlap validation, session date bounds,
  and single active term per session. `AcademicTermsDrawer` on Academic Sessions page.
  Auditable `AcademicTermChanged` event. Tests: `AcademicTermTest`.
- **Teacher assignment UX.** Teacher picker on Teacher Assignments uses searchable
  `UserSearchSelect` (name + email, not UUID). List filter by teacher added; multi-class/
  multi-subject assignments verified (`TeacherAssignmentTest`).
- **shadcn/ui design system.** Tailwind CSS v4 + shadcn/ui primitives
  (`resources/js/components/ui/`) with a dark navy sidebar, clean content
  area, and Inter typography. Migrated shell (`AppLayout`), login, dashboard,
  and students list to shadcn; MUI retained for remaining module pages during
  gradual migration. Color mode toggles both Tailwind `dark` class and MUI theme. Tenant admins can create custom
  roles, edit permission sets per role (`/admin/roles`), assign roles and
  direct permissions to users, and customize the sidebar menu (`/admin/navigation`
  — labels, icons, visibility, permission gates). Platform admins get a
  platform menu editor at `/platform/navigation`. Navigation is DB-backed
  (`navigation_sections` / `navigation_items` per tenant; central platform
  tables) with defaults seeded from `config/navigation-defaults.php`. New
  permissions: `rbac.manage_roles`, `tenant.manage_navigation`. API:
  `/api/v1/navigation`, `/admin/role-definitions`, `/admin/navigation/*`,
  `/platform/navigation/*`. Tests: `RbacCustomizationTest`.
- **Tenant & platform administration UI.** Tenant admins can manage schools
  (`/admin/schools`), per-school settings/branding/billing (`/admin/settings`),
  and user role assignment (`/admin/users`) via new `/api/v1/admin/*` endpoints
  gated by `tenant.manage_*` and `users.manage_roles` permissions. Platform
  admins get `/platform/settings` for global operator config (singleton
  `platform_settings` central table). Policies tightened: `school_admin` can
  assign roles within their school but cannot create schools or grant
  `tenant_admin`. Auditable events: `SchoolChanged`, `UserRolesChanged`,
  `PlatformSettingsChanged`. Tests: `TenantAdminTest`, `PlatformSettingsTest`;
  updated `SchoolPolicyTest`.
- **Stores & kitchen inventory module (Phase 7).** Full implementation per
  `docs/prd-stores-inventory-module.md`: inventory catalog with weighted-average
  unit cost; cook **requisitions** (Option A) with **partial multi-step issue**;
  append-only **stock movement** ledger; **purchase requests** to Finance
  (approve/amend/reject/fulfill with requested-vs-received comparison); low-stock
  `InventoryLowStock` event; dashboard `stores` summary counts. New roles:
  `kitchen_staff`, `storekeeper`. Backend: `App\Services\Stores\*`, controllers
  under `Api/Stores`, 11 auditable events. Frontend: `features/stores/` (8 pages,
  Stores nav section). Demo seeder adds sample items + submitted requisition.
  Tests: `tests/Feature/Stores/StoreInventoryTest.php` (8 tests). Run
  `php artisan tenants:migrate` on existing tenants to apply new tables.
- **Stores PRD completion — requisition→procurement, SKU auto-gen, valuation, cancel.**
  Storekeeper can add requisition lines to a draft purchase list (`POST
  .../add-to-purchase` with `shortfall` or `all` mode). SKU auto-generated as
  `SKU-YYYYMMDD-NNNN` when left blank. Stock valuation endpoint + catalog UI
  chip. Kitchen staff can cancel draft/submitted requisitions. Migration
  `2026_06_28_000006` links purchase requests to source requisitions. 11 store
  tests passing.
- **Emphasized totals on accounting-related lists.** API resources now expose
  line and header totals (`estimated_total`, `effective_total`, `line_value`,
  `restock_value`, etc.) computed server-side with BCMath. Shared
  `AccountingListTotal` / `EmphasizedMoney` components applied across stores
  purchase requests, requisitions, fulfillment, inventory catalog, low stock,
  stock movements, and finance slip lists/review drawer.
- **Searchable entity pickers — replace manual UUID entry across admin forms.**
  New shared `SearchableSelect` (MUI Autocomplete) for classes, academic sessions,
  subjects, and teaching assignments; `UserSearchSelect` with debounced server search
  for teachers and guardians. Backend: `GET /api/v1/users?role=teacher|parent&search=`
  with school scoping and role-gated authorization (`UserPolicy::lookup`). Wired into
  student admission, promotion, guardian linking, teacher assignments, new assignment,
  fee structures, and attendance forms.
- **Reporting: Excel/PDF export on every listing page + Excel bulk-import with downloadable
  templates** (explicit user request — closes the PDF/Excel-export half of PRD §5.9 that the
  dashboard UI deliberately deferred earlier). See PROJECT-PLAN.md Phase 5 for the full breakdown.
  Headline pieces:
  - New `maatwebsite/excel` dependency (PhpSpreadsheet-based, Laravel-11-compatible — the first
    `composer require` attempt silently resolved to the ancient, abandoned PHPExcel-based v1.1.5;
    caught it via `composer.lock` inspection and re-pinned to `^3.1`). That version requires PHP's
    `gd` extension, which wasn't enabled in this XAMPP install. Per explicit instruction to make this
    "simple and effective even in production... long term" rather than a local-only hack: enabled
    `gd` locally AND added `"ext-gd": "*"` to `composer.json`'s `require`, so `composer install` fails
    fast with a clear message on any environment (CI, staging, production, another dev's machine)
    missing it, instead of the dependency silently breaking only at runtime.
  - `App\Services\Reporting\ExportService` + `App\Exports\GenericListExport` +
    `resources/views/exports/list.blade.php`: one reusable export mechanism for the whole app — a
    controller's `export()` action supplies its rows (same query/scoping as `index()`, just
    unpaginated) and a `data_get()` path → heading map; no per-module Export class needed. Wired into
    18 listing endpoints (Students, Subjects, Classes, Academic Sessions, Teacher Assignments,
    Assignments, Attendance, Assessments, Results, Fee Structures, Payment Methods, Payment Slips,
    Hostels, Hostel Rooms, Hostel Allocations, Meal Plans, Hostel Leave Requests, Platform Audit Log)
    — every one preserves its `index()`'s exact authorization/ward-scoping rules (verified by reading
    every diff myself: parents still only see their own children's records across Attendance,
    Results, Hostel Allocations, Hostel Leave Requests, and Payment Slips).
  - `App\Imports\{Students,Subjects,Classes}Import` + `App\Support\Import\ImportResult` +
    `App\Services\Reporting\ImportService`: template-download then upload, for the 3 modules where
    bulk-create is safe and well-defined. Students resolves `class`/`academic_session` columns by
    name (not UUID) and reuses `StudentAdmissionService`, so an imported row is admitted exactly like
    one entered through the form — this is PRD §5.2's "bulk CSV/Excel import", flagged deferred back
    in Phase 1, built now. A bad row never aborts the batch: every valid row still imports, with a
    `{row, message}` error reported per failure. New `App\Http\Controllers\Concerns\
    ResolvesImportSchoolId` trait (a tenant-wide admin must pick which school a batch imports into,
    same rule already established for create forms). Deliberately did NOT add import for Payment
    Slips (financial evidence needs a human verification workflow, not bulk-creation — "record, don't
    transact"), Attendance/Results (too date/session-specific to genericize safely), Hostel
    allocations (real-time capacity/gender checks suit one-at-a-time), or Teacher
    Assignments/Fee Structures/Academic Sessions (no expressed bulk-creation need).
  - Frontend: `components/ExportButtons.tsx` + `components/ImportDialog.tsx` (new shared,
    reusable components — not per-page bespoke code) + `lib/downloadFile.ts`, wired into all 18
    export-enabled pages and the 3 import-enabled ones. `api/client.ts`'s 401 interceptor extended to
    also unwrap a Blob-typed error response back into parsed JSON before `getErrorMessage()` runs —
    needed because `responseType: 'blob'` requests get Blob error bodies too, and without this every
    export/import error would have shown as an unreadable raw blob instead of the server's friendly
    message.
  - Built via 3 parallel/sequential subagent passes (`api-builder` for non-finance controllers,
    `finance-specialist` for Fee Structures/Payment Methods/Payment Slips, `frontend-builder` for
    every page) on top of a core backbone built directly — every pass independently re-verified by
    reading the actual diffs and re-running `php -l`/`pint`/the full test suite/`tsc --noEmit`/
    `npm run build` myself, since 2 of the 3 subagent sessions had no shell access and explicitly
    flagged that rather than fabricating a "tests passed" claim.
  - 16 new backend tests (one `export` test per new endpoint, plus 3 import-flow tests covering a
    successful import, a tenant-admin-must-choose-a-school 422, and a bad-row-doesn't-abort-the-rest
    case). Full suite green at 252 tests (up from 219); `tsc --noEmit` and `npm run build` clean.
- **Session expiry UX: idle-timeout warning + reason-on-redirect** (explicit user request). Laravel's
  session lifetime (`config/session.php`, 120 min) is a sliding window — any authenticated request
  resets it — so idle detection is the meaningful signal, not a separate "absolute time" clock; one
  unified mechanism covers both ways a session lapses.
  - **New `IdleSessionGuard`** (`resources/js/app/IdleSessionGuard.tsx`, mounted once in `App.tsx`
    alongside the router): tracks real activity (`mousemove`/`mousedown`/`keydown`/`touchstart`/
    `scroll`, throttled). After 20 minutes of silence it shows a "Still there?" dialog with a 60-second
    countdown and two choices — **Stay signed in** (pings `GET /me`, which resets Laravel's session
    clock like any authenticated request would) or **Sign out now**. No response within the countdown
    signs the user out automatically. Ambient activity is deliberately ignored once the dialog is up —
    only its own buttons resolve it — so a stray mouse twitch over a backgrounded tab can't silently
    dismiss a prompt the user never saw. Renders nothing and attaches no listeners while logged out.
  - **Reason-on-redirect**: new `lib/authRedirectReason.ts` (a one-shot `sessionStorage` message,
    since a React state value wouldn't survive `<RequireAuth>` unmounting the authenticated tree).
    `api/client.ts`'s existing 401 interceptor now sets it — but only when the cached `/me` user was
    previously truthy (a *real* expiry), never on the app's very first unauthenticated `/me` probe,
    which would otherwise wrongly claim "your session expired" to a visitor who was never logged in.
    `IdleSessionGuard`'s auto-sign-out sets the same reason ("inactive for too long"). `LoginPage.tsx`
    reads and clears it once on mount, showing it as an info `<Alert>` above the form — distinct from
    `serverError`, which is reserved for an actual failed login attempt. A 401 hit on any other page no
    longer needs special handling: `<RequireAuth>` already swaps to `<Navigate>` the moment the cached
    user clears, so an inline error on the page the user was on never gets a chance to paint.
  - Verified with `tsc --noEmit` (clean) and `npm run build` (clean production bundle) — no backend
    changes, so the existing 219-test suite is unaffected.
- **Phase 6: Platform Admin — tenant creation, cross-tenant audit log, full impersonation** (explicit
  user request: Platform Admin should "oversee everything", "view all activities done by anyone", and
  "create new tenants" and "view as anyone in any role"). New ADR-0009 (ARCHITECTURE.md §10).
  - **Cross-tenant audit log.** Central `audit_logs` table (append-only — `id`, `tenant_id` *string,
    nullable, no FK* since `tenants.id` is a plain slug and platform-level actions have no tenant,
    `actor_type`/`actor_id`/`actor_name`/`actor_email` denormalized, `action`, `subject_type`/
    `subject_id` denormalized, `changes` jsonb, `ip_address`, `created_at` only — no `updated_at`/
    `deleted_at`). New `App\Models\AuditLog` (central connection, mirrors `PlatformAdmin`'s
    `getConnectionName()` override) and `App\Contracts\AuditableEvent` (`toAuditLog(): array`). New
    `App\Listeners\LogAudit`, registered **once** against the interface
    (`AppServiceProvider::boot()`: `Event::listen(AuditableEvent::class, LogAudit::class)`), not once
    per event class. Runs **synchronously, not queued** — deliberate deviation from ARCHITECTURE §5's
    convention, since this project has no Horizon/Redis running locally yet and an audit trail that
    silently never appears because a queue worker isn't running would defeat the entire point of
    cross-tenant oversight.
  - **Instrumented every previously-uninstrumented state-changing action.** New events: SIS
    (`StudentAdmitted`, `StudentGuardianChanged`, `StudentPromoted`), Academics (`SubjectChanged`,
    `ClassSubjectChanged`, `TeacherAssignmentChanged`, `AssignmentChanged`), Assessment
    (`AssessmentChanged`, `MarkEntered`, `ReportCardGenerated`), Hostel (`HostelChanged`,
    `HostelRoomChanged`, `HostelAllocationChanged`, `MealPlanChanged`, `HostelLeaveRequestChanged`),
    Finance config (`FeeStructureChanged`, `PaymentMethodChanged`). Most are one event per *resource*
    with an `action: created|updated|deleted`-style discriminator rather than one class per CRUD verb
    (e.g. `SubjectChanged($subject, 'created', $actor)`), to bound this to ~15 new classes instead of
    ~45 — a deliberate tradeoff against RULES §4's usual specific-past-tense event naming. Retrofitted
    the contract onto the 5 events that already existed and fired
    (`PaymentSlipSubmitted`/`Verified`/`Rejected`, `AttendanceRecorded`, `ResultsPublished`) — these
    previously had zero listeners despite firing (a gap repeatedly noted in earlier CHANGELOG entries).
  - **Tenant provisioning.** `POST /api/v1/platform/tenants` (`App\Services\Platform\
    TenantProvisioningService`): creates the schema (`Tenant::create()` → stancl's synchronous
    `CreateDatabase`+`MigrateDatabase` pipeline), seeds RBAC (`RoleAndPermissionSeeder` run directly,
    inside the now-initialized tenant), creates the first `School` + `tenant_admin` `User` from
    admin-supplied fields (no email/SMS engine exists yet — ADR-0007 still open — so the initial admin
    sets their own password in the create-tenant form rather than receiving a generated one). If
    anything after schema creation fails, the schema is dropped again (`$tenant->delete()`) rather than
    left half-provisioned and reachable. `tenant_id` validated against a tight identifier regex (no
    leading/trailing hyphen) plus a reserved-Postgres-namespace denylist, and the endpoint is
    throttled (`throttle:10,1` — security-auditor findings, both fixed before sign-off).
  - **Full read+write impersonation** (confirmed scope with the user — not a read-only "view as"; every
    write while impersonating stays attributable via the audit log). Dual-guard session design
    (`App\Services\Platform\ImpersonationService`): the Platform Admin stays authenticated on the
    `platform` guard while the impersonated user logs in on `web`, in the same session — mirrors
    `AuthController::login`'s existing pattern of not calling `tenancy()->end()` after success, since
    `InitializeTenancyFromSession` re-initializes from the session on every subsequent request anyway.
    `App\Http\Middleware\EnsurePlatformAdmin` gates every `/api/v1/platform/*` route by checking the
    `platform` guard *specifically* (not generic `auth:sanctum`, which would also pass a `web`-guard
    tenant user) — this stays correct even mid-impersonation, when `web` also has a user logged in.
    `AuthController::me()` gained a new first branch: if `web` is authenticated and
    `session('impersonation')` is set, return the impersonated identity (via Laravel Resources'
    `->additional()`, a sibling `impersonation` key) instead of the admin's own. Throttled
    (`throttle:20,1`).
  - **Frontend** (`resources/js/features/platform/`): `TenantsPage` (list/create tenants + an
    impersonation picker per tenant — fetches that tenant's users via a new
    `GET /platform/tenants/{tenant}/users`), `AuditLogPage` (filterable by tenant/action/date range),
    `ImpersonationBanner` ("Viewing as X — impersonated by Y — Return to Platform Admin", rendered in
    `AppLayout`). `AppLayout`'s nav now shows a Platform Admin a dedicated "Platform" section instead of
    the tenant-scoped sections (which would just 404 for a central, non-tenant-scoped account);
    `DashboardPage` gives Platform Admin its own landing instead of the generic "use the sidebar"
    message. `types/user.ts`'s `User` gained optional `type`/`impersonation` fields; `useAuth.ts` gained
    a `mergeImpersonation()` helper (the backend's `impersonation` key is a sibling of `data`, not
    nested in it) shared with the new impersonation start/stop mutations.
  - **Tests**: `tests/Feature/Platform/{TenantProvisioningTest,ImpersonationTest,AuditLogTest}.php` (10
    tests — happy path, 403 for non-platform callers on every endpoint, cross-tenant isolation during
    impersonation, audit rows correctly tagged). `security-auditor` + `code-reviewer` passes (mandatory
    per AGENTS.md — touches tenancy, auth, and impersonation) caught and fixed: missing rate limiting
    (above), the loose `tenant_id` regex (above), non-transactional provisioning (above), `AuditLogResource`
    leaking a raw `App\Models\X` FQCN as `subject_type` over the API (now `class_basename()`'d), and
    missing belt-and-suspenders `authorize()` checks in the two new FormRequests alongside the route
    middleware (both now also check `Auth::guard('platform')->check()`). Full suite green throughout
    (`pint` clean, `tsc --noEmit`/`npm run build` clean).
  - **Not built** (flagged for a follow-up, out of scope for this pass): a platform-level dashboard with
    cross-tenant aggregate stats (today's Platform Admin landing is navigation links only); tenant
    deletion/deactivation from the UI (the model supports it — `Tenant::delete()` drops the schema —
    just no endpoint/page); a bespoke past-tense event name per action instead of the `XChanged`+action
    pattern used here.
- **Full API-to-UI parity audit, then build-out for every role** (explicit user request: "make
  everything that was supposed to be done by API be able to be done on the system... for every
  role"). An Explore-agent audit enumerated every API route, policy, seeded permission, and
  frontend page to find where a role's permission/endpoint had no UI entry point, or where the UI
  and the policy disagreed. Scope was deliberately bounded to *existing* API capability — not
  inventing new backend modules (reconciliation, discounts, audit-log persistence, SMS, student
  auth all remain deferred per PROJECT-PLAN.md). Delivered in three frontend-builder passes plus
  direct backend fixes:
  - **Hostel frontend module** (`resources/js/features/hostel/`) — the entire Phase 4 backend
    (hostels, rooms, allocations, meal plans, leave requests; 19 tests) had zero UI. New pages
    `HostelsPage`, `HostelRoomsPage`, `HostelAllocationsPage`, `MealPlansPage`,
    `HostelLeaveRequestsPage` (staff queue + parent request-leave form, status badges
    pending/approved/rejected), full `api/`/`types/` per the feature-folder convention, new
    `RequireHostelStaff`/`HOSTEL_STAFF_ROLES` guard mirroring `RequireFinanceStaff`. Allocations
    and Leave Requests are visible to `parent` too (already ward-scoped server-side); Hostels/Rooms/
    Meal Plans are staff-only in the nav. Contract gaps found and worked around without changing
    the API: no student-search/typeahead endpoint (plain dropdown via `useStudents(1,200)`); no
    denormalized student/room names on `HostelAllocationResource`/`HostelLeaveRequestResource`
    (cross-referenced client-side from already-fetched lists).
  - **Parent per-child drill-down** (PRD §5.10) — dashboard ward cards previously went nowhere and
    `MySlipsPage` mixed every child's slips in one flat list. New `WardDetailPage.tsx`
    (`/my-children/:studentId`, reached only by clicking a dashboard ward card) with three
    independently-loading sections: fees/payment slips (`usePaymentSlips({student_id})`),
    attendance history (new `useAttendanceForStudent` hook), and published results
    (`useResults({student_id})`). Deliberately a new page, not a reuse of the staff
    `StudentDetailPage` (which exposes guardian-linking/promote controls).
  - **Academic Administration UI** (closes an RBAC/UI audit gap — four admin-facing API endpoints
    existed fully authorized server-side with no frontend at all). New pages under
    `resources/js/features/academics/pages/`: `ClassesPage.tsx` (CRUD, `/classes`),
    `AcademicSessionsPage.tsx` (CRUD + a "Current" chip, `/academic-sessions`),
    `TeacherAssignmentsPage.tsx` (filterable list + create form, `/teacher-assignments`), and a
    `ClassSubjectsDrawer` component (attach/detach subjects per class) wired into `ClassesPage`.
    New `api/{useClassRoomMutations,useAcademicSessionMutations,useClassSubjects}.ts` hooks
    mirroring `useSubjects.ts`'s create/update/delete pattern; new
    `ClassRoomRequest`/`AcademicSessionRequest` TS types in `types/academic.ts`. Role gating
    checked against each policy file rather than assumed: Classes mutate-gated to `tenant_admin`/
    `school_admin`/`academic_director` (`ClassRoomPolicy`); Academic Sessions and Teacher
    Assignments to `tenant_admin`/`school_admin` only (`AcademicSessionPolicy`/
    `TeacherAssignmentPolicy` — narrower than Classes, no `academic_director`) — all three remain
    view-open per their `viewAny()`. New `ACADEMIC_ADMIN_ROLES` constant in `AppLayout.tsx`
    (mirrors `FINANCE_STAFF_ROLES`) keeps Academic Sessions/Teacher Assignments out of a teacher's
    or parent's sidebar, even though the API permits them viewing it — UX only, per RULES §8.
    One contract gap remains genuinely unfixed: no `/users`/teacher-listing endpoint exists
    anywhere (checked `routes/tenant.php`), so `TeacherAssignmentsPage`'s teacher picker is a
    free-text user-id field — the same gap `GuardianList.tsx` already lives with for guardian
    linking.
  - **Backend: `ClassRoom`/`AcademicSession` CRUD** (`ClassRoomController`/`AcademicSessionController`)
    — both were read-only lookups (`index` only) despite their policies already defining
    create/update/delete abilities. Added `store`/`show`/`update`/`destroy` + `ClassRoomRequest`/
    `AcademicSessionRequest` FormRequests + `ClassRoomChanged`/`AcademicSessionChanged` events,
    mirroring `SubjectController`'s shape exactly. `AcademicSessionController` additionally demotes
    any other `is_current` session in the same school inside a transaction when one is marked
    current (`DashboardController` and report-card generation both assume exactly one current
    session per school — nothing previously enforced that). New `GET /classes/{classRoom}/subjects`
    (`ClassSubjectController::index`) closes the contract gap the academics-frontend pass flagged —
    `ClassSubjectsDrawer` now fetches real state instead of showing an "unknown until you attach
    one" banner. 8 new feature tests (`ClassRoomTest`, `AcademicSessionTest`) plus a new
    `ClassSubjectTest::test_index_lists_attached_subjects`.
- **Consistent, user-friendly error handling, backend + frontend.** Audited the current state first
  (no custom exception handler existed; 16+ frontend files duplicated an ad-hoc
  `error?.response?.data?.message ?? '...'` pattern with no shared helper) before changing anything.
  - **Backend** (`bootstrap/app.php`'s `withExceptions`): every `/api/*` error response is now
    friendly JSON regardless of `APP_DEBUG`. `ModelNotFoundException`/unknown routes (both arrive as
    `NotFoundHttpException` — Laravel's own `prepareException()` converts the former before any render
    callback runs) no longer leak the Eloquent class name + literal id ("No query results for model
    [App\Models\Student] *uuid*"); a denied Policy/Gate check returns "You do not have permission to
    perform this action."; an expired/missing session returns "Your session has expired. Please log in
    again."; a CSRF mismatch (419) gets its own plain-English message; and a catch-all safety net turns
    any genuinely unexpected exception (a bug, a third-party failure) into a generic 500 message instead
    of ever echoing its message/class/file/trace to the client. `ValidationException` (422) is
    deliberately untouched — its `{message, errors}` shape was already correct and every frontend page
    depends on the `errors` map. Also fixed `DashboardController::summary()`'s
    `ValidationException::withMessages([...])->status(403)` hack (mixed the 422 errors-object shape into
    a 403) to a plain `abort(403, '...')`. New `tests/Feature/ErrorHandlingTest.php` (3 tests: friendly
    404 on a missing model + an unknown route, friendly 403 on denied dashboard access) — full suite
    still green at 186 tests.
  - **Frontend**: new `lib/getErrorMessage.ts` (`getErrorMessage`/`getFieldErrors`) is now the *only*
    sanctioned way to read an API error — extracts the backend's friendly `message`, falls back to "Unable
    to reach the server…" when there's no response at all (network failure), and never displays a raw
    JS/axios property. Retrofitted all 14 files that had the old ad-hoc pattern (`SubmitSlipPage`,
    `StudentAdmissionPage`, `AttendanceTakerPage`, `AssessmentsPage`, `MarkEntryPage`, `SubjectsPage`,
    `SlipReviewDrawer`, `GuardianList`, `FeeStructuresPage`, `PaymentMethodsPage`,
    `PromoteEnrolmentForm`, `ReportCardPage`, `NewAssignmentForm`, `LoginPage`) plus `DashboardPage.tsx`,
    which previously ignored the real server error and always rendered a hardcoded string. Also added a
    response interceptor in `api/client.ts`: any 401 now clears the cached `/me` user
    (`api/queryClient.ts`'s new `AUTH_ME_QUERY_KEY`, shared with `features/auth/api/useAuth.ts` rather
    than duplicated), so a session that expires mid-use flips `isAuthenticated` false and
    `<RequireAuth>` redirects to `/login` on its own — previously a live 401 just fell into whatever ad
    hoc catch block the current page happened to have. `queryClient` itself moved out of `App.tsx` into
    `api/queryClient.ts` so `client.ts` can reach it without a circular import. Verified with
    `tsc --noEmit` (clean) and `npm run build` (clean production bundle).
- **`PlatformAdminSeeder`** (`database/seeders/PlatformAdminSeeder.php`) — seeds a local-dev Platform
  Admin (`platform-admin@sms.test` / `password`) on the **central** connection. Deliberately separate
  from the per-tenant `DatabaseSeeder` (which assumes an initialized tenant) since `PlatformAdmin` is
  central-only (ADR-0008) — run via `php artisan db:seed --class=PlatformAdminSeeder`, never through
  `tenants:seed`. Idempotent (`firstOrCreate` by email).

### Fixed
- **A second, more fundamental source of `relation "users" does not exist`, found live after the fix
  above ("I'm getting a lot of 500 Server Error after performing any action").** `DatabaseSessionHandler::
  write()` (`SESSION_DRIVER=database`) *unconditionally* resolves `Auth::guard()->user()` to stamp
  `sessions.user_id` on every session save, on **every** request — not just authenticated ones, and not
  only `/api/v1/*`. The earlier `InitializeTenancyBeforeAuthenticatingSession` fix only covered requests
  that pass through Sanctum's `EnsureFrontendRequestsAreStateful` (the `/api/v1/*` group's own hardcoded
  nested sub-pipeline). `/sanctum/csrf-cookie` and the SPA catch-all `/{any?}` (`routes/tenant.php`) use
  the plain `'web'` middleware group directly, with no tenancy-aware middleware anywhere in that path —
  so after a real login, the very next CSRF-cookie refresh or full page load/refresh hit the central
  schema's missing `users` table, regardless of which page or action the user was on. Fixed by appending
  `InitializeTenancyFromSession` to the `'web'` middleware group itself (`bootstrap/app.php`, after
  `StartSession` — confirmed via reflection that it lands last in the resolved group), which covers
  *every* request using that group, not just the ones inside `routes/tenant.php`'s explicit middleware
  array. New regression test (`LoginTest::test_session_survives_a_second_web_group_request_after_login`)
  does a real login, then replays the session cookie against both routes — this is the one case that
  needed a genuine `Auth::login()`-backed session (`actingAs()` alone never populates the session's own
  stored user id, so it can't reproduce this class of bug).
- **The same `school_id` NOT NULL gap as the Subject/Class/Session fix below, found in four more
  FormRequests once the pattern was checked systemically** (`HostelRequest`, `PaymentMethodRequest` —
  top-level, same fix: explicit `school_id`, required only for a tenant-wide admin; `MealPlanRequest`,
  `HostelRoomRequest`, `TeacherAssignmentRequest`, `FeeStructureRequest` — each already takes a parent id
  (`hostel_id`/`class_id`) that's already validated same-school, so `school_id` is now derived from that
  parent directly via `prepareForValidation()` instead, needing no picker UI and working for every role,
  not just tenant_admin). 8 new tests (`HostelCrudTest`, `PaymentMethodCrudTest`, 2 added to
  `HostelLeaveAndMealPlanTest`) plus the existing Academic/Finance/Hostel suites confirm no regression.
- **`SQLSTATE[23502]: null value in column "school_id"` when a tenant_admin creates a Subject/Class/
  Academic Session.** Found live (the same `admin@demo.sms.test` session as the bug above, this time
  on `POST /api/v1/subjects`). `SubjectRequest`/`ClassRoomRequest`/`AcademicSessionRequest` never
  accepted a `school_id` field at all — they relied entirely on `BelongsToSchool`'s `creating` hook,
  which stamps `school_id` from `auth()->user()->school_id` and silently no-ops when that's `null`
  (exactly the case for `tenant_admin`, who is tenant-wide by design — see `DatabaseSeeder`). A
  `school_admin` never hit this since their own `school_id` always stamps correctly; a `tenant_admin`
  hit it on every create. Fixed in all three FormRequests: `school_id` is now `required` only when the
  acting user has none of their own (a `tenant_admin` must say which school), validated against
  `schools.id`, and a `prepareForValidation()` hook forces it to the user's own `school_id` whenever
  they have one — closing a privilege-escalation gap (a `school_admin` could otherwise have claimed a
  different school for a new record) now that the field is accepted at all. `school_id` is dropped
  from the rules entirely on update — it's fixed once a record exists. New
  `GET /api/v1/schools` (`SchoolController`, `SchoolResource` — id/name/code only) feeds a "which
  school" `<Select>` shown on the Subjects/Classes/Academic Sessions create dialogs, but only when
  `user.school_id` is `null`; a `school_admin` keeps the existing picker-free form since their school
  is implicit. 3 new backend tests (`SubjectTest`: tenant_admin requires `school_id`, tenant_admin can
  create for a chosen school, school_admin's submitted `school_id` is ignored/overridden) + 1 new
  (`SchoolLookupTest`); `tsc --noEmit` and `npm run build` clean.
- **`SQLSTATE[42P01]: relation "users" does not exist` on real browser sessions** (found live, not by a
  test — the user hit it directly viewing/adding data through the SPA). Root cause was two-layered:
  (1) `config('sanctum.guard') = ['web', 'platform']` meant `auth:sanctum` accepted a bare Platform
  Admin session (no `web` auth, no `tenant_id` in session) on tenant-resource routes via Sanctum's
  multi-guard fallback (`Laravel\Sanctum\Guard::__invoke()` tries each guard in order) — fixed by
  splitting `routes/tenant.php`'s protected group so tenant-resource routes require `auth:web`
  specifically, while `/logout`/`/me` keep `auth:sanctum` (they're designed to serve both identities).
  (2) Deeper and still present after that fix: `EnsureFrontendRequestsAreStateful` (Sanctum, added by
  `statefulApi()`) builds its own nested sub-pipeline (`EncryptCookies`, `StartSession`,
  `AuthenticateSession`, …) and runs it to completion *before* calling onward to any route-level
  middleware — so `AuthenticateSession` resolved `$request->user()` (querying the tenant's `users`
  table to check for a stale session) *before* `InitializeTenancyFromSession` ever ran, every time,
  for every authenticated tenant session. This ordering is hardcoded inside Sanctum's middleware class
  itself and is NOT fixable via Laravel's middleware-priority list (confirmed by reading
  `SortedMiddleware`/`EnsureFrontendRequestsAreStateful` source — `AuthenticateSession` never appears
  in the route's own middleware array at all). Fixed via the hook Sanctum explicitly provides for this:
  new `App\Http\Middleware\InitializeTenancyBeforeAuthenticatingSession`, registered as
  `config('sanctum.middleware.authenticate_session')` in place of Sanctum's own class — it initializes
  tenancy from the session first (by the time it runs, `StartSession` has already run in the same
  sub-pipeline, so the session is readable), then delegates to the real `AuthenticateSession`,
  preserving its stale-session security check rather than disabling it. Full suite green throughout
  (215 tests).
- **Platform Admin login → blank white page.** `PlatformAdminResource` returned only
  `{id, name, email, type}`, but the frontend's `User` type (and every role/permission check —
  `AppLayout`'s nav filter, `RequireFinanceStaff`, `RequirePermission`, `DashboardPage`'s
  staff/parent branch) unconditionally calls `.includes()`/`.some()` on `user.roles`/
  `user.permissions`. Platform Admin has no Spatie roles (central, not tenant-scoped), so those
  were `undefined` → first render threw a `TypeError` → React unmounted with no error boundary →
  blank page. Fixed by having `PlatformAdminResource` return the full `UserResource`-compatible
  shape (`school_id`/`phone`/`locale: null`, `roles`/`permissions: []`). Covered by an added
  assertion in `test_platform_admin_login_falls_back_to_central_guard`. Platform Admin now lands
  on the dashboard's generic "use the sidebar" state — real platform-level tooling/nav is still
  unbuilt (out of scope so far; tracked for a future phase).

### Security
- **Six cross-family/cross-school data-leak fixes, found while auditing API-to-UI parity for the
  `parent` role** (the same bug shape each time: a policy's `view()` was correctly ward-scoped, but
  the corresponding `index()` controller action never applied that scope to the *list* query, or no
  scope existed on either at all). All six now match the existing secure pattern already used by
  `PaymentSlipController::index`/`PaymentSlipPolicy`, and each has a regression test:
  - `StudentController::index` + `StudentPolicy::view` — a parent could list/view **any** student in
    the school, not just their own children. Now `index` filters to `wards()` and `view` checks
    ward membership.
  - `ReportCardController` — inherited the fix above for free, since it already calls
    `authorize('view', $student)` before serving a card; only the stale docblock needed updating.
  - `HostelAllocationController::index` + `HostelAllocationPolicy::view` — a parent could see every
    student's hostel room allocation in the school.
  - `HostelLeaveRequestController::index` — same gap; `HostelLeaveRequestPolicy::view` was already
    correctly ward-scoped, `index()` just never used it.
  - `ResultController::index` — a parent could pass **any** `student_id` and receive that student's
    results, including unpublished/draft ones. Now forces `is_published=true` AND restricts to the
    parent's own wards regardless of the requested `student_id` (a non-ward id ANDs to zero rows,
    not someone else's data).
  - `AttendanceController::index` — previously only supported a `(class_id, attendance_date)`
    teacher-roster lookup with no ownership check at all (any authenticated user could read any
    class's full-roster attendance for any date). Added a second mode (`student_id` → that
    student's paginated history, needed for the new parent drill-down) and required a `parent` to
    use that mode for their own ward only — the roster mode now 403s for `parent`.
- **Two permission/policy mismatches**: `ClassRoomPolicy` and `SubjectPolicy` both hardcoded
  `create`/`update`/`delete` to `tenant_admin`/`school_admin` only, while `RoleAndPermissionSeeder`
  has granted `academic_director` the corresponding `academic.manage_classes`/
  `academic.manage_subjects` permissions since Phase 0 — the permission existed but the policy
  silently rejected it. Both now include `academic_director`.

### Changed
- **Theme rework: glassmorphism + persisted light/dark mode toggle** (explicit user request). The
  static single theme in `theme/index.ts` is now `getDesignTokens(mode)`/`createAppTheme(mode)`,
  switched by a new `theme/ColorModeProvider.tsx` (React context + `useColorMode()`) that persists
  the choice to `localStorage` (`sms-color-mode`) and falls back to `prefers-color-scheme` on first
  visit. `App.tsx` now wraps the tree in `<ColorModeProvider>` instead of a static `<ThemeProvider>`.
  Accent is a two-tone blue pair — **dark blue** (`#0B3D91`) and **light blue** (`#42A5F5`) — with the
  roles swapped per mode (dark blue primary on light glass, light blue primary on dark glass) so the
  accent keeps contrast against whichever surface it sits on. `AppBar`/`Drawer`/`Menu`/`Paper`/`Card`
  now render as frosted glass (translucent background + `backdrop-filter: blur(20px) saturate(180%)`,
  a soft border, no flat fill) over a fixed radial-gradient page background (light: soft blue/white;
  dark: deep navy) set via a `MuiCssBaseline` body override, so the blur has something colorful to
  pick up as content scrolls beneath the fixed topbar/sidebar. `AppLayout.tsx` got a Sun/Moon
  (`lucide-react`) toggle button in the topbar next to the avatar menu; the main content `Box` no
  longer paints an opaque `background.default` over the gradient. Verified with `tsc --noEmit`,
  `npm run build`, and the same isolated mocked-auth Vite preview pattern used above — screenshotted
  both light and dark mode end-to-end (toggle click included), no console errors. Preview scaffolding
  deleted afterward.
- **Icon library swapped: `@mui/icons-material` → `lucide-react`** (explicit user request). Replaced
  every icon import across the SPA (`AppLayout.tsx`'s nav + topbar/collapse/logout icons, and the
  add/edit/delete action icons in `AssessmentsPage`, `FeeStructuresPage`, `PaymentMethodsPage`,
  `AllocationEditor`, `StudentsListPage`, `GuardianList`, `AssignmentsPage`, `SubjectsPage`) with the
  closest Lucide equivalent, sized via Lucide's `size` prop instead of MUI's `fontSize` string prop
  (Lucide icons use `stroke="currentColor"`, so the existing `ListItemIcon`/theme color-on-selected
  styling still tints them correctly with no theme changes needed). Removed the now-unused
  `@mui/icons-material` package dependency; `FRONTEND.md`/`SETUP.md` updated to install/reference
  `lucide-react` instead, with a new conventions note on sizing. Verified with `tsc --noEmit`,
  `npm run build`, and the same isolated mocked-auth Vite preview used for the shell refresh above —
  screenshotted the full sidebar with every new icon, no console errors. Preview scaffolding deleted
  afterward.

### Added
- **Demo tenant sample data** (`database/seeders/DemoDataSeeder.php`, called from `DatabaseSeeder` only
  when `tenant('id') === 'demo'` — the `preview` tenant and every test tenant stay untouched). Builds a
  full walk-through dataset by reusing the real services wherever one exists (`PaymentSlipSubmissionService`
  + `PaymentSlipVerificationService` for slips/receipts/ledgers, `HostelAllocationService` +
  `HostelLeaveService` for hostel) rather than poking rows in directly, so the data is exactly as
  consistent as data created through the app: 1 current academic session, 3 classes (Form 1–3) with
  streams, 6 subjects mapped to every class, 9 staff accounts covering every staff role
  (`class_teacher`, `academic_director`, `finance_manager`, `accountant`, `hostel_manager`, 3
  `teacher`s) plus 18 `teacher_assignments`, 12 students (mixed day/boarding, mixed gender) with
  enrolments, 6 parent accounts each linked to 2 sibling students (exercises the multi-child parent
  switching case from PRD §5.10), 24 attendance records, 6 published assessments with 72 result
  records, 6 fee structures, 6 payment slips (3 verified → real receipts + ledgers, 1 rejected, 2 left
  pending), 2 hostels/4 rooms with 6 gender-correct allocations, and 1 pending leave request. Applied
  via `php artisan tenants:migrate-fresh --tenants=demo` + `tenants:seed --tenants=demo` (a destructive
  reset, confirmed with the user first since it discards whatever was in that schema — the `preview`
  tenant was deliberately left alone). Verified by querying every table's count post-seed and spot
  checking ledger arithmetic (assessed/paid/balance) and hostel gender/capacity matching by hand via
  `tinker`. All staff/teacher/parent logins use the password `password` (the shared `UserFactory`
  default), matching the existing `admin@demo.sms.test` / `school-admin@demo.sms.test` convention.
- **Phase 5 (Reporting & Portals) — dashboard backend core slice**: `GET /api/v1/dashboard/summary`
  (school_admin/tenant_admin/finance_manager/accountant/academic_director — active student count,
  today's attendance present/absent, pending + verified-today payment slips, hostel room/capacity
  totals, current academic session name) and `GET /api/v1/dashboard/wards` (parent — per-child class,
  fee balance/status, pending slip count, scoped through the existing `User::wards()` relation so a
  parent only ever sees their own children). Both are plain read-only aggregation queries in
  `DashboardController` — no service layer, since nothing here writes state. 3 feature tests (role
  rejection on `summary`, ward-scoping + correct balance on `wards`) passing; `pint` clean.
  **Deferred** (per standing "keep it simple, use less tokens" instruction): PDF/Excel export,
  response caching, and the student portal — the last is blocked on a real product decision (students
  have no `User` login yet; see PROJECT-PLAN.md Phase 5). A full `security-auditor` pass across
  Phases 3–5 is still owed before production use; only a manual spot-check was done here.
- **Phase 5 — cross-module dashboard UI.** `DashboardPage.tsx` (previously a Phase-0 "welcome" stub)
  now consumes the two endpoints above: a staff stat-card grid (active students, today's
  present/absent, pending + verified-today payment slips, hostel rooms/capacity, current session
  chip) for `tenant_admin`/`school_admin`/`finance_manager`/`accountant`/`academic_director`, or a
  per-child card grid (class, fee balance via the shared `formatMoney`, payment-status chip,
  pending-slip count) for `parent`. New `features/dashboard/api/useDashboard.ts` +
  `features/dashboard/types/dashboard.ts`, mirroring the existing feature-folder shape (React
  Query hooks, no `fetch`/axios in components). A role with neither (e.g. `teacher`) sees a plain
  landing message, since teachers' real workflow starts on attendance/mark-entry, not a dashboard.
  Verified with `tsc --noEmit` (clean) and `npm run build` (clean production bundle) — no dev server
  walkthrough this pass (no `.env`/DB wired in this environment, consistent with the Phase 5
  head-start note above).
- **Phase 5 head start — app shell visual refresh** (pulled forward from Phase 4/5, chrome only, no
  new data): `AppLayout.tsx` rebuilt as a collapsible sidebar grouped into Overview/Academics/Finance
  sections (`ListSubheader` per group, mini icon-only collapsed state with tooltips) and a quiet topbar
  showing the current page title plus an avatar menu (name/email + log out) replacing the old plain
  logout button. `theme/index.ts` retuned: soft neutral `background.default`, low-elevation `MuiCard`
  shadow, borderless `MuiAppBar`/`MuiDrawer` chrome, rounded selected-nav-item pill — visual language
  borrowed from the MUI "Minimal Dashboard" template per user request. Verified by rendering the shell
  against a mocked `useAuth()` in an isolated, temporary Vite preview (this environment has no
  `.env`/database configured, so standing up the full app just to view a CSS change wasn't justified)
  and screenshotting both the expanded and collapsed sidebar states — no console errors. All temporary
  preview scaffolding was deleted afterward; only `AppLayout.tsx` and `theme/index.ts` changed.
  Stat cards, charts, and real aggregate data are explicitly out of scope here and remain full Phase 5
  work (PROJECT-PLAN.md).
- **Phase 4 (Hostel)**: hostels → rooms → allocations. `HostelAllocationService::allocate()`
  checks room capacity, gender match, and one-active-allocation-per-session (mirrors the DB's partial
  unique index so a conflict is a clean 422, not an uncaught 500); `end()` sets `status=ended` —
  `hostel_allocations` is soft-delete-only per RULES.md §3, history is never removed. CRUD for
  `hostels`/`hostel-rooms`, `POST /api/v1/hostel-allocations`, `POST .../{id}/end`. Meal plans
  (per-hostel CRUD, optional `meal_plan_id` on an allocation) and leave/exeat approval
  (`HostelLeaveService`, `POST /api/v1/hostel-leave-requests` + `.../approve|reject` — parent can only
  request for their own `wards()`, hostel_manager decides, re-deciding an already-decided request is a
  clean 422). Opt-in fee-status gate on allocation (PRD §5.7) — `School.fee_terms->hostel_gate_enabled`,
  same pattern as the report-card gate, defaults OFF. 13 tests passing. **Deferred**: the notification
  engine (ADR-0007 SMS provider still open — product decision, not made here), and the React/MUI
  frontend. A full `security-auditor` pass was not run for this slice — same caveat as Phase 3, recommend auditing
  hostel + finance together before production use.
- **ADR-0008: credential-based tenant identification, single domain (replaces subdomains).**
  New central tables `tenant_user_directory` (email → tenant routing, unique email, kept in sync by
  `App\Observers\SyncTenantUserDirectoryObserver` on the tenant `User` model) and `platform_admins`
  (Platform Admin — the one login type not scoped to a tenant, separate `platform` guard).
  `AuthController::login` now looks the email up in the directory, initializes that tenant, then
  authenticates — falling back to the `platform` guard if the email isn't in any tenant. New
  `App\Http\Middleware\InitializeTenancyFromSession` replaces `InitializeTenancyBySubdomain` on every
  authenticated route, reading `tenant_id` from the session instead of the request host. Sessions are
  pinned to the literal `pgsql` connection (`SESSION_CONNECTION=pgsql`) so they're readable before any
  tenant is known. New `php artisan tenancy:backfill-directory` command rebuilds the directory from
  existing tenants. No more hosts-file/subdomain setup needed locally; `routes/web.php`'s central
  placeholder route was removed so the SPA catch-all in `routes/tenant.php` serves `/` directly.
- **Phase 3 (Financial module)**: fee-structure + payment-method CRUD (`GET/POST/PUT/DELETE
  /api/v1/fee-structures` and `/api/v1/payment-methods`, FormRequest → thin controller → Resource,
  cross-school membership guard mirroring `AssessmentRequest::withValidator`); payment-slip submission
  (`POST /api/v1/payment-slips`, `PaymentSlipSubmissionService`) — `AllocationSumMatchesTotal` BCMath rule
  (sum == total), duplicate-teller-per-bank-per-date rejection, advisory-lock sequential
  `SLP-YYYYMMDD-NNNN` per school per day, tenant/school-scoped attachment storage (thumbnails deferred),
  first `PaymentSlipLog` + `PaymentSlipSubmitted` event, parent ward-ownership enforced in the
  FormRequest's `authorize()`; verification workflow (`POST /api/v1/payment-slips/{slip}/verify|reject`,
  `PaymentSlipVerificationService`) — one transaction: status transition + ledger upsert (assessed summed
  from active `FeeStructure`s on first payment, `total_paid` incremented, `payment_status` recomputed via
  `->refresh()` of the STORED GENERATED `balance`) + immutable `RCP-YYYYMMDD-NNNN` receipt (DomPDF +
  amount-in-words, QR deferred) + `FeePayment` rows + log + `PaymentSlipVerified`; idempotency guard
  (re-verify → 422, `lockForUpdate`) and reject path (`PaymentSlipRejected`, no ledger change);
  `index`/`show` with parent-scoped (`wards()`) vs finance-queue visibility.
- feat(finance): optional per-school fee-status gate on report cards (PRD §5.5) — when
  `School.fee_terms->results_gate_enabled` and the student's ledger balance is outstanding,
  `GenerateReportCardPdf` withholds the PDF and records `report_cards.withheld_reason` (new migration,
  `file_path` made nullable); `ReportCardController@show` returns 403 + reason instead of a confusing 404.
  Gate defaults OFF so Phase 2 tests are unaffected.
- test(finance): end-to-end flow (submit → verify → receipt → correct ledger balance), partial-payment
  status, double-verify idempotency, rejection (no ledger/receipt), allocation-sum invariant,
  duplicate-teller, parent ward-ownership + index scoping + verify-denied; fee-gate on/off/cleared.
- **Phase 2 (Attendance & Assessment)**: idempotent batch attendance capture (`POST/GET /api/v1/attendance`,
  `AttendanceService` — `updateOrCreate` keyed on `(student_id, attendance_date, period)`, `AttendanceRecorded`
  event); assessment definitions with weightings (`GET/POST/PUT/DELETE /api/v1/assessments`); mark entry
  (`POST /api/v1/results`, `MarkEntryService` — versioned/append-only, a draft updates in place but a
  correction after publish always inserts a new `version`, never mutates a published row); gated publishing
  (`POST /api/v1/assessments/{assessment}/publish`, `ResultPublishingService`, `ResultsPublished` event,
  restricted to `academic_director`/`school_admin`/`tenant_admin` via `ResultRecordPolicy::publish`); queued
  report-card PDF generation (`POST/GET /api/v1/students/{student}/report-card`, `GenerateReportCardPdf` job
  on the `pdf` queue, DomPDF, weighted score per subject, new `report_cards` cache-pointer table/model).
  Widened `AttendanceRecordPolicy`/`AssessmentPolicy`/`ResultRecordPolicy::create()` beyond admin-only to
  match RULES.md §5's real RBAC matrix (`attendance.take` for teacher/class_teacher,
  `assessment.manage_grading` for academic_director) — these are real rules now, not Phase 0/1 placeholders.
- React/MUI: `features/attendance` (class roster + status picker, pre-fills from existing records),
  `features/assessment` (CRUD list, per-row publish, mark-entry grid, report-card generation/status page).
  Added `GET /classes`/`GET /academic-sessions` lookup hooks reused from Phase 1.
- 149 tests passing suite-wide: attendance idempotency + cross-school + ownership checks, assessment CRUD,
  mark-entry versioning (including a direct-model test of the append-only guard), gated publishing (event
  dispatch + real DB state), report-card weighted-score arithmetic, school-scope + schema isolation.
- Project scaffold: CLAUDE.md, PRD.md, ARCHITECTURE.md, RULES.md, SKILLS.md, FRONTEND.md,
  PROJECT-PLAN.md, AGENTS.md, SETUP.md, docs/prd-financial-module.md.
- Claude Code subagents (`.claude/agents/`): model-architect, migration-engineer, api-builder,
  finance-specialist, frontend-builder, test-engineer, security-auditor, code-reviewer.
- Claude Code slash commands (`.claude/commands/`): new-model, new-module, new-frontend-feature,
  verify-tenant-isolation, ship-check.
- `.claude/settings.json` project configuration.
- **Phase 0 bootstrap**: `composer create-project laravel/laravel` merged into the repo root; installed
  `stancl/tenancy` (PostgreSQL schema manager), `laravel/sanctum`, `spatie/laravel-permission`,
  `barryvdh/laravel-dompdf`, `intervention/image`.
- Central tables: `tenants`, `domains` (stancl), plus central-only `cache`/`jobs`/`sessions` infra tables.
  Tenant tables (`database/migrations/tenant`): `schools`, `users`, `password_reset_tokens`, `sessions`,
  spatie permission tables (UUID-adapted morph columns), `academic_sessions`, Sanctum
  `personal_access_tokens` (UUID morphs), `classes` (model `ClassRoom`), `streams`, `students`
  (soft-deletable). `cache`/`jobs` are also duplicated per-tenant as a local Redis-less stopgap, since
  `DatabaseTenancyBootstrapper` repoints the default connection's search_path per tenant.
- Models: `Tenant` (central, extends stancl's with `HasDatabase`+`HasDomains` for schema-per-tenant),
  `School`, `User` (tenant, Sanctum `HasApiTokens` + spatie `HasRoles`), `AcademicSession`, `ClassRoom`,
  `Stream`, `Student` (soft deletes). `BelongsToSchool` trait + `SchoolScope` global scope for campus
  isolation within a tenant. Policies (`SchoolPolicy`, `StudentPolicy`, `ClassRoomPolicy` — Phase 0
  placeholders), factories for all six models.
- RBAC: `RoleAndPermissionSeeder` seeds the full RULES.md §5 role/permission matrix per tenant;
  `DatabaseSeeder` seeds a demo school + tenant_admin/school_admin login per tenant.
- Sanctum SPA cookie auth: `routes/tenant.php` (subdomain-resolved, `InitializeTenancyBySubdomain` +
  `PreventAccessFromCentralDomains`), `POST /api/v1/login` (rate-limited `throttle:5,1`, rejects
  deactivated users), `POST /api/v1/logout`, `GET /api/v1/me`; tenant-aware `/sanctum/csrf-cookie`
  re-registration (Sanctum's own route registration disabled — it isn't tenant-aware).
- React + Vite + MUI SPA shell (`resources/js`): providers (React Query, MUI theme, router), auth
  context, route guards (`RequireAuth`, `RequirePermission`), typed axios client, login page, dashboard
  placeholder. Tailwind removed (ADR-0002 is MUI-only).
- Phase 0 test suite (27 tests): schema isolation (`SchemaIsolationTest`), campus/`school_id` scope
  (`SchoolScopeTest`), soft-delete (`StudentSoftDeleteTest`), auth flows (`LoginTest` — happy path, wrong
  password, deactivated user, validation, unauthenticated `/me`, logout), policy smoke tests. Tenant test
  harness (`tests/Concerns/CreatesTenant.php`) spins up/tears down real Postgres schemas per test.
- **Phase 1 (SIS & Academics)**: student admission + first enrolment (atomic), guardian linking
  (`student_guardians` pivot, guardians are `User`s with the `parent` role), promotion across
  sessions (append-only — old enrolment flips to `status=promoted`, a new row carries the new
  class/session/residence forward), subjects, class↔subject mapping, teacher assignments
  (teacher↔class↔subject↔session), and assignments/homework with a real visibility rule
  (`AssignmentVisibilityService`: owning teacher always, admins always, guardians only once
  *published* and only for actively-enrolled students). New tenant migrations: `enrolments`,
  `student_guardians`, `subjects`, `class_subjects`, `teacher_assignments`, `assignments`,
  `assignment_submissions` (model + schema only — no submission/grading endpoints yet, deferred).
  Two small read-only lookups added (`GET /api/v1/classes`, `GET /api/v1/academic-sessions`) to
  unblock frontend dropdowns.
- React/MUI: `features/students` (list, admission form, detail with guardians/enrolment),
  `features/academics` (subjects CRUD, assignments list + create + publish, role-gated).
- Phase 1 test suite brings the total to 93 passing: admission, guardian linking, promotion, subjects,
  class↔subject, teacher assignments, the full assignment visibility matrix (including drafts), plus
  `school_id` scope + schema isolation tests for every new model.
- **Explicitly deferred from Phase 1's task list** (not part of the exit criteria, will pick up
  separately): bulk CSV/Excel import, timetable with clash detection, student document attachments,
  assignment submission/grading endpoints (schema exists, no controller yet).

### Changed
- **ADR-0001 locked → `stancl/tenancy` PostgreSQL schema-per-tenant** (subdomain identification, like
  NexStays). Tenant tables drop `tenant_id`; isolation is the Postgres schema; tenant migrations live in
  `database/migrations/tenant` and run via `tenants:migrate`. `school_id` retained for campus scoping.
  Updated CLAUDE.md, ARCHITECTURE.md (§2), RULES.md, SKILLS.md (Recipe A), SETUP.md, PROJECT-PLAN.md,
  the migration-engineer/model-architect/security-auditor/finance-specialist/api-builder/code-reviewer
  agents, and the new-model/new-module/verify-tenant-isolation commands.
- **ADR-0002 locked → React SPA + Vite + Material UI**, Sanctum SPA (cookie) auth. Added FRONTEND.md,
  the frontend-builder agent, SKILLS Recipe J, the new-frontend-feature command, and frontend rules (RULES §8).
- SETUP.md: corrected `SESSION_DRIVER` guidance from `cookie` to `database` — the cookie driver would
  put the whole session payload client-side encrypted with one shared `APP_KEY`, defeating schema-per-
  tenant isolation (a session minted on one tenant subdomain could decrypt and authenticate on another).

### Deprecated
- _Nothing yet._

### Removed
- Default `routes/api.php` (dead code outside tenancy context — every API route lives in
  `routes/tenant.php` instead).

### Fixed
- **`tenants:migrate-fresh` + `tenants:seed` on the same tenant always failed the second time around.**
  `tenants:migrate-fresh` wipes the tenant's own schema (so its `users` table is empty again) but never
  touches the central `tenant_user_directory` table — that table's rows from the *previous* seed run
  were still there, pointing at user_ids that no longer exist. Re-seeding then hit
  `tenant_user_directory_email_unique` on the very first `User::create()`
  (`SyncTenantUserDirectoryObserver` tries to re-insert an email the directory still has from last
  time). Also surfaced a second-order issue: because `DatabaseSeeder::run()` isn't wrapped in one
  transaction, that failure left a stray `School` row behind, so even after the directory was fixed the
  *next* reseed attempt hit `schools_code_unique` instead — required one more `migrate-fresh` to clear.
  Fixed by having `DatabaseSeeder::run()` delete this tenant's own `tenant_user_directory` rows first,
  before creating any users — makes `tenants:migrate-fresh --tenants=<id>` followed by
  `tenants:seed --tenants=<id>` a safe, repeatable local workflow instead of a one-shot. Found via the
  user hitting it directly when re-running the demo-tenant reset from the previous entry.
- **Phase 2 — `GenerateReportCardPdf` job silently never ran, in every environment.** It defined a
  `queue(): string` method to name its Horizon queue, which collides with
  `Illuminate\Bus\Dispatcher::dispatchToQueue()`'s `queue($queue, $command)` dispatch-hook convention —
  any job class with a method literally named `queue` has that method called *instead of* being pushed
  onto the queue. No exception, no log entry, nothing in `failed_jobs`. Fixed by setting the
  `Queueable` trait's `$queue` property in the constructor instead. Found while writing `ReportCardTest`.

### Security
- **ADR-0008 credential-routing audit (security-auditor) caught and fixed 2 issues before sign-off:**
  (1) Platform Admin login didn't enforce `is_active` — a deactivated Platform Admin (the
  highest-privilege, cross-tenant account) could still authenticate; fixed by adding the same
  `is_active` constraint already applied to tenant-user login. (2) A tenant user's email + a
  Platform Admin's password could authenticate as the Platform Admin, because a failed tenant
  login attempt fell through to the `platform` guard — `tenant_user_directory.email` and
  `platform_admins.email` are independently unique, nothing prevented the same email existing in
  both. Fixed: an email present in the tenant directory now never falls through to the platform
  guard, regardless of whether the tenant password check succeeds. Both covered by new tests in
  `LoginTest`. **Noted, not yet acted on**: no policy enforces global email uniqueness between the
  two tables going forward (see ARCHITECTURE.md §2.4) — `SyncTenantUserDirectoryObserver` and
  `BackfillTenantUserDirectory` will throw a raw unique-constraint exception on collision rather
  than a clean validation error; acceptable for now since no endpoint creates arbitrary-email users
  yet, but flag before building one.
- **Phase 3 (finance) — audit incomplete.** A `security-auditor` pass was started but interrupted before
  completion. A manual spot-check confirmed `SubmitPaymentSlipRequest`/`FeeStructureRequest` apply the
  same cross-school `withoutGlobalScope(SchoolScope::class)` validation pattern established in Phases
  1-2, and `PaymentSlipPolicy::verify()` is correctly enforced server-side (parents cannot reach
  verify/reject). **Not yet independently verified**: file-upload path safety, sequential-numbering race
  safety under real concurrency, and receipt/slip immutability against every code path. Run a full
  security review before this module is used with real money.
- Login now rejects deactivated (`is_active = false`) users at the credential-check stage, not just
  post-login.
- `/login` is rate-limited (`throttle:5,1`) per RULES.md §7.
- **Phase 2 — cross-school `student_id` gaps (the same bug class as Phase 1, regressed on a new field).**
  Neither `EnterMarkRequest` nor `RecordAttendanceRequest` validated that the submitted `student_id`(s)
  belonged to the same school as the assessment/class — `Rule::exists` alone doesn't constrain
  `school_id`. A school_admin (or unscoped tenant_admin) could enter marks or record attendance against
  a student in a different campus. Both now validate same-school membership via
  `Student::withoutGlobalScope(SchoolScope::class)`, mirroring the Phase 1 fix pattern exactly.
- **Phase 2 — any teacher could overwrite another teacher's attendance for a class they don't teach.**
  `AttendanceRecordPolicy::create` correctly grants every teacher the ability to record attendance, but
  `AttendanceService::record()`'s `updateOrCreate` upsert meant that ability also let a teacher silently
  overwrite attendance for a class/session they have no `TeacherAssignment` for. `RecordAttendanceRequest`
  now requires a matching `TeacherAssignment` for non-admin roles (mirrors `EnterMarkRequest`'s ownership check).
- **Phase 2 — `ResultRecord`'s append-only invariant had no structural enforcement.** Score/grade/version
  were only protected by service-layer discipline (RULES.md §1/§3 treats results like finance records —
  "never overwrite in place"). Added a model-level `updating` guard (`ResultRecord::booted()`) that throws
  if any code path attempts to change `score`/`grade`/`version` on an already-published row, independent
  of which service/controller is calling.
- **Phase 1 — draft assignments were visible to guardians.** `AssignmentVisibilityService` now gates
  the guardian-visibility branch on `published_at !== null` (the owning teacher still sees their own
  drafts; admins always see everything).
- **Phase 1 — cross-school (intra-tenant) integrity gaps.** `Rule::exists` alone doesn't constrain
  `school_id`, so several FormRequests could let a `tenant_admin` (unscoped) or, in one case, a
  `school_admin` link/assign records across campuses within the same tenant: linking an arbitrary user
  (not necessarily a `parent`, not necessarily the same school) as a student's guardian
  (`LinkGuardianRequest`), assigning a teacher/class/subject from different schools to one
  `TeacherAssignment`, admitting a student into another school's class (`AdmitStudentRequest`),
  promoting a student into another school's class/session (`PromoteEnrolmentRequest`), and attaching a
  subject from a different school to a class (`ClassSubjectController`). All five now validate same-
  school membership before writing (bypassing `BelongsToSchool` deliberately where needed to actually
  see the cross-school record being checked, rather than have it silently resolve to null and skip
  the check).

---

## Decisions (see ARCHITECTURE ADR log)
- ADR-0001 Tenancy — `stancl/tenancy` PostgreSQL schema-per-tenant — **Locked**
- ADR-0002 Frontend — React + Vite + Material UI, Sanctum SPA auth — **Locked**
- ADR-0007 SMS provider — **OPEN** (needed by Phase 4)

---

## How to add an entry

```
## [Unreleased]
### Added
- feat(finance): payment slip submission endpoint with allocation validation (#PLAN Phase 3)
### Fixed
- fix(sis): promotion now carries forward guardian links
### Security
- security(uploads): enforce mime + size validation and tenant-scoped storage paths
```

## [0.0.0] - 2026-06-20
- Initial documentation & agent scaffold (no application code yet).
