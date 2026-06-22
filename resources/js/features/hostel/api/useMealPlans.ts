import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { MealPlan, MealPlanRequest } from '../types/hostel';

export const MEAL_PLANS_QUERY_KEY = ['meal-plans'] as const;

export interface MealPlanFilters {
    hostel_id?: string;
}

/**
 * GET /api/v1/meal-plans?hostel_id= — non-paginated bare collection. There is
 * no single-resource `show` endpoint (MealPlanController has no `show`
 * method) — only index/store/update/destroy, so a meal plan is always edited
 * from the row already present in the list, never re-fetched individually.
 */
export function useMealPlans(filters: MealPlanFilters = {}): UseQueryResult<MealPlan[]> {
    return useQuery({
        queryKey: [...MEAL_PLANS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: MealPlan[] }>('/meal-plans', { params: filters });
            return data.data;
        },
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/meal-plans */
export function useCreateMealPlan() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: MealPlanRequest) => {
            const { data } = await apiClient.post<ApiResource<MealPlan>>('/meal-plans', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: MEAL_PLANS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/meal-plans/{mealPlan} */
export function useUpdateMealPlan() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: MealPlanRequest }) => {
            const { data } = await apiClient.put<ApiResource<MealPlan>>(`/meal-plans/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: MEAL_PLANS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/meal-plans/{mealPlan} */
export function useDeleteMealPlan() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/meal-plans/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: MEAL_PLANS_QUERY_KEY });
        },
    });
}
