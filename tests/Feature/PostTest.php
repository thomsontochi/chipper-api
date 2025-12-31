<?php

namespace Tests\Feature;

use App\Jobs\NotifyFollowersOfNewPost;
use App\Models\Post;
use App\Models\User;
use App\Notifications\FavoritedAuthorPublished;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_create_a_post()
    {
        $response = $this->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_user_can_create_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'body',
                ]
            ])
            ->assertJson([
                'data' => [
                    'title' => 'Test Post',
                    'body' => 'This is a test post.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);
    }

    public function test_a_user_can_create_a_post_with_an_image()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('cover.png', 600, 600);

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Photo Post',
            'body' => 'Look at this image.',
            'image' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.image_url', fn ($url) => is_string($url) && str_contains($url, '/storage/'));

        $postId = Arr::get($response->json(), 'data.id');
        $post = Post::findOrFail($postId);

        Storage::disk('public')->assertExists($post->image_path);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'image_path' => $post->image_path,
        ]);
    }

    public function test_invalid_image_is_rejected()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Invalid Image',
            'body' => 'Should fail.',
            'image' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_a_user_can_update_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'title' => 'Updated title',
                    'body' => 'Updated body.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Updated title',
            'body' => 'Updated body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_not_update_a_post_by_other_user()
    {
        $john = User::factory()->create(['name' => 'John']);
        $jack = User::factory()->create(['name' => 'Jack']);

        $response = $this->actingAs($john)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($jack)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'title' => 'Original title',
            'body' => 'Original body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_destroy_one_of_his_posts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'My title',
            'body' => 'My body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->deleteJson(route('posts.destroy', ['post' => $id]));

        $response->assertNoContent();

        $this->assertDatabaseMissing('posts', [
            'id' => $id,
        ]);
    }

    public function test_job_is_dispatched_to_notify_followers_when_post_is_created()
    {
        Queue::fake();

        $author = User::factory()->create();

        $response = $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Queued Title',
            'body' => 'Queued body.',
        ]);

        $response->assertCreated();

        $postId = Arr::get($response->json(), 'data.id');

        Queue::assertPushed(NotifyFollowersOfNewPost::class, function (NotifyFollowersOfNewPost $job) use ($postId) {
            return $job->post->id === $postId;
        });
    }

    public function test_followers_receive_notification_when_job_runs()
    {
        Notification::fake();

        $author = User::factory()->create();
        $followers = User::factory()->count(2)->create();
        $stranger = User::factory()->create();

        $followers->each(fn (User $follower) => $follower->favorite($author));

        $post = Post::factory()->for($author)->create([
            'title' => 'Followers update',
            'body' => 'This is some content',
        ]);

        (new NotifyFollowersOfNewPost($post->fresh('user')))->handle();

        $followers->each(function (User $follower) {
            Notification::assertSentTo($follower, FavoritedAuthorPublished::class);
        });

        Notification::assertNotSentTo($stranger, FavoritedAuthorPublished::class);
    }
}
