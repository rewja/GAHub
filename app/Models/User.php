<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function requests()
    {
        return $this->hasMany(RequestItem::class);
    }

    // Relasi: User punya banyak Todo
    public function todos()
    {
        return $this->hasMany(Todo::class);
    }

    // Relasi: User bisa booking Meeting
    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    // User dengan role procurement/GA bisa punya banyak procurement
    public function procurements()
    {
        return $this->hasMany(Procurement::class);
    }
}
