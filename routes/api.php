<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\RequestItemController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\VisitorController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ---------------- AUTH ----------------
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ---------------- USERS (managed by admin) ----------------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']);          // list all users
    Route::get('/{id}', [UserController::class, 'show']);       // detail user
    Route::post('/', [UserController::class, 'store']);         // create user
    Route::patch('/{id}', [UserController::class, 'update']);   // update user
    Route::delete('/{id}', [UserController::class, 'destroy']); // delete user
    Route::get('/stats/global', [UserController::class, 'stats']); // new users per month/year
});

// ---------------- USER PROFILE (self access) ----------------
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);

// ---------------- TODOS ----------------
// User: manage own todos
Route::middleware(['auth:sanctum', 'role:user'])->prefix('todos')->group(function () {
    Route::get('/', [TodoController::class, 'index']);                    // list own todos
    Route::get('/stats', [TodoController::class, 'statsUser']);           // personal todo stats
    Route::post('/', [TodoController::class, 'store']);                   // create todo
    Route::put('/{id}', [TodoController::class, 'update']);               // update todo (full)
    Route::patch('/{id}', [TodoController::class, 'update']);             // update todo (partial)
    Route::post('/{id}', [TodoController::class, 'update']);              // update todo (form-data POST support)
    Route::patch('/{id}/start', [TodoController::class, 'start']);        // start todo (not_started -> in_progress)

    // FIXED: Use POST for file uploads - more reliable than PATCH/PUT
    Route::post('/{id}/submit', [TodoController::class, 'submitForChecking']); // submit for checking
    Route::post('/{id}/improve', [TodoController::class, 'submitImprovement']); // submit improvements during evaluating

    Route::delete('/{id}', [TodoController::class, 'destroy']);           // delete todo
});

// Admin/GA: manage all todos - FIXED role permission
Route::middleware(['auth:sanctum'])->prefix('todos')->group(function () {
    // Allow both admin and GA to access these routes
    Route::get('/all', [TodoController::class, 'indexAll'])->middleware('role:admin,ga'); // ?user_id=ID optional
    Route::get('/stats/global', [TodoController::class, 'statsGlobal'])->middleware('role:admin,ga');
    Route::get('/user/{userId}', [TodoController::class, 'indexByUser'])->middleware('role:admin,ga');
    Route::patch('/{id}/evaluate', [TodoController::class, 'evaluate'])->middleware('role:admin,ga');
    // Allow form-data POST for evaluate to avoid multipart PATCH issues
    Route::post('/{id}/evaluate', [TodoController::class, 'evaluate'])->middleware('role:admin,ga');
    Route::get('/evaluate/{userId}', [TodoController::class, 'evaluateOverall'])->middleware('role:admin,ga');
    Route::get('/warnings/leaderboard', [TodoController::class, 'warningsLeaderboard'])->middleware('role:admin,ga');

    // Legacy routes for backward compatibility (deprecated)
    Route::patch('/{id}/check', [TodoController::class, 'check'])->middleware('role:admin,ga');
    Route::patch('/{id}/note', [TodoController::class, 'addNote'])->middleware('role:admin,ga');
});

// ---------------- REQUESTS ----------------
// User: create request
Route::middleware(['auth:sanctum', 'role:user'])->prefix('requests')->group(function () {
    Route::get('/mine', [RequestItemController::class, 'mine']);
    Route::get('/stats', [RequestItemController::class, 'statsUser']);
    Route::post('/', [RequestItemController::class, 'store']);
});
// Admin: manage requests
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('requests')->group(function () {
    Route::get('/', [RequestItemController::class, 'index']);
    Route::get('/stats/global', [RequestItemController::class, 'statsGlobal']);
    Route::patch('/{id}/approve', [RequestItemController::class, 'approve']);
    Route::patch('/{id}/reject', [RequestItemController::class, 'reject']);
});

// ---------------- PROCUREMENT ----------------
Route::middleware(['auth:sanctum', 'role:procurement'])->prefix('procurements')->group(function () {
    Route::get('/', [ProcurementController::class, 'index']);
    Route::post('/', [ProcurementController::class, 'store']);
    Route::get('/stats', [ProcurementController::class, 'stats']);
});

// ---------------- ASSETS ----------------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('assets')->group(function () {
    Route::get('/', [AssetController::class, 'index']);
    Route::get('/stats', [AssetController::class, 'stats']);
    Route::post('/', [AssetController::class, 'store']);
    Route::patch('/{id}/status', [AssetController::class, 'updateStatus']);
});

// ---------------- MEETINGS ----------------
// All roles can access booking
Route::middleware('auth:sanctum')->prefix('meetings')->group(function () {
    Route::get('/', [MeetingController::class, 'index']);
    Route::get('/stats', [MeetingController::class, 'stats']);
    Route::post('/', [MeetingController::class, 'store']);
    Route::patch('/{id}/start', [MeetingController::class, 'start']);
    Route::patch('/{id}/end', [MeetingController::class, 'end']);
    Route::patch('/{id}/force-end', [MeetingController::class, 'forceEnd'])->middleware('role:admin');
});

// ---------------- VISITORS ----------------
Route::middleware(['auth:sanctum', 'role:admin,ga'])->prefix('visitors')->group(function () {
    Route::get('/', [VisitorController::class, 'index']);
    Route::get('/stats', [VisitorController::class, 'stats']);
    Route::post('/', [VisitorController::class, 'store']);
    Route::get('/{id}', [VisitorController::class, 'show']);
    Route::put('/{id}', [VisitorController::class, 'update']);
    Route::patch('/{id}', [VisitorController::class, 'update']);
    // Allow form-data POST for update to avoid multipart PUT/PATCH issues
    Route::post('/{id}', [VisitorController::class, 'update']);
    Route::delete('/{id}', [VisitorController::class, 'destroy']);
    Route::post('/{id}/check-in', [VisitorController::class, 'checkIn']);
    Route::post('/{id}/check-out', [VisitorController::class, 'checkOut']);
});

// (Debug routes removed)
