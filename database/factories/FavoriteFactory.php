<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Favorite::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'favorable_id' => Post::factory(),
            'favorable_type' => Post::class,
            'user_id' => User::factory(),
        ];
    }

    public function user(): self
    {
        return $this->state(function () {
            return [
                'favorable_id' => User::factory(),
                'favorable_type' => User::class,
            ];
        });
    }
}
