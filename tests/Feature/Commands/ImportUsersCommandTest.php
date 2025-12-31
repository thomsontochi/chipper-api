<?php

namespace Tests\Feature\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class ImportUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected array $payload = [
        [
            'name' => 'Leanne Graham',
            'email' => 'Sincere@april.biz',
            'username' => 'Bret',
        ],
        [
            'name' => 'Ervin Howell',
            'email' => 'Shanna@melissa.tv',
            'username' => 'Antonette',
        ],
        [
            'name' => 'Clementine Bauch',
            'email' => 'Nathan@yesenia.net',
            'username' => 'Samantha',
        ],
    ];

    public function test_it_imports_users_up_to_the_limit(): void
    {
        Http::fake([
            'jsonplaceholder.typicode.com/*' => Http::response($this->payload, 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => 2,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'Sincere@april.biz']);
        $this->assertDatabaseHas('users', ['email' => 'Shanna@melissa.tv']);
        $this->assertDatabaseMissing('users', ['email' => 'Nathan@yesenia.net']);
    }

    public function test_existing_users_are_skipped_without_failure(): void
    {
        User::factory()->create([
            'name' => 'Existing User',
            'email' => 'Sincere@april.biz',
        ]);

        Http::fake([
            'jsonplaceholder.typicode.com/*' => Http::response($this->payload, 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => 3,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('users', 3);
    }

    public function test_it_handles_invalid_limit_input(): void
    {
        $this->artisan('import:users', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => 0,
        ])->assertExitCode(Command::FAILURE);

        $this->artisan('import:users', [
            'url' => 'invalid-url',
            'limit' => 1,
        ])->assertExitCode(Command::FAILURE);
    }

    public function test_it_fails_when_request_is_unsuccessful(): void
    {
        Http::fake([
            'jsonplaceholder.typicode.com/*' => Http::response([], 500),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => 1,
        ])->assertExitCode(Command::FAILURE);
    }

    public function test_it_reports_malformed_records(): void
    {
        $payload = [
            ['name' => 'Missing Email'],
            'totally-invalid-structure',
        ];

        Http::fake([
            'jsonplaceholder.typicode.com/*' => Http::response($payload, 200),
        ]);

        $this->artisan('import:users', [
            'url' => 'https://jsonplaceholder.typicode.com/users',
            'limit' => 2,
        ])->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('users', 0);
    }
}
