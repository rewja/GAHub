<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;

class TodoController extends Controller
{
    // User: list own todos
    public function index(Request $request)
    {
        return response()->json($request->user()->todos);
    }

    // User: create todo
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date'
        ]);

        $data['user_id'] = $request->user()->id;

        // default workflow status: not_started
        $data['status'] = 'not_started';

        $todo = Todo::create($data);

        return response()->json(['message' => 'Todo created successfully', 'todo' => $todo], 201);
    }

    // User: update own todo
    public function update(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:not_started,in_progress,checking,completed',
            'due_date' => 'nullable|date'
        ]);

        $todo->update($data);

        return response()->json(['message' => 'Todo updated successfully', 'todo' => $todo]);
    }

    // User: start a todo (transition not_started -> in_progress)
    public function start(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'not_started') {
            return response()->json(['message' => 'Invalid state transition'], 422);
        }
        $todo->update(['status' => 'in_progress']);
        return response()->json(['message' => 'Todo started', 'todo' => $todo]);
    }

    // User: submit for checking (transition in_progress -> checking)
    public function submitForChecking(Request $request, $id)
    {
        $todo = Todo::where('user_id', $request->user()->id)->findOrFail($id);
        if ($todo->status !== 'in_progress') {
            return response()->json(['message' => 'Invalid state transition'], 422);
        }
        $data = $request->validate([
            'evidence' => 'nullable|image|max:2048'
        ]);
        $payload = ['status' => 'checking'];
        if ($request->hasFile('evidence')) {
            $payload['evidence_path'] = $request->file('evidence')->store('evidence', 'public');
        }
        $todo->update($payload);
        return response()->json(['message' => 'Todo submitted for checking', 'todo' => $todo]);
    }

    // GA: per-todo or overall evaluation approve -> completed, or request rework -> in_progress with note
    public function evaluate(Request $request, $id)
    {
        $todo = Todo::findOrFail($id);

        $data = $request->validate([
            'action' => 'required|in:approve,rework',
            'type' => 'required|in:individual,overall',
            'notes' => 'nullable|string'
        ]);

        if ($todo->status !== 'checking') {
            return response()->json(['message' => 'Todo is not in checking phase'], 422);
        }

        if ($data['action'] === 'approve') {
            $todo->update([
                'status' => 'completed',
                'notes' => $data['notes'] ?? $todo->notes,
                'checked_by' => $request->user()->id
            ]);
        } else {
            $todo->update([
                'status' => 'in_progress',
                'notes' => $data['notes'] ?? $todo->notes
            ]);
        }

        // If it's an overall evaluation, we might want to do additional processing
        if ($data['type'] === 'overall') {
            // Placeholder for future overall evaluation logic
            // For example, checking if all todos are completed
            $allTodosCompleted = Todo::where('user_id', $todo->user_id)->where('status', '!=', 'completed')->count() === 0;

            if ($allTodosCompleted) {
                // Trigger some overall completion event or notification
                // This is just a placeholder - you'd implement specific business logic here
                \Log::info('User has completed all todos', ['user_id' => $todo->user_id]);
            }
        }

        return response()->json(['message' => 'Evaluation recorded', 'todo' => $todo]);
    }

    // Remove the legacy check method
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
        $todo->delete();

        return response()->json(['message' => 'Todo deleted successfully']);
    }

    // GA: list all todos
    public function indexAll()
    {
        return response()->json(Todo::with('user')->get());
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
