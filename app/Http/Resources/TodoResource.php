<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve public path and absolute URL for evidence file
        $publicPath = $this->evidence_path ? Storage::url($this->evidence_path) : null; // "/storage/..."
        $absoluteUrl = $publicPath ? url($publicPath) : null; // APP_URL + /storage/...

        // evidence_name follows actual filename (without extension)
        $fileBase = $this->evidence_path ? pathinfo($this->evidence_path, PATHINFO_BASENAME) : null;
        $evidenceName = $fileBase ? pathinfo($fileBase, PATHINFO_FILENAME) : null;

        $data = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'evidence_name' => $evidenceName,      // no extension, mirrors filename (contains day)
            'status' => $this->status,
            'checked_by' => $this->checked_by,
            'checker_display' => $this->checker_display,
            'notes' => $this->notes,
            'due_date' => $this->due_date,
            'scheduled_date' => $this->scheduled_date,
            'started_at' => $this->started_at,
            'submitted_at' => $this->submitted_at,
            'total_work_time' => $this->total_work_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'formatted_created_at' => $this->formatted_created_at,
            'formatted_updated_at' => $this->formatted_updated_at,
            'formatted_started_at' => $this->formatted_started_at,
            'formatted_submitted_at' => $this->formatted_submitted_at,
            'formatted_due_date' => $this->formatted_due_date,
            'day_of_due_date' => $this->day_of_due_date
        ];

        if ($this->evidence_path) {
            $data['evidence'] = [
                'path' => $publicPath,
                'url' => $absoluteUrl,
                'exists' => Storage::disk('public')->exists($this->evidence_path)
            ];
        }

        return $data;
    }
}
