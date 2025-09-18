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

    // Procurement stats: counts and total value per month/year, top vendors if available
    public function stats()
    {
        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(purchase_date, "%Y-%m")' : 'strftime("%Y-%m", purchase_date)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(purchase_date, "%Y")'   : 'strftime("%Y", purchase_date)';

        $monthlyCount = \DB::table('procurements')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $monthlyAmount = \DB::table('procurements')
            ->selectRaw("{$monthExpr} as ym, SUM(amount) as total_amount")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearlyCount = \DB::table('procurements')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $yearlyAmount = \DB::table('procurements')
            ->selectRaw("{$yearExpr} as y, SUM(amount) as total_amount")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'monthly' => [ 'count' => $monthlyCount, 'amount' => $monthlyAmount ],
            'yearly' => [ 'count' => $yearlyCount, 'amount' => $yearlyAmount ],
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
