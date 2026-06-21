<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * RULES.md §5 grants `teacher`/`class_teacher` the `attendance.take`
 * permission directly, so — unlike the still-placeholder Subject/Assessment
 * policies — `create` here is a real rule, not a Phase 0/1 stand-in.
 * `update`/`delete` remain admin-only pending a "did this teacher own the
 * class/period" ownership check (RecordAttendanceRequest's cross-school
 * check covers campus, not per-teacher class assignment yet).
 */
class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin', 'academic_director', 'class_teacher', 'teacher']);
    }

    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }

    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->hasRole(['tenant_admin', 'school_admin']);
    }
}
