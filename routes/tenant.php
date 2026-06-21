<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Academic\AcademicSessionController;
use App\Http\Controllers\Api\Academic\AssignmentController;
use App\Http\Controllers\Api\Academic\ClassRoomController;
use App\Http\Controllers\Api\Academic\ClassSubjectController;
use App\Http\Controllers\Api\Academic\SubjectController;
use App\Http\Controllers\Api\Academic\TeacherAssignmentController;
use App\Http\Controllers\Api\Assessment\AssessmentController;
use App\Http\Controllers\Api\Assessment\ReportCardController;
use App\Http\Controllers\Api\Assessment\ResultController;
use App\Http\Controllers\Api\Assessment\ResultPublishController;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Sis\EnrolmentController;
use App\Http\Controllers\Api\Sis\StudentController;
use App\Http\Controllers\Api\Sis\StudentGuardianController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Every tenant subdomain (acme.sms.test) serves both the React SPA shell
| and its own /api/v1 — the SPA on a given subdomain only ever talks to
| that tenant's API (Sanctum SPA cookie auth, same-origin).
|
*/

Route::middleware(['web', InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class])
    ->get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->name('sanctum.csrf-cookie');

Route::prefix('api/v1')
    ->middleware(['api', InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class])
    ->group(function () {
        // RULES.md §7: rate limit auth endpoints against brute force.
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);

            // SIS — students, guardians, enrolments/promotion (Recipe B).
            Route::get('/students', [StudentController::class, 'index']);
            Route::post('/students', [StudentController::class, 'store']);
            Route::get('/students/{student}', [StudentController::class, 'show']);
            Route::post('/students/{student}/guardians', [StudentGuardianController::class, 'store']);
            Route::delete('/students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy']);

            Route::post('/enrolments/{enrolment}/promote', [EnrolmentController::class, 'promote']);

            // Academics — classes/sessions (read-only lookups), subjects, class↔subject mapping, teacher assignments, assignments.
            Route::get('/classes', [ClassRoomController::class, 'index']);
            Route::get('/academic-sessions', [AcademicSessionController::class, 'index']);

            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::post('/subjects', [SubjectController::class, 'store']);
            Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
            Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);

            Route::post('/classes/{classRoom}/subjects', [ClassSubjectController::class, 'store']);
            Route::delete('/classes/{classRoom}/subjects/{subject}', [ClassSubjectController::class, 'destroy']);

            Route::get('/teacher-assignments', [TeacherAssignmentController::class, 'index']);
            Route::post('/teacher-assignments', [TeacherAssignmentController::class, 'store']);
            Route::delete('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);

            Route::get('/assignments', [AssignmentController::class, 'index']);
            Route::post('/assignments', [AssignmentController::class, 'store']);
            Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
            Route::patch('/assignments/{assignment}/publish', [AssignmentController::class, 'publish']);

            // Attendance (Phase 2 / SKILLS Recipe G) — idempotent batch capture
            // per (class, period, date); unique DB constraint absorbs retries.
            Route::get('/attendance', [AttendanceController::class, 'index']);
            Route::post('/attendance', [AttendanceController::class, 'store']);

            // Assessment definitions (Phase 2 / SKILLS Recipe F).
            Route::get('/assessments', [AssessmentController::class, 'index']);
            Route::post('/assessments', [AssessmentController::class, 'store']);
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
            Route::put('/assessments/{assessment}', [AssessmentController::class, 'update']);
            Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy']);
            Route::post('/assessments/{assessment}/publish', ResultPublishController::class);

            // Mark entry — per (student, assessment); versioned/append-only.
            Route::get('/results', [ResultController::class, 'index']);
            Route::post('/results', [ResultController::class, 'store']);

            // Report cards — queued PDF generation + cache-pointer lookup.
            Route::post('/students/{student}/report-card', [ReportCardController::class, 'store']);
            Route::get('/students/{student}/report-card', [ReportCardController::class, 'show']);
        });
    });

Route::middleware(['web', InitializeTenancyBySubdomain::class, PreventAccessFromCentralDomains::class])
    ->get('/{any?}', function () {
        return view('app');
    })
    ->where('any', '^(?!api).*$');
