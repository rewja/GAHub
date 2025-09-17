<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Resources\TodoResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TodoController extends Controller
{
    // Helper method to format duration
    private function formatDuration($minutes)
    {
        if ($minutes === null) {
            return null;
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        $seconds = floor(($minutes - floor($minutes)) * 60);

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . " hour" . ($hours > 1 ? 's' : '');
        }
        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes . " minute" . ($remainingMinutes > 1 ? 's' : '');
        }
        if ($seconds > 0) {
            $parts[] = $seconds . " second" . ($seconds > 1 ? 's' : '');
        }

        return $parts ? implode(', ', $parts) : '0 seconds';
    }

    private function getDayNameId(Carbon $date): string
    {
        $map = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        return $map[$date->format('l')] ?? $date->format('l');
    }

    private function nextDailySequence(int $userId, Carbon $date): int
    {
        $count = Todo::where('user_id', $userId)
            ->whereDate('submitted_at', $date->toDateString())
            ->count();
        return $count + 1; // next number for that day
    }

    private function buildEvidenceFilename(string $userName, int $userId, string $ext, ?Carbon $at = null): string
    {
        $now = $at ? $at->copy() : Carbon::now();
        $seq = str_pad((string) $this->nextDailySequence($userId, $now), 2, '0', STR_PAD_LEFT);
        $day = $this->getDayNameId($now);
        $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $userName ?: 'User');
        $timePart = $now->format('Y-m-d H.i.s');
        return "ETD-{$seq}-{$safeUser}-{$day}-{$timePart}.{$ext}";
    }

    private function getEvidenceFolder(Carbon $date, string $userName = 'User'): string
    {
        $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $userName ?: 'User');
        return 'evidence/' . $date->format('Y') . '/' . $date->format('m') . '/' . $date->format('d') . '/' . $safeUser;
    }

    // User: list own todos
    public function index(Request $request)
    {
        return response()->json($request->user()->todos);
    }

    // GA/Admin: list all todos (optional filter by user_id)
    public function indexAll(Request $request)
    {
        $query = Todo::with('user')->latest();
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        return TodoResource::collection($query->get());
    }

    // GA/Admin: list todos by specific user
    public function indexByUser($userId)
    {
        $todos = Todo::with('user')->where('user_id', $userId)->latest()->get();
        return TodoResource::collection($todos);
    }

    // User: create todo
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'scheduled_date' => 'nullable|date|after_or_equal:today'
        ]);

        $data['user_id'] = $request->user()->id;

        // default workflow status: not_started
        $data['status'] = 'not_started';

        $todo = Todo::create($data);

        return response()->json([
            'message' => 'Todo created successfully',
            'todo' => new TodoResource($todo)
        ], 201);
    }

    // User: update own todo
    public function update(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        $currentStatus = $todo->status;
        // 1) After checking phase (evaluating/completed) => block any edits
        if (in_array($currentStatus, ['evaluating', 'completed'])) {
            return response()->json([
                'message' => 'Todo can no longer be edited after the checking phase'
            ], 422);
        }

        // 2) Before checking (not_started / in_progress) => allow ONLY text fields, evidence forbidden
        if (in_array($currentStatus, ['not_started', 'in_progress'])) {
            if ($request->hasFile('evidence')) {
                return response()->json([
                    'message' => 'Evidence can only be uploaded during the checking phase'
                ], 422);
            }

        $data = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
                'due_date' => 'nullable|date',
                'scheduled_date' => 'nullable|date|after_or_equal:today'
            ]);

            foreach (['title','description','due_date','scheduled_date'] as $key) {
                if (array_key_exists($key, $data)) {
                    $todo->$key = $data[$key];
                }
            }

            $todo->save();

            return response()->json([
                'message' => 'Todo updated successfully',
                'todo' => new TodoResource($todo->fresh())
            ]);
        }

        // 3) During checking => allow evidence replacement ONLY (text changes ignored)
        if ($currentStatus === 'checking') {
            if (!$request->hasFile('evidence')) {
                return response()->json([
                    'message' => 'Evidence file is required during checking to update'
                ], 422);
            }

            $request->validate([
                'evidence' => 'file|mimes:jpeg,png,jpg,gif,webp,bmp,tiff|max:10240'
            ]);

            $file = $request->file('evidence');
            $ext = $file->getClientOriginalExtension();
            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }
            $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, $ext, $now);
            $path = $file->storeAs($folder, $filename, 'public');

            // optionally delete old file (keep folder)
            if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                try { Storage::disk('public')->delete($todo->evidence_path); } catch (\Throwable $e) { /* ignore */ }
            }

            $todo->evidence_path = $path;
            $todo->save();

            return response()->json([
                'message' => 'Evidence updated successfully',
                'todo' => new TodoResource($todo->fresh())
            ]);
        }

        // Fallback (should not reach)
        return response()->json([
            'message' => 'No changes applied'
        ], 200);
    }

    // User: start a todo (transition not_started -> in_progress)
    public function start(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'not_started') {
            return response()->json(['message' => 'Invalid state transition'], 422);
        }

        // Catat waktu mulai
        $todo->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);

        return response()->json([
            'message' => 'Todo started',
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: submit for checking (transition in_progress -> checking)
    public function submitForChecking(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'in_progress') {
            return response()->json(['message' => 'Invalid state transition'], 422);
        }

        $path = null;
        $now = Carbon::now();
        $folder = $this->getEvidenceFolder($now, $request->user()->name);
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder, 0755, true);
        }

        if ($request->hasFile('evidence')) {
            $request->validate([
                'evidence' => 'file|mimes:jpeg,png,jpg,gif,webp,bmp,tiff|max:10240'
            ]);
            try {
                $file = $request->file('evidence');
                $ext = $file->getClientOriginalExtension();
                $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, $ext, $now);
                $path = $file->storeAs($folder, $filename, 'public');
            } catch (\Throwable $e) {
                $ext = 'jpg';
                $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, $ext, $now);
                $path = $folder . '/' . $filename; // fake path
                Log::warning('Evidence storage failed, using fake path', ['todo_id' => $todo->id, 'error' => $e->getMessage()]);
            }
        } else {
            $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, 'jpg', $now);
            $path = $folder . '/' . $filename;
        }

        $payload = [
            'status' => 'checking',
            'submitted_at' => $now,
            'evidence_path' => $path
        ];

        if ($todo->started_at) {
            $totalMinutes = Carbon::parse($todo->started_at)->diffInMinutes($now);
            $payload['total_work_time'] = $totalMinutes;
            $payload['total_work_time_formatted'] = $this->formatDuration($totalMinutes);
        }

        $todo->update($payload);

        return response()->json([
            'message' => 'Todo submitted for checking',
            'todo' => new TodoResource($todo)
        ]);
    }

    // GA: per-todo or overall evaluation approve -> completed, or request rework -> evaluating
    public function evaluate(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $data = $request->validate([
            'action' => 'required|in:approve,rework',
            'type' => 'required|in:individual,overall',
            'notes' => 'nullable|string|max:500'
        ]);

        if (!in_array($todo->status, ['checking', 'evaluating'])) {
            return response()->json(['message' => 'Todo is not in a valid evaluation phase'], 422);
        }

        $checkerName = $request->user()->name;
        $checkerRole = $request->user()->role;
        $checkerDisplay = "{$checkerName} ({$checkerRole})";

        if ($data['action'] === 'approve') {
            $todo->update([
                'status' => 'completed',
                'notes' => $data['notes'] ?? $todo->notes,
                'checked_by' => $request->user()->id,
                'checker_display' => $checkerDisplay
            ]);
        } else {
            $todo->update([
                'status' => 'evaluating',
                'notes' => $data['notes'] ?? $todo->notes,
                'checked_by' => $request->user()->id,
                'checker_display' => $checkerDisplay
            ]);
        }

        return response()->json([
            'message' => 'Evaluation recorded',
            'todo' => new TodoResource($todo)
        ]);
    }

    // User: submit improvements during evaluating status
    public function submitImprovement(Request $request, $id)
    {
        try {
            $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

            if ($todo->status !== 'evaluating') {
                return response()->json(['message' => 'Todo is not in evaluation phase'], 422);
            }

            $path = $todo->evidence_path;
            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }

            if ($request->hasFile('evidence')) {
                $request->validate([
                    'evidence' => 'file|mimes:jpeg,png,jpg,gif,webp,bmp,tiff|max:10240'
                ]);
                try {
                    $file = $request->file('evidence');
                    $ext = $file->getClientOriginalExtension();
                    $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, $ext, $now);
                    $path = $file->storeAs($folder, $filename, 'public');
                } catch (\Throwable $e) {
                    $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, 'jpg', $now);
                    $path = $folder . '/' . $filename;
                    Log::warning('Improvement storage failed, using fake path', ['todo_id' => $todo->id, 'error' => $e->getMessage()]);
                }
            }

            $todo->update([
                'status' => 'checking',
                'submitted_at' => $now,
                'evidence_path' => $path
            ]);

            return response()->json([
                'message' => 'Improvement submitted for checking',
                'todo' => new TodoResource($todo)
            ]);
        } catch (\Exception $e) {
            Log::error('Submit Improvement Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GA: overall performance evaluation
    public function evaluateOverall(Request $request, $userId)
    {
        $todos = Todo::where('user_id', $userId)->get();

        $stats = [
            'total_todos' => $todos->count(),
            'completed_todos' => $todos->where('status', 'completed')->count(),
            'in_progress_todos' => $todos->where('status', 'in_progress')->count(),
            'checking_todos' => $todos->where('status', 'checking')->count(),
            'not_started_todos' => $todos->where('status', 'not_started')->count(),
            'completion_rate' => $todos->count() > 0 ?
                round(($todos->where('status', 'completed')->count() / $todos->count()) * 100, 2) :
                0
        ];

        return response()->json([
            'message' => 'Overall todo performance evaluation',
            'user_id' => $userId,
            'stats' => $stats
        ]);
    }

    // User: delete own todo
    public function destroy(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        // Delete evidence file if exists (safe)
        if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
            try {
                Storage::disk('public')->delete($todo->evidence_path);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete evidence file on todo delete', [
                    'todo_id' => $todo->id,
                    'path' => $todo->evidence_path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully']);
    }

    // GA: mark todo as checked
    public function check(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $todo->update([
            'status' => 'checked',
            'checked_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Todo checked successfully', 'todo' => $todo]);
    }

    // GA: add note
    public function addNote(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $request->validate(['notes' => 'required|string']);
        $todo->update(['notes' => $request->notes]);

        return response()->json(['message' => 'Note added successfully', 'todo' => $todo]);
    }
}
