<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    protected $guarded = [];


    // Relasi: Todo dimiliki oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
