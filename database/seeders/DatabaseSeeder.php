<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name' => 'General Admin',
            'email' => 'ga@example.com',
            'password' => Hash::make('password123'), // ganti sesuai kebutuhan
            'role' => 'admin',
        ]);

        // Procurement
        User::create([
            'name' => 'Procurement Officer',
            'email' => 'procurement@example.com',
            'password' => Hash::make('password123'), // ganti sesuai kebutuhan
            'role' => 'procurement',
        ]);
    }
}
