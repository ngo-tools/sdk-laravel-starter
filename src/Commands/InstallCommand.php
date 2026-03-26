<?php

namespace NgoTools\LaravelStarter\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'ngotools:install
        {--token= : Bootstrap token from NGO.Tools}
        {--api= : NGO.Tools API base URL}
        {--port=8001 : Development server port}
        {--name= : App name}
        {--ui-slots= : Comma-separated UI slots (navigation_entry,dashboard_card,contact_tab)}
        {--webhooks= : Comma-separated webhook events (contact.created,donation.created,...)}
        {--scopes= : Comma-separated API scopes (contacts:read,donations:read,...)}
        {--non-interactive : Skip interactive prompts and use provided flags}';

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
            $uiSlots = $this->parseCsv($this->option('ui-slots'), ['navigation_entry']);
            $webhookEvents = $this->parseCsv($this->option('webhooks'), []);
            $scopes = $this->parseCsv($this->option('scopes'), ['contacts:read']);
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

        // Create view files for selected UI slots
        $this->createViews($uiSlots);

        // Publish webhook route if webhooks selected
        if (! empty($webhookEvents)) {
            $this->publishWebhookRoute();
        }

        // Publish UI routes
        $this->publishUiRoutes($uiSlots);

        $this->newLine();
        $this->components->info('NGO.Tools app setup complete!');
        $this->newLine();

        $items = ['Config: config/ngotools.php'];
        if (! empty($uiSlots)) {
            $items[] = 'Views: resources/views/ngotools/pages/';
        }
        $items[] = 'Routes: routes/ngotools-ui.php';
        $this->components->bulletList($items);

        $this->newLine();
        $this->components->info('Run `php artisan ngotools:dev` to start the development server with tunnel.');

        return self::SUCCESS;
    }

    protected function parseCsv(?string $value, array $default): array
    {
        if (! $value) {
            return $default;
        }

        return array_filter(array_map('trim', explode(',', $value)));
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

    protected function createViews(array $uiSlots): void
    {
        $viewDir = resource_path('views/ngotools/pages');

        if (! is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        foreach ($uiSlots as $slot) {
            $filename = match ($slot) {
                'navigation_entry' => 'navigation-page.blade.php',
                'dashboard_card' => 'dashboard-widget.blade.php',
                'contact_tab' => 'contact-tab.blade.php',
                default => null,
            };

            if (! $filename) {
                continue;
            }

            $path = $viewDir . '/' . $filename;

            if (file_exists($path)) {
                continue;
            }

            $content = match ($slot) {
                'navigation_entry' => $this->stubNavigationPage(),
                'dashboard_card' => $this->stubDashboardWidget(),
                'contact_tab' => $this->stubContactTab(),
            };

            file_put_contents($path, $content);
        }
    }

    protected function stubNavigationPage(): string
    {
        return <<<'BLADE'
@extends('ngotools::layouts.app')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>

    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Connected as <strong id="ngt-user-name">...</strong>
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-500">
            Tenant: <span id="ngt-tenant-id">...</span> &middot;
            Locale: <span id="ngt-locale">...</span>
        </p>
    </div>

    <div class="mt-6">
        <p class="text-gray-600 dark:text-gray-400">
            Your app is running. Edit
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">resources/views/ngotools/pages/navigation-page.blade.php</code>
            to get started.
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('ngotools:init', function(e) {
        var state = e.detail;
        document.getElementById('ngt-user-name').textContent = state.user.name;
        document.getElementById('ngt-tenant-id').textContent = state.tenantId;
        document.getElementById('ngt-locale').textContent = state.locale;
    });
</script>
@endpush
@endsection
BLADE;
    }

    protected function stubDashboardWidget(): string
    {
        return <<<'BLADE'
@extends('ngotools::layouts.app')

@section('content')
<div class="p-4">
    <h2 class="text-lg font-semibold">{{ config('app.name') }}</h2>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
        Dashboard widget is working.
    </p>
</div>
@endsection
BLADE;
    }

    protected function stubContactTab(): string
    {
        return <<<'BLADE'
@extends('ngotools::layouts.app')

@section('content')
<div class="p-4">
    <h2 class="text-lg font-semibold">{{ config('app.name') }}</h2>

    <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Contact: <strong id="ngt-entity-id">...</strong>
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('ngotools:init', function(e) {
        var state = e.detail;
        var entityId = state.context?.entityId ?? 'N/A';
        document.getElementById('ngt-entity-id').textContent = '#' + entityId;
    });
</script>
@endpush
@endsection
BLADE;
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

    protected function publishUiRoutes(array $uiSlots): void
    {
        $routesPath = base_path('routes/ngotools-ui.php');

        if (file_exists($routesPath)) {
            return;
        }

        $routes = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";

        foreach ($uiSlots as $slot) {
            $routes .= match ($slot) {
                'navigation_entry' => "Route::get('ui', fn () => view('ngotools.pages.navigation-page'));\n",
                'dashboard_card' => "Route::get('ui/widget', fn () => view('ngotools.pages.dashboard-widget'));\n",
                'contact_tab' => "Route::get('ui/contact', fn () => view('ngotools.pages.contact-tab'));\n",
                default => '',
            };
        }

        file_put_contents($routesPath, $routes);
    }
}
