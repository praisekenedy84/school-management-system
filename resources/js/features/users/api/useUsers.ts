import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';

export const USERS_QUERY_KEY = ['users'] as const;

export type UserLookupRole = 'teacher' | 'parent';

export interface UserLookup {
    id: string;
    name: string;
    email: string;
}

export interface UserLookupFilters {
    role: UserLookupRole;
    search?: string;
    school_id?: string;
}

/** GET /api/v1/users?role=&search=&school_id= — searchable teacher/guardian picker. */
export function useUsers(filters: UserLookupFilters): UseQueryResult<UserLookup[]> {
    return useQuery({
        queryKey: [...USERS_QUERY_KEY, 'lookup', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: UserLookup[] }>('/users', {
                params: {
                    role: filters.role,
                    search: filters.search || undefined,
                    school_id: filters.school_id || undefined,
                },
            });

            return data.data;
        },
        staleTime: 60 * 1000,
    });
}
