<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonInterval;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ktpPath = $this->ktp_image_path ? Storage::url($this->ktp_image_path) : null;
        $facePath = $this->face_image_path ? Storage::url($this->face_image_path) : null;
        $checkIn = $this->check_in ? $this->check_in->timezone('Asia/Jakarta') : null;
        $checkOut = $this->check_out ? $this->check_out->timezone('Asia/Jakarta') : null;
        $durationText = null;
        if ($checkIn && $checkOut) {
            // Use raw timestamps and clamp to avoid negative edge cases
            $seconds = max(0, (int)$checkOut->timestamp - (int)$checkIn->timestamp);
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $secs = $seconds % 60;
            $parts = [];
            if ($hours > 0) $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
            if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            if ($secs > 0 || empty($parts)) $parts[] = $secs . ' second' . ($secs != 1 ? 's' : '');
            $durationText = implode(', ', $parts);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'meet_with' => $this->meet_with,
            'purpose' => $this->purpose,
            'origin' => $this->origin,
            // visit_time uses same style as todos' formatted duration
            'visit_time' => $durationText,
            'check_in' => $checkIn?->locale('id')->translatedFormat('l, d F Y H:i:s'),
            'check_out' => $checkOut?->locale('id')->translatedFormat('l, d F Y H:i:s'),
            'ktp_image' => $ktpPath ? [
                'path' => $ktpPath,
                'url' => url($ktpPath),
                'exists' => Storage::disk('public')->exists($this->ktp_image_path)
            ] : null,
            'ktp_ocr' => $this->ktp_ocr,
            'face_image' => $facePath ? [
                'path' => $facePath,
                'url' => url($facePath),
                'exists' => Storage::disk('public')->exists($this->face_image_path)
            ] : null,
            'face_verified' => (bool) $this->face_verified,
            'status' => $this->status,
            'created_at' => $this->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y H:i:s'),
        ];
    }
}


