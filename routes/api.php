<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\TeacherDashboardController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubSubjectController;
use App\Http\Controllers\UserPhotoController;
use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;

/**
 * PUBLIC ROUTES
 */
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

/**
 * PROTECTED ROUTES - Require authentication
 */
Route::middleware('auth:sanctum')->group(function () {

    /**
     * Authentication Routes
     */
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/avatar', [UserController::class, 'updateProfileAvatar']);

    /**
     * Public Data Routes - Available to all authenticated users
     */
    Route::apiResource('subjects', SubjectController::class);
    Route::apiResource('sub-subjects', SubSubjectController::class);
    
    // Sessions - Read only for general users
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::get('/sessions/{session}', [SessionController::class, 'show']);
    Route::get('/sessions-with-schedules', [SessionController::class, 'withSchedules']);

    /**
     * ADMIN ROUTES
     */
    Route::prefix('admin')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);

            // User Import
            Route::post('/import/teachers', [UserController::class, 'importTeachers']);
            Route::post('/import/students', [UserController::class, 'importStudents']);

            // Photo Upload
            Route::post('/import/photos', [UserPhotoController::class, 'upload']);

            // User Management
            Route::apiResource('users', UserController::class);
            Route::apiResource('teachers', TeacherController::class);
            Route::apiResource('students', StudentController::class);

            // Class Management
            Route::apiResource('classes', ClassRoomController::class);
            Route::post('/classes/{id}/students', [ClassRoomController::class, 'addStudents']);

            // Session Management (Full CRUD for admin)
            Route::apiResource('sessions', SessionController::class)->except(['index', 'show']);

            // Schedule Management
            Route::apiResource('schedules', ScheduleController::class);
            Route::patch('/schedules/{schedule}/reassign', [ScheduleController::class, 'reassignTeacher']);
            Route::patch('/schedules/{schedule}/confirm-rejection', [ScheduleController::class, 'confirmRejection']);

            // Filtering Helpers
            Route::get('/subjects/{subject}/teachers', [ScheduleController::class, 'teachersBySubject']);
            Route::get('/subjects/{subject}/sub-subjects', [ScheduleController::class, 'subSubjectsBySubject']);

            // Schedule Views
            Route::get('/schedules/class/{classRoom}', [ScheduleController::class, 'byClass']);
            Route::get('/schedules/teacher/{teacher}', [ScheduleController::class, 'byTeacher']);
        });

    /**
     * TEACHER ROUTES
     */
    Route::prefix('teacher')
        ->middleware('role:teacher')
        ->group(function () {
            Route::get('/dashboard', [TeacherDashboardController::class, 'index']);
            Route::get('/schedules', [ScheduleController::class, 'mySchedule']);
            Route::patch('/schedules/{schedule}/reject', [ScheduleController::class, 'rejectSchedule']);
        });

    Route::prefix('student')
        ->middleware('role:student')
        ->group(function () {
            Route::get('/dashboard', [StudentDashboardController::class, 'index']);
            Route::get('/schedules', [ScheduleController::class, 'classSchedule']);
        });
});
