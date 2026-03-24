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

        if (! $devToken || ! $apiUrl) {
            $this->components->error('NGOTOOLS_DEV_TOKEN and NGOTOOLS_API_URL must be set in .env');
            $this->components->info('Run the bootstrap command first to set up your app.');

            return self::FAILURE;
        }

        // Register shutdown handler for cleanup
        $this->registerShutdownHandler($devToken, $apiUrl);

        // Start Laravel dev server
        $this->components->info("Starting development server on port {$port}...");

        $serverProcess = Process::start("php artisan serve --port={$port} --no-reload 2>&1");
        $this->serverProcess = $serverProcess;

        sleep(2); // Give server time to start

        if ($this->option('no-tunnel')) {
            $this->components->info("Server running at http://localhost:{$port}");
            $this->components->info('Press Ctrl+C to stop.');

            while ($serverProcess->running()) {
                $output = $serverProcess->latestOutput();
                if ($output) {
                    $this->output->write($output);
                }
                usleep(100_000);
            }

            return self::SUCCESS;
        }

        // Check cloudflared
        $checkResult = Process::run('which cloudflared');
        if (! $checkResult->successful()) {
            $this->components->error('cloudflared is not installed.');
            $this->components->info('Install it: brew install cloudflared');
            $serverProcess->signal(SIGTERM);

            return self::FAILURE;
        }

        // Start cloudflared tunnel
        $this->components->info('Starting cloudflared tunnel...');

        $origin = "http://localhost:{$port}";
        $tunnelCmd = "cloudflared tunnel --config /dev/null --url {$origin} --http-host-header localhost 2>&1";
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
            $serverProcess->signal(SIGTERM);
            $tunnelProcess->signal(SIGTERM);

            return self::FAILURE;
        }

        $this->components->info("Tunnel active: {$tunnelUrl}");

        // Register tunnel URL with NGO.Tools
        try {
            $response = Http::put("{$apiUrl}/api/tools-dev/tunnel-url", [
                'dev_token' => $devToken,
                'tunnel_url' => $tunnelUrl,
            ]);

            if ($response->successful()) {
                $this->components->info('Tunnel registered with NGO.Tools.');
            } else {
                $this->components->warn('Could not register tunnel: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->components->warn('Could not reach NGO.Tools API: ' . $e->getMessage());
        }

        $this->newLine();
        $this->components->info('Development server ready! Press Ctrl+C to stop.');
        $this->newLine();

        // Stream output
        while ($serverProcess->running() || $tunnelProcess->running()) {
            $serverOutput = $serverProcess->latestOutput();
            $tunnelOutput = $tunnelProcess->latestOutput() . $tunnelProcess->latestErrorOutput();

            if ($serverOutput) {
                $this->output->write($serverOutput);
            }
            if ($tunnelOutput) {
                $this->output->write($tunnelOutput);
            }

            usleep(100_000);
        }

        return self::SUCCESS;
    }

    protected function registerShutdownHandler(string $devToken, string $apiUrl): void
    {
        pcntl_async_signals(true);

        $cleanup = function () use ($devToken, $apiUrl) {
            $this->newLine();
            $this->components->info('Shutting down...');

            // Deactivate tunnel
            try {
                Http::delete("{$apiUrl}/api/tools-dev/tunnel-url", [
                    'dev_token' => $devToken,
                ]);
                $this->components->info('Tunnel deactivated.');
            } catch (\Exception) {
                // Ignore errors during cleanup
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
