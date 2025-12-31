<?php

namespace Database\Seeders;

use App\Models\Favorite;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Favorite::factory()
            ->count(5)
            ->create();

        Favorite::factory()
            ->user()
            ->count(3)
            ->create();
    }
}
