<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ImportUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users
        {url : URL pointing to the JSON users payload}
        {limit : Maximum number of users to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from a JSON endpoint';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $limitArg = $this->argument('limit');

        if (! is_numeric($limitArg)) {
            $this->components->error('Limit must be a numeric value.');

            return self::FAILURE;
        }

        $limit = (int) $limitArg;

        if ($limit < 1) {
            $this->components->error('Limit must be greater than zero.');

            return self::FAILURE;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->components->error('The provided URL is invalid.');

            return self::FAILURE;
        }

        $this->components->info("Fetching users from {$url} (limit: {$limit})");

        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
        } catch (Throwable $exception) {
            Log::error('User import request failed', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            $this->components->error("Request failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error("Request failed with status {$response->status()}");

            return self::FAILURE;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            $this->components->error('JSON payload is not an array.');

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $failures = [];

        foreach (array_slice($payload, 0, $limit) as $index => $record) {
            $result = $this->importRecord($record);

            if ($result === 'created') {
                $created++;
                Log::info('User imported', [
                    'email' => Arr::get($record, 'email'),
                    'index' => $index,
                ]);

                continue;
            }

            if ($result === 'skipped') {
                $skipped++;
                Log::info('User skipped (already exists)', [
                    'email' => Arr::get($record, 'email'),
                    'index' => $index,
                ]);

                continue;
            }

            $failed++;
            $failures[] = $result;
            Log::warning('User import failure', [
                'index' => $index,
                'reason' => $result,
            ]);
        }

        $this->components->info("Import completed. Created: {$created}, Skipped: {$skipped}, Failed: {$failed}");

        if ($failed > 0) {
            $this->newLine();
            $this->components->warn('Failures:');
            foreach ($failures as $failure) {
                $this->line("- {$failure}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function importRecord(mixed $record): string
    {
        if (! is_array($record)) {
            return 'Malformed record encountered.';
        }

        $email = Arr::get($record, 'email');
        $name = Arr::get($record, 'name');

        if (! $email || ! $name) {
            return sprintf('Missing name/email for record %s', json_encode($record));
        }

        try {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Str::random(32),
                ]
            );
        } catch (Throwable $exception) {
            Log::error('User import failed for record', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);

            return sprintf('Failed to import %s: %s', $email, $exception->getMessage());
        }

        return $user->wasRecentlyCreated ? 'created' : 'skipped';
    }
}
