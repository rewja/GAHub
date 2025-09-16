<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    // List all meetings for authenticated user (or all for admin)
    public function index(Request $request)
    {
        $query = Meeting::query();
        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }
        return response()->json($query->latest()->get());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'room_name' => 'required|string|max:100',
            'agenda' => 'required|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $data['user_id'] = $request->user()->id;

        $meeting = Meeting::create($data);

        return response()->json(['message' => 'Meeting booked', 'meeting' => $meeting], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Meeting $meeting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Meeting $meeting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function start(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeUser($request, $meeting);
        $meeting->update(['status' => 'ongoing']);
        return response()->json(['message' => 'Meeting started', 'meeting' => $meeting]);
    }

    public function end(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $this->authorizeUser($request, $meeting);
        $meeting->update(['status' => 'ended']);
        return response()->json(['message' => 'Meeting ended', 'meeting' => $meeting]);
    }

    public function forceEnd(Request $request, $id)
    {
        $meeting = Meeting::findOrFail($id);
        $meeting->update(['status' => 'force_ended']);
        return response()->json(['message' => 'Meeting force ended', 'meeting' => $meeting]);
    }

    private function authorizeUser(Request $request, Meeting $meeting): void
    {
        if ($request->user()->role !== 'admin' && $meeting->user_id !== $request->user()->id) {
            abort(response()->json(['message' => 'Unauthorized'], 403));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Meeting $meeting)
    {
        //
    }
}
