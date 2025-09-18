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
        $todos = $request->user()->todos()->with('warnings')->latest()->get();
        return TodoResource::collection($todos);
    }

    // GA/Admin: list all todos (optional filter by user_id)
    public function indexAll(Request $request)
    {
        $query = Todo::with(['user', 'warnings'])->latest();
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        $todos = $query->get();

        // If scoped to a single user, append warning totals meta
        if ($request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
            $totals = \App\Models\TodoWarning::query()
                ->whereHas('todo', function ($q) use ($userId) { $q->where('user_id', $userId); })
                ->selectRaw('SUM(points) as total_points, SUM(CASE WHEN level="low" THEN points ELSE 0 END) as low_points, SUM(CASE WHEN level="medium" THEN points ELSE 0 END) as medium_points, SUM(CASE WHEN level="high" THEN points ELSE 0 END) as high_points')
                ->first();

            return TodoResource::collection($todos)->additional([
                'warning_totals' => [
                    'low_points' => (int) ($totals->low_points ?? 0),
                    'medium_points' => (int) ($totals->medium_points ?? 0),
                    'high_points' => (int) ($totals->high_points ?? 0),
                    'total_points' => (int) ($totals->total_points ?? 0),
                ]
            ]);
        }

        return TodoResource::collection($todos);
    }

    // GA/Admin: list todos by specific user
    public function indexByUser($userId)
    {
        $todos = Todo::with(['user', 'warnings'])->where('user_id', $userId)->latest()->get();

        $totals = \App\Models\TodoWarning::query()
            ->whereHas('todo', function ($q) use ($userId) { $q->where('user_id', $userId); })
            ->selectRaw('SUM(points) as total_points, SUM(CASE WHEN level="low" THEN points ELSE 0 END) as low_points, SUM(CASE WHEN level="medium" THEN points ELSE 0 END) as medium_points, SUM(CASE WHEN level="high" THEN points ELSE 0 END) as high_points')
            ->first();

        return TodoResource::collection($todos)->additional([
            'warning_totals' => [
                'low_points' => (int) ($totals->low_points ?? 0),
                'medium_points' => (int) ($totals->medium_points ?? 0),
                'high_points' => (int) ($totals->high_points ?? 0),
                'total_points' => (int) ($totals->total_points ?? 0),
            ]
        ]);
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

        // Evidence is mandatory for submitForChecking
        if (!$request->hasFile('evidence')) {
            return response()->json([
                'message' => 'Evidence file is required when submitting for checking'
            ], 422);
        }

        $request->validate([
            'evidence' => 'required|file|mimes:jpeg,png,jpg,gif,webp,bmp,tiff|max:10240'
        ]);

        try {
            $file = $request->file('evidence');
            $ext = $file->getClientOriginalExtension();
            $filename = $this->buildEvidenceFilename($request->user()->name, $request->user()->id, $ext, $now);
            $path = $file->storeAs($folder, $filename, 'public');
        } catch (\Throwable $e) {
            Log::error('Evidence storage failed on submitForChecking', [
                'todo_id' => $todo->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to store evidence file'
            ], 500);
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
            'notes' => 'nullable|string|max:500',
            'warning_points' => 'nullable|integer|min:0|max:300',
            'warning_note' => 'nullable|string|max:500'
        ]);

        if (!in_array($todo->status, ['checking', 'evaluating'])) {
            return response()->json(['message' => 'Todo is not in a valid evaluation phase'], 422);
        }

        $checkerName = $request->user()->name;
        $checkerRole = $request->user()->role;
        $checkerDisplay = "{$checkerName} ({$checkerRole})";

        $createdWarning = null;

        if ($data['action'] === 'approve') {
            $todo->update([
                'status' => 'completed',
                'notes' => $data['notes'] ?? $todo->notes,
                'checked_by' => $request->user()->id,
                'checker_display' => $checkerDisplay
            ]);

            $points = (int)($data['warning_points'] ?? 0);
            if ($points > 0) {
                $level = null;
                if ($points >= 1 && $points <= 35) {
                    $level = 'low';
                } elseif ($points >= 36 && $points <= 65) {
                    $level = 'medium';
                } elseif ($points >= 66) {
                    $level = 'high';
                }

                $createdWarning = $todo->warnings()->create([
                    'evaluator_id' => $request->user()->id,
                    'points' => $points,
                    'level' => $level,
                    'note' => $data['warning_note'] ?? null,
                ]);
            }
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
            'todo' => new TodoResource($todo),
            'warning' => $createdWarning ? [
                'points' => (int) $createdWarning->points,
                'level' => $createdWarning->level,
                'note' => $createdWarning->note,
                'created_at' => $createdWarning->created_at,
            ] : null
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
        $date = $request->input('date');
        $day = $date ? Carbon::parse($date, 'Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        $startJakarta = $day->copy()->startOfDay();
        $endJakarta = $day->copy()->endOfDay();
        $startUtc = $startJakarta->copy()->timezone('UTC');
        $endUtc = $endJakarta->copy()->timezone('UTC');

        $todos = Todo::where('user_id', $userId)
            ->whereDate('submitted_at', $day->toDateString())
            ->get();

        $totalTodosToday = $todos->count();
        $totalMinutes = (int) $todos->sum(function ($t) {
            return (int) ($t->total_work_time ?? 0);
        });

        $low = 0; $medium = 0; $high = 0; $warningsCount = 0; $warningPoints = 0;
        $warnings = \App\Models\TodoWarning::whereHas('todo', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->get();
        foreach ($warnings as $w) {
            $warningsCount++;
            $warningPoints += (int) $w->points;
            if ($w->level === 'low') $low++;
            elseif ($w->level === 'medium') $medium++;
            elseif ($w->level === 'high') $high++;
        }

        // Example performance score: 100 - clamp(sum(points)/3, 0..100)
        $scorePenalty = (int) floor($warningPoints / 3);
        if ($scorePenalty > 100) $scorePenalty = 100;
        $performanceScore = max(0, 100 - $scorePenalty);

        return response()->json([
            'message' => 'Overall todo performance evaluation (daily)',
            'user_id' => $userId,
            'date' => $day->toDateString(),
            'total_todos_today' => $totalTodosToday,
            'total_time_minutes_today' => $totalMinutes,
            'total_time_formatted_today' => $this->formatDuration($totalMinutes),
            'warnings' => [
                'count' => $warningsCount,
                'sum_points' => $warningPoints,
                'breakdown' => [
                    'low' => $low,
                    'medium' => $medium,
                    'high' => $high,
                ],
            ],
            'performance_score' => $performanceScore,
        ]);
    }

    // Admin/GA: monthly leaderboard of warning points
    public function warningsLeaderboard(Request $request)
    {
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'search' => 'nullable|string',
            'user_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $month = $request->input('month');
        $perPage = (int)($request->input('per_page', 20));
        $search = $request->input('search');
        $filterUserId = $request->input('user_id');

        $start = $month ? Carbon::createFromFormat('Y-m', $month, 'Asia/Jakarta')->startOfMonth() : now('Asia/Jakarta')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $startUtc = $start->copy()->timezone('UTC');
        $endUtc = $end->copy()->timezone('UTC');

        $query = \App\Models\TodoWarning::query()
            ->selectRaw('users.id as user_id, users.name as user_name, users.role as user_role, SUM(todo_warnings.points) as total_points, COUNT(todo_warnings.id) as count_warnings, MAX(todo_warnings.created_at) as last_warning_at')
            ->join('todos', 'todo_warnings.todo_id', '=', 'todos.id')
            ->join('users', 'todos.user_id', '=', 'users.id')
            ->whereBetween('todo_warnings.created_at', [$startUtc, $endUtc])
            ->groupBy('users.id', 'users.name', 'users.role');

        if ($search) {
            $query->where('users.name', 'like', "%{$search}%");
        }
        if ($filterUserId) {
            $query->where('users.id', $filterUserId);
        }

        $paginator = $query->orderByDesc('total_points')->paginate($perPage)->withQueryString();

        $rankStart = ($paginator->currentPage() - 1) * $paginator->perPage();
        $data = [];
        foreach ($paginator->items() as $index => $row) {
            // Format total warning points (misal: 25/300)
            $totalPoints = (int) $row->total_points;
            $warningDisplay = "{$totalPoints}/300";

            // Format waktu terakhir mendapat peringatan
            $lastWarningAt = null;
            if ($row->last_warning_at) {
                $lastWarning = Carbon::parse($row->last_warning_at)->timezone('Asia/Jakarta');
                $dayNames = [
                    'Sunday' => 'Minggu',
                    'Monday' => 'Senin',
                    'Tuesday' => 'Selasa',
                    'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis',
                    'Friday' => 'Jumat',
                    'Saturday' => 'Sabtu'
                ];
                $dayName = $dayNames[$lastWarning->format('l')];
                $lastWarningAt = $dayName . ', ' . $lastWarning->format('d F Y H:i:s');
            }

            $data[] = [
                'rank' => $rankStart + $index + 1,
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'user_role' => $row->user_role,
                'warning_points' => $warningDisplay,
                'last_warning_at' => $lastWarningAt,
            ];
        }

        return response()->json([
            'month' => $start->format('Y-m'),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'data' => $data,
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
