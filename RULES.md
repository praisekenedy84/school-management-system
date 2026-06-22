# RULES.md — Engineering Rules & Guardrails

Read this before writing code. Enforced in review (`code-reviewer`, `security-auditor`).

## 1. Golden rules (never violate)

1. **Respect schema-per-tenant tenancy (`stancl/tenancy`).**
   - Tenant tables live in the tenant schema and have **NO `tenant_id` column**. Tenant migrations go in
     `database/migrations/tenant` and run via `php artisan tenants:migrate`.
   - Central tables (`tenants`, `domains`, `tenant_user_directory`, `platform_admins`, cross-tenant
     only) go in `database/migrations`.
   - Never hardcode a schema/search_path; let stancl switch it. Never read tenant data on the central
     connection or vice-versa.
   - **Tenant identification is credential-based, not subdomain-based** (ADR-0008). One domain serves
     every tenant. Never write code that resolves a tenant from the request's host — only from the
     session (`InitializeTenancyFromSession`) or the login directory lookup.
   - **Within a tenant, isolate campuses with `school_id` + `BelongsToSchool`.** That scope is the one
     you must test for leakage.
2. **Financial + published-result records are append-only.** Soft delete only. Corrections create a new
   versioned record + audit entry. Never `update()` a verified slip's amount or a published result in place.
3. **The finance module never moves money.** It records evidence and verifies. No gateway calls, ever.
4. **Money is `DECIMAL(15,2)`** in DB and decimal/BCMath in PHP. Never float for money. Default `TZS`.
5. **Every state change emits a domain event.** Audit + notifications hang off events, not controllers.
6. **Validate at the boundary, constrain at the DB.** Form Requests for input; DB constraints for invariants.
7. **Authorize every action** with a Policy. No endpoint without an explicit authorization check.
8. **Tests ship with code.** Not done without a feature test (happy path + one auth failure) and, for new
   tenant/school-owned models, an isolation test.

## 2. Code organization (backend)

- Vertical slices: `app/Models`, `app/Http/Controllers/Api/{Role}`, `app/Http/Requests`,
  `app/Services/{Module}`, `app/Policies`, `app/Events`, `app/Listeners`, `app/Http/Resources`.
- Central models (`Tenant`, `Domain`) separate from tenant models. Be explicit about which connection a
  model uses; default (tenant) for domain entities.
- Controllers are **thin**: validate (FormRequest) → call service → return Resource. No queries/logic.
- Business logic in **services**, wrapped in DB transactions for multi-write operations.

## 3. Database & migrations

- UUID PKs (`gen_random_uuid()` default).
- **Tenant tables:** no `tenant_id`; add `school_id` (indexed, NOT NULL) on school-owned tables; place
  the migration in `database/migrations/tenant`.
- **Central tables:** place in `database/migrations`.
- **Never edit a shipped migration.** Add a new one. (Tenant migrations re-run per schema via `tenants:migrate`.)
- Foreign keys indexed; composite index `(school_id, <key>)` for hot lookups.
- Soft deletes on: students, all finance tables, result_records, hostel_allocations.
- Stored generated columns for derived totals (ledger `balance`).

## 4. Naming conventions

| Thing | Convention | Example |
|-------|-----------|---------|
| Table | plural snake_case | `payment_slips` |
| Model | singular PascalCase | `PaymentSlip` |
| Controller | `{Resource}Controller` under role ns | `Api/Finance/VerificationController` |
| Service | `{Module}{Thing}Service` | `Finance/PaymentSlipService` |
| Event | past tense | `PaymentSlipVerified` |
| Listener | verb phrase | `GenerateReceipt` |
| Reference numbers | `SLP-YYYYMMDD-NNNN`, `RCP-YYYYMMDD-NNNN` | sequential per school per year |
| React feature dir | kebab-case | `resources/js/features/payment-slips` |
| React component | PascalCase `.tsx` | `SlipVerificationTable.tsx` |

## 5. RBAC matrix (scoped: school | class | personal — tenant boundary is the schema)

| Role | Level | Key permissions |
|------|-------|-----------------|
| super_admin | 1 | `*` (central / cross-tenant tooling) |
| tenant_admin | 2 | all schools in the tenant; branding, billing, settings |
| school_admin | 3 | full school; manage users + all modules |
| academic_director | 3 | `academic.manage_*`, `assessment.publish_results`, `assessment.manage_grading` |
| finance_manager | 4 | `finance.verify_slips`, `finance.approve_payments`, `finance.generate_receipts`, `finance.manage_fee_structures`, `finance.reconciliation` |
| accountant | 5 | `finance.verify_slips`, `finance.generate_receipts`, `finance.record_payments`, `finance.view_reports` |
| hostel_manager | 4 | `hostel.manage_rooms`, `hostel.manage_allocations`, `hostel.approve_leave`, `hostel.meal_management`, `hostel.view_financial_status` |
| class_teacher | 5 | teacher + `academic.manage_class`, `attendance.view_class_summary`, `assessment.assemble_report_card` |
| teacher | 6 | `academic.manage_assignments`, `attendance.take`, `assessment.enter_marks`, `students.view_basic_info` |
| parent | 7 | `finance.submit_slips`, `finance.view_own_payments`, `finance.download_receipts`, `students.view_own_children`, `academic.view_child_results` |
| student | 8 | `finance.view_own_fee_status`, `academic.view_assignments`, `academic.submit_assignments`, `assessment.view_own_results` |
| auditor | — | read-only: `audit.view_financial`, `audit.view_results`, `audit.view_access_logs` |

Permission shape: `{ name, guard: "web", scope: school|class|personal, description }` (within a tenant schema).

## 6. Validation rules (representative — finance)

```
payment_slip.total_amount   required|numeric|min:1|max:99999999
payment_slip.deposit_date   required|date|before_or_equal:today|after:2020-01-01
payment_slip.teller_number  required|string|max:50|unique per (bank, date)
payment_slip.slip_image     required|image|mimes:jpg,jpeg,png,pdf|max:5120
allocation total            must equal total_amount (custom rule)
verification.notes          required|string|min:10|max:1000
verification.rejection      required_if:action,reject|string|min:20
```

## 7. Security checklist (enforced by security-auditor)

- [ ] Tenancy initialized via stancl middleware on tenant routes; no manual search_path; no central/tenant
      connection cross-reads.
- [ ] `school_id` scope present on school-owned models; campus isolation tested.
- [ ] Every endpoint has a Policy/`authorize()`; parent endpoints check slip/child ownership.
- [ ] Uploads validated (mime + size) + scanned; stored under tenant/school-scoped paths.
- [ ] No secrets in code/logs; financial fields encrypted at rest.
- [ ] Rate limiting on submission + auth; CSRF on web; Sanctum SPA cookie configured correctly.
- [ ] Queries via Eloquent/builder; raw SQL avoided (and never assumes a tenant_id column).
- [ ] Mass-assignment guarded (`$fillable` explicit); Resources never leak sensitive fields.

## 8. Frontend rules (React + Vite + MUI)

- TypeScript throughout. Feature-first folders under `resources/js/features` (see `FRONTEND.md`).
- MUI components + theme tokens only; no ad-hoc inline styling for things the theme covers. Theme reads
  tenant branding at runtime.
- All server state via the typed API client + React Query; no fetch calls scattered in components.
- Route guards + conditional rendering driven by the user's permissions; never rely on hiding UI alone —
  the API authorizes too.
- Money formatted as TZS via a shared formatter; never do money math in the client.
- i18n-ready (EN/SW) strings; no hardcoded user-facing copy in components.

## 9. Testing rules

- Feature test per endpoint: happy path + ≥1 authorization failure + ≥1 validation failure.
- Isolation tests: (a) tenant schema isolation via `tenancy()->initialize()` — Tenant A's data absent
  after switching to Tenant B; (b) `school_id` scope — campus A cannot read campus B within a tenant.
- Finance flows tested end-to-end: submit → verify → receipt → correct ledger balance.
- Use factories; seed minimal data; assert DB state + emitted events.
- `php artisan test` green and `pint` clean before "done".

## 10. Git & changelog

- Conventional-ish commits: `feat(finance): …`, `fix(sis): …`, `feat(ui): …`, `chore:`, `test:`.
- Update `CHANGELOG.md` (Unreleased) and check off `PROJECT-PLAN.md` in the same change.
- Small PRs aligned to one plan task. Run `/ship-check` before marking done.

## 11. What NOT to do

- Don't add `tenant_id` to tenant tables or write a global tenant scope — schema isolation handles it.
- Don't put tenant migrations in `database/migrations` (they belong in `.../tenant`).
- Don't add a payment gateway, payroll, or transport routing (out of scope v1).
- Don't hard delete financial or published-result rows.
- Don't put business logic in controllers, models, or migrations.
- Don't scatter raw `fetch`/axios calls in React components or do money math client-side.
