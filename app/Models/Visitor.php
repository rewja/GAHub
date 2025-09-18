<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sequence',
        'meet_with',
        'purpose',
        'origin',
        'visit_time',
        'check_in',
        'check_out',
        'ktp_image_path',
        'ktp_ocr',
        'face_image_path',
        'face_verified',
        'status',
    ];

    protected $casts = [
        'visit_time' => 'datetime',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'ktp_ocr' => 'array',
        'face_verified' => 'boolean',
    ];
}
