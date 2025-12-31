<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateFavoriteRequest;
use App\Http\Resources\FavoriteResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * @group Favorites
 *
 * API endpoints for managing favorites
 */
class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites()->with('favorable')->get();

        return FavoriteResource::collection($favorites);
    }

    public function storePost(CreateFavoriteRequest $request, Post $post)
    {
        $this->favoriteTarget($request->user(), $post);

        return response()->noContent(Response::HTTP_CREATED);
    }

    public function destroyPost(Request $request, Post $post)
    {
        $this->unfavoriteTarget($request->user(), $post);

        return response()->noContent();
    }

    public function storeUser(CreateFavoriteRequest $request, User $user)
    {
        abort_if($request->user()->is($user), Response::HTTP_UNPROCESSABLE_ENTITY, 'You cannot favorite yourself.');

        $this->favoriteTarget($request->user(), $user);

        return response()->noContent(Response::HTTP_CREATED);
    }

    public function destroyUser(Request $request, User $user)
    {
        $this->unfavoriteTarget($request->user(), $user);
        return response()->noContent();
    }

    protected function favoriteTarget($authUser, $model): void
    {
        if ($authUser->hasFavorited($model)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Already favorited.');
        }

        $authUser->favorite($model);

        Log::info('Object favorited', [
            'user_id' => $authUser->id,
            'favorable_id' => $model->getKey(),
            'favorable_type' => $model->getMorphClass(),
        ]);
    }

    protected function unfavoriteTarget($authUser, $model): void
    {
        if (! $authUser->unfavorite($model)) {
            abort(Response::HTTP_NOT_FOUND, 'Favorite does not exist.');
        }

        Log::info('Object unfavorited', [
            'user_id' => $authUser->id,
            'favorable_id' => $model->getKey(),
            'favorable_type' => $model->getMorphClass(),
        ]);
    }
}
