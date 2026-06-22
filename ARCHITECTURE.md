# ARCHITECTURE.md — School Management System

## 1. Overview

Laravel 11 modular monolith on PostgreSQL 16, **multi-tenant via `stancl/tenancy` with one Postgres
schema per tenant** (ADR-0001). One single domain serves every tenant; tenant is resolved from the
**logged-in user's credentials** via a central directory, not from the request's host (ADR-0008).
Heavy work (PDFs, notifications, imports) runs on Redis queues via Horizon. API-first under
`/api/v1` (Sanctum SPA auth) consumed by a **React + Vite + MUI** SPA.

```
HTTP ─▶ session middleware ─▶ InitializeTenancyFromSession (reads tenant_id from session)
     ─▶ auth (Sanctum) ─▶ RBAC middleware
     ─▶ FormRequest (validation)
     ─▶ Controller (thin) ─▶ Service (logic, transaction, emits event)
     ─▶ Model (tenant connection) ─▶ PostgreSQL tenant schema
                             │
        Events ─▶ Listeners (queued) ─▶ audit log + notifications

/login (no tenant yet) ─▶ look up email in central tenant_user_directory
                       ─▶ tenancy()->initialize($tenant) ─▶ Auth::attempt()
                       ─▶ session()->put('tenant_id', ...)
```

## 2. Multi-tenancy — `stancl/tenancy`, schema-per-tenant (ADR-0001, like NexStays)

### 2.1 Two layers of isolation
- **Tenant** = a school or school group, isolated by its **own Postgres schema**. No `tenant_id` columns.
- **School** (campus) = a row-level boundary **inside** a tenant schema, isolated by `school_id` + a
  `BelongsToSchool` scope. (Single-school tenants simply have one school row.)

### 2.2 Package setup
```
composer require stancl/tenancy
php artisan tenancy:install     # publishes config, creates central tables: tenants, domains
# config/tenancy.php → enable PostgreSQL schema separation:
'database' => [
  'managers' => [
    'pgsql' => Stancl\Tenancy\Database\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
    // "Separate by schema instead of database"
  ],
],
```
> Confirm the exact manager namespace against the installed stancl/tenancy version (v3 vs v4 differ
> slightly: `Stancl\Tenancy\Database\TenantDatabaseManagers\...` vs `Stancl\Tenancy\TenantDatabaseManagers\...`).

### 2.3 Central vs tenant
- **Central schema** (default connection): `tenants`, `domains`, `tenant_user_directory`,
  `platform_admins`, and any truly cross-tenant tables. Central migrations live in
  `database/migrations`.
- **Tenant schema**: every domain table (students, classes, academic_sessions, fee_*, payment_*,
  attendance_records, result_records, hostels, …). Tenant migrations live in `database/migrations/tenant`
  and run with `php artisan tenants:migrate`. These tables have **no `tenant_id`**.
- A model is **central** (e.g. `Tenant`, `Domain`, `TenantUserDirectory`, `PlatformAdmin`) or
  **tenant** (everything else, default connection while tenancy is initialized). Tenant models need
  no special trait for tenant isolation — the schema switch handles it — but school-owned models use
  `BelongsToSchool`.

### 2.4 Identification & lifecycle (ADR-0008)
- **No subdomains.** One domain serves every tenant, in dev and production. A tenant is identified
  from the **logged-in user's credentials**, not from the request's host.
- **Central directory.** `tenant_user_directory` (central) maps `email → tenant_id`. It's kept in
  sync by `App\Observers\SyncTenantUserDirectoryObserver` on the tenant-scoped `User` model
  (created/updated/deleted) — no service writes to it directly. The `email` column is UNIQUE,
  which is what makes "one login = one tenant" structurally true platform-wide.
- **Login** (`AuthController::login`, central — runs with no tenant initialized): look up the email
  in the directory → `tenancy()->initialize($tenant)` → `Auth::attempt()` against that tenant's
  `users` table → on success, put `tenant_id` in the session. An email absent from the directory
  falls back to the central `platform` guard (`PlatformAdmin` model) — Platform Admin is the one
  login type not scoped to any tenant.
- **Every subsequent request**: `App\Http\Middleware\InitializeTenancyFromSession` reads `tenant_id`
  from the session and initializes that tenant, before `auth:sanctum` resolves the user. It does
  nothing if the session has no `tenant_id` (e.g. a Platform Admin session, or a request with no
  session store at all — Sanctum only attaches one to requests it considers "from the frontend").
- Tenant creation still triggers stancl's job pipeline to **create the schema** and run tenant
  migrations/seeders — that mechanic is unchanged, only how a *request* finds its tenant changed.
- In tests, wrap tenant assertions in `tenancy()->initialize($tenant)` / `tenancy()->end()`, same as
  before — the credential-routing layer sits on top of this, it doesn't replace it.
- A one-off/maintenance command, `php artisan tenancy:backfill-directory [tenant]`, rebuilds the
  directory from each tenant's own `users` table — useful for tenants/users that predate the
  observer, or if the directory ever drifts.

### 2.5 Isolation guarantees
- Cross-tenant data access is **physically impossible** without re-initializing tenancy to another
  tenant — there is no shared table to leak through, **except** the directory's `email → tenant_id`
  mapping itself (by design — that's the one piece of information that has to be central to make
  login possible at all). The directory holds no tenant *data*, only that routing pointer.
- Within a tenant, `school_id` + `BelongsToSchool` prevents one campus reading another's rows; this IS
  enforced by a global scope and must be tested.
- Never hardcode a schema/search_path; let stancl manage it. Never query the central connection for
  tenant data or vice-versa.

## 3. Data model (tenant schema unless noted)

```
[CENTRAL]  Tenant 1─* Domain
[TENANT]   School 1─* AcademicSession
           School 1─* User (role/permission, school-scoped)
           School 1─* ClassRoom 1─* Stream
           ClassRoom *─* Subject (class_subjects)
           School 1─* Student 1─* Enrolment (student↔class↔session, residence: day|boarding)
           Student *─* User[parent] (student_guardians)
           User[teacher] 1─* TeacherAssignment (teacher↔class↔subject↔session)
           Student 1─1 StudentFeeLedger 1─* PaymentSlip 1─0..1 Receipt
           Student 1─* AttendanceRecord
           Student 1─* ResultRecord (versioned, published)
           Student 1─0..1 HostelAllocation *─1 HostelRoom *─1 Hostel
```

### 3.1 Conventions for every tenant table
- UUID PK `gen_random_uuid()`. **No `tenant_id`.** Add `school_id` (indexed, NOT NULL) on school-owned tables.
- `created_at`/`updated_at`; `deleted_at` on critical tables (students, finance, results, hostel_allocations).
- Money: `DECIMAL(15,2)`. Flexible blobs: `JSONB`. Derived totals: stored generated columns.
- Index `school_id` and hot lookup keys; composite `(school_id, <key>)` where useful.

### 3.2 Selected schemas
Finance tables: see `docs/prd-financial-module.md` (schemas there drop `tenant_id` per ADR-0001; keep
`school_id`). Core tenant tables, e.g.:

```sql
CREATE TABLE attendance_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL,
  student_id UUID NOT NULL REFERENCES students(id),
  class_id UUID NOT NULL REFERENCES classes(id),
  academic_session_id UUID NOT NULL,
  attendance_date DATE NOT NULL, period VARCHAR(50),
  status VARCHAR(20) NOT NULL,              -- present|absent|late|excused
  note TEXT, recorded_by UUID REFERENCES users(id),
  created_at TIMESTAMP DEFAULT NOW(),
  UNIQUE(student_id, attendance_date, period)
);

CREATE TABLE result_records (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  school_id UUID NOT NULL,
  student_id UUID NOT NULL REFERENCES students(id),
  academic_session_id UUID NOT NULL, subject_id UUID NOT NULL, assessment_id UUID NOT NULL,
  score DECIMAL(6,2), grade VARCHAR(5),
  version INTEGER NOT NULL DEFAULT 1,        -- corrections bump version; never overwrite
  is_published BOOLEAN DEFAULT false,
  published_by UUID REFERENCES users(id), published_at TIMESTAMP,
  entered_by UUID REFERENCES users(id), created_at TIMESTAMP DEFAULT NOW()
);
```

## 4. Backend layering

| Layer | Responsibility | Must NOT |
|-------|----------------|----------|
| Controller | HTTP in/out, delegate to service, return Resource | contain business logic or queries |
| FormRequest | validation + authorization | mutate data |
| Service | business rules, DB transactions, emit events | know about HTTP |
| Model | persistence, relationships, scopes, casts | contain workflow logic |
| Policy | per-action authorization | query unrelated data |
| Resource | serialization shape | leak internal/sensitive fields |
| Job/Listener | async side effects (PDF, notify, audit) | be on the request's critical success path |

## 5. Events & listeners (audit + notifications backbone)

Every state change emits a past-tense event; notification listeners (NotifyX, ConfirmToParent, etc.)
are **queued** when they exist — most are still not built (no notification engine yet, ADR-0007 open).
**`LogAudit` (Phase 6/ADR-0009) is the one listener that IS built, and runs synchronously**, not queued —
see ADR-0009 below for why.

```
StudentAdmitted            → LogAudit
PaymentSlipSubmitted       → NotifyFinanceTeam, ConfirmToParent, LogAudit
PaymentSlipVerified        → GenerateReceipt, UpdateLedger, NotifyParent, SyncHostelStatus, LogAudit
PaymentSlipRejected        → NotifyParentWithReason, LogAudit
ReceiptGenerated           → StoreReceiptFile, NotifyReceiptReady
AttendanceRecorded         → NotifyGuardianIfAbsent, LogAudit
ResultsPublished           → SnapshotVersion, NotifyGuardians, LogAudit
HostelFeePaid              → NotifyHostelManager, MaybeEnableAllocation
StudentGuardianChanged, StudentPromoted, SubjectChanged, ClassSubjectChanged, TeacherAssignmentChanged,
AssignmentChanged, AssessmentChanged, MarkEntered, ReportCardGenerated, HostelChanged, HostelRoomChanged,
HostelAllocationChanged, MealPlanChanged, HostelLeaveRequestChanged, FeeStructureChanged,
PaymentMethodChanged       → LogAudit (Phase 6 — every other state-changing action across every module;
                              "XChanged" events carry an `action: created|updated|deleted`-style
                              discriminator instead of one class per CRUD verb, to avoid a 3x class count)
TenantProvisioned, ImpersonationStarted, ImpersonationEnded
                           → LogAudit (platform-level — central, no tenant initialized when these fire)
```
Every event above implements `App\Contracts\AuditableEvent` (`toAuditLog(): array`); `App\Listeners\LogAudit`
is registered once against that interface (`AppServiceProvider::boot()`), not once per event class.

Audit entries are append-only (central `audit_logs` table, ADR-0009): actor, action, subject, JSON
changes, IP, timestamp — never updated, never soft-deleted.

## 6. Frontend architecture (React + Vite + MUI — ADR-0002)

- **SPA in `resources/js`**, bundled by Vite (Laravel's default bundler), served via a catch-all Blade
  route so deep links work. One domain serves the SPA for every tenant (ADR-0008) — there is no
  per-tenant URL to construct or switch between.
- **MUI** for components + theming. One central theme that reads tenant branding (logo/colours) at runtime.
- **Auth:** Sanctum SPA (stateful cookie). SPA calls `/sanctum/csrf-cookie` then logs in with just
  email + password; the server figures out which tenant that email belongs to (central directory
  lookup) and the session cookie carries that going forward. RBAC drives route guards + conditional UI.
- **Structure:** `resources/js/{app, api, features, components, theme, routes, types}`. Feature-first:
  each feature folder owns its pages, components, API hooks, and types. See `FRONTEND.md`.
- **Data layer:** a typed API client (axios) + React Query for server state; MUI for presentation.
- Build artifacts via `npm run build`; dev via `npm run dev` (Vite HMR).

## 7. Storage layout

```
storage/app/
├── payment-slips/{original,thumbnails}/{tenant}/{year}/{month}/
├── receipts/{tenant}/{school}/{year}/RCP-YYYYMMDD-NNNN.pdf
├── report-cards/{tenant}/{school}/{session}/...
└── temp/imports/
```
(`{tenant}` path segment derives from the tenant key, not a column on tenant tables.)

## 8. Security model

Sanctum SPA cookie auth; RBAC via scoped permissions (school|class|personal within a tenant; tenant
boundary is the schema); encryption at rest for financial fields; upload scanning; rate limits on
submission/auth; CSRF on web; ORM-only queries. See `RULES.md` §Security.

## 9. Performance & async

Horizon supervises queues: `notifications`, `pdf`, `imports`, `default`. Receipt/report-card generation
and bulk imports are queued. `tenants:migrate` runs across all tenant schemas on deploy.

## 10. ADR log

| ID | Decision | Status |
|----|----------|--------|
| ADR-0001 | Tenancy: `stancl/tenancy` PostgreSQL **schema-per-tenant** | **Accepted** (identification clause superseded by ADR-0008 — isolation mechanism unchanged) |
| ADR-0002 | Frontend: **React SPA, Vite bundler, Material UI**, Sanctum SPA auth | **Accepted** |
| ADR-0003 | UUID PKs everywhere | Accepted |
| ADR-0004 | Append-only finance + results; soft delete only | Accepted |
| ADR-0005 | Money as DECIMAL(15,2), default TZS | Accepted |
| ADR-0006 | Event-driven audit + notifications | Accepted |
| ADR-0007 | SMS provider | **OPEN** — choose a TZ-capable gateway |
| ADR-0008 | Tenant identification: **credential-based** (central `tenant_user_directory`), single domain forever, dev and prod — no subdomains. Platform Admin is the one login type not scoped to a tenant (separate central `platform` guard). | **Accepted** |
| ADR-0009 | **Cross-tenant audit log**: a new central `audit_logs` table fed by a single generic `LogAudit` listener registered against a new `App\Contracts\AuditableEvent` interface (one registration, not one per event class), so Platform Admin can see activity from every tenant in one place. Every state-changing action across every module now dispatches an event implementing that contract. **Deliberately deviates from §5's "listeners are queued" convention** — `LogAudit` runs synchronously, since this project has no Horizon/Redis running locally yet and an audit trail that silently never appears because a queue worker isn't running would defeat the point. `tenant_id` on `audit_logs` is a denormalized string with no FK (some actions are platform-level and have no tenant); actor/subject are denormalized snapshots too (a central table can't FK into a tenant schema's tables). | **Accepted** |

> Making a new architectural decision? Add a row here + a CHANGELOG entry. Don't silently deviate.
