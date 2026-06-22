# CLAUDE.md — School Management System

> Auto-loaded by Claude Code every session. Keep it lean; detailed context is in the imported files.

## What this project is

A **multi-tenant school management platform** for day and boarding schools, East Africa first
(Tanzania / TZS / English + Swahili). Modules: student records, academics, attendance, assessment,
**financial recording**, hostel/boarding, communication, reporting.

**Finance principle:** the module *records and verifies* externally-made payments (bank / mobile money
/ cash). It does **NOT** process or move money. "Record, don't transact."

## Stack (locked — change only via an ADR)

- **Laravel 11 (PHP 8.2+) · PostgreSQL 16 · Redis (cache + queue) · Laravel Horizon**
- **Multi-tenancy: `stancl/tenancy` with PostgreSQL schema-per-tenant** (ADR-0001, like NexStays).
  Tenant tables live in each tenant's Postgres schema.
- **Tenant identified by login credentials, not subdomain** (ADR-0008). One single domain serves
  every tenant, forever — dev and production. `/login` looks the email up in a central directory,
  initializes that tenant, then authenticates; the session carries `tenant_id` from then on. The one
  exception is Platform Admin, a separate central account not scoped to any tenant.
- **Auth:** Laravel Sanctum — SPA cookie auth (stateful), same-origin since there's only one origin.
- **Frontend: React SPA built with Vite + Material UI (MUI)** (ADR-0002). Lives in `resources/js`,
  bundled by Vite, served via a catch-all Blade view. API-first under `/api/v1`.
- PDF: DomPDF (receipts, report cards). Images: Intervention Image. Search: PG full-text.

## Tenancy model — read this carefully (full detail in @ARCHITECTURE.md §2)

- **Central schema** holds `tenants`, `domains`, `tenant_user_directory` (email → tenant routing),
  `platform_admins` (stancl + ADR-0008). Central migrations: `database/migrations`.
- **Tenant schema** holds all domain tables (students, classes, fees, slips, …). Tenant migrations:
  `database/migrations/tenant`, run with `php artisan tenants:migrate`.
- **Tenant tables have NO `tenant_id` column.** Isolation is the Postgres schema, switched by
  `App\Http\Middleware\InitializeTenancyFromSession` (reading `tenant_id` from the session) on each
  authenticated request. Do not add `tenant_id` to tenant tables and do not write a global tenant scope.
- **`school_id` stays** as a normal column inside the tenant schema (multi-campus within one tenant),
  with a `BelongsToSchool` scope for campus isolation.
- A model is either **central** (extends/uses the central connection) or **tenant** (default). Know which.
- **A tenant-schema `User::created/updated/deleted` automatically syncs the central directory**
  (`App\Observers\SyncTenantUserDirectoryObserver`). Never write to `TenantUserDirectory` directly.

## The non-negotiable rules (full list in @RULES.md)

1. **Respect schema-per-tenant tenancy.** Tenant tables: no `tenant_id`, migrated via `tenants:migrate`.
   Never query across the central/tenant boundary by hand. Within a tenant, scope by `school_id`.
2. **UUID primary keys** (`gen_random_uuid()`) on all major tables.
3. **Financial + published-result records are append-only.** Soft delete only; never hard delete.
   Corrections create a new versioned record + audit entry. Never overwrite in place.
4. **The finance module never initiates payment.** It records evidence and runs verification.
5. **Money is `DECIMAL(15,2)`**, never float. Currency defaults to `TZS`.
6. **Validate at the boundary** (Form Requests) and enforce invariants in the DB (constraints).
7. **Every state-changing action emits a domain event** that feeds audit + notifications.
8. Write the test in the same change as the code. No "tests later."

## Where things are (read on demand)

- @PRD.md — product requirements, all modules
- @docs/prd-financial-module.md — deep finance spec (schemas drop `tenant_id` per ADR-0001)
- @ARCHITECTURE.md — tenancy mechanics, data model, layering, events, frontend, ADR log
- @RULES.md — coding standards + guardrails (read before coding)
- @SKILLS.md — step-by-step recipes (follow them)
- @FRONTEND.md — React + Vite + MUI structure, auth, conventions
- @PROJECT-PLAN.md — phases + task checklist (keep in sync)
- @AGENTS.md — specialist subagents and when to delegate
- @CHANGELOG.md — append on every meaningful change
- @SETUP.md — local environment + bootstrap

## How to work in this repo

- Start from @PROJECT-PLAN.md; pick the current phase's next unchecked task.
- Follow the matching recipe in @SKILLS.md. Don't improvise structure.
- Delegate (see @AGENTS.md): schema → `migration-engineer`, models → `model-architect`,
  endpoints → `api-builder`, finance → `finance-specialist`, React/MUI → `frontend-builder`,
  tests → `test-engineer`, then `code-reviewer` + `security-auditor` before done.
- After a unit of work: run tests, `/verify-tenant-isolation`, update @CHANGELOG.md and @PROJECT-PLAN.md.

## Common commands

```bash
composer install && php artisan key:generate
php artisan migrate                 # CENTRAL tables (tenants, domains, …)
php artisan tenants:migrate         # TENANT tables — the school/finance/etc. schema
php artisan tenants:seed            # tenant seed data
# Never edit a shipped migration; add a new one. Put tenant migrations in database/migrations/tenant.

php artisan serve
php artisan horizon                 # queues: notifications, pdf, imports
npm install && npm run dev          # Vite dev server for the React + MUI SPA

# Quality gates (must pass before "done")
./vendor/bin/pint
php artisan test
php artisan test --filter=Tenant    # schema-isolation tests
```

## Custom slash commands

- `/new-model <Name> [central|tenant] [school]` — scaffold a model + migration + policy + factory + isolation test
- `/new-module <name>` — scaffold a full module slice (backend + React/MUI UI)
- `/new-frontend-feature <name>` — scaffold a React + MUI feature (page, components, API client, types)
- `/verify-tenant-isolation` — run + reason about schema-isolation tests
- `/ship-check` — full pre-merge gate (style, tests, isolation, changelog updated)

## Definition of done (every task)

- [ ] Backend follows @RULES.md and the relevant @SKILLS.md recipe; tenant vs central placement correct
- [ ] Schema isolation verified (data in Tenant A's schema invisible to Tenant B); school scoping tested
- [ ] Feature test: happy path + at least one auth/permission failure
- [ ] Domain event + audit log entry where state changed
- [ ] If UI: React/MUI feature wired to the API, follows @FRONTEND.md
- [ ] `pint` clean, `php artisan test` green
- [ ] @CHANGELOG.md and @PROJECT-PLAN.md updated
