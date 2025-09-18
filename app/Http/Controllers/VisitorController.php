<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    // List all visitors (admin)
    public function index()
    {
        return response()->json(Visitor::latest()->get());
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
            'name' => 'required|string|max:100',
            'company' => 'nullable|string|max:100',
            'id_number' => 'nullable|string|max:50',
            'purpose' => 'required|string',
            'person_to_meet' => 'required|string|max:100',
            'photo' => 'nullable|image|max:4096',
            'check_in' => 'required|date',
            'check_out' => 'nullable|date|after:check_in',
            'notes' => 'nullable|string',
        ]);

        // Normalize datetime to DB format (handles ISO 8601 with timezone)
        if (!empty($data['check_in'])) {
            $data['check_in'] = \Carbon\Carbon::parse($data['check_in'])->format('Y-m-d H:i:s');
        }
        if (!empty($data['check_out'])) {
            $data['check_out'] = \Carbon\Carbon::parse($data['check_out'])->format('Y-m-d H:i:s');
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('visitors', 'public');
        }

        $visitor = Visitor::create($data);

        return response()->json(['message' => 'Visitor registered', 'visitor' => $visitor], 201);
    }

    // Admin: visitor statistics
    public function stats()
    {
        $daily = \DB::table('visitors')
            ->selectRaw('DATE(check_in) as date, COUNT(*) as total')
            ->groupByRaw('DATE(check_in)')
            ->orderByRaw('DATE(check_in) DESC')
            ->limit(30)
            ->get();

        $monthly = \DB::table('visitors')
            ->selectRaw('strftime("%Y-%m", check_in) as ym, COUNT(*) as total')
            ->groupByRaw('strftime("%Y-%m", check_in)')
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('visitors')
            ->selectRaw('strftime("%Y", check_in) as y, COUNT(*) as total')
            ->groupByRaw('strftime("%Y", check_in)')
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Visitor $visitor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Visitor $visitor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visitor $visitor)
    {
        //
    }
}
