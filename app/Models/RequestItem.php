<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestItem extends Model
{
    protected $fillable = [
        'user_id',
        'item_name',
        'quantity',
        'status', // pending, approved, rejected
    ];

    // Relasi: Request dimiliki oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Request bisa menghasilkan banyak Asset
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    // Request bisa punya banyak procurement
    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }
}
