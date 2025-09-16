<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'total_price',
        'purchase_date',
        'status',
        'notes'
    ];

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
