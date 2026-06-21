# School Management System — Claude Code Project Scaffold

A multi-tenant school management platform for day & boarding schools, built East-Africa-first
(Tanzania / TZS / EN + SW). This repository is a **documentation + agent scaffold** to be implemented
with **Claude Code**.

**Locked stack:** Laravel 11 + PostgreSQL 16 · **`stancl/tenancy` schema-per-tenant** (subdomain,
like NexStays) · **React + Vite + Material UI** SPA · Sanctum SPA auth · Redis + Horizon.

## How to use with Claude Code

1. Drop these files at the root of your Laravel repo (or start an empty repo with them).
2. Open the folder in Claude Code — it auto-loads **`CLAUDE.md`**, which imports the rest.
3. Ask it to start **Phase 0** in `PROJECT-PLAN.md`.
4. It delegates to the subagents in `.claude/agents/` and uses the slash commands in `.claude/commands/`.
5. Keep `CHANGELOG.md` and `PROJECT-PLAN.md` updated (the rules enforce it).

## File map

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Always-loaded context hub; golden rules + tenancy model. |
| `PRD.md` | Product requirements — every module. |
| `docs/prd-financial-module.md` | Deep finance spec (schemas drop `tenant_id` under schema-per-tenant). |
| `ARCHITECTURE.md` | Tenancy mechanics, data model, layering, events, frontend, ADR log. |
| `RULES.md` | Engineering rules + guardrails (backend + frontend). Read before coding. |
| `SKILLS.md` | Step-by-step recipes (incl. React/MUI feature recipe). |
| `FRONTEND.md` | React + Vite + MUI SPA structure, auth, conventions. |
| `PROJECT-PLAN.md` | Phased roadmap + live task checklist. |
| `AGENTS.md` | Subagent roster + orchestration. |
| `CHANGELOG.md` | Keep-a-Changelog history. |
| `SETUP.md` | Local setup + bootstrap (stancl + Vite/MUI). |
| `.claude/agents/*.md` | Real Claude Code subagents. |
| `.claude/commands/*.md` | Real Claude Code slash commands. |
| `.claude/settings.json` | Project settings (permissions, env). |

## The rules that matter most

1. **Tenant tables have no `tenant_id`** — isolation is the Postgres schema (stancl switches it). Tenant
   migrations live in `database/migrations/tenant` and run via `php artisan tenants:migrate`. Within a
   tenant, isolate campuses with `school_id` + `BelongsToSchool`.
2. **Finance records, never transacts.** Append-only finance + published results; soft delete only.

## Status

Pre-implementation. ADR-0001 (tenancy) and ADR-0002 (frontend) are **locked**. ADR-0007 (SMS provider)
is open and needed by Phase 4.
