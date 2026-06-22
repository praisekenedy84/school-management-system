const STORAGE_KEY = 'sms:auth-redirect-reason';

/**
 * One-shot "why was I sent to the login page" message, passed across a full
 * SPA navigation via sessionStorage (a React state/context value wouldn't
 * survive `<RequireAuth>` unmounting the authenticated tree). Read once via
 * `consumeAuthRedirectReason()` — that call clears it, so it never reappears
 * on a later, unrelated visit to /login.
 */
export function setAuthRedirectReason(message: string): void {
    sessionStorage.setItem(STORAGE_KEY, message);
}

export function consumeAuthRedirectReason(): string | null {
    const message = sessionStorage.getItem(STORAGE_KEY);

    if (message !== null) {
        sessionStorage.removeItem(STORAGE_KEY);
    }

    return message;
}
