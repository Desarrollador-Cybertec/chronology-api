<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeShiftController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | Manager + SuperAdmin (ambos roles)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:superadmin,manager')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['index', 'show']);
        Route::apiResource('shifts', ShiftController::class)->only(['index', 'show']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::get('/attendance/{attendanceDay}', [AttendanceController::class, 'show']);
        Route::post('/import', [ImportController::class, 'store']);
        Route::get('/import', [ImportController::class, 'index']);
        Route::get('/import/{importBatch}', [ImportController::class, 'show']);

        // Employee shift assignments (read)
        Route::get('/employees/{employee}/shifts', [EmployeeShiftController::class, 'index']);
        Route::get('/employee-shifts/{employeeShift}', [EmployeeShiftController::class, 'show']);
    });

    /*
    |----------------------------------------------------------------------
    | SuperAdmin only
    |----------------------------------------------------------------------
    */
    Route::middleware('role:superadmin')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['update']);
        Route::patch('/employees/{employee}/toggle-active', [EmployeeController::class, 'toggleActive']);
        Route::apiResource('shifts', ShiftController::class)->only(['store', 'update', 'destroy']);
        Route::put('/attendance/{attendanceDay}', [AttendanceController::class, 'update']);

        // Import reprocessing
        Route::post('/import/{importBatch}/reprocess', [ImportController::class, 'reprocess']);

        // Employee shift assignments (write)
        Route::post('/employee-shifts', [EmployeeShiftController::class, 'store']);
        Route::put('/employee-shifts/{employeeShift}', [EmployeeShiftController::class, 'update']);
        Route::delete('/employee-shifts/{employeeShift}', [EmployeeShiftController::class, 'destroy']);

        // System settings
        Route::get('/settings', [SystemSettingController::class, 'index']);
        Route::put('/settings', [SystemSettingController::class, 'update']);
    });
});
