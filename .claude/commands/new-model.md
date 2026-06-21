---
description: Scaffold a model (tenant or central) with migration, model, policy, factory, and isolation test
argument-hint: [ModelName] [central|tenant] [school]
---

Scaffold a new domain entity **$1** following `SKILLS.md` Recipe A and `RULES.md`.

- Placement `$2`: **tenant** (default — migration in `database/migrations/tenant`, no `tenant_id`) or
  **central** (`tenants`/`domains`/cross-tenant only — migration in `database/migrations`).
- If `$3` == "school", the entity is school-owned: add `school_id` (indexed, NOT NULL) + `BelongsToSchool`.

Steps (delegate to subagents):
1. **migration-engineer** — create the migration in the correct directory. UUID PK `gen_random_uuid()`;
   **no `tenant_id` on tenant tables**; `school_id` if school-owned; correct types (money `DECIMAL(15,2)`,
   blobs `JSONB`); FKs indexed; `timestamps()`; `softDeletes()` if critical; composite index on the hot
   lookup. Apply with `php artisan tenants:migrate` (tenant) or `php artisan migrate` (central).
2. **model-architect** — model (`HasUuids`[, `BelongsToSchool`][, `SoftDeletes`]), explicit `$fillable`,
   casts, relationships; Policy; Factory. No tenant trait/scope (schema isolation handles tenants).
3. **test-engineer** — isolation test: for tenant models use `tenancy()->initialize()` to prove Tenant B
   can't see Tenant A's $1; for school-owned, prove campus isolation. Run `php artisan test --filter=Tenant`.
4. Update `CHANGELOG.md` (Unreleased → Added) and check the relevant `PROJECT-PLAN.md` item.

Confirm the column list and tenant-vs-central placement with me first if the spec for $1 is ambiguous.
