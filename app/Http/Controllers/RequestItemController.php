<?php

namespace App\Http\Controllers;

use App\Models\RequestItem;
use Illuminate\Http\Request;

class RequestItemController extends Controller
{
    // User: list own requests
    public function mine(Request $request)
    {
        $items = RequestItem::where('user_id', $request->user()->id)->latest()->get();
        return response()->json($items);
    }

    // User: create request
    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name' => 'required|string|max:200',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;

        $req = RequestItem::create($data);

        return response()->json(['message' => 'Request created successfully', 'request' => $req], 201);
    }

    // GA: list all requests
    public function index()
    {
        return response()->json(RequestItem::with('user')->get());
    }

    // User: statistics (counts per day/month/year and status distribution)
    public function statsUser(Request $request)
    {
        $userId = $request->user()->id;
        $daily = \DB::table('request_items')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $monthly = \DB::table('request_items')
            ->selectRaw('strftime("%Y-%m", created_at) as ym, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupByRaw('strftime("%Y-%m", created_at)')
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('request_items')
            ->selectRaw('strftime("%Y", created_at) as y, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupByRaw('strftime("%Y", created_at)')
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $status = \DB::table('request_items')
            ->selectRaw('status, COUNT(*) as total')
            ->where('user_id', $userId)
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'status' => $status,
        ]);
    }

    // Admin: global statistics
    public function statsGlobal(Request $request)
    {
        $daily = \DB::table('request_items')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) DESC')
            ->limit(30)
            ->get();

        $monthly = \DB::table('request_items')
            ->selectRaw('strftime("%Y-%m", created_at) as ym, COUNT(*) as total')
            ->groupByRaw('strftime("%Y-%m", created_at)')
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('request_items')
            ->selectRaw('strftime("%Y", created_at) as y, COUNT(*) as total')
            ->groupByRaw('strftime("%Y", created_at)')
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        $status = \DB::table('request_items')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
            'status' => $status,
        ]);
    }

    // GA: approve request
    public function approve(Request $request, $id)
    {
        $req = RequestItem::findOrFail($id);
        $req->update([
            'status' => 'approved',
            'ga_note' => $request->ga_note ?? null,
        ]);

        return response()->json(['message' => 'Request approved', 'request' => $req]);
    }

    // GA: reject request
    public function reject(Request $request, $id)
    {
        $req = RequestItem::findOrFail($id);
        $req->update([
            'status' => 'rejected',
            'ga_note' => $request->ga_note ?? null,
        ]);

        return response()->json(['message' => 'Request rejected', 'request' => $req]);
    }
}
