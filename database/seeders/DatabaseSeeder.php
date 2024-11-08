<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use League\Csv\Reader;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Load CSV file
        $csvFilePath = database_path('seeders/data/users.csv');
        $csv = Reader::createFromPath($csvFilePath, 'r');
        $csv->setHeaderOffset(0); // The first row will be treated as the header

        // Loop through each record in the CSV and create a user
        foreach ($csv as $row) {
            User::factory()->create([
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'preferred_name' => $row['preferred_name'],
                'email' => $row['email'],
                'password' => Hash::make($row['password']),
                'role' => $row['role'],
            ]);
        }
    }
}
