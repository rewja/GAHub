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
            'due_date' => 'nullable|date',
            'evidence' => 'nullable|image|max:2048'
        ]);

        $data['user_id'] = $request->user()->id;

        // handle evidence upload if provided
        if ($request->hasFile('evidence')) {
            $path = $request->file('evidence')->store('evidence', 'public');
            $data['evidence_path'] = $path;
        }

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
            'status' => 'sometimes|in:pending,in_progress,done,checked',
            'due_date' => 'nullable|date',
            'evidence' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('evidence')) {
            $path = $request->file('evidence')->store('evidence', 'public');
            $data['evidence_path'] = $path;
        }

        $todo->update($data);

        return response()->json(['message' => 'Todo updated successfully', 'todo' => $todo]);
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
