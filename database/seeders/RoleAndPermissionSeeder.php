<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the RBAC matrix from RULES.md §5. Tenant-wide roles (tenant_admin,
 * super_admin) get every permission; school_admin gets everything except
 * tenant-level settings; the rest get the explicit grants from the table.
 */
class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionsByRole = [
            'tenant_admin' => [
                'tenant.manage_schools',
                'tenant.manage_branding',
                'tenant.manage_settings',
                'tenant.manage_billing',
                'tenant.manage_navigation',
                'users.manage_roles',
                'rbac.manage_roles',
            ],
            'academic_director' => [
                'academic.manage_subjects',
                'academic.manage_classes',
                'academic.manage_timetable',
                'academic.manage_assignments',
                'assessment.publish_results',
                'assessment.manage_grading',
            ],
            'finance_manager' => [
                'finance.verify_slips',
                'finance.approve_payments',
                'finance.generate_receipts',
                'finance.manage_fee_structures',
                'finance.reconciliation',
                'stores.approve_purchases',
                'stores.fulfill_purchases',
            ],
            'accountant' => [
                'finance.verify_slips',
                'finance.generate_receipts',
                'finance.record_payments',
                'finance.view_reports',
                'stores.approve_purchases',
                'stores.fulfill_purchases',
            ],
            'storekeeper' => [
                'stores.manage_catalog',
                'stores.approve_requisitions',
                'stores.issue_requisitions',
                'stores.create_requisitions',
                'stores.view_requisitions',
                'stores.create_purchase_requests',
                'stores.view_stock',
                'stores.view_movements',
            ],
            'kitchen_staff' => [
                'stores.create_requisitions',
                'stores.view_requisitions',
                'stores.view_stock',
            ],
            'hostel_manager' => [
                'hostel.manage_rooms',
                'hostel.manage_allocations',
                'hostel.approve_leave',
                'hostel.meal_management',
                'hostel.view_financial_status',
            ],
            'class_teacher' => [
                'academic.manage_assignments',
                'attendance.take',
                'assessment.enter_marks',
                'students.view_basic_info',
                'academic.manage_class',
                'attendance.view_class_summary',
                'assessment.assemble_report_card',
            ],
            'teacher' => [
                'academic.manage_assignments',
                'attendance.take',
                'assessment.enter_marks',
                'students.view_basic_info',
            ],
            'parent' => [
                'finance.submit_slips',
                'finance.view_own_payments',
                'finance.download_receipts',
                'students.view_own_children',
                'academic.view_child_results',
            ],
            'student' => [
                'finance.view_own_fee_status',
                'academic.view_assignments',
                'academic.submit_assignments',
                'assessment.view_own_results',
            ],
            'auditor' => [
                'audit.view_financial',
                'audit.view_results',
                'audit.view_access_logs',
                'stores.view_movements',
            ],
        ];

        $allPermissionNames = collect($permissionsByRole)
            ->flatten()
            ->merge(array_keys(config('permission-catalog', [])))
            ->unique()
            ->values();

        $allPermissionNames->each(
            fn (string $name) => Permission::findOrCreate($name, 'web')
        );

        foreach (array_keys($permissionsByRole) as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
        Role::findOrCreate('school_admin', 'web');
        Role::findOrCreate('super_admin', 'web');

        foreach ($permissionsByRole as $roleName => $permissions) {
            Role::findByName($roleName, 'web')->syncPermissions($permissions);
        }

        // school_admin: everything except tenant-wide settings.
        $schoolAdminPermissions = $allPermissionNames
            ->reject(fn (string $name) => str_starts_with($name, 'tenant.'))
            ->values();
        Role::findByName('school_admin', 'web')->syncPermissions($schoolAdminPermissions);

        // tenant_admin / super_admin: every permission that exists.
        Role::findByName('tenant_admin', 'web')->syncPermissions($allPermissionNames);
        Role::findByName('super_admin', 'web')->syncPermissions($allPermissionNames);
    }
}
