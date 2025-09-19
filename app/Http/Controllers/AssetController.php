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

    // Admin: asset statistics
    public function stats()
    {
        $byCategory = \DB::table('assets')
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->get();

        $byStatus = \DB::table('assets')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';

        $timeline = \DB::table('assets')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        return response()->json([
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'timeline' => $timeline,
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

    // User: list own assets
    public function mine(Request $request)
    {
        // Filter assets by the related request's owner since assets table has no user_id column
        $assets = Asset::with(['request', 'procurement'])
            ->whereHas('request', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })
            ->latest()
            ->get();
        return response()->json($assets);
    }

    // User: update asset status (received, not_received, needs_repair, needs_replacement)
    public function updateUserStatus(Request $request, $id)
    {
        $asset = Asset::whereHas('request', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })->findOrFail($id);
        
        $data = $request->validate([
            'status' => 'required|in:received,not_received,needs_repair,needs_replacement',
            'receipt_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'repair_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'status' => $data['status'],
            'user_notes' => $data['notes'] ?? null,
        ];

        // Handle file uploads
        if ($request->hasFile('receipt_proof')) {
            $file = $request->file('receipt_proof');
            $filename = 'receipt_' . $asset->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-proofs', $filename, 'public');
            $updateData['receipt_proof_path'] = $path;
        }

        if ($request->hasFile('repair_proof')) {
            $file = $request->file('repair_proof');
            $filename = 'repair_' . $asset->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('asset-proofs', $filename, 'public');
            $updateData['repair_proof_path'] = $path;
        }

        $asset->update($updateData);

        return response()->json(['message' => 'Asset status updated', 'asset' => $asset]);
    }
}
