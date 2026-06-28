<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\HostelRoom;
use App\Models\InventoryItem;
use App\Models\PaymentSlip;
use App\Models\StoreRequisition;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds a permission-filtered staff dashboard summary (PRD §5.9). Each
 * metric is included only when the user holds at least one of that widget's
 * Spatie permissions — direct grants from an admin count too.
 */
class DashboardSummaryBuilder
{
    /** @var array<string, list<string>> */
    private const WIDGET_PERMISSIONS = [
        'active_students' => [
            'tenant.manage_schools',
            'finance.manage_fee_structures',
            'academic.manage_subjects',
            'academic.manage_timetable',
        ],
        'attendance_today' => ['attendance.view_class_summary'],
        'payment_slips' => ['finance.verify_slips', 'finance.view_reports'],
        'hostel_occupancy' => [
            'hostel.manage_rooms',
            'hostel.manage_allocations',
            'hostel.view_financial_status',
        ],
        'stores' => [
            'stores.manage_catalog',
            'stores.view_stock',
            'stores.view_requisitions',
            'stores.approve_requisitions',
            'stores.issue_requisitions',
        ],
        'current_academic_session' => [
            'tenant.manage_schools',
            'finance.manage_fee_structures',
            'academic.manage_subjects',
            'academic.manage_timetable',
            'attendance.view_class_summary',
            'finance.verify_slips',
            'finance.view_reports',
            'hostel.manage_rooms',
            'hostel.manage_allocations',
            'hostel.view_financial_status',
            'stores.manage_catalog',
            'stores.view_stock',
            'stores.view_requisitions',
            'stores.approve_requisitions',
            'stores.issue_requisitions',
        ],
    ];

    public function canAccessSummary(User $user): bool
    {
        return $this->allowedWidgets($user)->isNotEmpty();
    }

    public function build(User $user): array
    {
        $allowed = $this->allowedWidgets($user);
        $today = now()->toDateString();
        $data = [];

        if ($allowed->contains('active_students')) {
            $data['active_students'] = Student::where('status', 'active')->count();
        }

        if ($allowed->contains('attendance_today')) {
            $data['attendance_today'] = [
                'present' => AttendanceRecord::where('attendance_date', $today)->where('status', 'present')->count(),
                'absent' => AttendanceRecord::where('attendance_date', $today)->where('status', 'absent')->count(),
            ];
        }

        if ($allowed->contains('payment_slips')) {
            $data['payment_slips'] = [
                'pending' => PaymentSlip::where('status', 'pending')->count(),
                'verified_today_total' => (float) PaymentSlip::where('status', 'verified')
                    ->whereDate('verified_at', $today)
                    ->sum('total_amount'),
            ];
        }

        if ($allowed->contains('hostel_occupancy')) {
            $data['hostel_occupancy'] = [
                'capacity' => (int) HostelRoom::sum('capacity'),
                'rooms' => HostelRoom::count(),
            ];
        }

        if ($allowed->contains('stores')) {
            $data['stores'] = [
                'low_stock_items' => InventoryItem::query()
                    ->where('is_active', true)
                    ->whereColumn('current_quantity', '<=', 'reorder_level')
                    ->count(),
                'pending_requisitions' => StoreRequisition::query()
                    ->whereIn('status', ['submitted', 'approved', 'partially_issued'])
                    ->count(),
            ];
        }

        if ($allowed->contains('current_academic_session')) {
            $data['current_academic_session'] = AcademicSession::where('is_current', true)->value('name');
        }

        return $data;
    }

    /** @return Collection<int, string> */
    private function allowedWidgets(User $user): Collection
    {
        return collect(self::WIDGET_PERMISSIONS)
            ->filter(fn (array $permissions) => $this->userHasAnyPermission($user, $permissions))
            ->keys()
            ->values();
    }

    /** @param list<string> $permissions */
    private function userHasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }
}
