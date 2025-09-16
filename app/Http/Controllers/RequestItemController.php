<?php

namespace App\Http\Controllers;

use App\Models\RequestItem;
use Illuminate\Http\Request;

class RequestItemController extends Controller
{
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
