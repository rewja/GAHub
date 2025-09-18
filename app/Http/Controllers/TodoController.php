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
            ->whereDate('created_at', $date->toDateString())
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

    private function renameEvidenceFile(string $oldPath, string $suffix): ?string
    {
        if (!$oldPath || !Storage::disk('public')->exists($oldPath)) {
            return null;
        }

        $pathInfo = pathinfo($oldPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $newFilename = $filename . '-' . $suffix . ($extension ? '.' . $extension : '');
        $newPath = $directory . '/' . $newFilename;

        try {
            Storage::disk('public')->move($oldPath, $newPath);
            return $newPath;
        } catch (\Throwable $e) {
            Log::warning('Failed to rename evidence file', [
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
        // 1) After checking phase (evaluating/reworked/completed) => block any edits
        if (in_array($currentStatus, ['evaluating', 'reworked', 'completed'])) {
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

        // 3) During checking => allow evidence addition/replacement (text changes ignored)
        if ($currentStatus === 'checking') {
            // Evidence is mandatory for update during checking
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if (!$hasEvidence) {
                return response()->json([
                    'message' => 'Evidence file is required during checking to update'
                ], 422);
            }

            // Simple validation that works with both single and array format
            $files = $request->file('evidence');
            if (!$files) {
                return response()->json([
                    'message' => 'No evidence files found'
                ], 422);
}

            $now = Carbon::now();
            $folder = $this->getEvidenceFolder($now, $request->user()->name);
            if (!Storage::disk('public')->exists($folder)) {
                Storage::disk('public')->makeDirectory($folder, 0755, true);
            }

            // Extract sequence number from original filename if exists
            $sequenceNumber = null;
            if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                // Extract sequence number from filename like "ETD-03-..."
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }
            }

            // Get sequence number for new files
            if (!$sequenceNumber) {
                $todoCreatedDate = Carbon::parse($todo->created_at);
                $sequenceNumber = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
            }

            // Handle files - support both single file and array format
            $evidenceFiles = $files;
            if (!is_array($evidenceFiles)) {
                $evidenceFiles = [$evidenceFiles];
            }

            // Filter out null files and limit to maximum 5 files
            $evidenceFiles = array_filter($evidenceFiles, function($file) {
                return $file !== null && $file->isValid();
            });
            $evidenceFiles = array_slice($evidenceFiles, 0, 5);

            if (empty($evidenceFiles)) {
                return response()->json([
                    'message' => 'No valid evidence files found'
                ], 422);
            }

            // Check maximum 5 files for new uploads
            if (count($evidenceFiles) > 5) {
                return response()->json([
                    'message' => 'Maximum 5 evidence files allowed'
                ], 422);
            }

            // IMPORTANT: User must re-upload ALL files - delete all old evidence files first
            if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                foreach ($todo->evidence_paths as $oldPath) {
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                Storage::disk('public')->delete($todo->evidence_path);
            }

            // Store only the new files uploaded by user
            $storedPaths = [];
            $day = $this->getDayNameId($now);
            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
            $timePart = $now->format('Y-m-d H.i.s');

            foreach ($evidenceFiles as $index => $file) {
                $ext = $file->getClientOriginalExtension();
                $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Updated-Checking-{$index}.{$ext}";
                $path = $file->storeAs($folder, $filename, 'public');
                $storedPaths[] = $path;
            }

            // Store the first file path as primary evidence_path and all paths in evidence_paths
            $todo->evidence_path = $storedPaths[0] ?? null;
            $todo->evidence_paths = $storedPaths;
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
        try {
            Log::info('SubmitForChecking Debug', [
                'user_id' => $request->user()->id,
                'todo_id' => $id,
                'has_evidence' => $request->hasFile('evidence'),
                'has_evidence_array' => $request->hasFile('evidence.*'),
                'all_files' => $request->allFiles(),
                'method' => $request->method()
            ]);

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
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if (!$hasEvidence) {
                return response()->json([
                    'message' => 'Evidence file is required when submitting for checking'
                ], 422);
            }

            // Simple validation that works with both single and array format
            $files = $request->file('evidence');
            if (!$files) {
                return response()->json([
                    'message' => 'No evidence files found'
                ], 422);
            }

            // Extract sequence number from original filename if exists
            $sequenceNumber = null;
            if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                // Extract sequence number from filename like "ETD-03-..."
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }
                // Delete old file completely
                Storage::disk('public')->delete($todo->evidence_path);
            }

            // Get sequence number for new files
            if (!$sequenceNumber) {
                $todoCreatedDate = Carbon::parse($todo->created_at);
                $sequenceNumber = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
            }

            // Prepare filename components
            $day = $this->getDayNameId($now);
            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
            $timePart = $now->format('Y-m-d H.i.s');

            // Handle files - support both single file and array format
            $evidenceFiles = $files;
            if (!is_array($evidenceFiles)) {
                $evidenceFiles = [$evidenceFiles];
            }

            // Filter out null files and limit to maximum 5 files
            $evidenceFiles = array_filter($evidenceFiles, function($file) {
                return $file !== null && $file->isValid();
            });
            $evidenceFiles = array_slice($evidenceFiles, 0, 5);

            if (empty($evidenceFiles)) {
                return response()->json([
                    'message' => 'No valid evidence files found'
                ], 422);
            }

            $storedPaths = [];
            foreach ($evidenceFiles as $index => $file) {
                $ext = $file->getClientOriginalExtension();
                $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-{$index}.{$ext}";
                $path = $file->storeAs($folder, $filename, 'public');
                $storedPaths[] = $path;
            }

            // Store the first file path as the main evidence_path and all paths in evidence_paths
            $path = $storedPaths[0] ?? null;

            $payload = [
                'status' => 'checking',
                'submitted_at' => $now,
                'evidence_path' => $path,
                'evidence_paths' => $storedPaths
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

        } catch (\Exception $e) {
            Log::error('SubmitForChecking Error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
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

        if (!in_array($todo->status, ['checking', 'reworked'])) {
            return response()->json(['message' => 'Todo is not in a valid evaluation phase'], 422);
        }

        $checkerName = $request->user()->name;
        $checkerRole = $request->user()->role;
        $checkerDisplay = "{$checkerName} ({$checkerRole})";

        $createdWarning = null;

        if ($data['action'] === 'approve') {
            // Delete old evidence files and create new ones with "Approved" suffix
            $newPaths = [];
            if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                // Handle multiple files
                foreach ($todo->evidence_paths as $index => $oldPath) {
                    if (Storage::disk('public')->exists($oldPath)) {
                        // Extract sequence number from original filename
                        $originalFilename = pathinfo($oldPath, PATHINFO_BASENAME);
                        $sequenceNumber = null;
                        if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                            $sequenceNumber = $matches[1];
                        }

                        // Delete old file completely
                        Storage::disk('public')->delete($oldPath);

                        // Create new file with Approved suffix
                        if ($sequenceNumber) {
                            $now = Carbon::now();
                            $day = $this->getDayNameId($now);
                            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $todo->user->name ?: 'User');
                            $timePart = $now->format('Y-m-d H.i.s');
                            $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
                            $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Approved-{$index}.{$ext}";

                            $folder = $this->getEvidenceFolder($now, $todo->user->name);
                            if (!Storage::disk('public')->exists($folder)) {
                                Storage::disk('public')->makeDirectory($folder, 0755, true);
                            }

                            // Create empty file with new name
                            $newPath = $folder . '/' . $filename;
                            Storage::disk('public')->put($newPath, '');
                            $newPaths[] = $newPath;
                        }
                    }
                }
                $todo->evidence_paths = $newPaths;
                $todo->evidence_path = $newPaths[0] ?? null;
            } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                // Handle single file for backward compatibility
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                $sequenceNumber = null;
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }

                // Delete old file completely
                Storage::disk('public')->delete($todo->evidence_path);

                // Create new file with Approved suffix
                if ($sequenceNumber) {
                    $now = Carbon::now();
                    $day = $this->getDayNameId($now);
                    $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $todo->user->name ?: 'User');
                    $timePart = $now->format('Y-m-d H.i.s');
                    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
                    $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Approved.{$ext}";

                    $folder = $this->getEvidenceFolder($now, $todo->user->name);
                    if (!Storage::disk('public')->exists($folder)) {
                        Storage::disk('public')->makeDirectory($folder, 0755, true);
                    }

                    // Create empty file with new name
                    $newPath = $folder . '/' . $filename;
                    Storage::disk('public')->put($newPath, '');
                    $todo->evidence_path = $newPath;
                    $todo->evidence_paths = [$newPath];
                }
            }

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
            // Delete old evidence files and create new ones with "Rework" suffix
            $newPaths = [];
            if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                // Handle multiple files
                foreach ($todo->evidence_paths as $index => $oldPath) {
                    if (Storage::disk('public')->exists($oldPath)) {
                        // Extract sequence number from original filename
                        $originalFilename = pathinfo($oldPath, PATHINFO_BASENAME);
                        $sequenceNumber = null;
                        if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                            $sequenceNumber = $matches[1];
                        }

                        // Delete old file completely
                        Storage::disk('public')->delete($oldPath);

                        // Create new file with Rework suffix
                        if ($sequenceNumber) {
                            $now = Carbon::now();
                            $day = $this->getDayNameId($now);
                            $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $todo->user->name ?: 'User');
                            $timePart = $now->format('Y-m-d H.i.s');
                            $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
                            $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Rework-{$index}.{$ext}";

                            $folder = $this->getEvidenceFolder($now, $todo->user->name);
                            if (!Storage::disk('public')->exists($folder)) {
                                Storage::disk('public')->makeDirectory($folder, 0755, true);
                            }

                            // Create empty file with new name
                            $newPath = $folder . '/' . $filename;
                            Storage::disk('public')->put($newPath, '');
                            $newPaths[] = $newPath;
                        }
                    }
                }
                $todo->evidence_paths = $newPaths;
                $todo->evidence_path = $newPaths[0] ?? null;
            } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                // Handle single file for backward compatibility
                $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                $sequenceNumber = null;
                if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                    $sequenceNumber = $matches[1];
                }

                // Delete old file completely
                Storage::disk('public')->delete($todo->evidence_path);

                // Create new file with Rework suffix
                if ($sequenceNumber) {
                    $now = Carbon::now();
                    $day = $this->getDayNameId($now);
                    $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $todo->user->name ?: 'User');
                    $timePart = $now->format('Y-m-d H.i.s');
                    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
                    $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Rework.{$ext}";

                    $folder = $this->getEvidenceFolder($now, $todo->user->name);
                    if (!Storage::disk('public')->exists($folder)) {
                        Storage::disk('public')->makeDirectory($folder, 0755, true);
                    }

                    // Create empty file with new name
                    $newPath = $folder . '/' . $filename;
                    Storage::disk('public')->put($newPath, '');
                    $todo->evidence_path = $newPath;
                    $todo->evidence_paths = [$newPath];
                }
            }

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

            // Check if evidence files are provided
            $hasEvidence = $request->hasFile('evidence') ||
                          (is_array($request->file('evidence')) && count(array_filter($request->file('evidence'))) > 0);

            if ($hasEvidence) {
                // Simple validation that works with both single and array format
                $files = $request->file('evidence');
                if (!$files) {
                    return response()->json([
                        'message' => 'No evidence files found'
                    ], 422);
                }

                // Extract sequence number from original filename if exists
                $sequenceNumber = null;
                if ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                    $originalFilename = pathinfo($todo->evidence_path, PATHINFO_BASENAME);
                    // Extract sequence number from filename like "ETD-03-..."
                    if (preg_match('/ETD-(\d+)-/', $originalFilename, $matches)) {
                        $sequenceNumber = $matches[1];
                    }
                }

                // Handle files - support both single file and array format
                $evidenceFiles = $files;
                if (!is_array($evidenceFiles)) {
                    $evidenceFiles = [$evidenceFiles];
                }

                // Filter out null files and limit to maximum 5 files
                $evidenceFiles = array_filter($evidenceFiles, function($file) {
                    return $file !== null && $file->isValid();
                });
                $evidenceFiles = array_slice($evidenceFiles, 0, 5);

                if (empty($evidenceFiles)) {
                    return response()->json([
                        'message' => 'No valid evidence files found'
                    ], 422);
                }

                // Check maximum 5 files for new uploads
                if (count($evidenceFiles) > 5) {
                    return response()->json([
                        'message' => 'Maximum 5 evidence files allowed'
                    ], 422);
                }

                // IMPORTANT: User must re-upload ALL files - delete all old evidence files first
                if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
                    foreach ($todo->evidence_paths as $oldPath) {
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
                    Storage::disk('public')->delete($todo->evidence_path);
                }

                // Store only the new files uploaded by user
                $storedPaths = [];

                // Prepare filename components
                $day = $this->getDayNameId($now);
                $safeUser = preg_replace('/[^A-Za-z0-9\- ]/', '', $request->user()->name ?: 'User');
                $timePart = $now->format('Y-m-d H.i.s');

                foreach ($evidenceFiles as $index => $file) {
                    $ext = $file->getClientOriginalExtension();

                    // Generate filename with same sequence number but current timestamp and -Reworked suffix
                    if ($sequenceNumber) {
                        $filename = "ETD-{$sequenceNumber}-{$safeUser}-{$day}-{$timePart}-Reworked-{$index}.{$ext}";
                    } else {
                        // If no original file, use todo's created_at date for sequence number
                        $todoCreatedDate = Carbon::parse($todo->created_at);
                        $seq = str_pad((string) $this->nextDailySequence($request->user()->id, $todoCreatedDate), 2, '0', STR_PAD_LEFT);
                        $filename = "ETD-{$seq}-{$safeUser}-{$day}-{$timePart}-Reworked-{$index}.{$ext}";
                    }

                    $path = $file->storeAs($folder, $filename, 'public');
                    $storedPaths[] = $path;
                }

                $path = $storedPaths[0] ?? null;
            }

            $todo->update([
                'status' => 'reworked',
                'submitted_at' => $now,
                'evidence_path' => $path,
                'evidence_paths' => $storedPaths ?? []
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
        $completedTodosToday = $todos->where('status', 'completed')->count();
        $totalMinutes = (int) $todos->sum(function ($t) {
            return (int) ($t->total_work_time ?? 0);
        });

        // Calculate average completion time per todo
        $averageTimePerTodo = $completedTodosToday > 0 ? round($totalMinutes / $completedTodosToday, 1) : 0;

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
            'completed_todos_today' => $completedTodosToday,
            'total_time_formatted_today' => $this->formatDuration($totalMinutes),
            'average_time_per_todo' => $this->formatDuration($averageTimePerTodo),
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

        // Rename evidence files with "Deleted" suffix instead of deleting
        if ($todo->evidence_paths && is_array($todo->evidence_paths)) {
            // Handle multiple files
            $newPaths = [];
            foreach ($todo->evidence_paths as $index => $path) {
                if (Storage::disk('public')->exists($path)) {
                    $newPath = $this->renameEvidenceFile($path, 'Deleted');
                    if ($newPath) {
                        $newPaths[] = $newPath;
                    }
                }
            }
            $todo->evidence_paths = $newPaths;
            $todo->evidence_path = $newPaths[0] ?? null;
            $todo->save(); // Save the updated paths before deleting
        } elseif ($todo->evidence_path && Storage::disk('public')->exists($todo->evidence_path)) {
            // Handle single file for backward compatibility
            $newPath = $this->renameEvidenceFile($todo->evidence_path, 'Deleted');
            if ($newPath) {
                $todo->evidence_path = $newPath;
                $todo->evidence_paths = [$newPath];
                $todo->save(); // Save the updated path before deleting
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

