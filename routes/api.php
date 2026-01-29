<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\ProfileRequestController;
// 👇 IMPORT THIS!
use App\Http\Controllers\DashboardController; 

// Public Login
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) { return $request->user(); });

    // DASHBOARD STATS (Make sure this line is here)
    Route::get('/dashboard-stats', [DashboardController::class, 'index']);

    Route::get('/employees/export-csv', [EmployeeController::class, 'exportCSV']);
    Route::get('/employees/export-pdf', [EmployeeController::class, 'exportPDF']);

    // ... your other routes (employees, offices, etc) ...
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}/verify', [EmployeeController::class, 'verify']);
    Route::post('/employees/{id}/transfer', [EmployeeController::class, 'transfer']);
    Route::post('/employees/{id}/promote', [EmployeeController::class, 'promote']);
    Route::post('/employees/{id}/create-login', [EmployeeController::class, 'createLogin']);
    Route::post('/employees/{id}/access', [EmployeeController::class, 'manageAccess']); // Access Control
    Route::post('/employees/{id}/photo', [EmployeeController::class, 'uploadPhoto']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    
    Route::get('/offices', [OfficeController::class, 'index']);
    Route::post('/offices', [OfficeController::class, 'store']);
    Route::put('/offices/{id}', [OfficeController::class, 'update']);

    Route::get('/designations', [DesignationController::class, 'index']);
    Route::post('/designations', [DesignationController::class, 'store']);
    Route::put('/designations/{id}', [DesignationController::class, 'update']);

    Route::get('/profile-requests', [ProfileRequestController::class, 'index']);
    Route::post('/profile-requests', [ProfileRequestController::class, 'store']);
    Route::put('/profile-requests/{id}', [ProfileRequestController::class, 'update']);

    Route::get('/forms', [FormController::class, 'index']);
    Route::post('/forms', [FormController::class, 'store']);

    Route::post('/employees/{id}/update-full', [EmployeeController::class, 'updateFullProfile']);
});