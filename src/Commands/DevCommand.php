<?php

namespace NgoTools\LaravelStarter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class DevCommand extends Command
{
    protected $signature = 'ngotools:dev
        {--port= : Override development server port}
        {--no-tunnel : Skip tunnel setup}';

    protected $description = 'Start the development server with cloudflared tunnel';

    protected ?object $serverProcess = null;

    protected ?object $tunnelProcess = null;

    public function handle(): int
    {
        $port = (int) ($this->option('port') ?? config('ngotools.port', 8001));
        $devToken = config('ngotools.dev_token');
        $apiUrl = config('ngotools.api_url');
        $isConnected = $devToken && $apiUrl;

        if ($isConnected) {
            $this->registerShutdownHandler($devToken, $apiUrl);
        } else {
            $this->registerShutdownHandler(null, null);
            $this->components->warn('Not connected to an NGO.Tools instance. Running in local-only mode.');
            $this->components->info('Run `ngotools connect` to link your app with an instance.');
            $this->newLine();
        }

        $tunnelUrl = null;

        // Start tunnel FIRST (if enabled) so we know the URL before starting the server
        if (! $this->option('no-tunnel') && $isConnected) {
            $tunnelUrl = $this->startTunnel($port);

            if (! $tunnelUrl) {
                return self::FAILURE;
            }

            // Register tunnel URL with NGO.Tools
            $this->registerTunnel($tunnelUrl, "http://localhost:{$port}", $devToken, $apiUrl);
        }

        // Start Laravel dev server — with APP_URL set to tunnel URL if available
        $this->components->info("Starting development server on port {$port}...");

        $env = [];
        if ($tunnelUrl) {
            $env['APP_URL'] = $tunnelUrl;
        }

        $serverCmd = "php artisan serve --port={$port} --no-reload 2>&1";
        $serverProcess = Process::env($env)->start($serverCmd);
        $this->serverProcess = $serverProcess;

        sleep(2);

        if ($tunnelUrl) {
            $this->newLine();
            $this->components->info("Tunnel:  {$tunnelUrl}");
            $this->components->info("Local:   http://localhost:{$port}");
        } else {
            $this->components->info("Server running at http://localhost:{$port}");
        }

        $this->components->info('Press Ctrl+C to stop.');
        $this->newLine();

        // Stream output
        while ($serverProcess->running()) {
            $serverOutput = $serverProcess->latestOutput();
            if ($serverOutput) {
                $this->output->write($serverOutput);
            }

            if ($this->tunnelProcess?->running()) {
                $tunnelOutput = $this->tunnelProcess->latestOutput() . $this->tunnelProcess->latestErrorOutput();
                if ($tunnelOutput) {
                    $this->output->write($tunnelOutput);
                }
            }

            usleep(100_000);
        }

        return self::SUCCESS;
    }

    protected function startTunnel(int $port): ?string
    {
        // Check cloudflared
        $checkResult = Process::run('which cloudflared');
        if (! $checkResult->successful()) {
            $this->components->error('cloudflared is not installed.');
            $this->components->info('Install it: brew install cloudflared');

            return null;
        }

        $this->components->info('Starting cloudflared tunnel...');

        $origin = "http://localhost:{$port}";
        $tunnelCmd = "cloudflared tunnel --config /dev/null --url {$origin} 2>&1";
        $tunnelProcess = Process::start($tunnelCmd);
        $this->tunnelProcess = $tunnelProcess;

        // Wait for tunnel URL
        $tunnelUrl = null;
        $attempts = 0;

        while ($attempts < 30 && $tunnelUrl === null) {
            $output = $tunnelProcess->latestOutput() . $tunnelProcess->latestErrorOutput();

            if (preg_match('/https:\/\/[a-z0-9-]+\.trycloudflare\.com/', $output, $matches)) {
                $tunnelUrl = $matches[0];
            }

            $attempts++;
            usleep(500_000);
        }

        if (! $tunnelUrl) {
            $this->components->error('Could not establish tunnel (timeout after 15s).');
            $tunnelProcess->signal(SIGTERM);

            return null;
        }

        $this->components->info("Tunnel active: {$tunnelUrl}");

        return $tunnelUrl;
    }

    protected function registerTunnel(string $tunnelUrl, string $origin, string $devToken, string $apiUrl): void
    {
        try {
            $response = Http::put("{$apiUrl}/api/tools-dev/tunnel-url", [
                'dev_token' => $devToken,
                'tunnel_url' => $tunnelUrl,
                'origin' => $origin,
            ]);

            if ($response->successful()) {
                $this->components->info('Tunnel registered with NGO.Tools.');
            } else {
                $this->components->warn('Could not register tunnel: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->components->warn('Could not reach NGO.Tools API: ' . $e->getMessage());
        }
    }

    protected function registerShutdownHandler(?string $devToken, ?string $apiUrl): void
    {
        pcntl_async_signals(true);

        $cleanup = function () use ($devToken, $apiUrl) {
            $this->newLine();
            $this->components->info('Shutting down...');

            // Deactivate tunnel
            if ($devToken && $apiUrl) {
                try {
                    Http::delete("{$apiUrl}/api/tools-dev/tunnel-url", [
                        'dev_token' => $devToken,
                    ]);
                    $this->components->info('Tunnel deactivated.');
                } catch (\Exception) {
                    // Ignore errors during cleanup
                }
            }

            // Stop processes
            if ($this->tunnelProcess?->running()) {
                $this->tunnelProcess->signal(SIGTERM);
            }
            if ($this->serverProcess?->running()) {
                $this->serverProcess->signal(SIGTERM);
            }

            exit(0);
        };

        pcntl_signal(SIGINT, $cleanup);
        pcntl_signal(SIGTERM, $cleanup);
    }
}
