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
        // Resolve public path and absolute URL for evidence file
        $publicPath = $this->evidence_path ? Storage::url($this->evidence_path) : null;
        $absoluteUrl = $publicPath ? url($publicPath) : null;

        // evidence_name follows actual filename (without extension)
        $fileBase = $this->evidence_path ? pathinfo($this->evidence_path, PATHINFO_BASENAME) : null;
        $evidenceName = $fileBase ? pathinfo($fileBase, PATHINFO_FILENAME) : null;

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
            'evidence_name' => $evidenceName,
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
            'evidence' => [
                'path' => $publicPath,
                'url' => $absoluteUrl,
                'exists' => Storage::disk('public')->exists($this->evidence_path)
            ],
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
