<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use App\Notifications\FavoritedAuthorPublished;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFollowersOfNewPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Post $post
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $author = $this->post->user()->with('favoritedBy.user')->first();

        if (! $author) {
            return;
        }

        $followers = $author->favoritedBy
            ->loadMissing('user')
            ->pluck('user')
            ->filter();

        if ($followers->isEmpty()) {
            return;
        }

        $followers->each(function (User $follower) use ($author) {
            $follower->notify(new FavoritedAuthorPublished($author, $this->post));
        });
    }
}
