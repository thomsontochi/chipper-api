<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedBy(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favorable');
    }

    public function hasFavorited(EloquentModel $model): bool
    {
        return $this->favoriteRelation($model)->exists();
    }

    public function favorite(EloquentModel $model): Favorite
    {
        return $this->favorites()->create([
            'favorable_id' => $model->getKey(),
            'favorable_type' => $model->getMorphClass(),
        ]);
    }

    public function unfavorite(EloquentModel $model): bool
    {
        $favorite = $this->favoriteRelation($model)->first();

        if (! $favorite) {
            return false;
        }

        return (bool) $favorite->delete();
    }

    protected function favoriteRelation(EloquentModel $model): HasMany
    {
        return $this->favorites()
            ->where('favorable_type', $model->getMorphClass())
            ->where('favorable_id', $model->getKey());
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
