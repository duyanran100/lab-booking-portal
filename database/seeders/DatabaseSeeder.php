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
        User::factory()->create([
            'first_name' => 'admin',
            'last_name' => 'user',
            'preferred_name' => 'admin',
            'email' => 'admin@adelaide.edu.au',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'first_name' => 'guest',
            'last_name' => 'user',
            'preferred_name' => 'guest',
            'email' => 'guest@adelaide.edu.au',
            'password' => Hash::make('password'),
            'role' => 'guest',
        ]);
    }
}
