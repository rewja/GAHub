<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $fillable = [
        'meeting_id',
        'name',
        'organization',
        'notes'
    ];

    // Relasi: Guest hadir di Meeting
    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
