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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    Route::get('/', [TodoController::class, 'index']);                    // list own todos
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
    Route::post('/', [RequestItemController::class, 'store']);
});
// Admin: manage requests
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
// All roles can access booking
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

// ---------------- TEST UPLOAD (for debugging) ----------------
Route::post('/test-upload', function (Request $request) {
    try {
        Log::info('Test Upload Debug', [
            'has_file' => $request->hasFile('evidence'),
            'all_files' => $request->allFiles(),
            'all_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method()
        ]);

        if (!$request->hasFile('evidence')) {
            return response()->json([
                'message' => 'No file found',
                'debug' => [
                    'has_file' => $request->hasFile('evidence'),
                    'all_files' => $request->allFiles(),
                    'content_type' => $request->header('Content-Type')
                ]
            ], 422);
        }

        $file = $request->file('evidence');
        $filename = 'test_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('evidence', $filename, 'public');

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'path' => $path,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'full_url' => asset('storage/' . $path)
        ]);
    } catch (\Exception $e) {
        Log::error('Test Upload Error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

// ---------------- DEBUG ROUTES (remove in production) ----------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/debug/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'roles' => $request->user()->getRoleNames(),
            'permissions' => $request->user()->getAllPermissions()
        ]);
    });

    Route::get('/debug/todos/{id}', function (Request $request, $id) {
        $todo = \App\Models\Todo::findOrFail($id);
        return response()->json([
            'todo' => $todo,
            'user_can_access' => $todo->user_id === $request->user()->id,
            'file_exists' => $todo->evidence_path ? Storage::disk('public')->exists($todo->evidence_path) : false,
            'full_path' => $todo->evidence_path ? storage_path('app/public/' . $todo->evidence_path) : null
        ]);
    });
});
