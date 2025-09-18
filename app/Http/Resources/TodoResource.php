<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\User;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Handle multiple evidence files
        $evidenceFiles = [];
        if ($this->evidence_paths && is_array($this->evidence_paths)) {
            foreach ($this->evidence_paths as $path) {
                $evidenceFiles[] = [
                    'path' => Storage::url($path),
                    'url' => url(Storage::url($path)),
                    'exists' => Storage::disk('public')->exists($path),
                    'name' => pathinfo($path, PATHINFO_FILENAME)
                ];
            }
        } elseif ($this->evidence_path) {
            // Fallback to single file for backward compatibility
            $publicPath = Storage::url($this->evidence_path);
            $absoluteUrl = url($publicPath);
            $evidenceName = pathinfo($this->evidence_path, PATHINFO_FILENAME);

            $evidenceFiles[] = [
                'path' => $publicPath,
                'url' => $absoluteUrl,
                'exists' => Storage::disk('public')->exists($this->evidence_path),
                'name' => $evidenceName
            ];
        }

        // Resolve checker display
        $checkerDisplay = null;
        if ($this->checked_by) {
            $checker = \App\Models\User::find($this->checked_by);
            if ($checker) {
                $checkerDisplay = "{$checker->name} ({$checker->role})";
            } else {
                $checkerDisplay = $this->checked_by;
            }
        }

    // Get latest warning for this todo
    $latestWarning = null;
    if ($this->relationLoaded('warnings')) {
        $latestWarning = $this->warnings->sortByDesc('created_at')->first();
    }

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'checked_by' => $checkerDisplay,
            'checker_display' => $checkerDisplay,
            'notes' => $this->notes,
            'due_date' => $this->due_date,
            'scheduled_date' => $this->scheduled_date,
            'started_at' => $this->started_at,
            'submitted_at' => $this->submitted_at,
            'total_work_time' => $this->total_work_time,
            'total_work_time_formatted' => $this->total_work_time_formatted,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' (' . $this->created_at->diffForHumans() . ')',
            'evidence_files' => $evidenceFiles,
        ];

        // Only include updated_at if it's different from created_at
        if ($this->updated_at && $this->updated_at->gt($this->created_at)) {
            $data['updated_at'] = $this->updated_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' (' . $this->updated_at->diffForHumans() . ')';
        }

    // Add warnings section with report (always present)
    $data['warnings'] = [
        'report' => [
            'points' => $latestWarning ? $latestWarning->points : null,
            'level' => $latestWarning ? $latestWarning->level : null,
            'note' => $latestWarning ? $latestWarning->note : null,
            'published_at' => $latestWarning ? $latestWarning->created_at->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : null
        ]
    ];

        return $data;
    }
}
