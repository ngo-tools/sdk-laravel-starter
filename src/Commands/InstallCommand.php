<?php

namespace NgoTools\LaravelStarter\Commands;

use Illuminate\Console\Command;
use NgoTools\LaravelStarter\Support\ManifestGenerator;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'ngotools:install
        {--token= : Bootstrap token from NGO.Tools}
        {--api= : NGO.Tools API base URL}
        {--port=8001 : Development server port}
        {--name= : App name}
        {--non-interactive : Skip interactive prompts and use defaults}';

    protected $description = 'Set up a new NGO.Tools app with interactive configuration';

    public function handle(): int
    {
        $this->components->info('NGO.Tools App Setup');
        $this->newLine();

        $name = $this->option('name') ?? text(
            label: 'App name',
            placeholder: 'My Tool',
            required: true,
        );

        $token = $this->option('token');
        $apiUrl = $this->option('api');
        $port = (int) $this->option('port');

        // Interactive feature selection
        if (! $this->option('non-interactive')) {
            $uiSlots = multiselect(
                label: 'Which UI integrations do you need?',
                options: [
                    'navigation_entry' => 'Navigation Entry (own page)',
                    'dashboard_card' => 'Dashboard Widget',
                    'contact_tab' => 'Contact Tab',
                ],
                default: ['navigation_entry'],
            );

            $webhookEvents = multiselect(
                label: 'Which webhooks do you want to receive?',
                options: [
                    'contact.created' => 'contact.created',
                    'contact.updated' => 'contact.updated',
                    'contact.deleted' => 'contact.deleted',
                    'donation.created' => 'donation.created',
                ],
                default: [],
            );

            $scopes = multiselect(
                label: 'Which API permissions do you need?',
                options: [
                    'contacts:read' => 'Read contacts',
                    'contacts:write' => 'Write contacts',
                    'donations:read' => 'Read donations',
                    'donations:write' => 'Write donations',
                    'donation-receipts:read' => 'Read donation receipts',
                    'members:read' => 'Read memberships',
                    'members:write' => 'Write memberships',
                    'projects:read' => 'Read projects',
                    'tenant:read' => 'Read organization info',
                ],
                default: ['contacts:read'],
            );
        } else {
            $uiSlots = ['navigation_entry'];
            $webhookEvents = [];
            $scopes = ['contacts:read'];
        }

        // Publish config
        $this->callSilently('vendor:publish', ['--tag' => 'ngotools-config']);

        // Write .env values
        $this->writeEnvValue('NGOTOOLS_PORT', $port);
        if ($token) {
            $this->writeEnvValue('NGOTOOLS_BOOTSTRAP_TOKEN', $token);
        }
        if ($apiUrl) {
            $this->writeEnvValue('NGOTOOLS_API_URL', $apiUrl);
        }

        // Generate manifest
        $slug = str($name)->slug()->toString();
        $generator = new ManifestGenerator;
        $manifest = $generator->generate([
            'slug' => $slug,
            'name' => $name,
            'scopes' => $scopes,
            'ui_slots' => $uiSlots,
            'webhook_events' => $webhookEvents,
        ]);
        $generator->writeToFile($manifest, base_path());

        // Publish views for selected slots
        $this->callSilently('vendor:publish', ['--tag' => 'ngotools-views', '--force' => true]);

        // Publish webhook route if webhooks selected
        if (! empty($webhookEvents)) {
            $this->publishWebhookRoute();
        }

        // Publish UI routes
        $this->publishUiRoutes($uiSlots, $webhookEvents);

        $this->newLine();
        $this->components->info('NGO.Tools app setup complete!');
        $this->newLine();

        if (! empty($uiSlots)) {
            $this->components->bulletList([
                'Manifest: .well-known/ngotools.json',
                'Views: resources/views/vendor/ngotools/pages/',
                'Config: config/ngotools.php',
            ]);
        }

        $this->newLine();
        $this->components->info('Run `php artisan ngotools:dev` to start the development server with tunnel.');

        return self::SUCCESS;
    }

    protected function writeEnvValue(string $key, mixed $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
        }

        $content = file_get_contents($envPath);

        if (str_contains($content, "{$key}=")) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($envPath, $content);
    }

    protected function publishWebhookRoute(): void
    {
        $routesPath = base_path('routes/ngotools-webhooks.php');

        if (file_exists($routesPath)) {
            return;
        }

        $stub = <<<'PHP'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use NgoTools\LaravelStarter\Http\Middleware\VerifyWebhookSignature;

Route::post('webhooks', function (Request $request) {
    $event = $request->header('X-NGOTools-Event');

    Log::info("Webhook received: {$event}", $request->all());

    // Handle events
    // match ($event) {
    //     'contact.created' => ...,
    //     'donation.created' => ...,
    // };

    return response()->json(['ok' => true]);
})->middleware(VerifyWebhookSignature::class);

PHP;

        file_put_contents($routesPath, $stub);
    }

    protected function publishUiRoutes(array $uiSlots, array $webhookEvents): void
    {
        $routesPath = base_path('routes/ngotools-ui.php');

        if (file_exists($routesPath)) {
            return;
        }

        $routes = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";

        foreach ($uiSlots as $slot) {
            $routes .= match ($slot) {
                'navigation_entry' => "Route::get('ui', fn () => view('ngotools::pages.navigation-page'));\n",
                'dashboard_card' => "Route::get('ui/widget', fn () => view('ngotools::pages.dashboard-widget'));\n",
                'contact_tab' => "Route::get('ui/contact', fn () => view('ngotools::pages.contact-tab'));\n",
            };
        }

        file_put_contents($routesPath, $routes);
    }
}
