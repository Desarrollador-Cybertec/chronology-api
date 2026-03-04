<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ShiftController;
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
    });

    /*
    |----------------------------------------------------------------------
    | SuperAdmin only
    |----------------------------------------------------------------------
    */
    Route::middleware('role:superadmin')->group(function () {
        Route::apiResource('employees', EmployeeController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('shifts', ShiftController::class)->only(['store', 'update', 'destroy']);
        Route::put('/attendance/{attendanceDay}', [AttendanceController::class, 'update']);
    });
});
