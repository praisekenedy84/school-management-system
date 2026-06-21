---
description: Scaffold a React + Vite + MUI feature (types, API hooks, pages, components) wired to an existing endpoint
argument-hint: [feature-name]
---

Build the **$1** frontend feature following `FRONTEND.md`, `SKILLS.md` Recipe J, and `RULES.md` §8.

Prerequisite: the backend endpoint contract for $1 must already exist (SKILLS Recipe B). If it doesn't,
stop and flag it — don't invent a contract.

Steps (delegate to the **frontend-builder** subagent):
1. Create `resources/js/features/$1/` with `pages/`, `components/`, `api/`, `types/`.
2. **types/**: TS types mirroring the API Resources for $1.
3. **api/**: React Query hooks wrapping the shared typed axios client (queries + mutations).
4. **components/ + pages/**: compose MUI using the shared theme; format money via the TZS formatter;
   pull strings from the EN/SW i18n catalog; handle loading/error/empty states.
5. **Guards**: gate routes/actions on the user's permissions (mirror backend RBAC).
6. Wire into the SPA router; verify against `npm run dev`; keep `npm run build` green.
7. Update `CHANGELOG.md` (Unreleased → Added) and check the relevant `PROJECT-PLAN.md` item.

Keep components dumb (logic in hooks). Do not change API contracts; report gaps instead.
