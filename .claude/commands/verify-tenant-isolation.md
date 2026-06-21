---
description: Run schema-isolation + school-scope tests and reason about cross-tenant leakage risk
allowed-tools: Bash, Read, Grep, Glob
---

Verify isolation for the `stancl/tenancy` schema-per-tenant setup.

1. Run the isolation suite:
   !`php artisan test --filter=Tenant 2>&1 | tail -40`

2. Static checks (use the **security-auditor** subagent for the reasoning):
   - Confirm tenant routes are wrapped in `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains`.
   - Search for any `tenant_id` column/scope wrongly added to tenant tables, and any tenant migration
     placed outside `database/migrations/tenant`.
   - Search for manual `search_path` / schema setting or `DB::connection('...')` cross-reads between the
     central and tenant connections.
   - Confirm school-owned models use `school_id` + `BelongsToSchool`, and that a campus-scope test exists.
   - Confirm parent-facing endpoints check ownership (guardian↔student, submitter↔slip).

3. Report: models lacking an isolation test, failing tests, and any CRITICAL/HIGH leakage risk with
   file:line + minimal fix. Tenancy/auth-touching work is not done until this is clean.

Note: tenant-isolation tests should initialize tenancy (`tenancy()->initialize($a)` / `$b`) to prove data
created in one tenant's schema is invisible from another.
