---
description: Scaffold a full module vertical slice following the project's layering and recipes
argument-hint: [module-name]
---

Build the **$1** module as a complete vertical slice, following `SKILLS.md` Recipe C, `ARCHITECTURE.md`
(§4 layering, §5 events), and `RULES.md`.

Order of work (delegate to subagents; serialize schema and finance, parallelize safe steps):
1. **migration-engineer** — migrations for all of $1's tables (Recipe A per table).
2. **model-architect** — models, relationships, casts, policies, factories.
3. **api-builder** (or **finance-specialist** if $1 touches money) — services encapsulating $1's
   workflows; define past-tense domain events + queued listeners; controllers + Form Requests +
   Resources + routes (under `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains` + `auth:sanctum`).
4. **frontend-builder** — React + Vite + MUI feature for $1 (Recipe J) wired to the new endpoints.
5. Notifications/templates (EN + SW) for any user-facing events in $1.
6. **test-engineer** — schema-isolation + school-scope test per model + feature test per endpoint + one end-to-end workflow test.
7. **code-reviewer** then **security-auditor** (mandatory if $1 touches tenancy/auth/uploads/finance).
8. Update `ARCHITECTURE.md` event list, `CHANGELOG.md`, and `PROJECT-PLAN.md`.

Before starting, read the $1 section in `PRD.md` (and `docs/prd-financial-module.md` if relevant) and
restate the scope + the "done when" criteria back to me.
