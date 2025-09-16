<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use App\Models\RequestItem;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $procurements = Procurement::with(['request', 'user'])->latest()->get();

        return response()->json($procurements);
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
            'request_items_id' => 'required|exists:request_items,id',
            'purchase_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['executed_by'] = $request->user()->id;

        $procurement = Procurement::create($data);

        // Update related request status to purchased if currently approved
        $req = RequestItem::find($data['request_items_id']);
        if ($req && $req->status !== 'purchased') {
            $req->update(['status' => 'purchased']);
        }

        return response()->json(['message' => 'Procurement recorded', 'procurement' => $procurement], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Procurement $procurement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Procurement $procurement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Procurement $procurement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Procurement $procurement)
    {
        //
    }
}
