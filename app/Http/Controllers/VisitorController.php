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

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('visitors', 'public');
        }

        $visitor = Visitor::create($data);

        return response()->json(['message' => 'Visitor registered', 'visitor' => $visitor], 201);
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
