<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $guarded = [];


    // Relasi: Procurement milik Request
    public function request()
    {
        return $this->belongsTo(RequestItem::class);
    }

    // Relasi: Procurement dieksekusi oleh User (procurement/GA)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
