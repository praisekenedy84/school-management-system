import axios, { type AxiosInstance } from 'axios';
import { queryClient, AUTH_ME_QUERY_KEY } from './queryClient';
import { setAuthRedirectReason } from '../lib/authRedirectReason';
import { getErrorMessage } from '../lib/getErrorMessage';

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

export const UNAUTHENTICATED_STATUS = 401;
export const FORBIDDEN_STATUS = 403;

/**
 * 401 handling: any request answered with 401 means there is no valid
 * session (it never had one, or it just expired) — clear the cached `/me`
 * user so `isAuthenticated` flips false and `<RequireAuth>` redirects to
 * `/login` on its own. This is the one piece of "app-level navigation"
 * concern client.ts needs to own, since it's the only place every request
 * passes through; it does NOT redirect directly (no router access here) —
 * the route guard does that from the now-cleared auth state.
 *
 * Only record a redirect reason when this is a real expiration — i.e. we
 * previously HAD a cached user and just lost it. The very first `/me` probe
 * on a fresh visit also 401s (nobody's logged in yet); that's not "your
 * session expired", so it must not show that message on the login page.
 */
apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        // Export/template-download requests use responseType: 'blob' so a
        // successful response can be handed straight to downloadBlobResponse().
        // An ERROR response under that same request config still arrives as
        // a Blob, not parsed JSON — getErrorMessage() can't read `.message`
        // off a Blob, so unwrap it here once, for every caller.
        if (error?.response?.data instanceof Blob && error.response.data.type.includes('json')) {
            try {
                error.response.data = JSON.parse(await error.response.data.text());
            } catch {
                // Leave it as a Blob if it somehow isn't valid JSON; getErrorMessage's fallback still applies.
            }
        }

        if (error?.response?.status === UNAUTHENTICATED_STATUS) {
            const wasAuthenticated = Boolean(queryClient.getQueryData(AUTH_ME_QUERY_KEY));

            if (wasAuthenticated) {
                setAuthRedirectReason(getErrorMessage(error, 'Your session has expired. Please log in again.'));
            }

            queryClient.setQueryData(AUTH_ME_QUERY_KEY, null);
        }

        return Promise.reject(error);
    },
);
