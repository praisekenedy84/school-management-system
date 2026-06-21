# SKILLS.md — Repeatable Implementation Recipes

Canonical procedures. Follow them exactly so the codebase stays consistent. If a recipe exists, don't
improvise a different structure.

---

## Recipe A — Add a model (tenant or central)

**First decide:** is this **central** (`Tenant`, `Domain`, cross-tenant only) or **tenant** (everything
else — students, classes, fees, slips, …)? Almost all domain entities are tenant models.

1. **Migration**
   - **Tenant model** → create the migration in `database/migrations/tenant`. **No `tenant_id` column.**
     Add `school_id` (indexed, NOT NULL) if school-owned.
   - **Central model** → create in `database/migrations`.
   - UUID PK `gen_random_uuid()`; correct types (money `decimal(15,2)`, blobs `jsonb`); FKs indexed;
     `timestamps()`; `softDeletes()` if critical; composite index `(school_id, <hot key>)`; DB constraints
     for invariants.
2. **Model**: `HasUuids`; `BelongsToSchool` (+ `SoftDeletes`) for school-owned tenant models; explicit
   `$fillable`; casts (`decimal:2`, array/`AsArrayObject` for JSONB, dates); relationships. Tenant models
   need NO tenant trait — schema switching isolates them.
3. **Factory** mirroring columns; **seeder** entry if reference data (tenant seeders run via `tenants:seed`).
4. **Policy**: viewAny/view/create/update/delete keyed to scoped permissions; ownership checks for parents.
5. **Isolation test**:
   - Tenant model → `tenancy()->initialize($a)` create rows; `tenancy()->initialize($b)` assert absent.
   - School-owned → assert campus A cannot read campus B within one tenant.
6. **Apply**: `php artisan tenants:migrate` (tenant) or `php artisan migrate` (central). Run `pint` + tests.
7. Update CHANGELOG + PROJECT-PLAN.

> Delegate: migration → `migration-engineer`, model/policy/factory → `model-architect`, tests → `test-engineer`.

---

## Recipe B — Add an API endpoint

1. **Route** in the correct role group in `routes/api.php` under stancl tenancy middleware
   (`InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains`) + `auth:sanctum`.
2. **FormRequest**: `authorize()` returns the policy check; `rules()` per RULES §6.
3. **Controller** (thin): inject service, call it, return a **Resource**. No queries/logic.
4. **Service**: business rules in a DB transaction for multi-write; emit the domain event on success.
5. **Resource**: shape JSON; never expose sensitive fields.
6. **Feature test**: happy path + one auth failure + one validation failure.
7. `pint`, tests, CHANGELOG + PLAN.

> Delegate to `api-builder` (or `finance-specialist` for money); review with `code-reviewer` + `security-auditor`.

---

## Recipe C — Add a full module slice (`/new-module`)

1. Migrations for the module's tenant tables (Recipe A each).
2. Models + relationships + policies.
3. Services with workflows; define events + queued listeners.
4. Controllers + FormRequests + Resources + routes (Recipe B each).
5. Notifications/templates (EN + SW) for user-facing events.
6. **Frontend**: build the module's React + MUI feature (Recipe J) wired to the new endpoints.
7. Tests: isolation per model + feature per endpoint + one end-to-end workflow test.
8. Update ARCHITECTURE event list, CHANGELOG, PROJECT-PLAN.

---

## Recipe D — Finance: payment slip submission (parent)

1. Parent selects student → views fee breakdown from the **ledger**.
2. `POST /api/v1/parent/payment-slips`: amount, method, bank/branch, teller no., deposit date, depositor
   name, **allocation[]** (fee_type → amount, must sum to total), slip image(s).
3. Service: validate allocation total == amount; check **duplicate teller per bank per date**; store
   images (original + thumbnail) under tenant/school-scoped path; generate `SLP-YYYYMMDD-NNNN`; create slip
   `status=pending`; write `payment_slip_logs`; emit `PaymentSlipSubmitted`.
4. Queued listeners: notify finance, confirm to parent, log audit.
5. Return slip number + status + ETA. **No money is moved.**

---

## Recipe E — Finance: verification → receipt (finance officer)

1. `GET /api/v1/finance/verification-queue` (sortable/filterable; flag overdue >48h, duplicates, mismatches).
2. Officer reviews against checklist.
3. Decision:
   - **Verify** → status `verified`; record verifier + timestamp; update **ledger** (`total_paid`,
     recompute `balance`, set `payment_status`); generate `RCP-YYYYMMDD-NNNN`; render PDF (DomPDF) + QR
     (queued); emit `PaymentSlipVerified` → GenerateReceipt, UpdateLedger, NotifyParent, SyncHostelStatus, LogAudit.
   - **Clarify** → status `clarification_needed`; notify parent.
   - **Reject** → status `rejected`; category + reason; notify parent.
4. Receipts immutable once generated; original slip image attached as reference.
   Wrap verify+receipt in ONE transaction; make it idempotent.

---

## Recipe F — Assessment: enter → publish results (gated, versioned)

1. Teacher enters marks scoped to their (class, subject, session) assignment.
2. Class teacher assembles; academic director **approves publication**.
3. Publish: set `is_published`, `published_by/at`; **snapshot version**; emit `ResultsPublished`.
4. Corrections after publish → new `version` record + audit; never overwrite.
5. Report card PDF (queued) with letterhead. Fee-gate check via ledger balance if enabled.

---

## Recipe G — Attendance with offline tolerance

1. Teacher takes attendance per (class, period); present/absent/late/excused.
2. SPA queues records locally when offline; syncs idempotently on reconnect (unique
   `student_id, attendance_date, period` dedupes).
3. Absence → event → notify guardian; update summaries.

---

## Recipe H — Notification (templated, bilingual)

1. Notification class supporting mail + SMS + database channels.
2. EN + SW templates keyed by event; respect per-user channel preference + locale.
3. Triggered by a listener on the domain event; **queued**.

---

## Recipe I — Reporting endpoint

1. `ReportService` method returning a typed DTO/array.
2. Scope + date-range filters; paginate large tables.
3. PDF (DomPDF) + Excel (Laravel Excel) export behind one interface.
4. Cache expensive aggregates with short TTL keyed by school/filters.

---

## Recipe J — React + MUI feature (frontend)

1. Create `resources/js/features/<feature-name>/` with `pages/`, `components/`, `api/`, `types/`.
2. **Types**: define the response/request TypeScript types mirroring the API Resources.
3. **API hooks**: in `api/`, wrap the typed axios client with React Query hooks (`useSlips`, `useVerifySlip`).
4. **Pages/components**: compose MUI components; use the shared theme; format money via the TZS formatter;
   pull user-facing strings from the i18n catalog (EN/SW).
5. **Guards**: gate routes/actions on the user's permissions (mirrors backend RBAC).
6. **Wire** into the SPA router; handle loading/error/empty states with MUI patterns.
7. Verify against the running API; keep components dumb (logic in hooks).

> Delegate to `frontend-builder`. Confirm the endpoint contract exists (Recipe B) before building UI.

---

## Standing checklist appended to every recipe

- [ ] Tenant vs central placement correct; tenant tables have no `tenant_id`; school scope where needed.
- [ ] Isolation test present (schema + school scope as applicable).
- [ ] Policy authorization on every action.
- [ ] Domain event emitted; audit + notification listeners wired.
- [ ] Money as decimal; references sequential per school per year.
- [ ] `pint` clean; `php artisan test` green.
- [ ] CHANGELOG (Unreleased) + PROJECT-PLAN updated.
