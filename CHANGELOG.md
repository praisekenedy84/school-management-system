# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/);
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Append to **[Unreleased]** as you work. Move entries under a version heading on release.

## [Unreleased]

### Added
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
- **Phase 2 — `GenerateReportCardPdf` job silently never ran, in every environment.** It defined a
  `queue(): string` method to name its Horizon queue, which collides with
  `Illuminate\Bus\Dispatcher::dispatchToQueue()`'s `queue($queue, $command)` dispatch-hook convention —
  any job class with a method literally named `queue` has that method called *instead of* being pushed
  onto the queue. No exception, no log entry, nothing in `failed_jobs`. Fixed by setting the
  `Queueable` trait's `$queue` property in the constructor instead. Found while writing `ReportCardTest`.

### Security
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
