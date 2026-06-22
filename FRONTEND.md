# FRONTEND.md — React + Vite + Material UI SPA

The frontend (ADR-0002) is a **TypeScript React SPA bundled by Vite**, using **Material UI (MUI)**,
talking to the Laravel API under `/api/v1`. It lives in `resources/js` and is served per-tenant subdomain.

## 1. Stack

- React 18 + TypeScript · Vite (Laravel's bundler) · React Router · MUI (`@mui/material`) ·
  **icons: `lucide-react`**, not `@mui/icons-material`
- Server state: TanStack Query (React Query) · HTTP: axios · Forms: React Hook Form + a schema validator
- Auth: **Sanctum SPA** (stateful cookie) — same subdomain as the tenant API

## 2. Install / run

```bash
npm install
npm install @mui/material @emotion/react @emotion/styled lucide-react \
            @tanstack/react-query axios react-router-dom react-hook-form
npm run dev      # Vite dev server (HMR)
npm run build    # production build
```

`vite.config.js` uses `laravel-vite-plugin`; the SPA mounts from a catch-all Blade view so deep links work.

## 3. Directory structure (feature-first)

```
resources/js/
├── app/             # bootstrap: providers (QueryClient, Theme, Router), App shell, layout
├── api/             # axios client (baseURL, CSRF, interceptors), shared query/mutation helpers
├── theme/           # MUI theme; reads tenant branding (logo/colours) at runtime
├── routes/          # route table + <RequireAuth>/<RequirePermission> guards
├── components/      # shared, presentational, reusable MUI compositions
├── features/        # one folder per module — owns its pages/components/api/types
│   ├── auth/
│   ├── students/
│   ├── attendance/
│   ├── assessment/
│   ├── payment-slips/        # finance — parent submission + finance verification
│   ├── hostel/
│   └── dashboard/
├── lib/             # formatters (TZS money, dates), i18n catalog (EN/SW), permission helpers
└── types/           # shared TS types mirroring API Resources
```

## 4. Auth flow (Sanctum SPA)

1. On app start, call `GET /sanctum/csrf-cookie`.
2. `POST /login` with credentials; the session cookie is set for the tenant subdomain.
3. `GET /api/v1/me` returns the user + roles + permissions; cache in an auth context.
4. axios sends cookies (`withCredentials: true`) and the `XSRF-TOKEN` header automatically.
5. 401 → redirect to login; 403 → show a "not permitted" state (the API is the source of truth).

## 5. Conventions

- **MUI + theme tokens only.** No ad-hoc inline styles for things the theme covers. Use `sx` sparingly.
- **Icons: `lucide-react`, not `@mui/icons-material`.** Import named icons (e.g. `import { Plus } from
  'lucide-react'`) and size them with the `size` prop (number, px) — not MUI's `fontSize` string prop.
  They use `stroke="currentColor"`, so CSS `color` on a wrapping element (e.g. `ListItemIcon`) still
  tints them correctly.
- **One theme, two modes**: `theme/createAppTheme(mode)` builds a light or dark glassmorphism theme
  (translucent `backdrop-filter` surfaces over a fixed gradient background); `theme/ColorModeProvider`
  holds the persisted (`localStorage`) light/dark choice via `useColorMode()` — call its
  `toggleColorMode()` rather than reaching into `localStorage` directly. Accent is the dark-blue/
  light-blue pair, swapped per mode for contrast; branding (logo/palette) from the tenant's settings
  endpoint at runtime is still a TODO layered on top of this, not yet wired.
- **All server state through React Query hooks** in each feature's `api/`. Components don't call axios directly.
- **Money** formatted via `lib/formatTZS`; never compute money in the client.
- **Permissions** drive route guards and conditional UI. Hiding UI is UX only — the API still authorizes.
- **i18n:** all user-facing copy from the EN/SW catalog; no hardcoded strings.
- **Components are dumb;** logic lives in hooks. Handle loading/error/empty with consistent MUI patterns.
- **TypeScript types** for every request/response mirror the backend Resources in `types/`.

## 6. Example feature shape (payment-slips)

```
features/payment-slips/
├── pages/
│   ├── ParentSubmitSlipPage.tsx      # parent: form + allocation editor + image upload
│   └── FinanceVerificationPage.tsx   # finance: queue table + review drawer
├── components/
│   ├── AllocationEditor.tsx          # ensures allocations sum to total (client hint; API enforces)
│   ├── SlipQueueTable.tsx
│   └── VerifyRejectActions.tsx
├── api/
│   ├── useSubmitSlip.ts
│   ├── useVerificationQueue.ts
│   └── useVerifySlip.ts
└── types/slip.ts
```

## 7. Build & deploy

- `npm run build` emits hashed assets; Laravel serves them via the Vite manifest.
- The same SPA build serves every tenant subdomain; tenant-specific data + branding load at runtime.

> Build UI only after the matching API endpoint contract exists (SKILLS Recipe B). Delegate UI work to
> the `frontend-builder` subagent.
