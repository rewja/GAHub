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
                'ktp_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'face_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
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

            // Determine sequence (global per day, keyed by name)
            $baseDatePath = "visitors/{$year}/{$month}/{$day}";

            $todayQuery = Visitor::whereDate('created_at', $now->toDateString());
            $sameNameQuery = (clone $todayQuery)->where('name', $data['name']);
            $existingSameName = $sameNameQuery->get();

            if ($existingSameName->count() > 0) {
                // If same name already exists today: reuse the assigned sequence (keep it stable)
                $seqsDb = $existingSameName->pluck('sequence')->filter()->all();
                $seqFromFs = null;
                if (Storage::disk('public')->exists($baseDatePath)) {
                    foreach (Storage::disk('public')->directories($baseDatePath) as $dir) {
                        $basename = basename($dir);
                        if (preg_match('/^' . preg_quote($safeName, '/') . '-(\d{2})$/', $basename, $m)) {
                            $seqFromFs = (int) $m[1];
                            // only capture for this name; loop continues but value is fine
                        }
                    }
                }
                $sequenceCandidates = $seqsDb;
                if ($seqFromFs) { $sequenceCandidates[] = $seqFromFs; }
                $sequence = count($sequenceCandidates) > 0 ? min($sequenceCandidates) : 1;

                // Replace: delete DB rows and folders for this name today
                foreach ($existingSameName as $ev) { $ev->delete(); }
                if (Storage::disk('public')->exists($baseDatePath)) {
                    foreach (Storage::disk('public')->directories($baseDatePath) as $dir) {
                        $basename = basename($dir);
                        if (preg_match('/^' . preg_quote($safeName, '/') . '-(\d{2})$/', $basename)) {
                            Storage::disk('public')->deleteDirectory($dir);
                        }
                    }
                }
            } else {
                // New name today: assign next global sequence for the day
                $maxSeqDb = (int) ($todayQuery->max('sequence') ?? 0);
                $maxSeqFs = 0;
                if (Storage::disk('public')->exists($baseDatePath)) {
                    foreach (Storage::disk('public')->directories($baseDatePath) as $dir) {
                        $basename = basename($dir);
                        if (preg_match('/-(\d{2})$/', $basename, $m)) {
                            $maxSeqFs = max($maxSeqFs, (int)$m[1]);
                        }
                    }
                }
                $baseline = max($maxSeqDb, $maxSeqFs);
                $sequence = $baseline > 0 ? $baseline + 1 : 1;
            }

            $folder = "visitors/{$year}/{$month}/{$day}/{$safeName}-" . str_pad((string)$sequence, 2, '0', STR_PAD_LEFT);
            $faceFolder = $folder . '/face';
            $ktpFolder = $folder . '/ktp';

            // Filenames
            $timePart = $now->format('Ymd_His');
            $ktpFilename = null;
            $faceFilename = null;
            if ($request->hasFile('ktp_image')) {
                if (!Storage::disk('public')->exists($ktpFolder)) {
                    Storage::disk('public')->makeDirectory($ktpFolder);
                }
                $ktpExt = $request->file('ktp_image')->getClientOriginalExtension();
                $ktpFilename = "KTP-{$timePart}-{$safeName}.{$ktpExt}";
            }
            if ($request->hasFile('face_image')) {
                if (!Storage::disk('public')->exists($faceFolder)) {
                    Storage::disk('public')->makeDirectory($faceFolder);
                }
                $faceExt = $request->file('face_image')->getClientOriginalExtension();
                $faceFilename = "FACE-{$timePart}-{$safeName}.{$faceExt}";
            }

            // Store as
            $ktpPath = null;
            $facePath = null;
            if ($ktpFilename) {
                $ktpPath = $request->file('ktp_image')->storeAs($ktpFolder, $ktpFilename, 'public');
            }
            if ($faceFilename) {
                $facePath = $request->file('face_image')->storeAs($faceFolder, $faceFilename, 'public');
            }

            // OCR stub (replace with real OCR service)
            $ktpOcr = $ktpPath ? app('app.services.ocr')->extract($ktpPath) : null;

            // Face recognition stub (replace with real FR service)
            $faceVerified = $facePath ? app('app.services.face')->verify($facePath, $data['name']) : false;

            $visitor = Visitor::create([
                'name' => $data['name'],
                'sequence' => $sequence,
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

    // Admin: visitor statistics
    public function stats()
    {
        $daily = \DB::table('visitors')
            ->selectRaw('DATE(check_in) as date, COUNT(*) as total')
            ->groupByRaw('DATE(check_in)')
            ->orderByRaw('DATE(check_in) DESC')
            ->limit(30)
            ->get();

        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(check_in, "%Y-%m")' : 'strftime("%Y-%m", check_in)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(check_in, "%Y")'   : 'strftime("%Y", check_in)';

        $monthly = \DB::table('visitors')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('visitors')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'daily' => $daily,
            'monthly' => $monthly,
            'yearly' => $yearly,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $visitor = Visitor::findOrFail($id);

            $data = $request->validate([
                'name' => 'sometimes|required|string|max:150',
                'meet_with' => 'sometimes|required|string|max:150',
                'purpose' => 'sometimes|required|string|max:300',
                'origin' => 'nullable|string|max:150',
                'ktp_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
                'face_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            if (isset($data['name'])) {
                $data['name'] = Str::title(trim($data['name']));
            }

            $now = Carbon::now('Asia/Jakarta');
            $year = $now->format('Y');
            $month = $now->format('m');
            $day = $now->format('d');
            $safeName = trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $data['name'] ?? $visitor->name));
            $folder = "visitors/{$year}/{$month}/{$day}/{$safeName}-01";
            $faceFolder = $folder . '/face';
            $ktpFolder = $folder . '/ktp';
            if (!Storage::disk('public')->exists($faceFolder)) {
                Storage::disk('public')->makeDirectory($faceFolder);
            }
            if (!Storage::disk('public')->exists($ktpFolder)) {
                Storage::disk('public')->makeDirectory($ktpFolder);
            }

            if ($request->hasFile('ktp_image')) {
                $timePart = $now->format('Ymd_His');
                $ktpExt = $request->file('ktp_image')->getClientOriginalExtension();
                $ktpFilename = "KTP-{$timePart}-{$safeName}.{$ktpExt}";
                $ktpPath = $request->file('ktp_image')->storeAs($ktpFolder, $ktpFilename, 'public');
                $data['ktp_image_path'] = $ktpPath;
                $data['ktp_ocr'] = app('app.services.ocr')->extract($ktpPath);
            }

            if ($request->hasFile('face_image')) {
                $timePart = $now->format('Ymd_His');
                $faceExt = $request->file('face_image')->getClientOriginalExtension();
                $faceFilename = "FACE-{$timePart}-{$safeName}.{$faceExt}";
                $facePath = $request->file('face_image')->storeAs($faceFolder, $faceFilename, 'public');
                $data['face_image_path'] = $facePath;
                $data['face_verified'] = app('app.services.face')->verify($facePath, $data['name'] ?? $visitor->name);
            }

            $visitor->update($data);

            return response()->json([
                'message' => 'Visitor updated',
                'visitor' => new VisitorResource($visitor->fresh()),
            ]);
        } catch (\Throwable $e) {
            Log::error('Visitor update failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Internal server error',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $visitor = Visitor::findOrFail($id);

            // Attempt to remove stored files if exist
            if ($visitor->ktp_image_path) {
                Storage::disk('public')->delete($visitor->ktp_image_path);
            }
            if ($visitor->face_image_path) {
                Storage::disk('public')->delete($visitor->face_image_path);
            }

            $visitor->delete();
            return response()->json(['message' => 'Visitor deleted']);
        } catch (\Throwable $e) {
            Log::error('Visitor delete failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Internal server error',
            ], 500);
        }
    }
}
