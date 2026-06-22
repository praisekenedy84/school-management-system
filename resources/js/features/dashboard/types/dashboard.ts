/** Mirrors DashboardController::summary()'s JSON shape. */
export interface DashboardSummary {
    active_students: number;
    attendance_today: {
        present: number;
        absent: number;
    };
    payment_slips: {
        pending: number;
        verified_today_total: number;
    };
    hostel_occupancy: {
        capacity: number;
        rooms: number;
    };
    current_academic_session: string | null;
}

/** Mirrors one entry of DashboardController::wards()'s JSON shape. */
export interface WardSummary {
    student_id: string;
    name: string;
    current_class: string | null;
    fee_balance: number | null;
    payment_status: string | null;
    pending_payment_slips: number;
}
