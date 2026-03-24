<?php

namespace NgoTools\LaravelStarter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncCommand extends Command
{
    protected $signature = 'ngotools:sync';

    protected $description = 'Push the local manifest to NGO.Tools to sync changes';

    public function handle(): int
    {
        $devToken = config('ngotools.dev_token');
        $apiUrl = config('ngotools.api_url');

        if (! $devToken || ! $apiUrl) {
            $this->components->error('NGOTOOLS_DEV_TOKEN and NGOTOOLS_API_URL must be set in .env');

            return self::FAILURE;
        }

        $manifestPath = base_path('.well-known/ngotools.json');

        if (! file_exists($manifestPath)) {
            $this->components->error('No manifest found at .well-known/ngotools.json');
            $this->components->info('Run `php artisan ngotools:install` first.');

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            $this->components->error('Invalid manifest JSON.');

            return self::FAILURE;
        }

        $this->components->info('Syncing manifest to NGO.Tools...');

        try {
            $response = Http::post("{$apiUrl}/api/tools-dev/sync", [
                'dev_token' => $devToken,
                'manifest' => $manifest,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->components->info("Manifest synced for tool: {$data['tool_slug']}");

                return self::SUCCESS;
            }

            $this->components->error('Sync failed: ' . $response->body());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->components->error('Could not reach NGO.Tools API: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
