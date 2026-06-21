/**
 * Envelope shape returned by Laravel's default paginator when wrapped in a
 * JsonResource::collection() (e.g. Student::paginate() -> StudentResource::collection()).
 * Mirrors `data`, `links`, `meta` as Laravel emits them.
 */
export interface PaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
}
