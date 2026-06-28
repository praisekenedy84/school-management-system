export interface SelectOption {
    id: string;
    label: string;
    secondary?: string;
}

/** Build a display label from primary text and optional secondary detail. */
export function formatSelectLabel(label: string, secondary?: string | null): string {
    return secondary ? `${label} (${secondary})` : label;
}

/** Map `{ id, name }` records to picker options. */
export function toNameOptions<T extends { id: string; name: string }>(
    items: T[] | undefined,
    secondary?: (item: T) => string | null | undefined,
): SelectOption[] {
    return (items ?? []).map((item) => ({
        id: item.id,
        label: item.name,
        secondary: secondary?.(item) ?? undefined,
    }));
}
