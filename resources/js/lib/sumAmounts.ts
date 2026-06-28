/**
 * Sums pre-computed decimal strings from the API (display hints only).
 * The server remains the source of truth for persisted totals.
 */
export function sumAmountStrings(values: Array<string | null | undefined>): string {
    const total = values.reduce((sum, value) => {
        if (!value) {
            return sum;
        }

        const numeric = Number(value);
        return Number.isNaN(numeric) ? sum : sum + numeric;
    }, 0);

    return total.toFixed(2);
}
