# ARCHITECTURE.md — School Management System

## 1. Overview

Laravel 11 modular monolith on PostgreSQL 16, **multi-tenant via `stancl/tenancy` with one Postgres
schema per tenant** (ADR-0001). Tenant resolved by **subdomain**; stancl middleware switches the DB
connection/search_path per request. Heavy work (PDFs, notifications, imports) runs on Redis queues via
Horizon. API-first under `/api/v1` (Sanctum SPA auth) consumed by a **React + Vite + MUI** SPA.

```
HTTP (subdomain) ─▶ stancl tenancy middleware (initialize tenant schema)
                 ─▶ auth (Sanctum) ─▶ RBAC middleware
                 ─▶ FormRequest (validation)
                 ─▶ Controller (thin) ─▶ Service (logic, transaction, emits event)
                 ─▶ Model (tenant connection) ─▶ PostgreSQL tenant schema
                                         │
        Events ─▶ Listeners (queued) ─▶ audit log + notifications
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
- **Central schema** (default connection): `tenants`, `domains`, and any truly cross-tenant tables.
  Central migrations live in `database/migrations`.
- **Tenant schema**: every domain table (students, classes, academic_sessions, fee_*, payment_*,
  attendance_records, result_records, hostels, …). Tenant migrations live in `database/migrations/tenant`
  and run with `php artisan tenants:migrate`. These tables have **no `tenant_id`**.
- A model is **central** (e.g. `Tenant`, `Domain`) or **tenant** (everything else, default connection
  while tenancy is initialized). Tenant models need no special trait for tenant isolation — the schema
  switch handles it — but school-owned models use `BelongsToSchool`.

### 2.4 Identification & lifecycle
- Routes that serve tenants use stancl middleware: `InitializeTenancyBySubdomain` +
  `PreventAccessFromCentralDomains`. The central (marketing/admin) domain serves central routes only.
- Tenant creation triggers stancl's job pipeline to **create the schema** and run tenant migrations/seeders.
- In tests, wrap tenant assertions in `tenancy()->initialize($tenant)` / `tenancy()->end()`.

### 2.5 Isolation guarantees
- Cross-tenant data access is **physically impossible** without re-initializing tenancy to another
  tenant — there is no shared table to leak through. This is stronger than column scoping.
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

Every state change emits a past-tense event; listeners are **queued**.

```
StudentAdmitted            → LogAudit
PaymentSlipSubmitted       → NotifyFinanceTeam, LogAudit, ConfirmToParent
PaymentSlipVerified        → GenerateReceipt, UpdateLedger, NotifyParent, SyncHostelStatus, LogAudit
PaymentSlipRejected        → NotifyParentWithReason, LogAudit
ReceiptGenerated           → StoreReceiptFile, NotifyReceiptReady
AttendanceRecorded         → NotifyGuardianIfAbsent
ResultsPublished           → SnapshotVersion, NotifyGuardians, LogAudit
HostelFeePaid              → NotifyHostelManager, MaybeEnableAllocation
```
Audit entries are append-only: actor, role, action, from/to status, JSON changes, IP, timestamp.

## 6. Frontend architecture (React + Vite + MUI — ADR-0002)

- **SPA in `resources/js`**, bundled by Vite (Laravel's default bundler), served via a catch-all Blade
  route so deep links work. Per-tenant subdomain serves the same SPA against that tenant's API.
- **MUI** for components + theming. One central theme that reads tenant branding (logo/colours) at runtime.
- **Auth:** Sanctum SPA (stateful cookie). SPA calls `/sanctum/csrf-cookie` then logs in; same-subdomain
  cookie scopes the session to the tenant. RBAC drives route guards + conditional UI.
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
| ADR-0001 | Tenancy: `stancl/tenancy` PostgreSQL **schema-per-tenant**, subdomain identification | **Accepted** |
| ADR-0002 | Frontend: **React SPA, Vite bundler, Material UI**, Sanctum SPA auth | **Accepted** |
| ADR-0003 | UUID PKs everywhere | Accepted |
| ADR-0004 | Append-only finance + results; soft delete only | Accepted |
| ADR-0005 | Money as DECIMAL(15,2), default TZS | Accepted |
| ADR-0006 | Event-driven audit + notifications | Accepted |
| ADR-0007 | SMS provider | **OPEN** — choose a TZ-capable gateway |

> Making a new architectural decision? Add a row here + a CHANGELOG entry. Don't silently deviate.
