<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    // List all meetings for authenticated user (or all for admin)
    public function index(Request $request)
    {
        $query = Meeting::with('user');
        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }
        return response()->json($query->latest()->get());
    }

    // Stats: counts per day/month/year, average duration, top rooms
    public function stats(Request $request)
    {
        $isAdmin = $request->user()->role === 'admin';
        $base = \DB::table('meetings');
        if (!$isAdmin) {
            $base->where('user_id', $request->user()->id);
        }

        $daily = (clone $base)
            ->selectRaw('DATE(start_time) as date, COUNT(*) as total')
            ->groupByRaw('DATE(start_time)')
            ->orderByRaw('DATE(start_time) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(start_time, "%Y-%m")' : 'strftime("%Y-%m", start_time)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(start_time, "%Y")'   : 'strftime("%Y", start_time)';

        $monthly = (clone $base)
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = (clone $base)
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        if ($driver === 'mysql') {
            $avgDuration = (clone $base)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_minutes')
                ->value('avg_minutes');
        } else {
            $avgDuration = (clone $base)
                ->selectRaw('AVG((julianday(end_time) - julianday(start_time)) * 24 * 60) as avg_minutes')
                ->value('avg_minutes');
        }

        $topRooms = (clone $base)
            ->selectRaw('room_name, COUNT(*) as total')
            ->groupBy('room_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'avg_duration_minutes' => $avgDuration ? round($avgDuration, 2) : 0,
            'top_rooms' => $topRooms,
        ]);
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
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $data['user_id'] = $request->user()->id;

        $meeting = Meeting::create($data);

        return response()->json(['message' => 'Meeting booked', 'meeting' => $meeting], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Meeting $meeting)
    {
        // Check if user has access to this meeting
        if ($request->user()->role !== 'admin' && $meeting->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $meeting->load('user');
        return response()->json($meeting);
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
        // Only creator or admin can delete
        // Note: route model binding provides $meeting
        // We will authorize using same rule as other actions
        request()->user()->role === 'admin' || $meeting->user_id === request()->user()->id
            ?: abort(response()->json(['message' => 'Unauthorized'], 403));

        $meeting->delete();
        return response()->json(['message' => 'Meeting deleted']);
    }
}
