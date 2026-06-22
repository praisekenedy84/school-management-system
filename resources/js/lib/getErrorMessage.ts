import { isAxiosError } from 'axios';

/**
 * Extracts a user-friendly message from an API error. Backend error
 * responses are shaped consistently (bootstrap/app.php's `withExceptions`):
 * `{ message: string, errors?: Record<string, string[]> }`, and the
 * `message` is always already friendly (no raw exception text, no stack
 * traces, no leaked class names — even 404s/403s/500s are rewritten
 * server-side). Components must never display `error.message` or any other
 * raw JS/axios property directly; always go through this.
 */
export function getErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    if (isAxiosError(error)) {
        if (!error.response) {
            return 'Unable to reach the server. Please check your connection and try again.';
        }

        const message = (error.response.data as { message?: unknown } | undefined)?.message;
        if (typeof message === 'string' && message.length > 0) {
            return message;
        }
    }

    return fallback;
}

/** Field-level validation errors from a 422 response (`{ errors: { field: [msg] } }`), or empty. */
export function getFieldErrors(error: unknown): Record<string, string[]> {
    if (isAxiosError(error) && error.response?.status === 422) {
        return (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {};
    }

    return {};
}
