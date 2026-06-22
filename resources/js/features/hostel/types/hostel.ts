/** Gender restriction for a hostel building (HostelRequest's `gender` enum). */
export type HostelGender = 'male' | 'female' | 'mixed';

/** Mirrors App\Http\Resources\HostelResource. */
export interface Hostel {
    id: string;
    school_id: string;
    name: string;
    gender: HostelGender;
    description: string | null;
    is_active: boolean;
}

/** Body for POST/PUT /api/v1/hostels (App\Http\Requests\Hostel\HostelRequest). */
export interface HostelRequest {
    name: string;
    gender: HostelGender;
    description?: string | null;
    is_active?: boolean | null;
}

/** Mirrors App\Http\Resources\HostelRoomResource — `occupied` is server-computed, never send it back. */
export interface HostelRoom {
    id: string;
    school_id: string;
    hostel_id: string;
    room_number: string;
    capacity: number;
    occupied: number;
    is_active: boolean;
}

/** Body for POST/PUT /api/v1/hostel-rooms (App\Http\Requests\Hostel\HostelRoomRequest). */
export interface HostelRoomRequest {
    hostel_id: string;
    room_number: string;
    capacity: number;
    is_active?: boolean | null;
}

/** Lifecycle status of a hostel allocation (HostelAllocationResource `status`). */
export type HostelAllocationStatus = 'active' | 'ended';

/** Mirrors App\Http\Resources\HostelAllocationResource. */
export interface HostelAllocation {
    id: string;
    school_id: string;
    student_id: string;
    hostel_room_id: string;
    academic_session_id: string;
    status: HostelAllocationStatus;
    allocated_at: string | null;
    ended_at: string | null;
}

/** Body for POST /api/v1/hostel-allocations (App\Http\Requests\Hostel\AllocateHostelRequest). */
export interface AllocateHostelRequest {
    student_id: string;
    hostel_room_id: string;
    academic_session_id: string;
}

/** Mirrors App\Http\Resources\MealPlanResource. */
export interface MealPlan {
    id: string;
    school_id: string;
    hostel_id: string;
    name: string;
    description: string | null;
    price: number | null;
    is_active: boolean;
}

/** Body for POST/PUT /api/v1/meal-plans (App\Http\Requests\Hostel\MealPlanRequest). */
export interface MealPlanRequest {
    hostel_id: string;
    name: string;
    description?: string | null;
    price?: number | null;
    is_active?: boolean | null;
}

/** Lifecycle status of a hostel leave request (HostelLeaveRequestResource `status`). */
export type HostelLeaveStatus = 'pending' | 'approved' | 'rejected';

/** Mirrors App\Http\Resources\HostelLeaveRequestResource. */
export interface HostelLeaveRequest {
    id: string;
    student_id: string;
    hostel_allocation_id: string;
    reason: string;
    depart_at: string | null;
    return_at: string | null;
    status: HostelLeaveStatus;
    decision_notes: string | null;
    decided_at: string | null;
}

/** Body for POST /api/v1/hostel-leave-requests (App\Http\Requests\Hostel\RequestLeaveRequest). */
export interface RequestLeaveRequest {
    hostel_allocation_id: string;
    reason: string;
    depart_at: string;
    return_at: string;
}

/** Body for POST .../approve and .../reject (App\Http\Requests\Hostel\DecideLeaveRequest). */
export interface DecideLeaveRequest {
    decision_notes?: string | null;
}
