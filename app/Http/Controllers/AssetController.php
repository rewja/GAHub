<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assets = Asset::with(['request', 'procurement'])->latest()->get();
        return response()->json($assets);
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
            'category' => 'required|string|max:100',
            'location' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        // simple asset code generator
        $data['asset_code'] = 'AST-' . Str::upper(Str::random(8));

        $asset = Asset::create($data);

        return response()->json(['message' => 'Asset created', 'asset' => $asset], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Asset $asset)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Asset $asset)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:not_received,received,needs_repair,needs_replacement',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $asset->update($data);

        return response()->json(['message' => 'Asset updated', 'asset' => $asset]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Asset $asset)
    {
        //
    }
}
