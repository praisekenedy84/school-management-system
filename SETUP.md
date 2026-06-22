# SETUP.md — Local Development & Bootstrap

## 1. Locked decisions

| ADR | Decision |
|-----|----------|
| ADR-0001 Tenancy | `stancl/tenancy`, **PostgreSQL schema-per-tenant** |
| ADR-0002 Frontend | **React SPA + Vite + Material UI**, Sanctum SPA (cookie) auth |
| ADR-0007 SMS | **OPEN** — choose a TZ-capable gateway before Phase 4 |
| ADR-0008 Tenant ID | **Credential-based** (central directory), single domain, no subdomains |

## 2. Prerequisites

```
PHP 8.2+   ·   Composer 2   ·   PostgreSQL 16   ·   Redis 7   ·   Node 20+
```
No hosts-file/DNS setup needed (ADR-0008) — one domain serves every tenant. Just visit
`http://localhost:8000` once `php artisan serve` is running.

## 3. Backend bootstrap

```bash
composer create-project laravel/laravel school-management
cd school-management

cp .env.example .env && php artisan key:generate
# .env: DB_CONNECTION=pgsql, DB_DATABASE=sms, REDIS_HOST=127.0.0.1,
#       QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=database,
#       SESSION_CONNECTION=pgsql,
#       SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:8000,127.0.0.1:8000,
#       SESSION_DOMAIN= (empty — single domain, no leading-dot needed)
#
# SESSION_DRIVER must be 'database' (or another server-side store), never
# 'cookie' (would put the whole payload client-side, encrypted with one
# shared APP_KEY across every tenant).
#
# SESSION_CONNECTION must be pinned to the literal 'pgsql' connection name
# (ADR-0008), NOT left to default to whatever `database.default` currently
# is. /login and every subsequent request need to read the session BEFORE
# any tenant is known — stancl's DatabaseTenancyBootstrapper swaps
# `database.default` to a separately-named 'tenant' connection while a
# tenant is initialized, but never touches 'pgsql' itself, so pinning the
# session there keeps it readable regardless of tenancy state. See
# App\Http\Middleware\InitializeTenancyFromSession.
#
# Until Redis/Horizon is set up, the same database-driver-locally reasoning
# applies to QUEUE_CONNECTION/CACHE_STORE.

composer require laravel/sanctum laravel/horizon barryvdh/laravel-dompdf intervention/image
composer require stancl/tenancy
# RBAC: composer require spatie/laravel-permission   (or custom scoped RBAC)

php artisan tenancy:install        # publishes config + central tables (tenants, domains)
# config/tenancy.php → enable PostgreSQL schema separation:
#   'database' => ['managers' => ['pgsql' =>
#     Stancl\Tenancy\Database\TenantDatabaseManagers\PostgreSQLSchemaManager::class ]]
#   (confirm exact namespace against the installed stancl/tenancy version)

php artisan migrate                # central tables
php artisan db:seed --class=PlatformAdminSeeder   # central: platform-admin@sms.test / password
# Put tenant tables' migrations in database/migrations/tenant, then:
php artisan tenants:migrate        # apply across all tenant schemas
php artisan tenants:seed           # per-tenant seed (roles/permissions, demo school/data)
```

Tenant is identified by login credentials, not by domain (ADR-0008) — `/login` looks the email up
in the central `tenant_user_directory` table and initializes that tenant itself; every other
authenticated route relies on `App\Http\Middleware\InitializeTenancyFromSession`. There's no
separate "central domain" to wire up routes against.

`PlatformAdminSeeder` is a **central** seeder (writes to `platform_admins`, never a tenant schema) —
run it directly via `db:seed --class=`, not through `tenants:seed`. It's idempotent (`firstOrCreate`
by email), so re-running it locally is safe.

## 4. Frontend bootstrap (React + Vite + MUI)

```bash
npm install
npm install @mui/material @emotion/react @emotion/styled lucide-react \
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
