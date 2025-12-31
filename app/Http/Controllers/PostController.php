<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\DestroyPostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Jobs\NotifyFollowersOfNewPost;
use App\Models\Post;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @group Posts
 *
 * API endpoints for managing posts
 */
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->orderByDesc('created_at')->get();
        return PostResource::collection($posts);
    }

    public function store(CreatePostRequest $request)
    {
        $user = $request->user();

        // Create a new post
        $payload = [
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'user_id' => $user->id,
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('post-images', 'public');
            $payload['image_path'] = $path;

            Log::info('Post image stored', [
                'user_id' => $user->id,
                'path' => $path,
                'mime' => $request->file('image')->getMimeType(),
                'size' => $request->file('image')->getSize(),
            ]);
        }

        $post = Post::create($payload);

        NotifyFollowersOfNewPost::dispatch($post->fresh('user'));

        return new PostResource($post);
    }

    public function show(Post $post)
    {
        return new PostResource($post);
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $post->update([
            'title' => $request->input('title'),
            'body' => $request->input('body'),
        ]);

        return new PostResource($post);
    }

    public function destroy(DestroyPostRequest $request, Post $post)
    {
        $post->delete();

        return response()->noContent();
    }
}
