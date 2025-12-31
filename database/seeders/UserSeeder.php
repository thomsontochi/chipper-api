<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $knownUsers = [
            [
                'name' => 'Chipper Author',
                'email' => 'author@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Chipper Follower',
                'email' => 'follower@example.com',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Chipper Power User',
                'email' => 'power@example.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($knownUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        User::factory()
            ->count(2)
            ->create();
    }
}
