<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = [];

    // Asset berasal dari Request
    public function request()
    {
        return $this->belongsTo(RequestItem::class);
    }

    // Asset terkait dengan Procurement
    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

}
