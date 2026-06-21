import axios, { type AxiosInstance } from 'axios';

/**
 * Shared typed axios client for the tenant API (/api/v1).
 *
 * - `withCredentials` sends the Sanctum session cookie cross-request.
 * - `withXSRFToken` tells axios 1.x to read the `XSRF-TOKEN` cookie (set by
 *   GET /sanctum/csrf-cookie) and attach it as the `X-XSRF-TOKEN` header on
 *   every request automatically — no manual header wiring needed, as long as
 *   the SPA and API share an origin/subdomain (they do: same tenant subdomain).
 * - Components must NEVER import this directly; only feature `api/` hooks do.
 */
export const apiClient: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

/**
 * A second client for the one root-level (non `/api/v1`) endpoint we need:
 * GET /sanctum/csrf-cookie. Sanctum issues this from the web middleware
 * group, not the api/v1 prefix, so it needs its own baseURL.
 */
export const sanctumClient: AxiosInstance = axios.create({
    baseURL: '/',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
    },
});

/**
 * Fetches the CSRF cookie. Must be called before the first state-changing
 * request (login) in a fresh browser session/tab.
 */
export async function ensureCsrfCookie(): Promise<void> {
    await sanctumClient.get('/sanctum/csrf-cookie');
}

/**
 * 401 handling: callers (the auth context) decide what a 401 means —
 * "not logged in yet" during the initial /me probe is not an error to
 * surface, whereas a 401 after the app thinks it's authenticated means the
 * session expired and should trigger a redirect to /login. We deliberately
 * do NOT hardcode a redirect here; that's app-level navigation concern.
 */
export const UNAUTHENTICATED_STATUS = 401;
export const FORBIDDEN_STATUS = 403;
