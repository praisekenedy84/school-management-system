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

- [ ] Fee structures; payment methods config.
- [ ] Student fee ledgers with assessed/discount/paid + stored `balance` + payment_status.
- [ ] Payment slip submission (Recipe D): images, allocation, dup-teller check, `SLP-` numbering, logs.
- [ ] Verification workflow (Recipe E): verify/clarify/reject; ledger update; `RCP-` receipts + PDF + QR.
- [ ] Discounts/scholarships; installment tracking.
- [ ] Reconciliation + finance reports.
- [ ] Wire fee-status gate into report cards (Phase 2 hook).
- [ ] React/MUI: parent submit-slip + receipts; finance verification queue + review drawer; ledger view.
- [ ] End-to-end finance tests: submit → verify → receipt → correct ledger balance.

**Exit:** parent submits slip (SPA) → finance verifies → receipt issued → ledger + hostel status correct; full audit trail.

---

## Phase 4 — Hostel & Communication (Weeks 10–11)

- [ ] Hostels → rooms → allocations (session-scoped); gender/capacity/type constraints.
- [ ] Boarding fees feed ledger; allocation gated on hostel-fee status; partial-pay flags review.
- [ ] Meal plans; leave/exeat approval; hostel-manager notifications.
- [ ] **Decide ADR-0007 (SMS provider)**; notification engine: email + SMS + in-app; EN/SW templates; preferences.
- [ ] React/MUI: hostel rooms/allocations, meal plans, leave approval, notification preferences.

**Exit:** boarding student with verified hostel fees gets a room; every lifecycle event notifies correctly.

---

## Phase 5 — Reporting, Portals & Hardening (Weeks 12–13)

- [ ] Cross-module dashboards + PDF/Excel export.
- [ ] Parent portal (per-child fees/slips/receipts, attendance, results, actions).
- [ ] Student portal (timetable, assignments, own results, own fee status read-only).
- [ ] Performance pass (queues, caching, indexes, Vite bundle); load test to NFR targets.
- [ ] Security review (security-auditor full pass); UAT; release notes.

**Exit:** all PRD §8 success criteria met under load; v1 release tagged.

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
