<?php

use App\Models\Post;
use App\Models\User;

it('prevents a guest from favoriting a post', function () {
    $post = Post::factory()->create();

    $this->postJson(route('favorites.posts.store', ['post' => $post]))
        ->assertUnauthorized();
});

it('allows a user to favorite a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->postJson(route('favorites.posts.store', ['post' => $post]))
        ->assertCreated();

    $this->assertDatabaseHas('favorites', [
        'favorable_id' => $post->id,
        'favorable_type' => Post::class,
        'user_id' => $user->id,
    ]);
});

it('allows a user to remove a post from favorites', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->postJson(route('favorites.posts.store', ['post' => $post]));

    $this->actingAs($user)
        ->deleteJson(route('favorites.posts.destroy', ['post' => $post]))
        ->assertNoContent();

    $this->assertDatabaseMissing('favorites', [
        'favorable_id' => $post->id,
        'favorable_type' => Post::class,
        'user_id' => $user->id,
    ]);
});

it('does not allow removing a non favorited post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('favorites.posts.destroy', ['post' => $post]))
        ->assertNotFound();
});

it('allows a user to favorite another user', function () {
    [$actor, $author] = User::factory()->count(2)->create();

    $this->actingAs($actor)
        ->postJson(route('favorites.users.store', ['user' => $author]))
        ->assertCreated();

    $this->assertDatabaseHas('favorites', [
        'favorable_id' => $author->id,
        'favorable_type' => User::class,
        'user_id' => $actor->id,
    ]);
});

it('prevents a user from favoriting themselves', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->postJson(route('favorites.users.store', ['user' => $actor]))
        ->assertUnprocessable();
});

it('prevents duplicate favorites for the same target', function () {
    [$actor, $author] = User::factory()->count(2)->create();

    $this->actingAs($actor)
        ->postJson(route('favorites.users.store', ['user' => $author]))
        ->assertCreated();

    $this->actingAs($actor)
        ->postJson(route('favorites.users.store', ['user' => $author]))
        ->assertUnprocessable();
});

it('allows a user to remove a favorite author', function () {
    [$actor, $author] = User::factory()->count(2)->create();

    $this->actingAs($actor)
        ->postJson(route('favorites.users.store', ['user' => $author]));

    $this->actingAs($actor)
        ->deleteJson(route('favorites.users.destroy', ['user' => $author]))
        ->assertNoContent();

    $this->assertDatabaseMissing('favorites', [
        'favorable_id' => $author->id,
        'favorable_type' => User::class,
        'user_id' => $actor->id,
    ]);
});

it('lists posts and users grouped on favorites index', function () {
    [$actor, $author, $anotherAuthor] = User::factory()->count(3)->create();
    $post = Post::factory()->for($author)->create([
        'title' => 'All about cats',
        'body' => 'Lorem Ipsum...',
    ]);

    $this->actingAs($actor)->postJson(route('favorites.posts.store', ['post' => $post]));
    $this->actingAs($actor)->postJson(route('favorites.users.store', ['user' => $anotherAuthor]));

    $response = $this->actingAs($actor)->getJson(route('favorites.index'));

    $response
        ->assertOk()
        ->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $post->id,
                        'title' => 'All about cats',
                        'body' => 'Lorem Ipsum...',
                        'user' => [
                            'id' => $author->id,
                            'name' => $author->name,
                        ],
                    ],
                ],
                'users' => [
                    [
                        'id' => $anotherAuthor->id,
                        'name' => $anotherAuthor->name,
                    ],
                ],
            ],
        ])
        ->assertJsonCount(1, 'data.posts')
        ->assertJsonCount(1, 'data.users');
});
