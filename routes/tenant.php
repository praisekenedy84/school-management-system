<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Academic\AcademicSessionController;
use App\Http\Controllers\Api\Academic\AcademicTermController;
use App\Http\Controllers\Api\Academic\AssignmentController;
use App\Http\Controllers\Api\Academic\ClassRoomController;
use App\Http\Controllers\Api\Academic\ClassSubjectController;
use App\Http\Controllers\Api\Academic\StreamController;
use App\Http\Controllers\Api\Academic\SubjectController;
use App\Http\Controllers\Api\Academic\TeacherAssignmentController;
use App\Http\Controllers\Api\Admin\AdminNavigationController;
use App\Http\Controllers\Api\Admin\AdminRoleController;
use App\Http\Controllers\Api\Admin\AdminSchoolController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Assessment\AssessmentController;
use App\Http\Controllers\Api\Assessment\GradingScaleController;
use App\Http\Controllers\Api\Assessment\ReportCardController;
use App\Http\Controllers\Api\Assessment\ResultController;
use App\Http\Controllers\Api\Assessment\ResultPublishController;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Finance\FeeStructureController;
use App\Http\Controllers\Api\Finance\PaymentMethodController;
use App\Http\Controllers\Api\Finance\PaymentSlipController;
use App\Http\Controllers\Api\Finance\PaymentSlipVerificationController;
use App\Http\Controllers\Api\Finance\StudentFeeStatementController;
use App\Http\Controllers\Api\Hostel\HostelAllocationController;
use App\Http\Controllers\Api\Hostel\HostelController;
use App\Http\Controllers\Api\Hostel\HostelLeaveRequestController;
use App\Http\Controllers\Api\Hostel\HostelRoomController;
use App\Http\Controllers\Api\Hostel\MealPlanController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\Platform\AuditLogController;
use App\Http\Controllers\Api\Platform\ImpersonationController;
use App\Http\Controllers\Api\Platform\PlatformNavigationController;
use App\Http\Controllers\Api\Platform\PlatformSettingsController;
use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\Sis\EnrolmentController;
use App\Http\Controllers\Api\Sis\StudentController;
use App\Http\Controllers\Api\Sis\StudentGuardianController;
use App\Http\Controllers\Api\Stores\InventoryItemController;
use App\Http\Controllers\Api\Stores\PurchaseRequestController;
use App\Http\Controllers\Api\Stores\StockMovementController;
use App\Http\Controllers\Api\Stores\StoreRequisitionController;
use App\Http\Controllers\Api\Tenant\SchoolController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\InitializeTenancyFromSession;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| ADR-0008: single domain — the SPA shell and its /api/v1 are served the
| same way regardless of which (if any) tenant the visitor turns out to be.
| /login is the one route that runs with no tenant initialized: it looks
| the email up in the central directory and initializes tenancy itself
| (see AuthController::login). Every other authenticated route relies on
| InitializeTenancyFromSession to have already done that from the session.
|
*/

Route::middleware(['web'])
    ->get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->name('sanctum.csrf-cookie');

Route::prefix('api/v1')
    ->middleware(['api'])
    ->group(function () {
        // RULES.md §7: rate limit auth endpoints against brute force.
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware([InitializeTenancyFromSession::class, 'auth:sanctum'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Tenant-resource routes require the `web` guard specifically, not
        // the generic `auth:sanctum` (which also accepts a bare Platform
        // Admin session via Sanctum's multi-guard fallback — see
        // config('sanctum.guard') = ['web', 'platform'] and
        // Laravel\Sanctum\Guard::__invoke(), which returns the first guard
        // in that list with a user, 'platform' included). A Platform Admin
        // who hasn't impersonated anyone has no `tenant_id` in session, so
        // InitializeTenancyFromSession is a no-op for them — letting them
        // fall through here used to reach these routes with NO tenant
        // initialized, hitting the central schema instead of a tenant's
        // (no `students`/`users` table there: SQLSTATE[42P01]). Mirrors
        // EnsurePlatformAdmin's same principle in reverse, just below.
        // Impersonation is unaffected: ImpersonationService explicitly logs
        // the target in on the `web` guard, so `auth:web` passes normally.
        Route::middleware([InitializeTenancyFromSession::class, 'auth:web'])->group(function () {
            // User lookup — searchable pickers for teachers / guardians (Recipe B).
            Route::get('/users', [UserController::class, 'index']);

            // SIS — students, guardians, enrolments/promotion (Recipe B).
            Route::get('/students', [StudentController::class, 'index']);
            Route::get('/students/export', [StudentController::class, 'export']);
            Route::get('/students/import-template', [StudentController::class, 'importTemplate']);
            Route::post('/students/import', [StudentController::class, 'import']);
            Route::post('/students', [StudentController::class, 'store']);
            Route::get('/students/{student}', [StudentController::class, 'show']);
            Route::get('/students/{student}/fee-statement', [StudentFeeStatementController::class, 'show']);
            Route::post('/students/{student}/guardians', [StudentGuardianController::class, 'store']);
            Route::delete('/students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy']);

            Route::post('/enrolments/{enrolment}/promote', [EnrolmentController::class, 'promote']);

            // Academics — classes, sessions, subjects, class↔subject mapping, teacher assignments, assignments.
            // Read-only school lookup — feeds the tenant-admin "which
            // school" picker on create forms that need one.
            Route::get('/schools', [SchoolController::class, 'index']);

            Route::get('/classes', [ClassRoomController::class, 'index']);
            Route::get('/classes/export', [ClassRoomController::class, 'export']);
            Route::get('/classes/import-template', [ClassRoomController::class, 'importTemplate']);
            Route::post('/classes/import', [ClassRoomController::class, 'import']);
            Route::post('/classes', [ClassRoomController::class, 'store']);
            Route::get('/classes/{classRoom}', [ClassRoomController::class, 'show']);
            Route::put('/classes/{classRoom}', [ClassRoomController::class, 'update']);
            Route::delete('/classes/{classRoom}', [ClassRoomController::class, 'destroy']);

            Route::get('/academic-sessions', [AcademicSessionController::class, 'index']);
            Route::get('/academic-sessions/export', [AcademicSessionController::class, 'export']);
            Route::post('/academic-sessions', [AcademicSessionController::class, 'store']);
            Route::get('/academic-sessions/{academicSession}', [AcademicSessionController::class, 'show']);
            Route::put('/academic-sessions/{academicSession}', [AcademicSessionController::class, 'update']);
            Route::delete('/academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy']);

            Route::get('/academic-sessions/{academicSession}/terms', [AcademicTermController::class, 'index']);
            Route::post('/academic-sessions/{academicSession}/terms', [AcademicTermController::class, 'store']);
            Route::put('/academic-sessions/{academicSession}/terms/{term}', [AcademicTermController::class, 'update']);
            Route::delete('/academic-sessions/{academicSession}/terms/{term}', [AcademicTermController::class, 'destroy']);

            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::get('/subjects/export', [SubjectController::class, 'export']);
            Route::get('/subjects/import-template', [SubjectController::class, 'importTemplate']);
            Route::post('/subjects/import', [SubjectController::class, 'import']);
            Route::post('/subjects', [SubjectController::class, 'store']);
            Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
            Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);

            Route::get('/classes/{classRoom}/subjects', [ClassSubjectController::class, 'index']);
            Route::post('/classes/{classRoom}/subjects', [ClassSubjectController::class, 'store']);
            Route::delete('/classes/{classRoom}/subjects/{subject}', [ClassSubjectController::class, 'destroy']);

            Route::get('/classes/{classRoom}/streams', [StreamController::class, 'index']);
            Route::post('/classes/{classRoom}/streams', [StreamController::class, 'store']);
            Route::put('/classes/{classRoom}/streams/{stream}', [StreamController::class, 'update']);
            Route::delete('/classes/{classRoom}/streams/{stream}', [StreamController::class, 'destroy']);

            Route::get('/teacher-assignments', [TeacherAssignmentController::class, 'index']);
            Route::get('/teacher-assignments/export', [TeacherAssignmentController::class, 'export']);
            Route::post('/teacher-assignments', [TeacherAssignmentController::class, 'store']);
            Route::delete('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);

            Route::get('/assignments', [AssignmentController::class, 'index']);
            Route::get('/assignments/export', [AssignmentController::class, 'export']);
            Route::post('/assignments', [AssignmentController::class, 'store']);
            Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
            Route::patch('/assignments/{assignment}/publish', [AssignmentController::class, 'publish']);
            Route::patch('/assignments/{assignment}/archive', [AssignmentController::class, 'archive']);
            Route::put('/assignments/{assignment}', [AssignmentController::class, 'update']);

            // Attendance (Phase 2 / SKILLS Recipe G) — idempotent batch capture
            // per (class, period, date); unique DB constraint absorbs retries.
            Route::get('/attendance', [AttendanceController::class, 'index']);
            Route::get('/attendance/report', [AttendanceController::class, 'report']);
            Route::get('/attendance/export', [AttendanceController::class, 'export']);
            Route::post('/attendance', [AttendanceController::class, 'store']);

            // Assessment definitions (Phase 2 / SKILLS Recipe F).
            Route::get('/assessments', [AssessmentController::class, 'index']);
            Route::get('/assessments/export', [AssessmentController::class, 'export']);
            Route::post('/assessments', [AssessmentController::class, 'store']);
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
            Route::put('/assessments/{assessment}', [AssessmentController::class, 'update']);
            Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy']);
            Route::post('/assessments/{assessment}/publish', ResultPublishController::class);

            // Mark entry — per (student, assessment); versioned/append-only.
            Route::get('/results', [ResultController::class, 'index']);
            Route::get('/results/export', [ResultController::class, 'export']);
            Route::post('/results', [ResultController::class, 'store']);

            Route::get('/grading-scale', [GradingScaleController::class, 'show']);
            Route::put('/grading-scale', [GradingScaleController::class, 'update']);

            // Report cards — synchronous PDF generation + download.
            Route::post('/report-cards/bulk', [ReportCardController::class, 'bulkStore']);
            Route::get('/report-cards/class-download', [ReportCardController::class, 'classDownload']);
            Route::post('/students/{student}/report-card', [ReportCardController::class, 'store']);
            Route::get('/students/{student}/report-card', [ReportCardController::class, 'show']);
            Route::get('/students/{student}/report-card/download', [ReportCardController::class, 'download']);

            // Finance (Phase 3 / SKILLS Recipes D + E). "Record, don't transact".
            // Fee-structure + payment-method config (plain CRUD).
            Route::get('/fee-structures', [FeeStructureController::class, 'index']);
            Route::get('/fee-structures/export', [FeeStructureController::class, 'export']);
            Route::post('/fee-structures', [FeeStructureController::class, 'store']);
            Route::get('/fee-structures/{feeStructure}', [FeeStructureController::class, 'show']);
            Route::put('/fee-structures/{feeStructure}', [FeeStructureController::class, 'update']);
            Route::delete('/fee-structures/{feeStructure}', [FeeStructureController::class, 'destroy']);

            Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
            Route::get('/payment-methods/export', [PaymentMethodController::class, 'export']);
            Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
            Route::get('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show']);
            Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
            Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

            // Payment slips — parent submission + finance read queue.
            // Submission is rate-limited (RULES.md §7: rate limit submission).
            Route::get('/payment-slips', [PaymentSlipController::class, 'index']);
            Route::get('/payment-slips/export', [PaymentSlipController::class, 'export']);
            Route::post('/payment-slips', [PaymentSlipController::class, 'store'])
                ->middleware('throttle:20,1');
            Route::get('/payment-slips/{paymentSlip}', [PaymentSlipController::class, 'show']);

            // Verification workflow (finance officer) — verify issues the
            // immutable receipt; reject is status-only.
            Route::post('/payment-slips/{paymentSlip}/verify', [PaymentSlipVerificationController::class, 'verify']);
            Route::post('/payment-slips/{paymentSlip}/reject', [PaymentSlipVerificationController::class, 'reject']);

            // Hostel — buildings, rooms, allocations (Phase 4 core slice).
            Route::get('/hostels', [HostelController::class, 'index']);
            Route::get('/hostels/export', [HostelController::class, 'export']);
            Route::post('/hostels', [HostelController::class, 'store']);
            Route::get('/hostels/{hostel}', [HostelController::class, 'show']);
            Route::put('/hostels/{hostel}', [HostelController::class, 'update']);
            Route::delete('/hostels/{hostel}', [HostelController::class, 'destroy']);

            Route::get('/hostel-rooms', [HostelRoomController::class, 'index']);
            Route::get('/hostel-rooms/export', [HostelRoomController::class, 'export']);
            Route::post('/hostel-rooms', [HostelRoomController::class, 'store']);
            Route::get('/hostel-rooms/{hostelRoom}', [HostelRoomController::class, 'show']);
            Route::put('/hostel-rooms/{hostelRoom}', [HostelRoomController::class, 'update']);
            Route::delete('/hostel-rooms/{hostelRoom}', [HostelRoomController::class, 'destroy']);

            Route::get('/hostel-allocations', [HostelAllocationController::class, 'index']);
            Route::get('/hostel-allocations/export', [HostelAllocationController::class, 'export']);
            Route::post('/hostel-allocations', [HostelAllocationController::class, 'store']);
            Route::put('/hostel-allocations/{hostelAllocation}', [HostelAllocationController::class, 'update']);
            Route::post('/hostel-allocations/{hostelAllocation}/end', [HostelAllocationController::class, 'end']);

            Route::get('/meal-plans', [MealPlanController::class, 'index']);
            Route::get('/meal-plans/export', [MealPlanController::class, 'export']);
            Route::post('/meal-plans', [MealPlanController::class, 'store']);
            Route::put('/meal-plans/{mealPlan}', [MealPlanController::class, 'update']);
            Route::delete('/meal-plans/{mealPlan}', [MealPlanController::class, 'destroy']);

            Route::get('/hostel-leave-requests', [HostelLeaveRequestController::class, 'index']);
            Route::get('/hostel-leave-requests/export', [HostelLeaveRequestController::class, 'export']);
            Route::post('/hostel-leave-requests', [HostelLeaveRequestController::class, 'store']);
            Route::post('/hostel-leave-requests/{hostelLeaveRequest}/approve', [HostelLeaveRequestController::class, 'approve']);
            Route::post('/hostel-leave-requests/{hostelLeaveRequest}/reject', [HostelLeaveRequestController::class, 'reject']);

            // Stores & Kitchen Inventory — Phase 7b/7c (docs/prd-stores-inventory-module.md).
            Route::get('/inventory-items', [InventoryItemController::class, 'index']);
            Route::get('/inventory-items/low-stock', [InventoryItemController::class, 'lowStock']);
            Route::get('/inventory-items/valuation', [InventoryItemController::class, 'valuation']);
            Route::get('/inventory-items/export', [InventoryItemController::class, 'export']);
            Route::post('/inventory-items', [InventoryItemController::class, 'store']);
            Route::put('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
            Route::delete('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'destroy']);

            Route::get('/stock-movements', [StockMovementController::class, 'index']);
            Route::get('/stock-movements/export', [StockMovementController::class, 'export']);

            Route::get('/store-requisitions', [StoreRequisitionController::class, 'index']);
            Route::get('/store-requisitions/export', [StoreRequisitionController::class, 'export']);
            Route::post('/store-requisitions', [StoreRequisitionController::class, 'store']);
            Route::get('/store-requisitions/{storeRequisition}', [StoreRequisitionController::class, 'show']);
            Route::put('/store-requisitions/{storeRequisition}', [StoreRequisitionController::class, 'update']);
            Route::post('/store-requisitions/{storeRequisition}/submit', [StoreRequisitionController::class, 'submit']);
            Route::post('/store-requisitions/{storeRequisition}/approve', [StoreRequisitionController::class, 'approve']);
            Route::post('/store-requisitions/{storeRequisition}/reject', [StoreRequisitionController::class, 'reject']);
            Route::post('/store-requisitions/{storeRequisition}/issue', [StoreRequisitionController::class, 'issue']);
            Route::post('/store-requisitions/{storeRequisition}/close-line', [StoreRequisitionController::class, 'closeLine']);
            Route::post('/store-requisitions/{storeRequisition}/add-to-purchase', [StoreRequisitionController::class, 'addToPurchase']);
            Route::post('/store-requisitions/{storeRequisition}/cancel', [StoreRequisitionController::class, 'cancel']);

            Route::get('/purchase-requests', [PurchaseRequestController::class, 'index']);
            Route::get('/purchase-requests/export', [PurchaseRequestController::class, 'export']);
            Route::post('/purchase-requests', [PurchaseRequestController::class, 'store']);
            Route::get('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show']);
            Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update']);
            Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit']);
            Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve']);
            Route::post('/purchase-requests/{purchaseRequest}/amend', [PurchaseRequestController::class, 'amend']);
            Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject']);
            Route::post('/purchase-requests/{purchaseRequest}/fulfill', [PurchaseRequestController::class, 'fulfill']);
            Route::get('/purchase-requests/{purchaseRequest}/fulfillment', [PurchaseRequestController::class, 'showFulfillment']);

            // Phase 5 — read-only cross-module dashboards (PRD §5.9/§5.10).
            Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
            Route::get('/dashboard/wards', [DashboardController::class, 'wards']);

            Route::get('/navigation', [NavigationController::class, 'index']);

            // Tenant administration — schools, settings, branding, billing, RBAC, navigation.
            Route::prefix('admin')->group(function () {
                Route::get('/schools', [AdminSchoolController::class, 'index']);
                Route::post('/schools', [AdminSchoolController::class, 'store']);
                Route::get('/schools/{school}', [AdminSchoolController::class, 'show']);
                Route::put('/schools/{school}', [AdminSchoolController::class, 'update']);
                Route::delete('/schools/{school}', [AdminSchoolController::class, 'destroy']);
                Route::patch('/schools/{school}/settings', [AdminSchoolController::class, 'updateSettings']);
                Route::patch('/schools/{school}/branding', [AdminSchoolController::class, 'updateBranding']);
                Route::patch('/schools/{school}/billing', [AdminSchoolController::class, 'updateBilling']);

                Route::get('/users', [AdminUserController::class, 'index']);
                Route::get('/roles', [AdminUserController::class, 'roles']);
                Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles']);

                Route::get('/permissions', [AdminRoleController::class, 'permissions']);
                Route::get('/role-definitions', [AdminRoleController::class, 'index']);
                Route::post('/role-definitions', [AdminRoleController::class, 'store']);
                Route::put('/role-definitions/{role}/permissions', [AdminRoleController::class, 'syncPermissions']);
                Route::delete('/role-definitions/{role}', [AdminRoleController::class, 'destroy']);
                Route::put('/users/{user}/permissions', [AdminRoleController::class, 'syncUserPermissions']);

                Route::get('/navigation', [AdminNavigationController::class, 'index']);
                Route::patch('/navigation/sections/{section}', [AdminNavigationController::class, 'updateSection']);
                Route::patch('/navigation/items/{item}', [AdminNavigationController::class, 'updateItem']);
                Route::post('/navigation/reorder', [AdminNavigationController::class, 'reorder']);
            });
        });

        // Phase 6 — Platform Admin & cross-tenant oversight. Deliberately a
        // sibling of the group above, NOT wrapped in
        // InitializeTenancyFromSession: these routes either have no tenant
        // known yet (tenant list/creation, audit log spans every tenant) or
        // manage tenancy themselves (impersonation, tenant user listing).
        // EnsurePlatformAdmin checks the `platform` guard specifically, so
        // this stays correct even mid-impersonation, when the `web` guard
        // also has a user logged in alongside the still-authenticated
        // `platform` guard in the same session.
        Route::prefix('platform')
            ->middleware(['auth:sanctum', EnsurePlatformAdmin::class])
            ->group(function () {
                Route::get('/tenants', [TenantController::class, 'index']);
                // RULES.md §7: rate limit — this runs real schema-creation
                // DDL per call, a much heavier action than a normal write.
                Route::post('/tenants', [TenantController::class, 'store'])
                    ->middleware('throttle:10,1');
                Route::get('/tenants/{tenant}/users', [TenantController::class, 'users']);

                Route::get('/audit-logs', [AuditLogController::class, 'index']);
                Route::get('/audit-logs/export', [AuditLogController::class, 'export']);

                Route::get('/settings', [PlatformSettingsController::class, 'show']);
                Route::patch('/settings', [PlatformSettingsController::class, 'update']);

                Route::get('/navigation', [PlatformNavigationController::class, 'index']);
                Route::get('/navigation/manage', [PlatformNavigationController::class, 'adminIndex']);
                Route::patch('/navigation/items/{item}', [PlatformNavigationController::class, 'updateItem']);
                Route::post('/navigation/reorder', [PlatformNavigationController::class, 'reorder']);

                Route::post('/impersonate', [ImpersonationController::class, 'start'])
                    ->middleware('throttle:20,1');
                Route::post('/impersonate/stop', [ImpersonationController::class, 'stop']);
            });
    });

Route::middleware(['web'])
    ->get('/{any?}', function () {
        return view('app');
    })
    ->where('any', '^(?!api).*$');
