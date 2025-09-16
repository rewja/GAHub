<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'user_id',
        'room_name',
        'start_time',
        'end_time',
        'status' // scheduled, ongoing, ended
    ];

    // Relasi: Meeting dibuat oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Meeting punya banyak Guest
    public function guests()
    {
        return $this->hasMany(Visitor::class);
    }
}
