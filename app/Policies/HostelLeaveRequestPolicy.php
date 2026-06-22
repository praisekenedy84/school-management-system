<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HostelLeaveRequest;
use App\Models\User;

/**
 * RULES.md §5: hostel_manager holds hostel.approve_leave. Parents may
 * request leave for their own ward (checked in the FormRequest via
 * wards(), mirroring PaymentSlipPolicy's create/view split); only
 * hostel_manager/admins can decide (approve/reject).
 */
class HostelLeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HostelLeaveRequest $hostelLeaveRequest): bool
    {
        if ($user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager'])) {
            return true;
        }

        if ($user->hasRole('parent')) {
            return $user->wards()->whereKey($hostelLeaveRequest->student_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager', 'parent']);
    }

    public function decide(User $user, HostelLeaveRequest $hostelLeaveRequest): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'hostel_manager']);
    }
}
