# AGENTS.md — Specialist Subagents & Orchestration

This project ships a team of Claude Code **subagents** (`.claude/agents/`). Each runs in its own context
window with a scoped tool set. This file is the human-readable map; the real definitions (YAML frontmatter
+ system prompt) live in `.claude/agents/*.md`.

> Auto-delegated by `description`, or call explicitly:
> *"Use the migration-engineer subagent to create the attendance_records tenant migration."*

## The roster

| Subagent | Role | Tools (scope) | Model |
|----------|------|---------------|-------|
| `model-architect` | Eloquent models (tenant vs central), relationships, casts, policies, factories | read/write code | sonnet |
| `migration-engineer` | PostgreSQL migrations — central vs `tenant/`, schema, indexes, constraints | read/write + bash (artisan) | sonnet |
| `api-builder` | Routes, FormRequests, thin controllers, services, resources (non-finance) | read/write code | sonnet |
| `finance-specialist` | The finance module: slips, verification, receipts, ledgers | read/write code | opus |
| `frontend-builder` | React + Vite + MUI SPA: features, components, API hooks, types, guards | read/write code + bash (npm) | sonnet |
| `test-engineer` | Feature tests, schema + school isolation tests, end-to-end flows | read/write + bash (test) | sonnet |
| `security-auditor` | Read-only security + tenancy-isolation review | read-only (Read/Grep/Glob) | opus |
| `code-reviewer` | Read-only quality/standards review vs RULES.md | read-only (Read/Grep/Glob) | sonnet |

Read-only reviewers (`security-auditor`, `code-reviewer`) cannot modify files — they report; an
implementer subagent or the main session acts on findings.

## Standard pipeline per backend task

```
1. PLAN        main session picks next PROJECT-PLAN task, reads the SKILLS recipe
2. SCHEMA      → migration-engineer    (central vs database/migrations/tenant; no tenant_id)
3. DOMAIN      → model-architect       (models, policies, factories; BelongsToSchool where needed)
4. BEHAVIOR    → api-builder / finance-specialist   (services, endpoints, events)
5. UI          → frontend-builder      (React/MUI feature wired to the endpoint)
6. TESTS       → test-engineer         (schema isolation + school scope + feature + workflow)
7. REVIEW      → code-reviewer then security-auditor
8. SHIP        main session runs /ship-check, updates CHANGELOG + PROJECT-PLAN
```

Serialize high-risk steps (schema, finance, security review). Parallelize safe ones (frontend-builder can
start types/components once the endpoint contract is fixed).

## When to use which

- **New entity?** migration-engineer → model-architect → test-engineer (Recipe A). Decide tenant vs central first.
- **New endpoint?** api-builder → test-engineer → code-reviewer (Recipe B).
- **Money/slips/receipts/ledgers?** Route through **finance-specialist** (record-don't-transact, append-only,
  allocation invariants, slip→receipt flow).
- **Any UI?** **frontend-builder** (Recipe J) — only after the endpoint contract exists.
- **Before done?** code-reviewer + security-auditor. Security-auditor is mandatory for anything touching
  tenancy, auth, uploads, or finance.

## Tenancy reminders for every backend subagent

- Tenant tables have **no `tenant_id`**; their migrations live in `database/migrations/tenant`.
- Isolation between tenants is the Postgres schema (stancl switches it) — don't add a tenant scope.
- Isolate campuses within a tenant via `school_id` + `BelongsToSchool`; that's the scope to test.

## Guardrails for every subagent

- Obey `RULES.md`; follow the matching `SKILLS.md` recipe.
- Stay in your lane: reviewers don't write; migration-engineer adds no business logic; frontend-builder
  doesn't change API contracts (it consumes them).
- Return a concise summary (what changed, what to verify next), not a transcript.
- Out of scope for your role? Say so and hand back.

## Notes

- Override a model in frontmatter (`model: inherit` to match the main session).
- Reviewer tool allowlists are intentionally tight; widen only with a reason.
- Project agents are committed to version control so the whole team shares the same specialists.
