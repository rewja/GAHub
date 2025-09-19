<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = [];

    // Asset berasal dari Request
    public function request()
    {
        return $this->belongsTo(RequestItem::class, 'request_items_id');
    }

    // Asset terkait dengan Procurement
    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    // Asset assigned to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
