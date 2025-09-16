<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Todo extends Model
{
    protected $fillable = [
        'user_id',
        'task',
        'is_done'
    ];

    // Relasi: Todo dimiliki oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
