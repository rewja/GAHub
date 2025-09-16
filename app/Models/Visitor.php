<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = [];


    // Relasi: Guest hadir di Meeting
    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
