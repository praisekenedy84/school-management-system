/**
 * Shared TZS money formatter (RULES.md §8: "money formatted via a shared
 * formatter; never do money math in the client"). Components must never
 * compute money themselves — only format values the API already computed
 * (ledger balances, slip totals, allocation amounts).
 */
const formatter = new Intl.NumberFormat('en-TZ', {
    style: 'currency',
    currency: 'TZS',
    maximumFractionDigits: 2,
});

/** Formats a decimal-as-string-or-number value the API returned as TZS, e.g. "TZS 150,000.00". */
export function formatMoney(amount: number | string | null | undefined, currency: string = 'TZS'): string {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }

    const numeric = typeof amount === 'string' ? Number(amount) : amount;

    if (Number.isNaN(numeric)) {
        return '—';
    }

    if (currency !== 'TZS') {
        return new Intl.NumberFormat('en-TZ', { style: 'currency', currency }).format(numeric);
    }

    return formatter.format(numeric);
}
