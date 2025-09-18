<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Http\Resources\VisitorResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VisitorController extends Controller
{
    public function index(Request $request)
    {
        $query = Visitor::query();
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('meet_with', 'like', "%$s%");
            });
        }
        $visitors = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));
        return VisitorResource::collection($visitors);
    }

    public function show($id)
    {
        $visitor = Visitor::findOrFail($id);
        return new VisitorResource($visitor);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:150',
                'meet_with' => 'required|string|max:150',
                'purpose' => 'required|string|max:300',
                'origin' => 'nullable|string|max:150',
                'ktp_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
                'face_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            $now = Carbon::now('Asia/Jakarta');

            // Normalize visitor name to Title Case to keep it tidy
            $normalizedName = Str::title(trim($data['name']));
            $data['name'] = $normalizedName;

            // Build folder: visitors/YYYY/MM/DD/{visitor_name}-{sequence}
            $year = $now->format('Y');
            $month = $now->format('m');
            $day = $now->format('d');
            $safeName = trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $data['name']));

            // Replace existing entries for same name on the same day
            $baseDatePath = "visitors/{$year}/{$month}/{$day}";
            $existingDirs = Storage::disk('public')->directories($baseDatePath);
            foreach ($existingDirs as $dir) {
                // Expect directory like .../Name-XX
                $basename = basename($dir);
                if (preg_match('/^' . preg_quote($safeName, '/') . '-\\d{2}$/', $basename)) {
                    Storage::disk('public')->deleteDirectory($dir);
                }
            }
            Visitor::where('name', $data['name'])
                ->whereDate('created_at', $now->toDateString())
                ->delete();

            // Always start fresh with sequence 01 after replacement
            $sequence = 1;

            $folder = "visitors/{$year}/{$month}/{$day}/{$safeName}-" . str_pad((string)$sequence, 2, '0', STR_PAD_LEFT);
            $faceFolder = $folder . '/face';
            $ktpFolder = $folder . '/ktp';
            if (!Storage::disk('public')->exists($faceFolder)) {
                Storage::disk('public')->makeDirectory($faceFolder);
            }
            if (!Storage::disk('public')->exists($ktpFolder)) {
                Storage::disk('public')->makeDirectory($ktpFolder);
            }

            // Filenames
            $timePart = $now->format('Ymd_His');
            $ktpExt = $request->file('ktp_image')->getClientOriginalExtension();
            $faceExt = $request->file('face_image')->getClientOriginalExtension();
            $ktpFilename = "KTP-{$timePart}-{$safeName}.{$ktpExt}";
            $faceFilename = "FACE-{$timePart}-{$safeName}.{$faceExt}";

            // Store as
            $ktpPath = $request->file('ktp_image')->storeAs($ktpFolder, $ktpFilename, 'public');
            $facePath = $request->file('face_image')->storeAs($faceFolder, $faceFilename, 'public');

            // OCR stub (replace with real OCR service)
            $ktpOcr = app('app.services.ocr')->extract($ktpPath);

            // Face recognition stub (replace with real FR service)
            $faceVerified = app('app.services.face')->verify($facePath, $data['name']);

            $visitor = Visitor::create([
                'name' => $data['name'],
                'meet_with' => $data['meet_with'],
                'purpose' => $data['purpose'],
                'origin' => $data['origin'] ?? null,
                'visit_time' => $now,
                'check_in' => $now, // set at registration time
                'ktp_image_path' => $ktpPath,
                'ktp_ocr' => $ktpOcr,
                'face_image_path' => $facePath,
                'face_verified' => $faceVerified,
                'status' => 'checked_in',
            ]);

            return response()->json([
                'message' => 'Visitor registered',
                'visitor' => new VisitorResource($visitor),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Visitor register failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function checkIn(Request $request, $id)
    {
        $visitor = Visitor::findOrFail($id);
        $visitor->update([
            'status' => 'checked_in',
            'check_in' => Carbon::now('Asia/Jakarta'),
        ]);
        return response()->json(['message' => 'Visitor checked in', 'visitor' => new VisitorResource($visitor)]);
    }

    public function checkOut(Request $request, $id)
    {
        $visitor = Visitor::findOrFail($id);
        if ($visitor->status !== 'checked_in') {
            return response()->json([
                'message' => 'Invalid state',
                'error' => 'Visitor is not in checked_in status',
            ], 422);
        }

        $visitor->update([
            'status' => 'checked_out',
            'check_out' => Carbon::now('Asia/Jakarta'),
        ]);
        return response()->json(['message' => 'Visitor checked out', 'visitor' => new VisitorResource($visitor)]);
    }
}
