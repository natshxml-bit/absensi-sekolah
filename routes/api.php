<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('admin')->middleware('role:'.User::ROLE_ADMIN)->group(function () {
        Route::get('/overview', [AdminController::class, 'overview']);

        Route::get('/classes', [AdminController::class, 'classes']);
        Route::post('/classes', [AdminController::class, 'storeClass']);

        Route::get('/students', [AdminController::class, 'students']);
        Route::post('/students', [AdminController::class, 'storeStudent']);
        Route::post('/students/import', [AdminController::class, 'importStudents']);

        Route::get('/teachers', [AdminController::class, 'teachers']);
        Route::post('/teachers', [AdminController::class, 'storeTeacher']);

        Route::get('/settings', [AdminController::class, 'settings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);

        Route::get('/attendance', [AdminController::class, 'attendance']);
        Route::delete('/attendance', [AdminController::class, 'clearAttendance']);
        Route::post('/attendance/manual', [AdminController::class, 'storeManualAttendance']);
    });

    Route::prefix('student')->middleware('role:'.User::ROLE_SISWA)->group(function () {
        Route::get('/profile', [StudentController::class, 'profile']);
        Route::get('/today', [StudentController::class, 'today']);
        Route::post('/attendance', [StudentController::class, 'checkIn']);
        Route::post('/attendance/izin', [StudentController::class, 'requestIzin']);
        Route::get('/attendance', [StudentController::class, 'history']);
        Route::get('/schedules', [StudentController::class, 'schedules']);
    });

    Route::prefix('teacher')->middleware('role:'.User::ROLE_GURU)->group(function () {
        Route::get('/classes', [TeacherController::class, 'classes']);
        Route::get('/classes/{class}/students', [TeacherController::class, 'classStudents']);
        Route::get('/classes/{class}/attendance', [TeacherController::class, 'attendance']);
        Route::post('/classes/{class}/attendance', [TeacherController::class, 'storeAttendance']);
        Route::get('/classes/{class}/attendance/export', [TeacherController::class, 'exportAttendance']);
    });

    Route::prefix('parent')->middleware('role:'.User::ROLE_ORTU)->group(function () {
        Route::get('/children', [ParentController::class, 'children']);
        Route::get('/children/{student}/attendance', [ParentController::class, 'childAttendance']);
    });
});
