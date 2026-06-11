<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SummaryController;
use Illuminate\Support\Facades\Route;

// --- Auth (public) ---
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/summaries', [SummaryController::class, 'index']);
    Route::post('/summaries', [SummaryController::class, 'store'])->middleware('throttle:summaries');
    Route::get('/summaries/{id}', [SummaryController::class, 'show'])->whereNumber('id');
    Route::delete('/summaries/{id}', [SummaryController::class, 'destroy'])->whereNumber('id');

    // --- Admin (role=admin) ---
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/summaries', [AdminController::class, 'summaries']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/stats', [AdminController::class, 'stats']);
    });
});
