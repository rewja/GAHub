<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestItem extends Model
{
    protected $guarded = [];


    // Relasi: Request dimiliki oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi: Request bisa menghasilkan banyak Asset
    public function assets()
    {
        return $this->hasMany(Asset::class, 'request_items_id');
    }

    // Request bisa punya banyak procurement
    public function procurements()
    {
        return $this->hasMany(Procurement::class, 'request_items_id');
    }
}
