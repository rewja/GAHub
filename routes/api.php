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
});

// ---------------- USER PROFILE (self access) ----------------
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);

// ---------------- TODOS ----------------
// User: manage own todos
Route::middleware(['auth:sanctum', 'role:user'])->prefix('todos')->group(function () {
    Route::get('/', [TodoController::class, 'index']);            // list own todos
    Route::post('/', [TodoController::class, 'store']);           // create todo
    Route::put('/{id}', [TodoController::class, 'update']);       // update todo (full)
    Route::patch('/{id}', [TodoController::class, 'update']);     // update todo (partial)
    Route::patch('/{id}/start', [TodoController::class, 'start']); // start todo (not_started -> in_progress)
    Route::patch('/{id}/submit', [TodoController::class, 'submitForChecking']); // submit for checking
    Route::delete('/{id}', [TodoController::class, 'destroy']);   // delete todo
});

// admin: manage all todos
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('todos')->group(function () {
    Route::get('/all', [TodoController::class, 'indexAll']);           // semua todos
    Route::patch('/{id}/evaluate', [TodoController::class, 'evaluate']); // new evaluation endpoint
    Route::get('/evaluate/{userId}', [TodoController::class, 'evaluateOverall']); // overall performance evaluation
    // Removed legacy routes
});

// ---------------- REQUESTS ----------------
// User: create request
Route::middleware(['auth:sanctum', 'role:user'])->prefix('requests')->group(function () {
    Route::post('/', [RequestItemController::class, 'store']);
});
// admin: manage requests
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('requests')->group(function () {
    Route::get('/', [RequestItemController::class, 'index']);
    Route::patch('/{id}/approve', [RequestItemController::class, 'approve']);
    Route::patch('/{id}/reject', [RequestItemController::class, 'reject']);
});

// ---------------- PROCUREMENT ----------------
Route::middleware(['auth:sanctum', 'role:procurement'])->prefix('procurements')->group(function () {
    Route::get('/', [ProcurementController::class, 'index']);
    Route::post('/', [ProcurementController::class, 'store']);
});

// ---------------- ASSETS ----------------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('assets')->group(function () {
    Route::get('/', [AssetController::class, 'index']);
    Route::post('/', [AssetController::class, 'store']);
    Route::patch('/{id}/status', [AssetController::class, 'updateStatus']);
});

// ---------------- MEETINGS ----------------
// semua role bisa akses booking
Route::middleware('auth:sanctum')->prefix('meetings')->group(function () {
    Route::get('/', [MeetingController::class, 'index']);
    Route::post('/', [MeetingController::class, 'store']);
    Route::patch('/{id}/start', [MeetingController::class, 'start']);
    Route::patch('/{id}/end', [MeetingController::class, 'end']);
    Route::patch('/{id}/force-end', [MeetingController::class, 'forceEnd'])->middleware('role:admin');
});

// ---------------- VISITORS ----------------
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('visitors')->group(function () {
    Route::get('/', [VisitorController::class, 'index']);
    Route::post('/', [VisitorController::class, 'store']);
});
