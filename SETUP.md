# SETUP.md — Local Development & Bootstrap

## 1. Locked decisions

| ADR | Decision |
|-----|----------|
| ADR-0001 Tenancy | `stancl/tenancy`, **PostgreSQL schema-per-tenant**, subdomain identification (like NexStays) |
| ADR-0002 Frontend | **React SPA + Vite + Material UI**, Sanctum SPA (cookie) auth |
| ADR-0007 SMS | **OPEN** — choose a TZ-capable gateway before Phase 4 |

## 2. Prerequisites

```
PHP 8.2+   ·   Composer 2   ·   PostgreSQL 16   ·   Redis 7   ·   Node 20+
```
Local subdomain testing: map `*.sms.test` (or similar) to 127.0.0.1 (Valet/Herd wildcard, or hosts entries
like `acme.sms.test`, `central.sms.test`).

## 3. Backend bootstrap

```bash
composer create-project laravel/laravel school-management
cd school-management

cp .env.example .env && php artisan key:generate
# .env: DB_CONNECTION=pgsql, DB_DATABASE=sms, REDIS_HOST=127.0.0.1,
#       QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=database,
#       SANCTUM_STATEFUL_DOMAINS=*.sms.test, SESSION_DOMAIN=.sms.test
#
# SESSION_DRIVER must be 'database' (or another server-side store), never
# 'cookie': the cookie driver puts the whole session payload client-side,
# encrypted with the app's single shared APP_KEY — with one Laravel
# installation serving every tenant, a session minted on one tenant
# subdomain would decrypt and authenticate just as validly on another.
# The 'database' driver keeps session rows inside each tenant's own Postgres
# schema (see database/migrations/tenant/*_create_sessions_table.php), so a
# session is physically unreadable outside the schema it was created in —
# consistent with how every other tenant table is isolated (ADR-0001).
# Until Redis/Horizon is set up, the same reasoning applies to
# QUEUE_CONNECTION/CACHE_STORE: use 'database' locally, not 'redis', unless
# Redis tenancy bootstrapping is also configured.

composer require laravel/sanctum laravel/horizon barryvdh/laravel-dompdf intervention/image
composer require stancl/tenancy
# RBAC: composer require spatie/laravel-permission   (or custom scoped RBAC)

php artisan tenancy:install        # publishes config + central tables (tenants, domains)
# config/tenancy.php → enable PostgreSQL schema separation:
#   'database' => ['managers' => ['pgsql' =>
#     Stancl\Tenancy\Database\TenantDatabaseManagers\PostgreSQLSchemaManager::class ]]
#   (confirm exact namespace against the installed stancl/tenancy version)

php artisan migrate                # central tables
# Put tenant tables' migrations in database/migrations/tenant, then:
php artisan tenants:migrate        # apply across all tenant schemas
php artisan db:seed                # central seed (demo tenant + domain)
php artisan tenants:seed           # per-tenant seed (roles/permissions, demo school/data)
```

Wire tenant routes (`routes/tenant.php`) behind `InitializeTenancyBySubdomain` +
`PreventAccessFromCentralDomains`; keep central routes (marketing/super-admin) on the central domain.

## 4. Frontend bootstrap (React + Vite + MUI)

```bash
npm install
npm install @mui/material @emotion/react @emotion/styled @mui/icons-material \
            @tanstack/react-query axios react-router-dom react-hook-form
# Scaffold resources/js per FRONTEND.md (app/ api/ theme/ routes/ components/ features/ lib/ types/)
npm run dev        # Vite HMR
```

## 5. Run

```bash
php artisan serve          # API + Blade shell
php artisan horizon        # queues: notifications, pdf, imports, default
npm run dev                # SPA dev server
```

## 6. Quality gates (must pass before "done")

```bash
./vendor/bin/pint
php artisan test
php artisan test --filter=Tenant   # schema + school isolation
```
Or run `/ship-check` in Claude Code.

## 7. Environment notes

- **Local only:** `migrate:fresh` / `tenants:migrate --fresh`. Never on staging/prod.
- Storage paths are tenant/school-scoped (ARCHITECTURE §7); ensure `storage/app` writable / S3 configured.
- Encrypt financial fields at rest; never commit `.env`/secrets.
- Deployment: Forge-managed Ubuntu VPS, Horizon supervised; run `tenants:migrate` on deploy (matches NexStays).

## 8. First task

Open Claude Code here and say:
> "Read CLAUDE.md, then start Phase 0 in PROJECT-PLAN.md: bootstrap stancl/tenancy schema-per-tenant,
> the central/tenant migration split, and the React+Vite+MUI SPA shell."
