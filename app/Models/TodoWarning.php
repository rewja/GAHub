<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TodoWarning extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function todo()
    {
        return $this->belongsTo(Todo::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}




